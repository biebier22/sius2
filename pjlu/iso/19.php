<?php
session_start();
include "../../config.php";

// ==========================================================
// 1. DEFINISI BASE URL & PARAMETER
// ==========================================================
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'];
$app_root = 'sius2'; 
$base_url = "{$protocol}://{$host}/{$app_root}/";

$masa   = "20252";
$username = $_SESSION['nama_user'];
$hari = $_GET['hari']  
     ?? $_POST['hari']  
     ?? $_SESSION['hari']
     ?? "1";

$lokasi = $_GET['lokasi']
       ?? $_POST['lokasi']  
       ?? $_SESSION['lokasi']
       ?? "";

if ($lokasi == "") {
    die("Lokasi tidak ditemukan");
}


// ==========================================================
// 2. QUERY DATA PENILAI (PJLU/PJTU) - untuk TTD PJLU
// Data ini tunggal untuk satu lokasi
// ==========================================================
$tanda_tangan_pjlu_file = null;
$nama_pjlu_umum = "Nama Penanggung Jawab Lokasi Ujian";

$sql_ttd = "SELECT nama, tanda_tangan FROM pj_setting WHERE lokasi = ? AND role IN ('PJLU', 'PJTU', 'PJTU/PJLU/PK') LIMIT 1"; 
$stmt_ttd = $conn2->prepare($sql_ttd);
$stmt_ttd->bind_param("s", $lokasi);
$stmt_ttd->execute();
$result_ttd = $stmt_ttd->get_result();

if ($result_ttd && $result_ttd->num_rows > 0) {
    $d_ttd = $result_ttd->fetch_assoc();
    $tanda_tangan_pjlu_file = $d_ttd['tanda_tangan'];
    $nama_pjlu_umum = $d_ttd['nama']; 
}
$stmt_ttd->close();


// ==========================================================
// 3. HELPER TANDA TANGAN (PATH DIPERBARUI)
// ==========================================================
function getSignatureImageTag($filename, $base_url, $role) {
    if (empty($filename)) {
        return '';
    }
    
    switch ($role) {
        case 'pjlu':
            $folder_path = 'pjlu/signature_pj/'; 
            break;
        case 'wasling':
            $folder_path = 'wasling/signature/'; 
            break;
        case 'wasrung':
            $folder_path = 'wasrung/signature/'; 
            break;
        default:
            return ''; 
    }

    $full_web_path = $base_url . $folder_path . $filename;
    $style = 'max-width: 100%; max-height: 35px; display: block; margin: 0 auto; object-fit: contain;';
    
    return '<img src="'.htmlspecialchars($full_web_path).'" style="'.$style.'" alt="TTD">';
}


// ==========================================================
// 4. QUERY DAN PENGELOMPOKAN DATA BAST (KUNCI PERBAIKAN)
// ==========================================================
// Kueri semua BAST yang relevan (filter Hari & Lokasi)
$q = $conn2->prepare("
    SELECT
        bas.*,
        w.nama_wasling,
        w.id_wasling,
        pr.nama_pengawas,
        pr.kode_tpu
    FROM berita_acara_serah_terima bas
    INNER JOIN wasling w 
        ON w.id_wasling = bas.id_wasling
    INNER JOIN pengawas_ruang pr
        ON pr.id_pengawas = bas.id_pengawas
    WHERE bas.hari = ?
      AND bas.lokasi = ? 
      AND bas.masa = ?
    ORDER BY bas.id_wasling, bas.jam_ke ASC
");
$q->bind_param("sss", $hari, $lokasi, $masa);
$q->execute();
$all_bast_data = $q->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($all_bast_data)) {
    die("Data Berita Acara Serah Terima tidak ditemukan untuk hari {$hari} di lokasi {$lokasi}.");
}

// Kelompokkan data BAST berdasarkan id_wasling
$grouped_bast_data = [];
$unique_wasling_info = [];

foreach ($all_bast_data as $d) {
    $id_wasling = $d['id_wasling'];
    
    if (!isset($grouped_bast_data[$id_wasling])) {
        $grouped_bast_data[$id_wasling] = [];
        // Simpan info Wasling (umum) yang akan digunakan di header
        $unique_wasling_info[$id_wasling] = [
            'nama_wasling' => $d['nama_wasling'],
            'tanggal_ujian' => $d['tanggal_ujian'],
            'kode_tpu' => $d['kode_tpu'],
            'lokasi' => $d['lokasi'],
            'id_wasling' => $id_wasling,
        ];
    }
    
    $grouped_bast_data[$id_wasling][] = $d;
}

