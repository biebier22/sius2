<?php
session_start();
include "../config.php";

// =========================================================
// DEFINISI BASE URL SECARA DINAMIS
// =========================================================

// Mendapatkan protokol (http atau https)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

// Mendapatkan host (e.g., localhost, example.com, 192.168.1.1)
$host = $_SERVER['HTTP_HOST'];

// Mendapatkan path directory saat ini (misal: /sius2/pjlu/)
// Menggunakan dirname($_SERVER['PHP_SELF']) untuk mendapatkan path folder
// dan pastikan diakhiri dengan slash (/)
$path_parts = pathinfo($_SERVER['PHP_SELF']);
$current_dir = dirname($_SERVER['PHP_SELF']);

// Tentukan path ke folder PJLU (Asumsi file ini ada di folder /sius2/pjlu/)
// Jika file ini adalah 'cetak_iso.php', maka base URLnya adalah path folder saat ini.
$base_url_path = $current_dir;

// Jika direktori saat ini adalah root (misal '/'), tambahkan slash jika belum ada
if (substr($base_url_path, -1) !== '/') {
    $base_url_path .= '/';
}

// Gabungkan semua komponen
$base_url = "{$protocol}://{$host}{$base_url_path}";


// =========================================================
// VALIDASI AKSES
// =========================================================
if (!isset($_SESSION['pjtu_unlock'])) {
    echo "<div style='padding:20px; text-align:center; color:#c33;'>Akses ditolak</div>";
    exit;
}

// ... (lanjutkan dengan kode lainnya)

// =========================================================
// FUNGSI CEK PDF (dipanggil dari AJAX)
// =========================================================
if (isset($_GET['check_pdf'])) {
    $id = $_GET['id'] ?? '';

    // Path absolut VALID sesuai struktur folder kamu
    $pdf_file = __DIR__ . "/iso/" . $id . ".pdf";

    if (file_exists($pdf_file)) {
        echo "PDF";
    } else {
        echo "PHP";
    }
    var_dump($pdf_file, file_exists($pdf_file));
    exit;
}




// =========================================================
// DATA DARI SESSION
// =========================================================
$lokasi_tpu   = $_SESSION['lokasi'] ?? 'Lokasi Tidak Diketahui';
$id_pembuat   = $_SESSION['username'] ?? 'PJTU001';
$nama_pembuat = $_SESSION['nama_user'] ?? 'PJTU/PJLU';
$masa         = "20252";

// =========================================================
// AMBIL LIST FILE ISO DARI DATABASE
// =========================================================
$items = [];
$q = $conn2->query("SELECT id, file_iso FROM tabel_iso ORDER BY id ASC");
if ($q && $q->num_rows > 0) {
    while ($row = $q->fetch_assoc()) {
        $items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak ISO</title>

<style>
.table-responsive { overflow-x: auto; }
.iso-table { width: 100%; border-collapse: collapse; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
.iso-table th, .iso-table td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
.iso-table th { background-color: #f3f4f6; font-weight: 600; text-align: center; }
.iso-table tr:nth-child(even) { background-color: #fafafa; }
.iso-table tr:hover { background-color: #f1f5f9; }
.btn-print { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; }
.btn-print:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
@media (max-width: 480px) {
    .iso-table th, .iso-table td { padding: 8px 6px; font-size: 14px; }
    .btn-print { padding: 5px 10px; font-size: 13px; }
}
</style>

</head>
<body>

<h1 style="font-size:22px; color:#1f2937;">📠 Cetak ISO</h1>
<p style="color:#4b5563;">Klik tombol untuk mencetak ISO sesuai hari. Sistem otomatis memilih PDF jika tersedia.</p>

<div class="table-responsive">
<table class="iso-table">
    <thead>
        <tr>
            <th>No</th>
            <th>File ISO</th>
            <th>H1</th>
            <th>H2</th>
        </tr>
    </thead>
    <tbody>

<?php if (!empty($items)): ?>
    <?php foreach ($items as $idx => $item): ?>

    <?php
        $id = $item['id'];
        
        // CEK FILE DI FOLDER iso/
        $file_pdf = __DIR__ . "/iso/" . $id . ".pdf";
        $file_php = __DIR__ . "/iso/" . $id . ".php";

        $has_pdf = file_exists($file_pdf);
        $has_php = file_exists($file_php);

        // Warna tombol
        $btnColor = $has_pdf ? "background:#dc2626;" : "background:#2563eb;";
        $btnColor .= "color:white;";
    ?>

    <tr>
        <td><?= $idx + 1 ?></td>
        <td><?= htmlspecialchars($item['file_iso']) ?></td>

        <!-- CETAK H1 -->
        <td>
            <button class="btn-print"
                style="<?= $btnColor ?>"
                onclick="cetakISO('<?= $id ?>', 1, '<?= $lokasi_tpu ?>', <?= $has_pdf ? "'PDF'" : "'PHP'" ?>)">
                Cetak H1
            </button>
        </td>

        <!-- CETAK H2 -->
        <td>
            <button class="btn-print"
                style="<?= $btnColor ?>"
                onclick="cetakISO('<?= $id ?>', 2, '<?= $lokasi_tpu ?>', <?= $has_pdf ? "'PDF'" : "'PHP'" ?>)">
                Cetak H2
            </button>
        </td>
    </tr>

    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="4" style="text-align:center;">Tidak ada file ISO</td>
    </tr>
<?php endif; ?>

</tbody>

</table>
</div>

<script>
function cetakISO(id, hari, lokasi, fileType) {

    const newWindow = window.open("about:blank", "_blank"); 

    newWindow.document.write('<html><body style="font-family: sans-serif; text-align: center; padding-top: 50px;"><h2>⏳ Memuat file ISO...</h2><p>Mohon tunggu sebentar.</p></body></html>');

    let targetUrl;

    if (fileType === "PDF") {
        targetUrl = `<?= $base_url ?>iso/${id}.pdf`;
    } else {
        targetUrl = `<?= $base_url ?>iso/${id}.php?hari=${hari}&lokasi=${encodeURIComponent(lokasi)}`;
    }

    newWindow.location.href = targetUrl;
}


</script>

</body>
</html>