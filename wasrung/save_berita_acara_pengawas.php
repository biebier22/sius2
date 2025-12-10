<?php
session_start();
include "../config.php"; 

header('Content-Type: application/json');

// --- 1. Validasi Sesi Pengawas Ruang ---
if (!isset($_SESSION['id_wasrung'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session Pengawas Ruang tidak ditemukan. Mohon login kembali.'
    ]);
    exit;
}

$id_pengawas = $_SESSION['id_wasrung'];
$masa = "20252"; 

// --- 2. Ambil TANDA TANGAN dari TABEL PENGAWAS_RUANG ---
$ttd_pengawas = null;
try {
    $stmt_ttd = $conn2->prepare("
        SELECT tanda_tangan 
        FROM pengawas_ruang 
        WHERE id_pengawas = ? 
        LIMIT 1
    ");
    $stmt_ttd->bind_param("s", $id_pengawas); 
    $stmt_ttd->execute();
    $result_ttd = $stmt_ttd->get_result();

    if ($row_ttd = $result_ttd->fetch_assoc()) {
        $ttd_pengawas = $row_ttd['tanda_tangan'];
    }
    $stmt_ttd->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error saat mengambil Tanda Tangan: ' . $e->getMessage()]);
    exit;
}

if (empty($ttd_pengawas)) {
    echo json_encode(['status' => 'error', 'message' => 'Silahkan isi Tanda Tangan Anda di menu Tanda Tangan .']);
    exit;
}

// ----------------------------------------------------------------------
// --- 3. Ambil Data (POST dan Tetapkan Nilai Statis) ---
// ----------------------------------------------------------------------
$ruang = intval($_POST['ruang'] ?? 0);
$hari = trim($_POST['hari'] ?? '');
$jam_ke = intval($_POST['jam_ke'] ?? 0);

// Ambil data LOKASI dari POST (disediakan oleh wasrung_home.php)
$lokasi = trim($_POST['lokasi'] ?? 'Lokasi Default'); 

// Ambil nilai input user: Naskah (eksp. per amplop)
$nilai_naskah_input = intval($_POST['hasil_naskah'] ?? -1); 
$hasil_lju_terisi = intval($_POST['hasil_lju_terisi'] ?? -1);
$hasil_lju_kosong = intval($_POST['hasil_lju_kosong'] ?? -1);

// ✅ PENETAPAN NILAI STATIS (Sesuai Ralat)
$bahan_naskah_amplop_fix = 1; // Selalu diisi 1 amplop

// ----------------------------------------------------------------------
// --- 4. Validasi Data ---
// ----------------------------------------------------------------------
if ($ruang <= 0 || empty($hari) || $jam_ke < 1 || $jam_ke > 5 || empty($lokasi)) {
    echo json_encode(['status' => 'error', 'message' => 'Data input (Ruang, Hari, Jam, atau Lokasi) tidak valid.']);
    exit;
}
if ($nilai_naskah_input < 0 || $hasil_lju_terisi < 0 || $hasil_lju_kosong < 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nilai hasil ujian tidak boleh negatif.']);
    exit;
}
if (($hasil_lju_terisi + $hasil_lju_kosong) != $nilai_naskah_input) {
    echo json_encode(['status' => 'error', 'message' => 'Total LJU (' . ($hasil_lju_terisi + $hasil_lju_kosong) . ') harus sama dengan jumlah Naskah (eksp. per amplop) (' . $nilai_naskah_input . ').']);
    exit;
}

// ----------------------------------------------------------------------
// --- 5. Cek Keberadaan Data (INSERT atau UPDATE) ---
// ----------------------------------------------------------------------
try {
    $stmt_check = $conn2->prepare("
        SELECT id
        FROM berita_acara_serah_terima
        WHERE id_pengawas = ? AND hari = ? AND ruang = ? AND jam_ke = ? AND masa = ?
        LIMIT 1
    ");
    $stmt_check->bind_param("ssiis", $id_pengawas, $hari, $ruang, $jam_ke, $masa);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $existing_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($existing_data) {
        // --- UPDATE DATA YANG SUDAH ADA ---
        $stmt = $conn2->prepare("
            UPDATE berita_acara_serah_terima
            SET lokasi = ?, bahan_naskah_amplop = ?, bahan_lju_lembar = ?, hasil_naskah_eksp = ?, hasil_lju_terisi = ?, hasil_lju_kosong = ?, ttd_pengawas_ruang = ?
            WHERE id = ?
        ");
        // Binding order: s, i, i, i, i, i, s, i
        $stmt->bind_param("siiiiisi", 
            $lokasi, 
            $bahan_naskah_amplop_fix,    // 1. bahan_naskah_amplop = 1
            $nilai_naskah_input,         // 2. bahan_lju_lembar = $nilai_naskah_input
            $nilai_naskah_input,         // 3. hasil_naskah_eksp = $nilai_naskah_input (sama dengan bahan_lju_lembar)
            $hasil_lju_terisi,           // 4. hasil_lju_terisi = $hasil_lju_terisi
            $hasil_lju_kosong,           // 5. hasil_lju_kosong = $hasil_lju_kosong
            $ttd_pengawas, 
            $existing_data['id']
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => 'ok', 'message' => 'Data Serah Terima Jam ' . $jam_ke . ' berhasil diperbarui!']);
        } else {
            throw new Exception("Gagal memperbarui data: " . $stmt->error);
        }
        $stmt->close();

    } else {
        // --- INSERT DATA BARU ---
        $stmt = $conn2->prepare("
            INSERT INTO berita_acara_serah_terima 
            (id_pengawas, hari, ruang, jam_ke, masa, lokasi, bahan_naskah_amplop, bahan_lju_lembar, hasil_naskah_eksp, hasil_lju_terisi, hasil_lju_kosong, ttd_pengawas_ruang)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Binding order: s, s, i, i, s, s, i, i, i, i, i, s
        $stmt->bind_param("ssiissiiiiis", 
            $id_pengawas, 
            $hari, 
            $ruang, 
            $jam_ke, 
            $masa, 
            $lokasi, 
            $bahan_naskah_amplop_fix,    // 1. bahan_naskah_amplop = 1
            $nilai_naskah_input,         // 2. bahan_lju_lembar = $nilai_naskah_input
            $nilai_naskah_input,         // 3. hasil_naskah_eksp = $nilai_naskah_input (sama dengan bahan_lju_lembar)
            $hasil_lju_terisi,           // 4. hasil_lju_terisi = $hasil_lju_terisi
            $hasil_lju_kosong,           // 5. hasil_lju_kosong = $hasil_lju_kosong
            $ttd_pengawas
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => 'ok', 'message' => 'Data Serah Terima Jam ' . $jam_ke . ' berhasil disimpan!']);
        } else {
             throw new Exception("Gagal menyimpan data: " . $stmt->error);
        }
        $stmt->close();
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error Server: ' . $e->getMessage()
    ]);
}

if ($conn2) {
    mysqli_close($conn2);
}
?>