// Ambil info umum dari data pertama (karena hari dan lokasi sama)
$tanggal_ujian_umum = $all_bast_data[0]['tanggal_ujian'];
$kode_tpu_umum = $all_bast_data[0]['kode_tpu'];
$lokasi_umum = $all_bast_data[0]['lokasi'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Berita Acara Serah Terima - Ruangan</title>

<style>
    body {
        font-family: "Times New Roman", serif;
        font-size: 14px; 
        margin: 20px;
    }

    .page-break {
        width: 100%;
        page-break-after: always; /* KUNCI pemisahan halaman untuk cetak */
        padding-top: 20px; 
    }

    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 14px;
    }

    th, td {
        border: 1px solid #000;
        padding: 4px;
        text-align: center;
        vertical-align: top;
    }

    .no-border td {
        border: none;
        padding: 2px 4px;
    }

    .header-title {
        text-align: center;
        font-weight: bold;
        margin-bottom: 15px;
        font-size: 16px; 
    }

    .arsir {
        background: repeating-linear-gradient(
            -45deg,
            #ccc,
            #ccc 3px,
            #fff 3px,
            #fff 6px
        );
    }
    
    tbody tr {
        height: 50px; 
    }

    .top-right-box {
        position: absolute;
        top: 10px;       
        right: 10px;     
        font-size: 14px;
    }
</style>
</head>
<body>

<?php
// ==========================================================
// 6. LOOP UTAMA: ITERASI BERDASARKAN ID WASLING
// ==========================================================
foreach ($grouped_bast_data as $id_wasling => $wasling_data_for_all_rooms):
    
    // Set header Wasling spesifik untuk grup saat ini
    $current_wasling_info = $unique_wasling_info[$id_wasling];
    $nama_wasling_umum = $current_wasling_info['nama_wasling'];

    // 6a. EKSTRAK DAFTAR RUANGAN UNIK (Pengawas Ruang) DARI KELOMPOK WASLING INI
    $rooms_to_print = [];
    $unique_pengawas_ids = [];
    
    foreach ($wasling_data_for_all_rooms as $d) {
        $id_pengawas = $d['id_pengawas'];
        
        if (!isset($unique_pengawas_ids[$id_pengawas])) {
            $rooms_to_print[] = [
                'nama_ruang' => $d['ruang'], 
                'id_pengawas' => $id_pengawas,
                'nama_pengawas' => $d['nama_pengawas'],
            ];
            $unique_pengawas_ids[$id_pengawas] = true;
        }
    }

    // ==========================================================
    // 7. LOOP DALAM: ITERASI BERDASARKAN RUANGAN (Pencetakan per halaman)
    // ==========================================================
    foreach ($rooms_to_print as $room_header): 
        
        $current_ruang = $room_header['nama_ruang'];
        $current_id_pengawas = $room_header['id_pengawas'];
        $current_nama_pengawas = $room_header['nama_pengawas'];

        // 7a. FILTER data BAST hanya untuk ruangan ini (mengambil data per jam)
        // Kita filter dari data BAST yang sudah dikelompokkan per Wasling (wasling_data_for_all_rooms)
        $room_bast_data_filtered = array_filter($wasling_data_for_all_rooms, function($d) use ($current_id_pengawas) {
            return $d['id_pengawas'] == $current_id_pengawas;
        });

        $jam = [];
        $current_total = [
            'bahan_naskah_amplop' => 0, 'bahan_lju_lembar' => 0, 'bahan_bju_buku' => 0,
            'hasil_naskah_eksp' => 0, 'hasil_lju_terisi' => 0, 'hasil_lju_kosong' => 0,
            'hasil_bju_terisi' => 0, 'hasil_bju_kosong' => 0
        ];

        foreach ($room_bast_data_filtered as $d) {
            $jam[intval($d['jam_ke'])] = $d;

            // Hitung total untuk ruangan ini
            $current_total['bahan_naskah_amplop'] += intval($d['bahan_naskah_amplop']);
            $current_total['bahan_lju_lembar']    += intval($d['bahan_lju_lembar']);
            $current_total['bahan_bju_buku']      += intval($d['bahan_bju_buku']);

            $current_total['hasil_naskah_eksp']   += intval($d['hasil_naskah_eksp']);
            $current_total['hasil_lju_terisi']    += intval($d['hasil_lju_terisi']);
            $current_total['hasil_lju_kosong']    += intval($d['hasil_lju_kosong']);
            $current_total['hasil_bju_terisi']    += intval($d['hasil_bju_terisi']);
            $current_total['hasil_bju_kosong']    += intval($d['hasil_bju_kosong']);
        }

