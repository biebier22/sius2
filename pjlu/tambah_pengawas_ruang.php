<?php
session_start();
include "../config.php";

if (!isset($_SESSION['pjtu_unlock'])) {
    echo "Akses ditolak";
    exit;
}

if (!isset($conn2) || $conn2->connect_error) {
    echo "Koneksi database error: " . $conn2->connect_error;
    exit;
}

// Ambil lokasi dari session
$lokasi_tpu = $_SESSION['lokasi'] ?? 'Lokasi Default/Tidak Diketahui';
$masa = '20252';

// =============================
//     GENERATE ID PENGAWAS OTOMATIS
// =============================
function generateIdPengawas($conn2) {
    $q = $conn2->query("SELECT id_pengawas FROM pengawas_ruang ORDER BY id_pengawas DESC LIMIT 1");

    if ($q->num_rows == 0) {
        return "PGW001";
    }

    $d = $q->fetch_assoc();
    $last = intval(substr($d['id_pengawas'], 3)); 
    $new  = $last + 1;

    return "PGW" . str_pad($new, 3, "0", STR_PAD_LEFT);
}

// =============================
//   GET KODE TPU BERDASARKAN LOKASI
// =============================
function getKodeTpu($conn2, $lokasi, $masa) {
    $stmt = $conn2->prepare("SELECT DISTINCT kode_tpu FROM e_lokasi_uas WHERE lokasi = ? AND masa = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $lokasi, $masa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['kode_tpu'];
        }
        $stmt->close();
    }
    // Fallback ke session jika tidak ditemukan
    return $_SESSION['kode_tpu'] ?? '';
}

// =============================
//   GET NILAI JAM DARI REKAP_UJIAN
// =============================
function getJamFromRekap($conn2, $hari, $ruang, $lokasi, $masa) {
    // Default values
    $jam1 = 0;
    $jam2 = 0;
    $jam3 = 0;
    $jam4 = 0;
    $jam5 = 0;
    
    $stmt = $conn2->prepare("
        SELECT 
            r.jam1, r.jam2, r.jam3, r.jam4, r.jam5
        FROM rekap_ujian r
        LEFT JOIN e_lokasi_uas e 
            ON r.kode_tpu = e.kode_tpu 
            AND e.masa = ?
            AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED) AND CAST(e.ruang_akhir AS UNSIGNED)
            AND CAST(r.hari_ke AS CHAR) = e.hari
        WHERE 
            r.masa = ? 
            AND e.lokasi = ?
            AND r.hari_ke = ?
            AND CAST(r.ruang_ke AS UNSIGNED) = CAST(? AS UNSIGNED)
        LIMIT 1
    ");
    
    if ($stmt) {
        $hari_str = (string)$hari;
        $ruang_str = (string)$ruang;
        $stmt->bind_param("sssss", $masa, $masa, $lokasi, $hari_str, $ruang_str);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $jam1 = intval($row['jam1'] ?? 0);
            $jam2 = intval($row['jam2'] ?? 0);
            $jam3 = intval($row['jam3'] ?? 0);
            $jam4 = intval($row['jam4'] ?? 0);
            $jam5 = intval($row['jam5'] ?? 0);
        }
        $stmt->close();
    }
    
    return [
        'jam1' => $jam1,
        'jam2' => $jam2,
        'jam3' => $jam3,
        'jam4' => $jam4,
        'jam5' => $jam5
    ];
}

// =============================
//   GET DAFTAR RUANG BERDASARKAN HARI DAN LOKASI
// =============================
function getDaftarRuang($conn2, $lokasi, $hari, $masa) {
    $ruang_list = [];
    $stmt = $conn2->prepare("
        SELECT DISTINCT r.ruang_ke
        FROM rekap_ujian r
        LEFT JOIN e_lokasi_uas e 
            ON r.kode_tpu = e.kode_tpu 
            AND e.masa = ?
            AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED) AND CAST(e.ruang_akhir AS UNSIGNED)
            AND CAST(r.hari_ke AS CHAR) = e.hari
        WHERE 
            r.masa = ? 
            AND e.lokasi = ?
            AND r.hari_ke = ?
        ORDER BY CAST(r.ruang_ke AS UNSIGNED) ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param("ssss", $masa, $masa, $lokasi, $hari);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ruang_list[] = $row['ruang_ke'];
        }
        $stmt->close();
    }
    return $ruang_list;
}

