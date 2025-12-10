<?php
session_start();
// Pastikan path ke config.php benar!
include "../../config.php"; 

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
$tanggal_cetak = date('d F Y');

// ==========================================================
// 2. QUERY DATA PENILAI (PJLU/PJTU)
// ==========================================================
$nama_penilai = 'Nama Penilai (PJLU/PJTU)'; // Default
$jabatan_penilai = 'PJLU/PJTU/Pengawas Keliling'; // Default
$tanda_tangan_pjlu_file = null;
$web_path_ttd = null; 
$server_path_ttd = null; 

// Mengambil data penilai dari pj_setting (asumsi conn2 terdefinisi)
$sql_ttd = "SELECT nama, role, tanda_tangan FROM pj_setting WHERE lokasi = ? AND role IN ('PJLU', 'PJTU', 'PJTU/PJLU/PK') LIMIT 1"; 
$stmt_ttd = $conn2->prepare($sql_ttd);
$stmt_ttd->bind_param("s", $lokasi_tpu);
$stmt_ttd->execute();
$result_ttd = $stmt_ttd->get_result();

if ($result_ttd && $result_ttd->num_rows > 0) {
    $d_ttd = $result_ttd->fetch_assoc();
    $tanda_tangan_pjlu_file = $d_ttd['tanda_tangan'];
    $nama_penilai = $d_ttd['nama'];
    $jabatan_penilai = $d_ttd['role'];
    
    // Jalur Web (URL)
    $web_path_ttd = $base_url . 'pjlu/signature_pj/' . $tanda_tangan_pjlu_file;
    
    // Jalur Server (Untuk file_exists())
    $server_path_ttd = $_SERVER['DOCUMENT_ROOT'] . '/sius2/pjlu/signature_pj/' . $tanda_tangan_pjlu_file;
}
$stmt_ttd->close();


// ==========================================================
// 3. QUERY DATA PENGAWAS KELILING (WASLING) MENGGUNAKAN JOIN
// ==========================================================
$wasling_data = [];
$query_error_message = ""; 

$sql_wasling = "SELECT DISTINCT w.nama_wasling, w.id_wasling 
                FROM wasling w 
                JOIN wasling_ruang wr ON w.id_wasling = wr.id_wasling
                WHERE w.lokasi_tpu = ? AND wr.hari = ?
                ORDER BY w.nama_wasling";

$stmt_wasling = $conn2->prepare($sql_wasling);
if (!$stmt_wasling) {
    $query_error_message = "🔴 Gagal Prepare Query Wasling: " . $conn2->error;
} else {
    // Binding: s untuk lokasi_tpu, s untuk hari
    $stmt_wasling->bind_param("ss", $lokasi_tpu, $hari_terpilih);
    if ($stmt_wasling->execute()) {
        $result_wasling = $stmt_wasling->get_result();
        if ($result_wasling) {
            while ($row = $result_wasling->fetch_assoc()) {
                $wasling_data[] = $row;
            }
        }
    } else {
        $query_error_message = "🔴 Gagal Execute Query Wasling: " . $stmt_wasling->error;
    }
    $stmt_wasling->close();
}