?>
<div class="page-break">

<div class="top-right-box">
    <table style="border-collapse: collapse;">
        <tr>
            <td style="text-align: right; border:none; padding: 0 0 2px 0;">P3U-SOP03-RK05-RI.01</td>
        </tr>
        <tr>
            <td style="text-align: right; border:none; padding: 2px 0 0 0;"><?= date('d F Y'); ?></td>
        </tr>
    </table>
</div>

<div style="height:56px;"></div>

<div class="header-title">
    BERITA ACARA SERAH TERIMA BAHAN UJIAN DARI PJLU KE PENGAWAS KELILING/RUANG UJIAN DAN PENYERAHAN HASIL UJIAN DARI PENGAWAS KELILING/RUANG UJIAN KE PJLU
</div>

<table class="no-border fr-table-selection-hover" width="100%">
<tbody>
<tr><td style="width: 20%; text-align: left;">UT Daerah</td>
<td style="width: 30%; text-align:left;">UT SERANG</td>
<td style="width: 20%; text-align: left;">Tanggal Ujian</td>
<td style="width: 30%; text-align:left;"><?= htmlspecialchars($tanggal_ujian_umum ?: '_____________________________') ?></td>
</tr>
<tr>
<td style="text-align: left;">Tempat Ujian</td>
<td style="width: 30%; text-align:left;"><?= htmlspecialchars($kode_tpu_umum ?: '_____________________________') ?></td>
<td style="width: 20%; text-align: left;">Nama Penanggung Jawab Lokasi Ujian</td>
<td style="width: 30%; text-align:left;"><?= htmlspecialchars($nama_pjlu_umum ?: '_____________________________') ?></td>
</tr>
<tr>
<td style="text-align: left;">Lokasi Ujian</td>
<td style="text-align:left;"><?= htmlspecialchars($lokasi_umum ?: '_____________________________') ?></td>
<td style="text-align: left;">Nama Pengawas Keliling</td>
<td style="text-align:left;">**<?= htmlspecialchars($nama_wasling_umum ?: '_____________________________') ?>**</td>
</tr>
<tr>
<td style="text-align: left;">Ruang Ujian</td>
<td style="text-align:left;">**<?= htmlspecialchars($current_ruang) ?>**</td>
<td style="text-align: left;">Nama Pengawas Ruang Ujian</td>
<td style="text-align:left;">**<?= htmlspecialchars($current_nama_pengawas ?: '_____________________________') ?>**</td>
</tr>
<tr>
<td style="text-align: left;">Hari Ujian Ke</td>
<td style="text-align:left;"><?= htmlspecialchars($hari) ?></td>
<td style="text-align: left;">&nbsp;</td>
<td style="text-align: left;">&nbsp;</td>
</tr>
</tbody>
</table>

<br>

<table>
<thead>
<tr>
    <th rowspan="2" width="50">Jam Ke</th>

    <th colspan="3">Jumlah Bahan Ujian yang diserahkan</th>
    <th colspan="3">Tanda Tangan *</th>
    <th colspan="5">Jumlah Hasil Ujian yang diterima</th>
    <th colspan="3">Tanda Tangan **</th>
</tr>

<tr>
    <th>Naskah<br>(amplop)</th>
    <th>LJU<br>(lembar)</th>
    <th>BIU<br>(buku)</th>

    <th>PJLU</th>
    <th>Pengawas Keliling</th>
    <th>Pengawas Ruang Ujian</th>

    <th>Naskah<br>(ekspl. per amplop)</th>
    <th>LJU<br>Terisi</th>
    <th>LJU<br>Kosong</th>
    <th>BIU<br>Terisi</th>
    <th>BIU<br>Kosong</th>

    <th>Pengawas Ruang Ujian</th>
    <th>Pengawas Keliling</th>
    <th>PJLU</th>
</tr>
</thead>

<tbody>
<?php for ($i=1; $i<=5; $i++):
    $d = $jam[$i] ?? null;
