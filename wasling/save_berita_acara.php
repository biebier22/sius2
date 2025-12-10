<?php
// TAMPILKAN SEMUA ERROR UNTUK DEBUGGING - Hapus baris ini (atau buat komentar) jika sudah production
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);

session_start();
// Pastikan file config.php tidak memiliki output sebelum ini.
include "../config.php";

header('Content-Type: application/json'); 

if (!isset($_SESSION['id_wasling'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session Wasling tidak ditemukan.']);
    exit;
}

$id_wasling = $_SESSION['id_wasling'];
$masa = "20252";

// =========================================================================
// LOGIC UTAMA: VERIFIKASI DATA OLEH WASLING (Single Click)
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'verifikasi_data_wasling') {
    
    // 1. Ambil data yang dibutuhkan untuk identifikasi ruangan
    $lokasi_verif = trim($_POST['lokasi'] ?? '');
    $hari_verif = trim($_POST['hari'] ?? '');
    $ruang_verif = intval($_POST['ruang'] ?? 0);
    $id_wasling_verif = trim($_POST['id_wasling'] ?? '');

    // Validasi dasar
    if (empty($lokasi_verif) || empty($hari_verif) || $ruang_verif <= 0 || empty($id_wasling_verif) || $id_wasling_verif !== $id_wasling) {
        echo json_encode(['status' => 'error', 'message' => 'Data verifikasi tidak lengkap atau tidak valid.']);
        exit;
    }

    // 2. Ambil TANDA TANGAN (nama file) dari tabel wasling
    $q_ttd = $conn2->prepare("SELECT tanda_tangan FROM wasling WHERE id_wasling = ? LIMIT 1");
    if (!$q_ttd) {
        echo json_encode(['status' => 'error', 'message' => 'Kesalahan persiapan query TTD: ' . $conn2->error]);
        exit;
    }
    
    $q_ttd->bind_param("s", $id_wasling_verif);
    $q_ttd->execute();
    $d_ttd = $q_ttd->get_result()->fetch_assoc();
    // Ambil nilai string nama file TTD dari kolom 'tanda_tangan'
    $ttd_wasling_value = $d_ttd['tanda_tangan'] ?? null; 
    $q_ttd->close();

    if (empty($ttd_wasling_value)) {
        echo json_encode(['status' => 'error', 'message' => 'Tanda tangan Wasling (nama file) belum tersimpan di data profil Anda. Mohon lengkapi terlebih dahulu.']);
        exit;
    }

    // 3. Lakukan UPDATE untuk SEMUA record di berita_acara_serah_terima (BAST)
    // HANYA MENGISI id_wasling dan ttd_wasling
    $stmt_update = $conn2->prepare("
        UPDATE berita_acara_serah_terima 
        SET id_wasling = ?, ttd_wasling = ? /* timestamp_wasling Dihapus, akan otomatis terisi di updated_at */
        WHERE lokasi = ? AND hari = ? AND ruang = ? AND masa = ?
    ");
    
    if ($stmt_update) {
        $stmt_update->bind_param("ssssis", 
            $id_wasling_verif,  
            $ttd_wasling_value,  // Nama file TTD (string)
            $lokasi_verif,  
            $hari_verif,  
            $ruang_verif,  
            $masa
        );
        $stmt_update->execute();
        
        if ($stmt_update->error) {
             echo json_encode(['status' => 'error', 'message' => 'Kesalahan database saat update: ' . $stmt_update->error]);
        } elseif ($stmt_update->affected_rows > 0) {
            echo json_encode(['status' => 'ok', 'message' => 'Verifikasi Wasling berhasil disimpan.']);
        } else {
             echo json_encode(['status' => 'error', 'message' => 'Tidak ada data Berita Acara yang diupdate. Pastikan Pengawas Ruang sudah mengisi datanya terlebih dahulu.']);
        }
        $stmt_update->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Kesalahan persiapan query saat UPDATE BAST: ' . $conn2->error]);
    }
    
    exit; 
} 
// =========================================================================
// END LOGIC UTAMA
// =========================================================================

// =========================================================================
// LOGIC PENGAWAS RUANG (DIBIARKAN AGAR FUNGSI SIMPAN WASRUNG BERJALAN)
// =========================================================================

// Validasi input
$ruang = intval($_POST['ruang'] ?? 0);
$hari = trim($_POST['hari'] ?? '');
$id_pengawas = trim($_POST['id_pengawas'] ?? '');
$jam_ke = intval($_POST['jam_ke'] ?? 0);

if ($ruang <= 0 || empty($hari) || empty($id_pengawas) || $jam_ke < 1 || $jam_ke > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid.']);
    exit;
}

// ... (Sisa kode untuk logika Pengawas Ruang) ...

$stmt_check = $conn2->prepare("
    SELECT wr.lokasi
    FROM wasling_ruang wr
    WHERE wr.id_wasling = ? AND wr.no_ruang = ? AND wr.hari = ? AND wr.id_pengawas = ?
    LIMIT 1
");

$lokasi = '';
if ($stmt_check) {
    $stmt_check->bind_param("siss", $id_wasling, $ruang, $hari, $id_pengawas);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($row = $result_check->fetch_assoc()) {
        $lokasi = $row['lokasi'];
    }
    $stmt_check->close();
}

if (empty($lokasi)) {
    echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses ke ruang ini.']);
    exit;
}

// Ambil data bahan ujian per jam
$bahan_per_jam = [];
$stmt_bahan = $conn2->prepare("
    SELECT jam1, jam2, jam3, jam4, jam5
    FROM pengawas_ruang
    WHERE id_pengawas = ? AND hari = ? AND ruang = ? AND masa = ?
    LIMIT 1
");

if ($stmt_bahan) {
    $hari_int = intval($hari);
    $stmt_bahan->bind_param("siis", $id_pengawas, $hari_int, $ruang, $masa);
    $stmt_bahan->execute();
    $result_bahan = $stmt_bahan->get_result();
    if ($row = $result_bahan->fetch_assoc()) {
        $bahan_per_jam = [
            1 => intval($row['jam1'] ?? 0),
            2 => intval($row['jam2'] ?? 0),
            3 => intval($row['jam3'] ?? 0),
            4 => intval($row['jam4'] ?? 0),
            5 => intval($row['jam5'] ?? 0)
        ];
    }
    $stmt_bahan->close();
}

// Proses data untuk jam yang dipilih
$hasil_naskah = intval($_POST['hasil_naskah'] ?? 0);
$hasil_lju_terisi = intval($_POST['hasil_lju_terisi'] ?? 0);
$hasil_lju_kosong = intval($_POST['hasil_lju_kosong'] ?? 0);
$hasil_bju_terisi = 0; 
$hasil_bju_kosong = 0;

// Ambil tanda tangan
$ttd_pengawas_ruang = trim($_POST['ttd_pengawas_ruang'] ?? '');

// Data bahan ujian untuk jam ini
$bahan_naskah = $bahan_per_jam[$jam_ke] ?? 0;
$bahan_lju = $bahan_naskah; 
$bahan_bju = 0;

// Cek apakah data sudah ada untuk jam ini
$stmt_check_existing = $conn2->prepare("
    SELECT id FROM berita_acara_serah_terima
    WHERE id_pengawas = ? AND hari = ? AND ruang = ? AND jam_ke = ? AND masa = ?
    LIMIT 1
");

$is_update = false;
$existing_id = null;

if ($stmt_check_existing) {
    $stmt_check_existing->bind_param("siiis", $id_pengawas, $hari, $ruang, $jam_ke, $masa); 
    $stmt_check_existing->execute();
    $result_existing = $stmt_check_existing->get_result();
    if ($row = $result_existing->fetch_assoc()) {
        $is_update = true;
        $existing_id = $row['id'];
    }
    $stmt_check_existing->close();
}

// Jika tidak ada tanda tangan baru dan data sudah ada, gunakan tanda tangan existing
if (empty($ttd_pengawas_ruang) && $is_update) {
    $stmt_get_ttd = $conn2->prepare("
        SELECT ttd_pengawas_ruang FROM berita_acara_serah_terima
        WHERE id = ? LIMIT 1
    ");
    if ($stmt_get_ttd) {
        $stmt_get_ttd->bind_param("i", $existing_id);
        $stmt_get_ttd->execute();
        $result_ttd = $stmt_get_ttd->get_result();
        if ($row_ttd = $result_ttd->fetch_assoc()) {
            $ttd_pengawas_ruang = $row_ttd['ttd_pengawas_ruang'] ?? '';
        }
        $stmt_get_ttd->close();
    }
}

if (empty($ttd_pengawas_ruang)) {
    echo json_encode(['status' => 'error', 'message' => "Tanda tangan pengawas ruang untuk Jam $jam_ke wajib diisi."]);
    exit;
}

// Simpan tanda tangan (base64)
$ttd_base64 = str_replace('data:image/png;base64,', '', $ttd_pengawas_ruang);

if ($is_update) {
    // Update existing data
    $stmt = $conn2->prepare("
        UPDATE berita_acara_serah_terima
        SET 
            bahan_naskah_amplop = ?,
            bahan_lju_lembar = ?,
            bahan_bju_buku = ?,
            hasil_naskah_eksp = ?,
            hasil_lju_terisi = ?,
            hasil_lju_kosong = ?,
            hasil_bju_terisi = ?,
            hasil_bju_kosong = ?,
            ttd_pengawas_ruang = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("iiiiiiiisi", 
            $bahan_naskah,
            $bahan_lju,
            $bahan_bju,
            $hasil_naskah, 
            $hasil_lju_terisi, 
            $hasil_lju_kosong, 
            $hasil_bju_terisi, 
            $hasil_bju_kosong, 
            $ttd_base64,
            $existing_id
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => 'ok', 'message' => "Berita acara Jam $jam_ke berhasil diupdate!"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement: ' . $conn2->error]);
    }
} else {
    // Insert new data
    $stmt = $conn2->prepare("
        INSERT INTO berita_acara_serah_terima
        (masa, id_wasling, id_pengawas, hari, ruang, jam_ke, lokasi, 
         bahan_naskah_amplop, bahan_lju_lembar, bahan_bju_buku,
         hasil_naskah_eksp, hasil_lju_terisi, hasil_lju_kosong, hasil_bju_terisi, hasil_bju_kosong,
         ttd_pengawas_ruang)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param("sssiiisiiiiiiiis", 
            $masa, 
            $id_wasling, 
            $id_pengawas, 
            $hari, 
            $ruang, 
            $jam_ke,
            $lokasi,
            $bahan_naskah,
            $bahan_lju,
            $bahan_bju,
            $hasil_naskah, 
            $hasil_lju_terisi, 
            $hasil_lju_kosong, 
            $hasil_bju_terisi, 
            $hasil_bju_kosong, 
            $ttd_base64
        );

        if ($stmt->execute()) {
            echo json_encode(['status' => 'ok', 'message' => "Berita acara Jam $jam_ke berhasil disimpan!"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement: ' . $conn2->error]);
    }
}
?>