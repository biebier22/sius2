<?php
session_start();
include "../../config.php";

// =============================
// DEFINISI BASE URL (SOLUSI WARNING)
// =============================
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'];
$app_root = 'sius2'; // Nama folder aplikasi utama Anda
// Hasil: http://[domain_atau_localhost]/sius2/
$base_url = "{$protocol}://{$host}/{$app_root}/";


// =============================
// CEK SESSION PJLU
// =============================
if (!isset($_SESSION['pjtu_unlock']) || empty($_SESSION['lokasi'])) {
    echo "<div style='padding:20px; text-align:center; color:#c33;'>Akses PJLU atau lokasi belum tersedia.</div>";
    exit;
}

// =============================
// AMBIL DATA SESSION
// =============================
$lokasi_tpu     = $_SESSION['lokasi'] ?? 'Lokasi Tidak Diketahui';
$id_pembuat     = $_SESSION['username'] ?? 'PJTU001';
$nama_pembuat = $_SESSION['nama_user'] ?? 'PJTU/PJLU';
$masa_ujian     = "20252";

// =============================
// AMBIL HARI DARI GET / DEFAULT
// =============================
$hari_terpilih = $_GET['hari'] ?? '1';  // default ke Hari 1
$_SESSION['hari_tanggal'] = $hari_terpilih; // simpan juga ke session untuk turunannya


// =========================================================
// 1. AMBIL TANDA TANGAN PJLU DARI pj_setting
// =========================================================
$tanda_tangan_pjlu_file = null;
$web_path_ttd = null; // Jalur untuk URL <img>
$server_path_ttd = null; // Jalur absolut untuk file_exists()

$sql_ttd = "SELECT tanda_tangan FROM pj_setting WHERE lokasi = ? LIMIT 1";
$stmt_ttd = $conn2->prepare($sql_ttd);
$stmt_ttd->bind_param("s", $lokasi_tpu);
$stmt_ttd->execute();
$result_ttd = $stmt_ttd->get_result();

// ... (Kode koneksi DB dan query pj_setting di atas)

if ($result_ttd && $result_ttd->num_rows > 0) {
    $d_ttd = $result_ttd->fetch_assoc();
    $tanda_tangan_pjlu_file = $d_ttd['tanda_tangan'];
    
    // ... (di dalam if ($result_ttd && $result_ttd->num_rows > 0))

// ...
    
// Jalur Web (URL): Sudah diperbaiki di atas
$web_path_ttd = $base_url . 'pjlu/signature_pj/' . $tanda_tangan_pjlu_file;
    
// ⚠️ KOREKSI JALUR SERVER menggunakan DOCUMENT_ROOT
// Asumsi $_SERVER['DOCUMENT_ROOT'] mengarah ke E:\app_2024\htdocs\
// Folder sius2 berada di dalamnya.
$server_path_ttd = $_SERVER['DOCUMENT_ROOT'] . '/sius2/pjlu/signature_pj/' . $tanda_tangan_pjlu_file;

// ...

// ...
}
$stmt_ttd->close();
// ...

// Baris yang Anda miliki sebelumnya: echo $tanda_tangan_pjlu_file;
// Hapus baris di atas, karena itu bisa mengganggu output header.

// =============================
// QUERY DATA PENGAWAS DARI DATABASE
// =============================
$pengawas_data = [];
$sql = "SELECT ruang, hari, nama_pengawas, lokasi AS instansi_asal
        FROM pengawas_ruang
        WHERE masa = ? AND lokasi = ? AND hari = ?
        ORDER BY hari, ruang";

$stmt = $conn2->prepare($sql);
$stmt->bind_param("sss", $masa_ujian, $lokasi_tpu, $hari_terpilih);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pengawas_data[] = $row;
}
$stmt->close();