// =============================
//   TAMBAH PENGAWAS RUANG (AJAX) - SINGLE ENTRY
// =============================
if (isset($_POST['add_pengawas_ruang'])) {
    header('Content-Type: application/json');
    
    $nama_pengawas = trim($_POST['nama_pengawas'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $institusi = trim($_POST['institusi'] ?? '');
    $hari = intval($_POST['hari'] ?? 0);
    $ruang = intval($_POST['ruang'] ?? 0);
    $lokasi = trim($_POST['lokasi_hidden'] ?? '');
    $masa = '20252';
    
    // Validasi
    if (empty($nama_pengawas) || empty($no_wa) || empty($institusi) || $hari < 1 || $ruang <= 0 || empty($lokasi)) {
        echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi dengan benar!']);
        exit;
    }
    
    // Cek duplikasi: ruang + hari + lokasi tidak boleh duplikat
    $stmt_check = $conn2->prepare("
        SELECT id FROM pengawas_ruang 
        WHERE ruang = ? AND hari = ? AND lokasi = ? AND masa = ?
        LIMIT 1
    ");
    $stmt_check->bind_param("iiss", $ruang, $hari, $lokasi, $masa);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        echo json_encode(['status' => 'error', 'message' => "Ruang $ruang untuk Hari $hari di lokasi ini sudah memiliki pengawas!"]);
        exit;
    }
    $stmt_check->close();
    
    // Generate ID pengawas
    $id_pengawas = generateIdPengawas($conn2);
    
    // Get kode_tpu
    $kode_tpu = getKodeTpu($conn2, $lokasi, $masa);
    
    // Get nilai jam dari rekap_ujian
    $jam_data = getJamFromRekap($conn2, $hari, $ruang, $lokasi, $masa);
    
    // Insert ke pengawas_ruang
    $stmt_insert = $conn2->prepare("
        INSERT INTO pengawas_ruang 
        (masa, id_pengawas, nama_pengawas, hari, ruang, kode_tpu, lokasi, no_wa, institusi, jam1, jam2, jam3, jam4, jam5)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt_insert === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement: ' . $conn2->error]);
        exit;
    }
    
    $stmt_insert->bind_param("sssiissssiiiii", 
        $masa, 
        $id_pengawas, 
        $nama_pengawas, 
        $hari, 
        $ruang, 
        $kode_tpu, 
        $lokasi, 
        $no_wa, 
        $institusi,
        $jam_data['jam1'],
        $jam_data['jam2'],
        $jam_data['jam3'],
        $jam_data['jam4'],
        $jam_data['jam5']
    );
    
    if ($stmt_insert->execute()) {
        echo json_encode(['status' => 'ok', 'message' => 'Pengawas ruang berhasil ditambahkan!', 'id_pengawas' => $id_pengawas]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $stmt_insert->error]);
    }
    $stmt_insert->close();
    exit;
}

// =============================
// UPLOAD CSV PENGAWAS RUANG (BATCH)
// =============================
if (isset($_POST['upload_csv_pengawas'])) {
    header('Content-Type: application/json');

    $hari = intval($_POST['hari_csv'] ?? 0);
    $institusi = trim($_POST['institusi_csv'] ?? '');
    $lokasi = trim($_POST['lokasi_hidden'] ?? '');
    $masa = '20252';
    $no_wa_default = trim($_POST['no_wa_csv'] ?? '-'); 
    
    if ($hari < 1 || empty($institusi) || empty($lokasi)) {
        echo json_encode(['status' => 'error', 'message' => 'Hari dan Institusi wajib diisi untuk upload batch!']);
        exit;
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengupload file. Error code: ' . ($_FILES['csv_file']['error'] ?? 'N/A')]);
        exit;
    }
    
    $file_ext = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($file_ext), ['csv', 'txt'])) {
         echo json_encode(['status' => 'error', 'message' => 'Format file harus .csv atau .txt']);
         exit;
    }

    $file_handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    if (!$file_handle) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membaca file yang diupload.']);
        exit;
    }

    $imported_count = 0;
    $failed_rows = [];
    $conn2->begin_transaction();

    try {
        while (($line = fgets($file_handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            // Memisahkan berdasarkan spasi pertama. Format: NOMOR_RUANG NAMA_PENGAWAS
            $parts = preg_split('/\s+/', $line, 2);
            
            $ruang = intval($parts[0] ?? 0);
            $nama_pengawas = trim($parts[1] ?? '');
            
            if ($ruang <= 0 || empty($nama_pengawas)) {
                $failed_rows[] = "Baris tidak valid: **$line** (Ruang/Nama kosong)";
                continue;
            }

            // --- Logika Penyisipan ---
            $id_pengawas = generateIdPengawas($conn2);
            $kode_tpu = getKodeTpu($conn2, $lokasi, $masa);
            $jam_data = getJamFromRekap($conn2, $hari, $ruang, $lokasi, $masa);

            // Cek duplikasi
            $stmt_check = $conn2->prepare("
                SELECT id FROM pengawas_ruang
                WHERE ruang = ? AND hari = ? AND lokasi = ? AND masa = ?
                LIMIT 1
            ");
            $stmt_check->bind_param("iiss", $ruang, $hari, $lokasi, $masa);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $failed_rows[] = "Gagal: Ruang **$ruang** Hari **$hari** sudah terdaftar (Nama: $nama_pengawas)";
                $stmt_check->close();
                continue;
            }
            $stmt_check->close();

            $stmt_insert = $conn2->prepare("
                INSERT INTO pengawas_ruang
                (masa, id_pengawas, nama_pengawas, hari, ruang, kode_tpu, lokasi, no_wa, institusi, jam1, jam2, jam3, jam4, jam5)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt_insert === false) {
                 throw new Exception('Error prepare statement: ' . $conn2->error);
            }

            $stmt_insert->bind_param("sssiissssiiiii",
                $masa, $id_pengawas, $nama_pengawas, $hari, $ruang, $kode_tpu, $lokasi,
                $no_wa_default, 
                $institusi, 
                $jam_data['jam1'], $jam_data['jam2'], $jam_data['jam3'], $jam_data['jam4'], $jam_data['jam5']
            );

            if ($stmt_insert->execute()) {
                $imported_count++;
            } else {
                $failed_rows[] = "Gagal menyimpan **$nama_pengawas** (Ruang $ruang): " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }

        fclose($file_handle);

        if (count($failed_rows) > 0) {
            $conn2->rollback(); 
            $message = "⚠️ Proses Selesai. **Semua data dibatalkan (Rollback)** karena ditemukan **" . count($failed_rows) . "** baris yang gagal/duplikat. Hanya batch yang bersih 100% yang akan disimpan.";
             echo json_encode(['status' => 'error_batch', 'message' => $message, 'details' => $failed_rows]);
        } else {
            $conn2->commit();
            $message = "🎉 Berhasil! **$imported_count** Pengawas Ruang berhasil ditambahkan.";
            echo json_encode(['status' => 'ok', 'message' => $message]);
        }


    } catch (Exception $e) {
        $conn2->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
    }

    exit;
}

// =============================
//   EDIT PENGAWAS RUANG (AJAX)
// =============================
if (isset($_POST['edit_pengawas_ruang'])) {
    header('Content-Type: application/json');
    
    $id_pengawas = trim($_POST['id_pengawas'] ?? '');
    $nama_pengawas = trim($_POST['nama_pengawas'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $institusi = trim($_POST['institusi'] ?? '');
    $hari = intval($_POST['hari'] ?? 0);
    $ruang = intval($_POST['ruang'] ?? 0);
    $lokasi = trim($_POST['lokasi_hidden'] ?? ''); // Lokasi dari hidden input
    $old_ruang = intval($_POST['old_ruang'] ?? 0);
    $old_hari = intval($_POST['old_hari'] ?? 0);
    $masa = '20252';
    
    // Validasi
    if (empty($id_pengawas) || empty($nama_pengawas) || empty($no_wa) || empty($institusi) || $hari < 1 || $ruang <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi dengan benar!']);
        exit;
    }
    
    // Cek duplikasi HANYA jika Ruang atau Hari berubah
    if ($ruang != $old_ruang || $hari != $old_hari) {
        $stmt_check = $conn2->prepare("
            SELECT id FROM pengawas_ruang 
            WHERE ruang = ? AND hari = ? AND lokasi = ? AND masa = ? AND id_pengawas != ?
            LIMIT 1
        ");
        $stmt_check->bind_param("iiss", $ruang, $hari, $lokasi, $masa, $id_pengawas);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $stmt_check->close();
            echo json_encode(['status' => 'error', 'message' => "Ruang $ruang untuk Hari $hari di lokasi ini sudah memiliki pengawas lain!"]);
            exit;
        }
        $stmt_check->close();
    }

    // Ambil nilai jam yang baru (jika ruang atau hari berubah)
    $jam_data = getJamFromRekap($conn2, $hari, $ruang, $lokasi, $masa);
    
    // Update pengawas_ruang
    $stmt_update = $conn2->prepare("
        UPDATE pengawas_ruang 
        SET nama_pengawas = ?, no_wa = ?, institusi = ?, hari = ?, ruang = ?, 
            jam1 = ?, jam2 = ?, jam3 = ?, jam4 = ?, jam5 = ? 
        WHERE id_pengawas = ? AND masa = ?
    ");
    
    if ($stmt_update === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement: ' . $conn2->error]);
        exit;
    }
    
    $stmt_update->bind_param("sssiiiiiiiss", 
        $nama_pengawas, 
        $no_wa, 
        $institusi, 
        $hari, 
        $ruang, 
        $jam_data['jam1'], 
        $jam_data['jam2'], 
        $jam_data['jam3'], 
        $jam_data['jam4'], 
        $jam_data['jam5'],
        $id_pengawas, 
        $masa
    );
    
    if ($stmt_update->execute()) {
        echo json_encode(['status' => 'ok', 'message' => 'Data pengawas ruang berhasil diupdate!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate: ' . $stmt_update->error]);
    }
    $stmt_update->close();
    exit;
}


// =============================
//   GET DATA SINGLE PENGAWAS (AJAX)
// =============================
if (isset($_POST['get_pengawas_data'])) {
    header('Content-Type: application/json');
    $id_pengawas = trim($_POST['id_pengawas'] ?? '');
    $masa = '20252';

    if (empty($id_pengawas)) {
        echo json_encode(['status' => 'error', 'message' => 'ID pengawas tidak ditemukan!']);
        exit;
    }

    $stmt = $conn2->prepare("
        SELECT id_pengawas, nama_pengawas, no_wa, institusi, hari, ruang, lokasi 
        FROM pengawas_ruang 
        WHERE id_pengawas = ? AND masa = ? 
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("ss", $id_pengawas, $masa);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['status' => 'ok', 'pengawas' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Pengawas tidak ditemukan!']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement']);
    }
    exit;
}

// =============================
// DELETE PENGAWAS RUANG (AJAX)
// =============================
if (isset($_POST['delete_pengawas_ruang'])) {
    header('Content-Type: application/json');
    $id_pengawas = trim($_POST['id_pengawas'] ?? '');
    $masa = '20252';
    
    if (empty($id_pengawas)) {
        echo json_encode(['status' => 'error', 'message' => 'ID pengawas tidak ditemukan!']);
        exit;
    }

    // Mulai transaksi
    $conn2->begin_transaction();
    
    try {
        // Hapus entri dari tabel pengawas_ruang
        $stmt_delete = $conn2->prepare("DELETE FROM pengawas_ruang WHERE id_pengawas = ? AND masa = ?");
        $stmt_delete->bind_param("ss", $id_pengawas, $masa);
        
        if (!$stmt_delete->execute()) {
            throw new Exception("Gagal menghapus data pengawas ruang: " . $stmt_delete->error);
        }
        $stmt_delete->close();

        // Hapus penugasan di wasling_ruang (jika ada)
        $stmt_delete_wasling = $conn2->prepare("DELETE FROM wasling_ruang WHERE id_pengawas = ?");
        $stmt_delete_wasling->bind_param("s", $id_pengawas);
        
        if (!$stmt_delete_wasling->execute()) {
            throw new Exception("Gagal menghapus penugasan wasling_ruang: " . $stmt_delete_wasling->error);
        }
        $stmt_delete_wasling->close();

        $conn2->commit();
        echo json_encode(['status' => 'ok', 'message' => 'Pengawas ruang berhasil dihapus beserta penugasannya!']);

    } catch (Exception $e) {
        $conn2->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


// =============================
//   GET DATA PENGAWAS RUANG UNTUK DISPLAY
// =============================
$pengawas_list = [];
$stmt_list = $conn2->prepare("
    SELECT id_pengawas, nama_pengawas, hari, ruang, no_wa, institusi 
    FROM pengawas_ruang 
    WHERE lokasi = ? AND masa = ? 
    ORDER BY hari ASC, CAST(ruang AS UNSIGNED) ASC
");

if ($stmt_list) {
    $stmt_list->bind_param("ss", $lokasi_tpu, $masa);
    $stmt_list->execute();
    $result_list = $stmt_list->get_result();
    $pengawas_list = $result_list->fetch_all(MYSQLI_ASSOC);
    $stmt_list->close();
}


// =============================
// GET DAFTAR RUANG UNTUK HARI 1 DAN 2
// =============================
$ruang_hari1 = getDaftarRuang($conn2, $lokasi_tpu, '1', $masa);
$ruang_hari2 = getDaftarRuang($conn2, $lokasi_tpu, '2', $masa);

// Ambil juga ruangan yang sudah terisi pengawas
$terisi_hari1 = array_column(array_filter($pengawas_list, fn($p) => $p['hari'] == '1'), 'ruang');
$terisi_hari2 = array_column(array_filter($pengawas_list, fn($p) => $p['hari'] == '2'), 'ruang');

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengawas Ruang</title>
    <link rel="stylesheet" href="../dist/css/style.min.css"> 
    
    <style>
        /* CSS Tambahan untuk style yang konsisten */
        body { font-family: sans-serif; background-color: #f1f5f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .form-card, .card { 
            background: white; 
            padding: 20px; 
            border-radius: 15px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 0 10px; /* Kurangi padding atas/bawah */
            border-bottom: 1px solid #e2e8f0;
        }
        .form-header h3 { margin: 0 0 6px; font-size: 20px; font-weight: 700; color: #0f172a; }
        .form-subtitle { margin: 0; font-size: 14px; color: #64748b; }
        
        /* New Tab Styles */
        .tab-menu { 
            display: flex; 
            border-bottom: 2px solid #e2e8f0; 
            /* Mengakali padding card agar border tab full width */
            margin: 0 -20px; 
            padding: 0 20px;
            margin-bottom: 15px;
        }
        .tab-btn {
            padding: 10px 15px;
            border: none;
            background-color: transparent;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            color: #64748b;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
            margin-right: 15px;
        }
        .tab-btn:hover {
            color: #0f172a;
        }
        .tab-btn.active {
            color: #2979ff;
            border-bottom: 3px solid #2979ff;
            font-weight: 600;
        }
        .tab-content {
            padding-top: 15px;
        }

        .form-label { display: block; font-weight: 500; margin-top: 15px; margin-bottom: 5px; font-size: 14px; color: #334155; }
        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-btn {
            width: 100%;
            padding: 12px;
            background-color: #2979ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.2s;
        }
        .form-btn:hover { background-color: #0d47a1; }
        .form-message { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 500; }
        .form-message.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .form-message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Style Tabel Daftar Pengawas */
        .pengawas-table-wrapper { overflow-x: auto; margin-top: 16px; }
        .pengawas-detail-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px; 
            min-width: 600px; 
        }
        .pengawas-detail-table th, .pengawas-detail-table td { 
            padding: 10px; 
            border: 1px solid #e2e8f0; 
            text-align: left; 
        }
        .pengawas-detail-table thead { 
            background: #f8fafc; 
            color: #0f172a; 
            border-bottom: 2px solid #e2e8f0; 
        }
        .action-btn { 
            padding: 5px 10px; 
            margin: 2px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 12px;
        }
        .edit-btn { background-color: #ffc107; color: #333; }
        .delete-btn { background-color: #dc3545; color: white; }
        
        /* Modal Styles */
        .modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); display: none; 
            justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-content {
            background-color: #fff; padding: 20px; border-radius: 10px;
            width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .modal-close { font-size: 24px; font-weight: bold; cursor: pointer; border: none; background: none; }
    </style>
</head>
<body>
<div class="container">
    
    <div class="form-card">
        <div class="form-header" style="border-bottom:none; padding-bottom: 0;">
            <div>
                <h3>Manajemen Pengawas Ruang</h3>
                <p class="form-subtitle">Lokasi: <b><?= htmlspecialchars($lokasi_tpu) ?></b></p>
            </div>
        </div>
        
        <div class="tab-menu">
            <button id="tab-add-btn" class="tab-btn active" onclick="switchTab('add')">➕ Add Wasrung</button>
            <button id="tab-upload-btn" class="tab-btn" onclick="switchTab('upload')">⬆️ Upload Wasrung (Batch)</button>
        </div>
        
        <div id="form-message" class="form-message" style="display:none; margin-top: 15px;"></div>
        
        <div id="tab-content-add" class="tab-content">
            <label class="form-label">Nama Pengawas</label>
            <input type="text" id="nama_pengawas" class="form-input" placeholder="Masukkan nama pengawas" required>
            
            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" id="no_wa" class="form-input" placeholder="Masukkan nomor WhatsApp" required>
            
            <label class="form-label">Institusi</label>
            <input type="text" id="institusi" class="form-input" placeholder="Masukkan nama institusi" required>
            
            <label class="form-label">Pilih Hari</label>
            <select id="hari" class="form-input" onchange="updateRuangDropdown()">
                <option value="">-- Pilih Hari --</option>
                <option value="1">Hari 1</option>
                <option value="2">Hari 2</option>
            </select>
            
            <label class="form-label">Pilih Ruang</label>
            <select id="ruang" class="form-input" required>
                <option value="">-- Pilih Hari terlebih dahulu --</option>
            </select>
            
            <input type="hidden" id="lokasi_hidden" value="<?= htmlspecialchars($lokasi_tpu) ?>">
            
            <button class="form-btn" onclick="simpanPengawasRuang()">
                <span>💾</span> Simpan Pengawas Ruang
            </button>
        </div>
        
        <div id="tab-content-upload" class="tab-content" style="display:none;">
             <p class="form-subtitle" style="margin-bottom: 15px;">Format file: **`NOMOR_RUANG NAMA_PENGAWAS`** per baris. Ruang yang sudah terisi akan diabaikan.</p>
            
            <div id="batch-notif-box" class="form-message" style="display:none; margin-top: 15px;"></div>
            
            <form id="csvUploadForm" enctype="multipart/form-data" onsubmit="return false;">
                <input type="hidden" name="upload_csv_pengawas" value="1">
                <input type="hidden" name="lokasi_hidden" value="<?= htmlspecialchars($lokasi_tpu) ?>">
                
                <label class="form-label">Pilih Hari untuk Batch</label>
                <select name="hari_csv" id="hari_csv" class="form-input" required>
                    <option value="">-- Pilih Hari --</option>
                    <option value="1">Hari 1</option>
                    <option value="2">Hari 2</option>
                </select>

                <label class="form-label">Institusi untuk Batch</label>
                <input type="text" name="institusi_csv" id="institusi_csv" class="form-input" placeholder="Masukkan nama institusi untuk semua pengawas" required>
                
                <label class="form-label">Nomor WhatsApp Default (Opsional, gunakan '-' jika tidak ada)</label>
                <input type="text" name="no_wa_csv" id="no_wa_csv" class="form-input" value="-" placeholder="Nomor WA default untuk semua pengawas">
                
                <label class="form-label">Pilih File CSV/TXT</label>
                <input type="file" name="csv_file" id="csv_file" class="form-input" accept=".csv, .txt" required>
                
                <button type="submit" class="form-btn" onclick="submitCsvUpload()">
                    <span>⬆️</span> Proses Upload Batch
                </button>
            </form>
            
            <div id="batch-details" class="form-message error" style="display:none; margin-top: 20px;">
                <p style="font-weight: bold; margin-bottom: 5px;">Detail Kegagalan (Semua Batch Dibatalkan):</p>
                <ul id="failed-list" style="margin: 0; padding-left: 20px;"></ul>
            </div>
        </div>
    </div> <div class="card">
        <h3>📋 Daftar Pengawas Ruang Lokasi: <?= htmlspecialchars($lokasi_tpu) ?></h3>
        
        <?php if (empty($pengawas_list)): ?>
            <p style="text-align:center; color:#999; padding:20px;">
                Belum ada data Pengawas Ruang yang terdaftar untuk lokasi ini.
            </p>
        <?php else: ?>
            <div class="pengawas-table-wrapper">
                <table class="pengawas-detail-table">
                    <thead>
                        <tr>
                            <th>ID Pengawas</th>
                            <th>Nama Pengawas</th>
                            <th>Hari</th>
                            <th>Ruang</th>
                            <th>No. WA</th>
                            <th>Institusi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pengawas_list as $pengawas): ?>
                        <tr>
                            <td><?= htmlspecialchars($pengawas['id_pengawas']) ?></td>
                            <td><?= htmlspecialchars($pengawas['nama_pengawas']) ?></td>
                            <td>Hari <?= htmlspecialchars($pengawas['hari']) ?></td>
                            <td>Ruang <?= htmlspecialchars($pengawas['ruang']) ?></td>
                            <td><?= htmlspecialchars($pengawas['no_wa']) ?></td>
                            <td><?= htmlspecialchars($pengawas['institusi']) ?></td>
                            <td>
                                <button class="action-btn edit-btn" onclick="showEditModal('<?= htmlspecialchars($pengawas['id_pengawas']) ?>')">
                                    Edit
                                </button>
                                <button class="action-btn delete-btn" onclick="hapusPengawas('<?= htmlspecialchars($pengawas['id_pengawas']) ?>')">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>


    <div id="editModal" class="modal" style="display:none;" onclick="if(event.target === this) closeEditModal();">
        <div class="modal-content" onclick="event.stopPropagation();">
            <div class="modal-header">
                <h3>✏️ Edit Pengawas Ruang</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="edit-message" class="form-message" style="display:none;"></div>
                
                <input type="hidden" id="edit_id_pengawas">
                <input type="hidden" id="edit_old_ruang">
                <input type="hidden" id="edit_old_hari">
                <input type="hidden" id="edit_lokasi_hidden" value="<?= htmlspecialchars($lokasi_tpu) ?>">

                <label class="form-label">Nama Pengawas</label>
                <input type="text" id="edit_nama_pengawas" class="form-input" placeholder="Masukkan nama pengawas" required>
                
                <label class="form-label">Nomor WhatsApp</label>
                <input type="text" id="edit_no_wa" class="form-input" placeholder="Masukkan nomor WhatsApp" required>
                
                <label class="form-label">Institusi</label>
                <input type="text" id="edit_institusi" class="form-input" placeholder="Masukkan nama institusi" required>
                
                <label class="form-label">Pilih Hari</label>
                <select id="edit_hari" class="form-input" onchange="updateEditRuangDropdown()">
                    <option value="">-- Pilih Hari --</option>
                    <option value="1">Hari 1</option>
                    <option value="2">Hari 2</option>
                </select>
                
                <label class="form-label">Pilih Ruang</label>
                <select id="edit_ruang" class="form-input" required>
                    <option value="">-- Pilih Hari terlebih dahulu --</option>
                </select>

                <button class="form-btn" onclick="updatePengawasRuang(event)">
                    <span>💾</span> Update Data
                </button>
            </div>
        </div>
    </div>
    
</div>

<script>
    // Data ruang untuk hari 1 dan hari 2 yang tersedia di rekap_ujian
    const ruangData = {
        '1': <?= json_encode($ruang_hari1) ?>,
        '2': <?= json_encode($ruang_hari2) ?>
    };

    // Data ruang yang sudah terisi pengawas (untuk memberikan peringatan)
    const ruangTerisi = {
        '1': <?= json_encode($terisi_hari1) ?>,
        '2': <?= json_encode($terisi_hari2) ?>
    };

    // ========================================================
    // LOGIC TAB MENU
    // ========================================================
    function switchTab(tabName) {
        // Sembunyikan semua konten dan non-aktifkan semua tombol
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Tampilkan konten yang dipilih dan aktifkan tombol yang dipilih
        if (tabName === 'add') {
            document.getElementById('tab-content-add').style.display = 'block';
            document.getElementById('tab-add-btn').classList.add('active');
        } else if (tabName === 'upload') {
            document.getElementById('tab-content-upload').style.display = 'block';
            document.getElementById('tab-upload-btn').classList.add('active');
        }
        
        // Hapus pesan notifikasi umum saat berganti tab
        document.getElementById('form-message').style.display = 'none';
    }

    // Fungsi untuk menampilkan pesan pada form entry tunggal
    function showMessage(message, type) {
        const messageDiv = document.getElementById('form-message');
        messageDiv.textContent = message;
        messageDiv.className = 'form-message ' + type;
        messageDiv.style.display = 'block';
        
        // Sembunyikan detail error batch jika notif OK/error single muncul
        document.getElementById('batch-details').style.display = 'none';
        document.getElementById('batch-notif-box').style.display = 'none';

        setTimeout(() => { messageDiv.style.display = 'none'; }, 5000);
    }
    
    // ========================================================
    // LOGIC DROPDOWN RUANG
    // ========================================================
    function updateRuangDropdown() {
        const hari = document.getElementById('hari').value;
        const ruangSelect = document.getElementById('ruang');
        ruangSelect.innerHTML = '<option value="">-- Pilih Ruang --</option>';

        if (hari && ruangData[hari]) {
            ruangData[hari].forEach(function(ruang) {
                const option = document.createElement('option');
                option.value = ruang;
                
                let text = 'Ruang ' + ruang;
                if (ruangTerisi[hari].includes(String(ruang))) {
                    text += ' (Sudah Ada Pengawas)';
                    option.disabled = true;
                    option.style.backgroundColor = '#ffe4e6'; // Warna peringatan
                }
                
                option.textContent = text;
                ruangSelect.appendChild(option);
            });
        } else {
            ruangSelect.innerHTML = '<option value="">-- Pilih Hari terlebih dahulu --</option>';
        }
    }

    // ========================================================
    // LOGIC FORM TAMBAH (SINGLE ENTRY)
    // ========================================================
    function simpanPengawasRuang() {
        const nama_pengawas = document.getElementById('nama_pengawas').value.trim();
        const no_wa = document.getElementById('no_wa').value.trim();
        const institusi = document.getElementById('institusi').value.trim();
        const hari = document.getElementById('hari').value;
        const ruang = document.getElementById('ruang').value;
        const lokasi = document.getElementById('lokasi_hidden').value;

        if (!nama_pengawas || !no_wa || !institusi || !hari || !ruang) {
            showMessage('Semua field wajib diisi!', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('add_pengawas_ruang', '1');
        formData.append('nama_pengawas', nama_pengawas);
        formData.append('no_wa', no_wa);
        formData.append('institusi', institusi);
        formData.append('hari', hari);
        formData.append('ruang', ruang);
        formData.append('lokasi_hidden', lokasi);
        
        let btn = document.querySelector('#tab-content-add .form-btn');
        let originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Menyimpan...';

        fetch('tambah_pengawas_ruang.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                showMessage('🎉 ' + data.message, 'success');
                // Reset form
                document.getElementById('nama_pengawas').value = '';
                document.getElementById('no_wa').value = '';
                document.getElementById('institusi').value = '';
                document.getElementById('hari').value = '';
                document.getElementById('ruang').innerHTML = '<option value="">-- Pilih Hari terlebih dahulu --</option>';
                
                // Reload untuk update daftar dan dropdown terisi
                setTimeout(() => location.reload(), 1500); 
            } else {
                showMessage('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            showMessage('Terjadi kesalahan: ' + error, 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    // ========================================================
    // LOGIC FORM UPLOAD CSV (BATCH ENTRY)
    // ========================================================
    function submitCsvUpload() {
        const form = document.getElementById('csvUploadForm');
        const formData = new FormData(form);
        const fileInput = document.getElementById('csv_file');
        const batchNotifBox = document.getElementById('batch-notif-box');
        const detailDiv = document.getElementById('batch-details');
        const failedList = document.getElementById('failed-list');
        
        // Reset notifikasi batch
        failedList.innerHTML = '';
        detailDiv.style.display = 'none';
        batchNotifBox.style.display = 'none';
        document.getElementById('form-message').style.display = 'none';


        if (!document.getElementById('hari_csv').value || !document.getElementById('institusi_csv').value) {
            batchNotifBox.textContent = 'Hari dan Institusi untuk batch wajib diisi!';
            batchNotifBox.className = 'form-message error';
            batchNotifBox.style.display = 'block';
            return;
        }
        if (fileInput.files.length === 0) {
            batchNotifBox.textContent = 'Pilih file CSV/TXT terlebih dahulu.';
            batchNotifBox.className = 'form-message error';
            batchNotifBox.style.display = 'block';
            return;
        }

        let btn = document.querySelector('#csvUploadForm button');
        let originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Memproses Upload...';
        

        fetch('tambah_pengawas_ruang.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                batchNotifBox.textContent = '🎉 ' + data.message;
                batchNotifBox.className = 'form-message success';
                batchNotifBox.style.display = 'block';
                fileInput.value = '';
                setTimeout(() => location.reload(), 1500); 
            } else if (data.status === 'error_batch') {
                // Menampilkan notif error_batch di kotak khusus batch
                batchNotifBox.textContent = data.message;
                batchNotifBox.className = 'form-message error';
                batchNotifBox.style.display = 'block';
                
                if (data.details && data.details.length > 0) {
                    data.details.forEach(detail => {
                        const li = document.createElement('li');
                        li.innerHTML = detail;
                        failedList.appendChild(li);
                    });
                    detailDiv.style.display = 'block';
                }
            } else {
                // Error lain-lain (misal error file upload)
                batchNotifBox.textContent = '❌ ' + data.message;
                batchNotifBox.className = 'form-message error';
                batchNotifBox.style.display = 'block';
            }
        })
        .catch(error => {
            batchNotifBox.textContent = 'Terjadi kesalahan saat memproses file: ' + error;
            batchNotifBox.className = 'form-message error';
            batchNotifBox.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    // ========================================================
    // LOGIC MODAL EDIT & DELETE (TIDAK BERUBAH)
    // ========================================================
    function updateEditRuangDropdown() {
        const hari = document.getElementById('edit_hari').value;
        const ruangSelect = document.getElementById('edit_ruang');
        ruangSelect.innerHTML = '<option value="">-- Pilih Ruang --</option>';
        const old_ruang = document.getElementById('edit_old_ruang').value;
        const old_hari = document.getElementById('edit_old_hari').value;

        if (hari && ruangData[hari]) {
            ruangData[hari].forEach(function(ruang) {
                const option = document.createElement('option');
                option.value = ruang;
                
                let text = 'Ruang ' + ruang;
                const isTerisi = ruangTerisi[hari].includes(String(ruang));
                const isCurrentRoom = (String(ruang) === old_ruang && String(hari) === old_hari);

                if (isTerisi && !isCurrentRoom) {
                    text += ' (Sudah Terisi)';
                    option.disabled = true;
                    option.style.backgroundColor = '#ffe4e6';
                } else if (isCurrentRoom) {
                    text += ' (Saat Ini)';
                }
                
                option.textContent = text;
                ruangSelect.appendChild(option);
            });
        } else {
            ruangSelect.innerHTML = '<option value="">-- Pilih Hari terlebih dahulu --</option>';
        }
    }

    function showEditMessage(message, type) {
        const messageDiv = document.getElementById('edit-message');
        messageDiv.textContent = message;
        messageDiv.className = 'form-message ' + type;
        messageDiv.style.display = 'block';
        setTimeout(() => { messageDiv.style.display = 'none'; }, 5000);
    }
    
    function showEditModal(id_pengawas) {
        // Ambil data pengawas dari server
        const formData = new FormData();
        formData.append('get_pengawas_data', '1');
        formData.append('id_pengawas', id_pengawas);

        fetch('tambah_pengawas_ruang.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                const pengawas = data.pengawas;
                
                // Isi field modal
                document.getElementById('edit_id_pengawas').value = pengawas.id_pengawas;
                document.getElementById('edit_nama_pengawas').value = pengawas.nama_pengawas;
                document.getElementById('edit_no_wa').value = pengawas.no_wa;
                document.getElementById('edit_institusi').value = pengawas.institusi;
                document.getElementById('edit_hari').value = pengawas.hari;
                
                // Simpan nilai lama untuk cek duplikasi
                document.getElementById('edit_old_ruang').value = pengawas.ruang;
                document.getElementById('edit_old_hari').value = pengawas.hari;

                // Update dropdown ruang berdasarkan hari
                updateEditRuangDropdown();

                // Set ruang setelah dropdown ter-update
                setTimeout(() => {
                    document.getElementById('edit_ruang').value = pengawas.ruang;
                }, 100); 

                // Tampilkan modal
                document.getElementById('editModal').style.display = 'flex';
                document.getElementById('edit-message').style.display = 'none';

            } else {
                alert('Gagal mengambil data pengawas: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error);
        });
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('edit-message').style.display = 'none';
    }

    function updatePengawasRuang(event) {
        const nama_pengawas = document.getElementById('edit_nama_pengawas').value.trim();
        const no_wa = document.getElementById('edit_no_wa').value.trim();
        const institusi = document.getElementById('edit_institusi').value.trim();
        const hari = document.getElementById('edit_hari').value;
        const ruang = document.getElementById('edit_ruang').value;
        const id_pengawas = document.getElementById('edit_id_pengawas').value;
        const lokasi = document.getElementById('edit_lokasi_hidden').value;
        const old_ruang = document.getElementById('edit_old_ruang').value;
        const old_hari = document.getElementById('edit_old_hari').value;


        if (!nama_pengawas || !no_wa || !institusi || !hari || !ruang) {
            showEditMessage('Semua field wajib diisi!', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('edit_pengawas_ruang', '1');
        formData.append('id_pengawas', id_pengawas);
        formData.append('nama_pengawas', nama_pengawas);
        formData.append('no_wa', no_wa);
        formData.append('institusi', institusi);
        formData.append('hari', hari);
        formData.append('ruang', ruang);
        formData.append('lokasi_hidden', lokasi);
        formData.append('old_ruang', old_ruang);
        formData.append('old_hari', old_hari);


        let btn = event.target;
        let originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span> Mengupdate...';

        fetch('tambah_pengawas_ruang.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                showEditMessage('🎉 ' + data.message, 'success');
                // Reload untuk update daftar
                setTimeout(() => {
                    closeEditModal();
                    location.reload();
                }, 1500);
            } else {
                showEditMessage('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            showEditMessage('Terjadi kesalahan: ' + error, 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    function hapusPengawas(id_pengawas) {
        if (!confirm('Yakin ingin menghapus pengawas ini? Data yang terkait (seperti penugasan Wasling) juga akan dihapus.')) return;
        
        const formData = new FormData();
        formData.append('delete_pengawas_ruang', '1');
        formData.append('id_pengawas', id_pengawas);
        
        fetch('tambah_pengawas_ruang.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan saat menghapus: ' + error);
        });
    }


    // Inisialisasi: jalankan fungsi switchTab saat halaman dimuat untuk menampilkan tab 'add'
    document.addEventListener("DOMContentLoaded", function() {
        switchTab('add');
        // Panggil updateRuangDropdown() untuk mengisi dropdown ruang saat hari sudah terpilih
        if (document.getElementById('hari').value) {
             updateRuangDropdown();
        }
    });
</script>

</body>
</html>