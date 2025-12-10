<?php
session_start();
include "../config.php";

// =============================
// SESSION CHECK
// =============================
if (!isset($_SESSION['pjtu_unlock'])) {
    echo "<div style='padding:20px; text-align:center; color:#c33;'>Akses PJLU tidak ditemukan.</div>";
    exit;
}

$current_lokasi = $_SESSION['lokasi'] ?? '';
$kode_tpu = $_SESSION['kode_tpu'] ?? '';
$namaUser = $_SESSION['nama_user'] ?? '';

// =============================
// AMBIL ISO DARI GET PARAMETER
// =============================
$items_param = $_GET['items'] ?? '';
$items_kode = array_filter(array_map('trim', explode(',', $items_param)));

if (empty($items_kode)) {
    echo "<div style='padding:20px; text-align:center; color:#c33;'>Tidak ada ISO yang dipilih.</div>";
    exit;
}

// =============================
// AMBIL DATA ISO DARI DATABASE
// =============================
$isoData = [];
if ($conn2 && !empty($items_kode)) {
    $in_placeholders = implode(',', array_fill(0, count($items_kode), '?'));
    $types = str_repeat('s', count($items_kode));
    
    $stmt = $conn2->prepare("SELECT id, file_iso FROM tabel_iso WHERE id IN ($in_placeholders) ORDER BY id ASC");
    $stmt->bind_param($types, ...$items_kode);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $isoData[] = $row;
    }
    $stmt->close();
}

// =============================
// AMBIL TANDA TANGAN PJLU
// =============================
$pjlu_signature = '';
$pjlu_nama = $namaUser;
if ($conn2) {
    $stmt = $conn2->prepare("SELECT nama, tanda_tangan FROM pj_setting WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $pjlu_nama = $row['nama'] ?? $pjlu_nama;
        $pjlu_signature = 'signature_pj/'.$row['tanda_tangan'] ?? '';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak ISO</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; margin:0; padding:0; font-size:12pt; }
        .print-area { width:210mm; min-height:297mm; margin:0 auto; padding:20mm; box-sizing:border-box; }
        .page-break { page-break-after: always; }
        h2 { text-align:center; margin-bottom:20px; }
        table { width:100%; border-collapse: collapse; margin-top:10px; }
        th, td { border:1px solid #000; padding:6px; text-align:left; }
        th { background-color:#f0f0f0; }
        .signature { margin-top:50px; text-align:right; }
        .signature img { max-height:80px; display:block; margin-bottom:4px; }
    </style>
</head>
<body>

<div class="print-area">
    <h2>Daftar ISO Cetak</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode ISO</th>
                <th>Nama ISO</th>
                <th>Lokasi Ujian</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($isoData as $idx => $iso): ?>
                <tr>
                    <td><?php echo $idx+1; ?></td>
                    <td><?php echo htmlspecialchars($iso['id']); ?></td>
                    <td><?php echo htmlspecialchars($iso['file_iso']); ?></td>
                    <td><?php echo htmlspecialchars($current_lokasi); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signature">
        <?php if(!empty($pjlu_signature)): ?>
            <img src="<?php echo htmlspecialchars($pjlu_signature); ?>" alt="Tanda Tangan PJLU">
        <?php endif; ?>
        <div><?php echo htmlspecialchars($pjlu_nama); ?></div>
        <div>PJLU</div>
    </div>
</div>

</body>
</html>
    