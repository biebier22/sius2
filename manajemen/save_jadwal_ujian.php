<?php
// Disable error display dan output buffering untuk mencegah output sebelum JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start(); // Start output buffering

session_start();

// Set header JSON dulu sebelum include
header('Content-Type: application/json; charset=utf-8');

// Cek session sebelum include config
if (!isset($_SESSION['manajemen_unlock']) && !isset($_SESSION['pengawas_unlock'])) {
    ob_clean(); // Clear any output
    echo json_encode(['status' => 'error', 'message' => 'Anda harus login terlebih dahulu.']);
    exit;
}

// Include config dengan error handling
try {
    include "../config.php";
} catch (Exception $e) {
    ob_clean(); // Clear any output
    echo json_encode(['status' => 'error', 'message' => 'Error loading config: ' . $e->getMessage()]);
    exit;
}

if (!isset($conn2) || $conn2->connect_error) {
    ob_clean(); // Clear any output
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database tidak ditemukan.']);
    exit;
}

$masa = trim($_POST['masa'] ?? '20252');
$hari = intval($_POST['hari'] ?? 0);

// Validasi hari
if ($hari < 1 || $hari > 2) {
    echo json_encode(['status' => 'error', 'message' => 'Hari harus 1 atau 2.']);
    exit;
}

