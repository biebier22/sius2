<?php
session_start();
include "../../config.php"; // Pastikan path ke config.php benar!

// ==========================================================
// 1. DEFINISI BASE URL & VARIABEL KUNCI
// ==========================================================
// Mengambil URL dasar aplikasi untuk path gambar
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'];
$app_root = 'sius2'; // Sesuaikan jika nama folder aplikasi Anda berbeda
$base_url = "{$protocol}://{$host}/{$app_root}/";

// Variabel untuk kueri (Asumsi variabel ini sudah disiapkan, misal dari $_SESSION atau $_GET)
$masa_ujian = $_SESSION['masa'] ?? "20252"; 
$lokasi_tpu = $_SESSION['lokasi'] ?? 'Lokasi Ujian Tidak Diketahui'; 
$hari_terpilih = $_GET['hari'] ?? '1'; 
$nama_lokasi = $lokasi_tpu; 

// 2. QUERY DATA PENGAWAS
$pengawas_data = [];
$query_error_message = ""; 

$sql_pengawas = "SELECT nama_pengawas FROM pengawas_ruang
                WHERE masa = ? AND lokasi = ? AND hari = ?
                ORDER BY hari, ruang";

$stmt = $conn2->prepare($sql_pengawas);
if (!$stmt) {
    $query_error_message = "🔴 Gagal Prepare Query Pengawas: " . $conn2->error;
} else {
    $stmt->bind_param("sss", $masa_ujian, $lokasi_tpu, $hari_terpilih);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $pengawas_data[] = $row;
            }
        }
    } else {
        $query_error_message = "🔴 Gagal Execute Query Pengawas: " . $stmt->error;
    }
    $stmt->close();
}

// 3. QUERY DATA PJLU UNTUK TANDA TANGAN DAN NAMA
$nama_pembuat = 'Nama Pengisi (PJTU/PJLU/PK)'; // Default
$jabatan_pembuat = 'PJTU/PJLU/Pengawas Keliling'; // Default

$tanda_tangan_pjlu_file = null;
$web_path_ttd = null; 
$server_path_ttd = null; 

$sql_ttd = "SELECT nama, role, tanda_tangan FROM pj_setting WHERE lokasi = ? AND role IN ('PJLU', 'PJTU', 'PJTU/PJLU/PK') LIMIT 1"; 
$stmt_ttd = $conn2->prepare($sql_ttd);
$stmt_ttd->bind_param("s", $lokasi_tpu);
$stmt_ttd->execute();
$result_ttd = $stmt_ttd->get_result();

if ($result_ttd && $result_ttd->num_rows > 0) {
    $d_ttd = $result_ttd->fetch_assoc();
    $tanda_tangan_pjlu_file = $d_ttd['tanda_tangan'];
    $nama_pembuat = $d_ttd['nama'];
    $jabatan_pembuat = $d_ttd['role'];
    
    // Jalur Web (URL)
    $web_path_ttd = $base_url . 'pjlu/signature_pj/' . $tanda_tangan_pjlu_file;
    
    // Jalur Server (Untuk file_exists())
    $server_path_ttd = $_SERVER['DOCUMENT_ROOT'] . '/sius2/pjlu/signature_pj/' . $tanda_tangan_pjlu_file;
}
$stmt_ttd->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Form Evaluasi Kinerja Pengawas Ruang Ujian</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 40px;
        font-size: 14px;
    }
    h2 {
        text-align: center;
        margin-top: 80px;
        text-transform: uppercase;
        font-size: 18px;
        margin-bottom: 20px;
    }
    table {
        width: 100%; 
        border-collapse: collapse;
        margin-top: 10px;
    }
    /* Mengubah default table td/th text-align menjadi center, kecuali yang diset inline */
    table th, table td {
        border: 1px solid #000;
        padding: 5px;
        text-align: center; 
    }
    .info-table td {
        border: none;
        padding: 4px;
    }
    .sign {
        margin-top: 40px;
        width: 100%;
        display: flex;
        justify-content: space-between; 
    }
    .signature-area {
        height: 80px;
    }
</style>
</head>
<body>

<div style="float: right; margin-top: -20px;">
    <table border="1" style="width: 170.5px; border-collapse: collapse; margin-top: 0;">
    <tbody>
    <tr>
    <td style="width: 152.5px; border: 1px solid #000; padding: 5px; text-align: center; font-size: 14px;">P3U-SOP03-RK14-RI.00</td>
    </tr>
    <tr>
    <td style="width: 152.5px; border: 1px solid #000; padding: 5px; text-align: center; font-size: 14px;"><?php echo date('d F Y'); ?></td>
    </tr>
    </tbody>
    </table>
</div>

<div style="clear: both;"></div> 
<h2>FORM EVALUASI KINERJA PENGAWAS RUANG UJIAN</h2>

<table class="info-table" style="width: 335px; border: none; margin-top: 20px;">
<tbody>
<tr>
<td style="border: none; width: 150px;">UT Daerah</td>
<td style="border: none; width: 175px;">: UT SERANG</td>
</tr>
<tr>
<td style="border: none; width: 150px;">Masa Ujian</td>
<td style="border: none; width: 175px;">: <?php echo htmlspecialchars($masa_ujian); ?></td>
</tr>
<tr>
<td style="border: none; width: 150px;">Tempat Ujian</td>
<td style="border: none; width: 175px;">: .........................</td>
</tr>
<tr>
<td style="border: none; width: 150px;">Lokasi Ujian</td>
<td style="border: none; width: 175px;">: <?php echo htmlspecialchars($nama_lokasi); ?></td>
</tr>
</tbody>
</table>