// =============================
// TANGGAL CETAK
// =============================
$tanggal_cetak = date('d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Pengawas Ruang Ujian</title>
<style>
/* Styles sama seperti sebelumnya */
body { font-family: 'Times New Roman', Times, serif; margin:0; padding:0; font-size:11pt; color:#000; }
.print-area { width:210mm; min-height:297mm; margin:0 auto; padding:20mm; box-sizing:border-box; position:relative; }
.top-metadata { position:absolute; top:20mm; right:20mm; font-size:8pt; text-align:right; line-height:1.3; border:1px solid #000; padding:3px 5px; display:inline-block; }
.doc-title { font-size:14pt; font-weight:bold; text-align:center; margin:10px 0 15px 0; text-decoration:underline; }
.doc-header table { width:100%; border-collapse:collapse; table-layout:fixed; margin-bottom:10px; }
.doc-header td:first-child { width:150px; font-weight:normal; }
.doc-header td { padding:2px 0; }
.pengawas-table { width:100%; border-collapse:collapse; font-size:10pt; margin-top:5px; }
.pengawas-table th, .pengawas-table td { border:1px solid #000; padding:4px 6px; text-align:center; vertical-align:middle; }
.pengawas-table th { font-weight:bold; }
.pengawas-table .nama-pengawas, .pengawas-table .instansi-asal { text-align:left; }
.signature-block { margin-top:50px; font-size:11pt; overflow:auto; }
.signature-block .signer { width:50%; float:right; text-align:center; }
.signature-block .signer p { margin:5px 0; }
.signature-block .spacer { height:80px; padding: 5px 0; } /* Menambahkan padding dan tinggi untuk gambar TTD */

/* Style tambahan untuk gambar tanda tangan */
.signature-block .signer img {
    max-width: 150px; 
    max-height: 80px; 
    display: block; 
    margin: 0 auto;
}

@media print { .print-area { padding:10mm; margin:0; } }
</style>
</head>
<body>
<div class="print-area">

    <div class="top-metadata">
        <p style="margin:0;">P3U-SOP03-RK07-RI.00</p>
        <p style="margin:0;"><?php echo $tanggal_cetak; ?></p>
    </div>

    <div class="doc-title">DAFTAR PENGAWAS RUANG UJIAN</div>

    <div class="doc-header">
        <table>
            <tr><td>UNIT DAERAH</td><td>: UT SERANG</td></tr>
            <tr><td>TEMPAT UJIAN</td><td>: <?php echo htmlspecialchars($tempat_ujian ?? '-'); ?></td></tr>
            <tr><td>LOKASI UJIAN</td><td>: <?php echo htmlspecialchars($lokasi_tpu); ?></td></tr>
            <tr><td>MASA UJIAN</td><td>: <?php echo htmlspecialchars($masa_ujian); ?></td></tr>
            <tr><td>HARI/TANGGAL UJIAN</td><td>: H<?php echo htmlspecialchars($hari_terpilih); ?></td></tr>
        </table>
    </div>

    <table class="pengawas-table">
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Ruang Ujian</th>
                <th colspan="5">Jam Ujian</th>
                <th rowspan="2" class="nama-pengawas">Nama Pengawas</th>
                <th rowspan="2" class="instansi-asal">Instansi Asal</th>
            </tr>
            <tr>
                <th>1</th>
                <th>2</th>
                <th>3</th>
                <th>4</th>
                <th>5</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            foreach ($pengawas_data as $row) {
                echo "<tr>";
                echo "<td>{$no}</td>";
                echo "<td>".htmlspecialchars($row['ruang'])."</td>";
                // Asumsi semua jam terisi, isi dengan "V"
                for ($j=1; $j<=5; $j++) echo "<td>V</td>"; 
                echo "<td class='nama-pengawas'>".htmlspecialchars($row['nama_pengawas'])."</td>";
                echo "<td class='instansi-asal'>".htmlspecialchars($row['instansi_asal'])."</td>";
                echo "</tr>";
                $no++;
            }
            ?>
        </tbody>
    </table>

    <div class="signature-block">
        <div class="signer">
            <p>PJLU</p>
            <div class="spacer">
            <?php 
                // Cek apakah nama file TTD ditemukan di database
                if (!empty($tanda_tangan_pjlu_file) && !empty($server_path_ttd)) {
                    
                    // Cek apakah file benar-benar ada di lokasi server
                    if (file_exists($server_path_ttd)) {
                        // Jika ada, tampilkan gambar
                        echo '<img src="'.htmlspecialchars($web_path_ttd).'" alt="Tanda Tangan PJLU">';
                    } else {
                        // Jika file tidak ada di server, tampilkan notifikasi debug
                        $debug_path = ' (Server mencari: ' . htmlspecialchars($server_path_ttd) . ')';
                        echo '<div style="height: 80px; text-align:center; font-style: italic; color: #777; border: 1px dashed #f00;">File Tanda Tangan Tidak Ditemukan'.$debug_path.'</div>';
                    }
                    
                } else {
                    // Jika nama file di database kosong
                    echo '<div style="height: 80px; text-align:center; font-style: italic; color: #777;">Tanda Tangan Belum Tersedia (Database Kosong)</div>';
                }
            ?>
            </div>
            <p>**( <?php echo $nama_pembuat ?> )**</p>
            <p>NIP</p>
        </div>
    </div>

</div>
</body>
</html>