// Jika tidak ada data wasling, gunakan data dummy
if (empty($wasling_data)) {
    $wasling_data[] = ['nama_wasling' => 'Pengawas Keliling Belum Terdata', 'id_wasling' => 'WAS000'];
    $query_error_message = empty($query_error_message) ? "⚠️ Data Pengawas Keliling tidak ditemukan untuk hari ini di lokasi {$lokasi_tpu}." : $query_error_message;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Evaluasi Kinerja Pengawas Keliling</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 12px;
        }
        .container {
            width: 900px; 
            margin: 20px auto;
            padding: 20px;
            /* BARIS INI DIHAPUS: border: 1px solid #000; */
            page-break-after: always;
        }
        .header, .footer {
            text-align: center;
            font-weight: bold;
        }
        h2 {
            margin-top: 5px;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .content {
            margin-top: 20px;
        }
        .content p {
            margin: 5px 0;
        }
        .table-container {
            width: 100%;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px; 
            text-align: left;
            border: 1px solid #000;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .center-text {
            text-align: center !important;
        }
        .footer-details {
            text-align: left;
            margin-top: 30px;
            font-size: 12px;
        }
        .signature-container {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end; 
        }
        .signature-block {
            width: 45%; 
            text-align: center;
        }
        .signature-area {
            height: 80px;
            margin: 10px 0;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            display: block;
            margin: 0 auto 5px auto;
            width: 80%;
        }
        .wasling-evaluated {
            text-align: left;
            margin-top: 40px;
            margin-bottom: 50px;
        }
    </style>
</head>
<body>

<?php 
// ==========================================================
// 4. LOOP PENCETAKAN FORMULIR UNTUK SETIAP WASLING
// ==========================================================
foreach ($wasling_data as $data_wasling): 
?>

<div class="container">
    <div class="header">
        <h2>Form Evaluasi Kinerja Pengawas Keliling</h2>
        <!-- <p><?php echo htmlspecialchars($tanggal_cetak); ?></p> -->
    </div>

    <?php if (!empty($query_error_message) && $data_wasling['id_wasling'] === 'WAS000'): ?>
        <div style="color: red; font-weight: bold; margin-bottom: 10px; padding: 10px; border: 1px solid red; text-align: center;">
            <?php echo $query_error_message; ?>
        </div>
    <?php endif; ?>

    <div class="content">
        <p><strong>Nama Pengawas Keliling:</strong> <?php echo htmlspecialchars($data_wasling['nama_wasling']); ?></p>
        <p><strong>Tempat/Lokasi Ujian:</strong> <?php echo htmlspecialchars($lokasi_tpu); ?></p>
        <p><strong>Masa Ujian/Hari ke:</strong> <?php echo htmlspecialchars($masa_ujian); ?> / <?php echo htmlspecialchars($hari_terpilih); ?></p>
        <p><strong>Berilah tanda √ pada kolom Ya atau Tidak yang menunjukkan kriteria Pengawas Keliling</strong></p>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">No.</th>
                    <th>Kriteria</th>
                    <th style="width: 50px;">Ya</th>
                    <th style="width: 50px;">Tidak</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center-text">1.</td>
                    <td>Mengikuti pengarahan teknis pelaksanaan ujian dan ketentuan yang berlaku.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">2.</td>
                    <td>Meminta bahan ujian untuk jam ke-2, 3 dan 5 ke PJLU.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">3.</td>
                    <td>Mengantarkan bahan ujian ke ruang ujian dan menyerahkannya kepada pengawas ujian 15 menit sebelum ujian berlangsung.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">4.</td>
                    <td>Mengingatkan Pengawas Ruang Ujian untuk memeriksa kebenaran pengisian LJU, daftar hadir.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">5.</td>
                    <td>Memeriksa tanda tangan pengawas ruang ujian pada LJU dan daftar hadir pada setiap jam ujian.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">6.</td>
                    <td>Selalu berkeliling dari ruang ke ruang yang menjadi tanggung jawabnya.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">7.</td>
                    <td>Mengambil LJU/BJU, naskah ujian, sisa naskah ujian dan sisa LJU/BJU dari setiap ruang ujian setelah jam ujian ke-1, 2, dan 4 selesai.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">8.</td>
                    <td>Mencocokkan jumlah LJU/BJU yang diterima dengan daftar hadir dan UJ02-RK02.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
                <tr>
                    <td class="center-text">9.</td>
                    <td>Menyerahkan hasil ujian setelah jam ujian ke-1, 2, dan 4 ke PJLU/seketariat ujian.</td>
                    <td class="center-text"><input type="checkbox"></td>
                    <td class="center-text"><input type="checkbox"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer-details">
        <p><strong>Catatan:</strong></p>
        <p>Bila terdapat jawaban "Tidak" sebanyak tiga buah, terutama no.1, 4 dan 6, Pengawas keliling tersebut direkomendasikan untuk tidak digunakan lagi minimal 1 semester berikutnya.</p>
    </div>

    <div class="signature-container">
        <div class="signature-block">
            <p style="margin-bottom: 50px;"><?php echo htmlspecialchars($lokasi_tpu); ?>, <?php echo date('d F Y'); ?></p>
            <p>Penilai (<?php echo htmlspecialchars($jabatan_penilai); ?>)</p>

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
            <div class="signature-line"></div>
            <p>(<?php echo htmlspecialchars($nama_penilai); ?>)</p>
        </div>
    </div>
</div>

<?php 
endforeach; 
// ==========================================================
// AKHIR LOOP
// ==========================================================
?>

</body>
</html>