// Fungsi untuk save/update jadwal per hari
function saveJadwalHari($conn2, $masa, $hari, $tanggal_ujian, $jam_data) {
    try {
        // Validasi input
        if (empty($tanggal_ujian)) {
            return ['status' => 'error', 'message' => "Tanggal ujian Hari $hari wajib diisi."];
        }
        
        // Validasi jam mulai dan selesai
        for ($j = 1; $j <= 5; $j++) {
            $mulai = $jam_data['jam' . $j . '_mulai'] ?? '';
            $selesai = $jam_data['jam' . $j . '_selesai'] ?? '';
            
            if (empty($mulai) || empty($selesai)) {
                return ['status' => 'error', 'message' => "Hari $hari - Jam mulai dan selesai untuk Jam $j wajib diisi."];
            }
            
            // Validasi jam selesai harus setelah jam mulai
            if ($selesai <= $mulai) {
                return ['status' => 'error', 'message' => "Hari $hari - Jam selesai harus setelah jam mulai untuk Jam $j."];
            }
        }
        
        // Cek apakah tabel jadwal_ujian ada
        $check_table = $conn2->query("SHOW TABLES LIKE 'jadwal_ujian'");
        if ($check_table->num_rows == 0) {
            return ['status' => 'error', 'message' => 'Tabel jadwal_ujian belum ada. Silakan jalankan script SQL create_table_jadwal_ujian.sql terlebih dahulu.'];
        }
        
        // Cek apakah sudah ada jadwal untuk masa dan hari ini
        $stmt_check = $conn2->prepare("SELECT id FROM jadwal_ujian WHERE masa = ? AND hari = ? LIMIT 1");
        if (!$stmt_check) {
            return ['status' => 'error', 'message' => 'Error prepare check: ' . $conn2->error];
        }
        
        $stmt_check->bind_param("si", $masa, $hari);
        if (!$stmt_check->execute()) {
            $error = $stmt_check->error;
            $stmt_check->close();
            return ['status' => 'error', 'message' => 'Error execute check: ' . $error];
        }
        
        $result_check = $stmt_check->get_result();
        $exists = $result_check->num_rows > 0;
        $stmt_check->close();
    
        if ($exists) {
            // Update existing
            $stmt = $conn2->prepare("
                UPDATE jadwal_ujian 
                SET 
                    tanggal_ujian = ?,
                    jam1_mulai = ?,
                    jam1_selesai = ?,
                    jam2_mulai = ?,
                    jam2_selesai = ?,
                    jam3_mulai = ?,
                    jam3_selesai = ?,
                    jam4_mulai = ?,
                    jam4_selesai = ?,
                    jam5_mulai = ?,
                    jam5_selesai = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE masa = ? AND hari = ?
            ");
            
            if (!$stmt) {
                return ['status' => 'error', 'message' => 'Error prepare update: ' . $conn2->error];
            }
            
            // 13 parameters: tanggal_ujian (s), jam1-5 mulai/selesai (10s), masa (s), hari (i)
            // Query: tanggal_ujian, jam1_mulai, jam1_selesai, jam2_mulai, jam2_selesai, jam3_mulai, jam3_selesai, jam4_mulai, jam4_selesai, jam5_mulai, jam5_selesai, masa, hari
            // Type string: 12s + 1i = 13 total
            $stmt->bind_param("ssssssssssssi",
                $tanggal_ujian,                    // s
                $jam_data['jam1_mulai'],           // s
                $jam_data['jam1_selesai'],         // s
                $jam_data['jam2_mulai'],           // s
                $jam_data['jam2_selesai'],         // s
                $jam_data['jam3_mulai'],           // s
                $jam_data['jam3_selesai'],         // s
                $jam_data['jam4_mulai'],           // s
                $jam_data['jam4_selesai'],         // s
                $jam_data['jam5_mulai'],           // s
                $jam_data['jam5_selesai'],         // s
                $masa,                             // s
                $hari                              // i
            );
            
            if ($stmt->execute()) {
                $stmt->close();
                return ['status' => 'ok', 'message' => "Jadwal Hari $hari berhasil diupdate!"];
            } else {
                $error = $stmt->error;
                $stmt->close();
                return ['status' => 'error', 'message' => "Gagal mengupdate Hari $hari: " . $error];
            }
        } else {
            // Insert new
            $stmt = $conn2->prepare("
                INSERT INTO jadwal_ujian 
                (masa, hari, tanggal_ujian, jam1_mulai, jam1_selesai, jam2_mulai, jam2_selesai, 
                 jam3_mulai, jam3_selesai, jam4_mulai, jam4_selesai, jam5_mulai, jam5_selesai)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                return ['status' => 'error', 'message' => 'Error prepare insert: ' . $conn2->error];
            }
            
            // 13 parameters: masa (s), hari (i), tanggal_ujian (s), jam1-5 mulai/selesai (10s)
            // Type: s + i + s + 10s = sisssssssssss (13 chars)
            $stmt->bind_param("sisssssssssss",
                $masa,                             // s
                $hari,                             // i
                $tanggal_ujian,                    // s
                $jam_data['jam1_mulai'],           // s
                $jam_data['jam1_selesai'],         // s
                $jam_data['jam2_mulai'],           // s
                $jam_data['jam2_selesai'],         // s
                $jam_data['jam3_mulai'],           // s
                $jam_data['jam3_selesai'],         // s
                $jam_data['jam4_mulai'],           // s
                $jam_data['jam4_selesai'],         // s
                $jam_data['jam5_mulai'],           // s
                $jam_data['jam5_selesai']          // s
            );
            
            if ($stmt->execute()) {
                $stmt->close();
                return ['status' => 'ok', 'message' => "Jadwal Hari $hari berhasil disimpan!"];
            } else {
                $error = $stmt->error;
                $stmt->close();
                return ['status' => 'error', 'message' => "Gagal menyimpan Hari $hari: " . $error];
            }
        }
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Proses jadwal untuk hari yang dipilih
try {
    ob_clean(); // Clear any output before JSON
    
    $tanggal_ujian = trim($_POST['tanggal_ujian'] ?? '');
    $jam_data = [];
    for ($j = 1; $j <= 5; $j++) {
        $jam_data['jam' . $j . '_mulai'] = trim($_POST['jam' . $j . '_mulai'] ?? '');
        $jam_data['jam' . $j . '_selesai'] = trim($_POST['jam' . $j . '_selesai'] ?? '');
    }
    
    $result = saveJadwalHari($conn2, $masa, $hari, $tanggal_ujian, $jam_data);
    
    // Pastikan output hanya JSON
    ob_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Error processing request: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Error $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Fatal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
