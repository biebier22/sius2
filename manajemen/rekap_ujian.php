<?php
session_start();
include "../config.php";

// =====================================
// CEK LOGIN - Support untuk manajemen dan pengawas
// =====================================
$id = '';
$userType = '';

if (isset($_SESSION['manajemen_unlock']) && $_SESSION['manajemen_unlock'] === true) {
    // User manajemen - untuk saat ini tidak ada rekap khusus, bisa ditampilkan pesan
    echo "<div style='padding: 20px; text-align: center; color: #666;'>";
    echo "<h3>Rekap Ujian</h3>";
    echo "<p>Fitur ini sedang dalam pengembangan untuk Direktur & Manajer.</p>";
    echo "</div>";
    exit;
} elseif (isset($_SESSION['pengawas_unlock']) && $_SESSION['pengawas_unlock'] === true) {
    $id = $_SESSION['id_pengawas'] ?? '';
    $userType = 'pengawas';
} else {
    echo "<h3>Anda harus login terlebih dahulu!</h3>";
    exit;
}

// ============================
//  PROSES AJAX SIMPAN
// ============================
if (($_POST['ajax'] ?? '') === "save") {

    $id_pengawas  = $_POST['id_pengawas'];
    $jam          = (int) $_POST['jam'];
    $jumlah_hadir = (int) $_POST['jumlah_hadir'];

    // Cek apakah data sudah ada
    $cek = mysqli_query($conn2, "
        SELECT id FROM laporan_hadir
        WHERE id_pengawas='$id_pengawas' AND jam='$jam'
        LIMIT 1
    ");

    if (mysqli_num_rows($cek) > 0) {

        mysqli_query($conn2, "
            UPDATE laporan_hadir SET 
                jumlah_hadir='$jumlah_hadir',
                created_at=NOW()
            WHERE id_pengawas='$id_pengawas' AND jam='$jam'
        ");

    } else {

        mysqli_query($conn2, "
            INSERT INTO laporan_hadir (id_pengawas, jam, jumlah_hadir)
            VALUES ('$id_pengawas', '$jam', '$jumlah_hadir')
        ");

    }

    echo "ok";
    exit;
}

// ============================
//  AMBIL DATA NASKAH
// ============================
$q = mysqli_query($conn2,"
    SELECT jam1, jam2, jam3, jam4, jam5
    FROM pengawas_ruang
    WHERE id_pengawas='$id'
");
$data = mysqli_fetch_assoc($q);

// ============================
//  AMBIL DATA HADIR
// ============================
$hadir_list = [];
$get = mysqli_query($conn2,"
    SELECT jam, jumlah_hadir 
    FROM laporan_hadir 
    WHERE id_pengawas='$id'
");

while($r = mysqli_fetch_assoc($get)){
    $hadir_list[$r['jam']] = $r['jumlah_hadir'];
}

// ============================
//  AMBIL DATA LOKASI
// ============================
$q_lokasi = mysqli_query($conn2,"
    SELECT lokasi, ruang
    FROM pengawas_ruang
    WHERE id_pengawas='$id'
    LIMIT 1
");

$data_lokasi = mysqli_fetch_assoc($q_lokasi);
$lokasi = $data_lokasi['lokasi'] ?? 'Lokasi Tidak Ditemukan';
$ruang_ke = $data_lokasi['ruang'] ?? 'N/A';
// ============================
?>

<!DOCTYPE html>
<html>
<head>
<title>Rekap Ujian</title>

<style>
/* ==================================== */
/* 1. UMUM & LAYOUT */
/* ==================================== */
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    padding:15px; 
    background:#f0f3f6;
    color:#333; 
    margin: 0;
}
h2 { 
    margin: 0 0 5px 0; 
    text-align: center; 
    color: #4a4a4a; 
    font-size: 1.5em; 
}
p { 
    margin: 0 0 20px 0; 
    text-align: center; 
    color: #777; 
    font-size: 1em; 
}

/* ==================================== */
/* 2. TABLE STYLE */
/* ==================================== */
table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 6px 20px rgba(0,0,0,0.15);
    margin-top: 20px;
}
th,td{
    padding:14px 10px;
    border-bottom:1px solid #e0e0e0;
    text-align: center;
}
th{
    background:#5c40bc;
    color:#fff;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9em;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}
tr:hover {
    background-color: #f0f0ff;
}
td:last-child {
    font-size: 0.9em;
}

/* ==================================== */
/* 3. INPUT & BUTTON */
/* ==================================== */
input{
    padding:8px;
    border:1px solid #ddd;
    border-radius:8px;
    width:70px; 
    text-align: center;
    transition: border 0.3s;
}
input:focus {
    border-color: #6a4ff7;
    outline: none;
}
.btn-ok{
    background:#4caf50;
    color:#fff;
    border:none;
    padding:8px 14px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    margin-left: 5px;
    transition: background 0.2s, transform 0.1s;
    white-space: nowrap;
}
.btn-ok:hover {
    background:#43a047;
}
.btn-ok:active {
    transform: scale(0.98);
}

/* ==================================== */
/* 4. STATUS */
/* ==================================== */
.status-ok{
    color:#28a745; 
    font-weight:bold;
}
.status-belum{
    color:#ff8800;
    font-weight: 500;
    font-style: italic;
}

#loader {
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(255,255,255,0.8); 
    backdrop-filter: blur(2px);
    z-index:9999;
    justify-content:center;
    align-items:center;
    font-size:22px;
    font-weight:bold;
    color:#6a4ff7;
}
</style>
</head>

<body>

<div id="loader">Loading...</div>

<h2><?php echo htmlspecialchars($lokasi); ?></h2>
<p>Ruang Ujian: <strong><?php echo htmlspecialchars($ruang_ke); ?></strong></p>

<table>
    <tr>
        <th>Jam Ujian</th>
        <th>Jumlah Naskah</th>
        <th>Input Hadir</th>
        <th>Status</th>
    </tr>

<?php
for($i=1;$i<=5;$i++):
    $naskah = $data["jam$i"] ?? 0;
    $hadir  = $hadir_list[$i] ?? "";
?>
<tr>
    <td>Jam <?php echo $i; ?></td>

    <td><?php echo $naskah; ?></td>

    <td>
        <input 
            id="hadir<?php echo $i; ?>" 
            type="number" 
            value="<?php echo $hadir; ?>"
            min="0"
            max="<?php echo $naskah; ?>"
            placeholder="0"
        >

        <button
            class="btn-ok"
            onclick="simpanHadir(
                '<?php echo $id; ?>',
                <?php echo $i; ?>,
                document.getElementById('hadir<?php echo $i; ?>'),
                this,
                document.getElementById('status<?php echo $i; ?>')
            )"
        >SIMPAN</button>
    </td>

    <td id="status<?php echo $i; ?>" 
        class="<?php echo ($hadir > 0 ? 'status-ok' : 'status-belum'); ?>">
        <?php echo ($hadir > 0 ? '✔ Sudah diisi' : 'Belum Input'); ?>
    </td>

</tr>
<?php endfor; ?>

</table>


<script>
// ===============================
//  AJAX SIMPAN HADIR
// ===============================
function simpanHadir(id_pengawas, jam, input, btn, statusCell){

    const jumlahHadir = parseInt(input.value);
    if (isNaN(jumlahHadir) || jumlahHadir < 0) {
        alert("Jumlah hadir harus berupa angka positif.");
        return;
    }

    const fd = new FormData();
    fd.append("ajax", "save");
    fd.append("id_pengawas", id_pengawas);
    fd.append("jam", jam);
    fd.append("jumlah_hadir", jumlahHadir);

    document.getElementById('loader').style.display = 'flex';

    btn.disabled = true;
    btn.innerText = "...";

    fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
        method: "POST",
        body: fd
    })
    .then(r => r.text())
    .then(res => {
        if(res.trim() === "ok") {
            statusCell.innerHTML = "✔ Sudah diisi";
            statusCell.className = "status-ok";
        } else {
            alert("Gagal menyimpan: " + res);
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerText = "SIMPAN";
        document.getElementById('loader').style.display = 'none';
    });
}

window.simpanHadir = simpanHadir;
</script>

</body>
</html>