<p>Berilah tanda &radic; pada kolom Masalah sesuai dengan kenyataan yang ada.</p>

<table border="1" style="width: 100%;">
<tbody>
<tr style="background: #eee;">
<th style="width: 40px;">No.</th>
<th>Masalah</th>
<th style="width: 120px;">Sanksi</th>
</tr>
<tr>
<td>1</td>
<td style="text-align: left;">Tidak hadir tanpa pemberitahuan</td>
<td>Daftar Hitam</td>
</tr>
<tr>
<td>2</td>
<td style="text-align: left;">Hadir di lokasi ujian kurang dari 30 menit sebelum ujian dimulai</td>
<td>Peringatan</td>
</tr>
<tr>
<td>3</td>
<td style="text-align: left;">Keluar ruang ujian selama ujian berlangsung tanpa izin Pengawas Keliling</td>
<td>Peringatan</td>
</tr>
<tr>
<td>4</td>
<td style="text-align: left;">Membantu peserta ujian dalam menyelesaikan soal ujian</td>
<td>Daftar Hitam</td>
</tr>
<tr>
<td>5</td>
<td style="text-align: left;">Membiarkan peserta ujian melakukan kecurangan ujian</td>
<td>Daftar Hitam</td>
</tr>
<tr>
<td>6</td>
<td style="text-align: left;">Melakukan kegiatan yang mengganggu kegiatan pengawasan (merokok, tidur, dll)</td>
<td>Peringatan</td>
</tr>
<tr>
<td>7</td>
<td style="text-align: left;">Tidak tertib administrasi (misal: pengisian form, tanda tangan LJU dan Absensi, dll)</td>
<td>Peringatan</td>
</tr>
<tr>
<td>8</td>
<td style="text-align: left;">Tidak mengikuti pengarahan teknis pelaksanaan ujian</td>
<td>Peringatan</td>
</tr>
</tbody>
</table>
<p>&nbsp;</p>

<?php if (!empty($query_error_message)): ?>
    <div style="color: red; font-weight: bold; margin-bottom: 10px; padding: 10px; border: 1px solid red; text-align: center;">
        <?php echo $query_error_message; ?>
    </div>
<?php endif; ?>
<table border="1" style="width: 100%;">
<tbody>
<tr style="background: #eee;">
<th style="width: 40px;" rowspan="2">NO</th>
<th style="width: 200px;" rowspan="2">NAMA</th>
<th colspan="8">MASALAH</th>
<th style="width: 120px;" rowspan="2">Rekomendasi</th>
</tr>
<tr style="background: #eee;">
<th>1</th>
<th>2</th>
<th>3</th>
<th>4</th>
<th>5</th>
<th>6</th>
<th>7</th>
<th>8</th>
</tr>

    <?php 
    $row_count = count($pengawas_data);
    $limit = 20; // Batas maksimal baris
    $no = 1;
    
    // 1. ISI DATA DARI DATABASE (List Nama Pengawas)
    foreach ($pengawas_data as $data) {
        echo '<tr>';
        echo '<td>'.$no++.'</td>';
        // Kolom NAMA sudah rata kiri di kode sebelumnya
        echo '<td style="text-align:left;">'.htmlspecialchars($data['nama_pengawas']).'</td>'; 
        
        // Kolom Masalah dan Rekomendasi (Total 9 kolom kosong)
        for ($i = 1; $i <= 9; $i++) {
            echo '<td>&nbsp;</td>'; 
        }
        echo '</tr>';
    }

    // 2. ISI BARIS KOSONG (JIKA TOTAL KURANG DARI LIMIT 20)
    $remaining_rows = $limit - $row_count;
    for ($i = 0; $i < $remaining_rows; $i++) {
        echo '<tr>';
        echo '<td>'.$no++.'</td>';
        echo '<td>&nbsp;</td>'; // Kolom NAMA kosong
        
        // Kolom Masalah dan Rekomendasi (Total 9 kolom kosong)
        for ($j = 1; $j <= 9; $j++) {
            echo '<td>&nbsp;</td>';
        }
        echo '</tr>';
    }
    ?>
</tbody>
</table>

<div class="sign">
    <div>
        Mengetahui<br />
        Ka UPBJJ-UT<br /><br /><br /><br />
        _________________________<br />
        NIP.
    </div>

    <div>
        <?php echo htmlspecialchars($jabatan_pembuat); ?><br /><br />
        <div class="signature-area">
            <?php 
            // Tampilkan Tanda Tangan
            if (!empty($tanda_tangan_pjlu_file) && file_exists($server_path_ttd)) {
                echo '<img src="'.htmlspecialchars($web_path_ttd).'" alt="Tanda Tangan" style="max-height: 80px; max-width: 150px; display: block; margin: 0 auto;">';
            } else {
                // Placeholder agar jarak baris tidak berubah
                echo '<div style="height: 80px; text-align:center; font-style: italic; color: #777;">[Tanda Tangan]</div>';
            }
            ?>
        </div>
        _________________________<br />
        <?php echo htmlspecialchars($nama_pembuat); ?><br />
    </div>
</div>

</body>
</html>