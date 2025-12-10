<?php
session_start();
// Pastikan file config dan koneksi database di-include
include "../../config.php";

// ========== VALIDASI INPUT ==========
$lokasi = $_GET['lokasi'] ?? '';
$hari = intval($_GET['hari'] ?? 0);
$masa = '20252'; // Asumsi masa tetap
$masa_reg = '20252'; // Variabel yang digunakan di cetak_rekap_d20an.php

if (empty($lokasi) || $hari < 1) {
    echo "Parameter Lokasi atau Hari tidak lengkap.";
    exit;
}

if (!isset($conn2)) {
    echo "Koneksi database error.";
    exit;
}

// ========== QUERY METADATA LOKASI (TGL, KODE TPU, POS) ==========
$tpu_info = [];
$stmt_tpu = $conn2->prepare("
    SELECT DISTINCT
        e.kode_tpu,
        e.pos_ujian
    FROM e_lokasi_uas e
    WHERE e.lokasi = ?
      AND e.hari = ?
      AND e.masa = ?
    LIMIT 1
");

$tgl_ujian = '';
$tempat = ''; // kode_tpu
$pos_ujian = '';
if ($stmt_tpu) {
    $stmt_tpu->bind_param("sis", $lokasi, $hari, $masa);
    $stmt_tpu->execute();
    $result_tpu = $stmt_tpu->get_result();
    if ($tpu_info_row = $result_tpu->fetch_assoc()) {
        // $tgl_ujian = $tpu_info_row['tgl_ujian'];
        $tempat = $tpu_info_row['kode_tpu'];
        $pos_ujian = $tpu_info_row['pos_ujian'];
    }
    $stmt_tpu->close();
}

// Persiapan untuk HARI dalam Bahasa Indonesia (untuk header)
$day = date('D', strtotime($tgl_ujian));
$dayList = [
    'Sun' => 'MINGGU',
    'Mon' => 'SENIN',
    'Tue' => 'SELASA',
    'Wed' => 'RABU',
    'Thu' => 'KAMIS',
    'Fri' => 'JUMAT',
    'Sat' => 'SABTU'
];
$hari2 = $dayList[$day] ?? '';

// ========== QUERY REKAP RUANG PER LOKASI DAN HARI (Menggunakan logika BAST yang sudah ada) ==========

$rekapDataHari = [
    'ruangs' => [],
    'ruang_awal' => null,
    'ruang_akhir' => null
];

// Inisialisasi Total untuk TABEL 1 & 2 (Header Total dan Row Total)
$total_naskah_jam = array_fill(1, 5, 0);
$total_lju_jam = array_fill(1, 5, 0);
$total_bju_jam = array_fill(1, 5, 0);
$grand_total_naskah = 0;
$grand_total_lju = 0;
$grand_total_bju = 0;
$grand_total_amplop = 0;

$stmt = $conn2->prepare("
    SELECT DISTINCT
        r.hari_ke AS hari,
        r.ruang_ke,
        COALESCE(r.jam1, 0) AS jam1,
        COALESCE(r.jam2, 0) AS jam2,
        COALESCE(r.jam3, 0) AS jam3,
        COALESCE(r.jam4, 0) AS jam4,
        COALESCE(r.jam5, 0) AS jam5,
        COALESCE(r.total, 0) AS total,
        e.ruang_awal,
        e.ruang_akhir
    FROM rekap_ujian r
    LEFT JOIN e_lokasi_uas e
        ON r.kode_tpu = e.kode_tpu
        AND e.masa = ?
        AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED) AND CAST(e.ruang_akhir AS UNSIGNED)
        AND CAST(r.hari_ke AS CHAR) = e.hari
    WHERE r.masa = ?
        AND e.lokasi = ?
        AND r.hari_ke = ?
        AND r.hari_ke IS NOT NULL
        AND r.ruang_ke IS NOT NULL
    ORDER BY CAST(r.ruang_ke AS UNSIGNED) ASC
");

if ($stmt) {
    $hari_str = (string)$hari;
    $stmt->bind_param("sssi", $masa, $masa, $lokasi, $hari);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ruang_ke = $row['ruang_ke'];
        
        // Update ruang_awal dan ruang_akhir (Untuk informasi di header)
        if ($rekapDataHari['ruang_awal'] === null || $row['ruang_awal'] < $rekapDataHari['ruang_awal']) {
            $rekapDataHari['ruang_awal'] = $row['ruang_awal'];
        }
        if ($rekapDataHari['ruang_akhir'] === null || $row['ruang_akhir'] > $rekapDataHari['ruang_akhir']) {
            $rekapDataHari['ruang_akhir'] = $row['ruang_akhir'];
        }

        // Query data berita_acara (BAST) per ruang
        $berita_acara_data = [];
        $stmt_ba = $conn2->prepare("
            SELECT 
                jam_ke,
                COALESCE(hasil_lju_terisi, 0) AS lju,
                COALESCE(hasil_bju_terisi, 0) AS bju,
                COALESCE(hasil_naskah_eksp, 0) AS naskah
            FROM berita_acara_serah_terima
            WHERE masa = ? 
                AND hari = ? 
                AND ruang = ?
                AND lokasi = ? 
            ORDER BY jam_ke ASC
        ");
        
        if ($stmt_ba) {
            $stmt_ba->bind_param("ssis", $masa, $hari_str, $ruang_ke, $lokasi);
            $stmt_ba->execute();
            $result_ba = $stmt_ba->get_result();
            
            while ($row_ba = $result_ba->fetch_assoc()) {
                $berita_acara_data[$row_ba['jam_ke']] = [
                    'lju' => $row_ba['lju'],
                    'bju' => $row_ba['bju'],
                    'naskah' => $row_ba['naskah']
                ];
            }
            $stmt_ba->close();
        }

        // Hitung data per jam dan total
        $jam_data = [];
        $total_lju = 0;
        $total_bju = 0;
        $total_naskah = 0;
        
        for ($j = 1; $j <= 5; $j++) {
            $jam_key = 'jam' . $j;
            $naskah_rekap = $row[$jam_key] ?? 0;
            
            if (isset($berita_acara_data[$j])) {
                $lju = $berita_acara_data[$j]['lju'];
                $bju = $berita_acara_data[$j]['bju'];
                // Gunakan Naskah dari BAST jika ada, atau dari rekap jika tidak ada
                $naskah = $berita_acara_data[$j]['naskah'] > 0 ? $berita_acara_data[$j]['naskah'] : $naskah_rekap;
            } else {
                $lju = 0;
                $bju = 0;
                $naskah = $naskah_rekap;
            }
            
            $jam_data[$j] = [
                'lju' => $lju,
                'bju' => $bju,
                'naskah' => $naskah,
            ];
            
            $total_lju += $lju;
            $total_bju += $bju;
            $total_naskah += $naskah;

            // UPDATE AGGREGATES FOR TABLE 1 & 2 TOTAL ROW
            $total_lju_jam[$j] += $lju;
            $total_bju_jam[$j] += $bju;
            $total_naskah_jam[$j] += $naskah;
        }
        
        // Update Grand Totals
        $grand_total_lju += $total_lju;
        $grand_total_bju += $total_bju;
        $grand_total_naskah += $total_naskah;
        $grand_total_amplop += 1;

        // Simpan data ruang
        $rekapDataHari['ruangs'][] = [
            'ruang_ke' => $ruang_ke,
            'jam_data' => $jam_data,
            'total' => [
                'lju' => $total_lju,
                'bju' => $total_bju,
                'naskah' => $total_naskah
            ],
            'jumlah_amplop' => 1 // Asumsi 1 amplop per ruang
        ];
    }
    $stmt->close();
}

?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; }
        .container { width: 95%; margin: auto; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        th, td { border: 1px solid #000; padding: 4px; }
        th { background-color: #f0f0f0; }
        h3 { margin-bottom: 5px; }
        .btn { display: none; } /* Hide button when printing */
        
        /* Tambahkan style untuk menyesuaikan cetak_rekap_d20an.php */
        p strong { font-weight: bold; }
        table.info-header td { border: none; padding: 2px 5px; font-size: 11pt; }
        .table-data td, .table-data th { font-size: 8pt; }
    </style>
</head>

<body>
<div class="container">

<p style="text-align: right;"><strong>P3U-SOP03-RK04-RI.00</strong></p>
<p style="text-align: right;"><strong><?php echo date('d F Y'); ?></strong></p>

<h3><p style="text-align: center;"><strong>BERITA ACARA PENYERAHAN NASKAH UJIAN DAN LJU/BJU DARI PJTU KE PJLU</strong></p>
<p style="text-align: center;"><strong>DAN PENYERAHAN HASIL UJIAN DARI PJLU KE PJTU</strong></p></h3>

<table class="info-header" width="100%" border="0" style="margin-left: calc(0%); width: 100%;">
    <tbody>
        <tr>
            <td style="width: 17.1029%;"><strong><span style="font-size: 12px;">UPBJJ-UT</span></strong></td>
            <td style="width: 1.7003%;"><span style="font-size: 12px;">:</span></td>
            <td style="width: 24.004%;"><span style="font-size: 12px;"><strong>SERANG</strong></span></td>
            <td style="width: 28%;; width: 14.2857%;"><span style="font-size: 12px;"><br></span></td>
            <td style="width: 17.1029%;"><span style="font-size: 12px;"><strong>MASA UJIAN</strong></span></td>
            <td style="width: 1.7003%;"><span style="font-size: 12px;">:</span></td>
            <td style="width: 24.004%;"><span style="font-size: 12px;"><?php echo $masa_reg ?></td>
        </tr>
        <tr>
            <td style="width: 17.1029%;"><span style="font-size: 12px;"><strong>TEMPAT UJIAN</strong></span></td>
            <td style="width: 1.7003%;"><span style="font-size: 12px;">:</span></td>
            <td style="width: 24.004%;"><span style="font-size: 12px;"><?php echo htmlspecialchars($pos_ujian); ?></td>
            <td style="null; width: 14.2857%;"><span style="font-size: 12px;"><br></span></td>
            <td style="width: 17.1029%;"><span style="font-size: 12px;"><strong>HARI</strong></span></td>
            <td style="width: 1.7003%;"><span style="font-size: 12px;">:</span></td>
            <td style="width: 24.004%;"><span style="font-size: 12px;"><?php echo htmlspecialchars($hari2); ?></td>
        </tr>
        <tr>
            <td style="width: 17.1029%;"><span style="font-size: 12px;"><strong>LOKASI UJIAN</strong></span></td>
            <td style="width: 1.7003%;"><span style="font-size: 12px;">:</span></td>
            <td style="width: 24.004%;"><span style="font-size: 12px;"><?php echo htmlspecialchars($lokasi); ?></span><br></td>
            <td style="null; width: 14.2857%;"><span style="font-size: 12px;"><br></span></td>
            <td style="width: 17.1029%;"><span style="font-size: 12px;"><strong>TANGGAL</strong></span></td>
            <td style="width: 1.7003%;"><span style="font-size: 12px;">:</span></td>
            <td style="width: 24.004%;"><span style="font-size: 12px;"><?php echo date('d F Y', strtotime($tgl_ujian)); ?></td>
        </tr>
    </tbody>
</table>

<table class="table-data" border="1" width="100%">
<thead>
<tr>
    <th rowspan="2">NO.</th>
    <th rowspan="2">JENIS BAHAN UJIAN</th>
    <th colspan="15" class="text-center">JUMLAH NASKAH UJIAN DAN LJU/BJU YANG DISERAHKAN</th>
    <th rowspan="2">JUMLAH</th>
    <th rowspan="2">TTD PJTU</th>
    <th rowspan="2">TTD PJLU</th>
</tr>

<tr>
    <th colspan="3">JAM 1</th>
    <th colspan="3">JAM 2</th>
    <th colspan="3">JAM 3</th>
    <th colspan="3">JAM 4</th>
    <th colspan="3">JAM 5</th>
</tr>
</thead>

<tbody>
<tr>
    <td class="text-center">1</td>
    <td class="text-left">Naskah Ujian</td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[1]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[2]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[3]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[4]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[5]; ?></td>
    <td class="text-right"><strong><?php echo $grand_total_naskah; ?></strong></td>
    <td width="5%" class="text-center"></td>
    <td width="5%" class="text-center"></td>
</tr>

<tr>
    <td class="text-center">2</td>
    <td class="text-left">LJU</td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[1]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[2]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[3]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[4]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_naskah_jam[5]; ?></td>
    <td class="text-right"><strong><?php echo $grand_total_naskah; ?></strong></td>
    <td class="text-center"></td>
    <td class="text-center"></td>
</tr>

<tr>
    <td class="text-center">3</td>
    <td class="text-left">BJU</td>
    <td colspan="3" class="text-right"><?php echo $total_bju_jam[1]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_bju_jam[2]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_bju_jam[3]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_bju_jam[4]; ?></td>
    <td colspan="3" class="text-right"><?php echo $total_bju_jam[5]; ?></td>
    <td class="text-right"><strong><?php echo $grand_total_bju; ?></strong></td>
    <td class="text-center"></td>
    <td class="text-center"></td>
</tr>

<tr><td colspan="21" height="30"></td></tr>
</tbody>
</table>

<table class="table-data" border="1" width="100%">
<thead>
<tr>
    <th rowspan="3">NO</th>
    <th rowspan="3">NO RUANG</th>
    <th colspan="18" class="text-center">JUMLAH HASIL UJIAN DAN NASKAH UJIAN</th>
    <th rowspan="3">JML AMPLOP</th>
</tr>

<tr>
    <th colspan="3">JAM 1</th>
    <th colspan="3">JAM 2</th>
    <th colspan="3">JAM 3</th>
    <th colspan="3">JAM 4</th>
    <th colspan="3">JAM 5</th>
    <th colspan="3">TOTAL</th>
</tr>

<tr>
    <th>LJU</th><th>BJU</th><th>Naskah</th>
    <th>LJU</th><th>BJU</th><th>Naskah</th>
    <th>LJU</th><th>BJU</th><th>Naskah</th>
    <th>LJU</th><th>BJU</th><th>Naskah</th>
    <th>LJU</th><th>BJU</th><th>Naskah</th>
    <th>LJU</th><th>BJU</th><th>Naskah</th>
</tr>
</thead>
<tbody>
<?php if (!empty($rekapDataHari['ruangs'])): ?>
    <?php $no = 1; foreach ($rekapDataHari['ruangs'] as $r): ?>
    <tr>
        <td class="text-center"><?php echo $no++; ?></td>
        <td class="text-center">R. <?php echo htmlspecialchars($r['ruang_ke']); ?></td>

        <?php for ($j = 1; $j <= 5; $j++): ?>
        <td class="text-right"><?php echo htmlspecialchars($r['jam_data'][$j]['lju'] ?? 0); ?></td>
        <td class="text-right"><?php echo htmlspecialchars($r['jam_data'][$j]['bju'] ?? 0); ?></td>
        <td class="text-right"><?php echo htmlspecialchars($r['jam_data'][$j]['naskah'] ?? 0); ?></td>
        <?php endfor; ?>

        <td class="text-right"><?php echo htmlspecialchars($r['total']['lju'] ?? 0); ?></td>
        <td class="text-right"><?php echo htmlspecialchars($r['total']['bju'] ?? 0); ?></td>
        <td class="text-right"><?php echo htmlspecialchars($r['total']['naskah'] ?? 0); ?></td>

        <td class="text-center"><?php echo htmlspecialchars($r['jumlah_amplop'] ?? 0); ?></td>
    </tr>
    <?php endforeach; ?>
    
    <tr style="font-weight: bold; background: #f2f2f2;">
        <td colspan="2" class="text-center">TOTAL</td>

        <td class="text-right"><?php echo $total_lju_jam[1]; ?></td>
        <td class="text-right"><?php echo $total_bju_jam[1]; ?></td>
        <td class="text-right"><?php echo $total_naskah_jam[1]; ?></td>

        <td class="text-right"><?php echo $total_lju_jam[2]; ?></td>
        <td class="text-right"><?php echo $total_bju_jam[2]; ?></td>
        <td class="text-right"><?php echo $total_naskah_jam[2]; ?></td>

        <td class="text-right"><?php echo $total_lju_jam[3]; ?></td>
        <td class="text-right"><?php echo $total_bju_jam[3]; ?></td>
        <td class="text-right"><?php echo $total_naskah_jam[3]; ?></td>

        <td class="text-right"><?php echo $total_lju_jam[4]; ?></td>
        <td class="text-right"><?php echo $total_bju_jam[4]; ?></td>
        <td class="text-right"><?php echo $total_naskah_jam[4]; ?></td>

        <td class="text-right"><?php echo $total_lju_jam[5]; ?></td>
        <td class="text-right"><?php echo $total_bju_jam[5]; ?></td>
        <td class="text-right"><?php echo $total_naskah_jam[5]; ?></td>

        <td class="text-right"><?php echo $grand_total_lju; ?></td>
        <td class="text-right"><?php echo $grand_total_bju; ?></td>
        <td class="text-right"><?php echo $grand_total_naskah; ?></td>

        <td class="text-center"><?php echo $grand_total_amplop; ?></td>
    </tr>
<?php else: ?>
    <tr>
        <td colspan="22" class="text-center">Tidak ada data rekap untuk Hari ke-<?php echo $hari; ?> di lokasi ini.</td>
    </tr>
<?php endif; ?>
</tbody>
</table>
<br><br>
<table class="table-data" width="100%" border="0">
<tr>
    <td class="text-center" width="50%"><strong>Penanggung Jawab Tempat Ujian</strong></td>
    <td class="text-center" width="50%"><strong>Penanggung Jawab Lokasi Ujian</strong></td>
</tr>

<tr><td colspan="2" height="60"></td></tr>

<tr>
    <td class="text-center">(___________________________)</td>
    <td class="text-center">(___________________________)</td>
</tr>
</table>

</div>
</body>
</html>