?>
<tr>
    <td><?= $i ?></td>

    <td><?= $d ? intval($d['bahan_naskah_amplop']) : '' ?></td>
    <td><?= $d ? intval($d['bahan_lju_lembar']) : '' ?></td> 
    <td><?= $d ? intval($d['bahan_bju_buku']) : '' ?></td>

    <td class="<?= ($i==2 || $i==3 ? 'arsir' : '') ?>">
        <?php if ($i != 2 && $i != 3): ?>
            <?= getSignatureImageTag($tanda_tangan_pjlu_file, $base_url, 'pjlu') ?>
        <?php endif; ?>
    </td>
    
    <td class="<?= ($i==3 || $i==4 ? 'arsir' : '') ?>">
        <?php if ($i != 3 && $i != 4 && $d): ?>
            <?= getSignatureImageTag($d['ttd_wasling'] ?? '', $base_url, 'wasling') ?>
        <?php endif; ?>
    </td>
    
    <td class="<?= ($i==1 || $i==4 ? 'arsir' : '') ?>">
        <?php if ($i != 1 && $i != 4 && $d): ?>
            <?= getSignatureImageTag($d['ttd_pengawas_ruang'] ?? '', $base_url, 'wasrung') ?>
        <?php endif; ?>
    </td>

    <td><?= $d ? intval($d['hasil_naskah_eksp']) : '' ?></td>
    <td class="<?= ($d && intval($d['hasil_lju_terisi']) > 0) ? 'rekap-jam-terisi' : '' ?>"><?= $d ? intval($d['hasil_lju_terisi']) : '' ?></td>
    <td><?= $d ? intval($d['hasil_lju_kosong']) : '' ?></td>
    <td><?= $d ? intval($d['hasil_bju_terisi']) : '' ?></td>
    <td><?= $d ? intval($d['hasil_bju_kosong']) : '' ?></td>


    <td class="<?= ($i==3 ? 'arsir' : '') ?>">
        <?php if ($i != 3 && $d): ?>
            <?= getSignatureImageTag($d['ttd_pengawas_ruang'] ?? '', $base_url, 'wasrung') ?>
        <?php endif; ?>
    </td>
    
    <td class="<?= ($i==2 ? 'arsir' : '') ?>">
        <?php if ($i != 2 && $d): ?>
            <?= getSignatureImageTag($d['ttd_wasling'] ?? '', $base_url, 'wasling') ?>
        <?php endif; ?>
    </td>
    
    <td class="<?= ($i==5 ? 'arsir' : '') ?>">
        <?php if ($i != 5): ?>
            <?= getSignatureImageTag($tanda_tangan_pjlu_file, $base_url, 'pjlu') ?>
        <?php endif; ?>
    </td>
</tr>
<?php endfor; ?>

<tr>
    <td><b>Jumlah</b></td>

    <td><?= $current_total['bahan_naskah_amplop'] ?></td>
    <td><?= $current_total['bahan_lju_lembar'] ?></td>
    <td><?= $current_total['bahan_bju_buku'] ?></td>

    <td></td>
    <td></td>
    <td></td>

    <td><?= $current_total['hasil_naskah_eksp'] ?></td>
    <td><?= $current_total['hasil_lju_terisi'] ?></td>
    <td><?= $current_total['hasil_lju_kosong'] ?></td>
    <td><?= $current_total['hasil_bju_terisi'] ?></td>
    <td><?= $current_total['hasil_bju_kosong'] ?></td>

    <td></td>
    <td></td>
    <td></td>
</tr>

</tbody>
</table>

<br><br>

<b>Keterangan:</b><br>
* : Pada jam pertama dan keempat bahan ujian diserahkan dari PJLU langsung kepada Pengawas Ruang Ujian<br>
** : Pada jam kedua, ketiga, dan kelima bahan ujian diserahkan dari PJLU kepada Pengawas Keliling selanjutnya, dari Pengawas Keliling kepada Pengawas Ruang Ujian<br>
*** : Pada jam pertama, kedua, dan keempat hasil ujian diserahkan oleh Pengawas Ruang Ujian kepada Pengawas Keliling selanjutnya, dari Pengawas Keliling kepada PJLU<br>
**** : Pada jam ketiga, dan kelima hasil ujian diserahkan oleh Pengawas Ruang Ujian kepada PJLU<br>

<br>

<b>Bahan ujian</b> adalah naskah ujian + LJU dan/atau BIU + Form Berita Acara + Form Daftar Hadir. <br>
<b>Hasil ujian</b> adalah naskah ujian + LJU dan/atau BIU terisi dan kosong + Berita Acara Terisi + Daftar Hadir Terisi.

</div>
<?php 
    endforeach; // Akhir loop ruangan (Pencetakan per halaman)
endforeach; // Akhir loop Wasling (Pengelompokan)
?>

</body>
</html>