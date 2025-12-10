<?php
session_start();
include "../config.php";

// =========================================================
// 1. VALIDASI AKSES
// =========================================================
if (!isset($_SESSION['pjtu_unlock'])) {
    echo "Akses ditolak";
    exit;
}

if (!isset($conn2) || $conn2->connect_error) {
    echo "Koneksi database error: " . $conn2->connect_error;
    exit;
}

// =========================================================
// 2. MODE AJAX: jika request POST AJAX → simpan catatan
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {

    header("Content-Type: application/json");

    $lokasi_tpu   = $_SESSION['lokasi'] ?? 'Lokasi Tidak Diketahui';
    $id_pembuat   = $_SESSION['username'] ?? 'PJTU001';
    $nama_pembuat = $_SESSION['nama_user'] ?? 'PJTU/PJLU';
    $masa         = "20252";

    $hari    = intval($_POST['hari'] ?? 0);
    $ruang   = intval($_POST['ruang'] ?? 0);
    $jam     = intval($_POST['jam'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? "");

    if ($hari == 0 || $ruang == 0 || $jam == 0 || $catatan == "") {
        echo json_encode([
            "status" => "warning",
            "message" => "Semua field wajib diisi!"
        ]);
        exit;
    }

    $catatan_final = $catatan;

    $stmt = $conn2->prepare("
        INSERT INTO catatan_temuan
        (masa, hari, lokasi, ruang, jam_ujian, id_pembuat_temuan, nama_pembuat, catatan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => $conn2->error]);
        exit;
    }

    $stmt->bind_param(
        "sisiisss",
        $masa, $hari, $lokasi_tpu, $ruang, $jam,
        $id_pembuat, $nama_pembuat, $catatan_final
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Catatan berhasil disimpan!"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }

    exit; // pastikan tidak render HTML
}


// =========================================================
// 3. MODE NORMAL (TAMPILKAN HTML)
// =========================================================
$lokasi_tpu   = $_SESSION['lokasi'];
$id_pembuat   = $_SESSION['username'];
$nama_pembuat = $_SESSION['nama_user'];
$masa         = '20252';

function getDaftarRuang($conn2, $lokasi, $hari, $masa) {
    $list = [];
    $stmt = $conn2->prepare("
        SELECT DISTINCT r.ruang_ke
        FROM rekap_ujian r
        LEFT JOIN e_lokasi_uas e 
            ON r.kode_tpu = e.kode_tpu 
            AND e.masa = ?
            AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED)
                                                AND CAST(e.ruang_akhir AS UNSIGNED)
            AND CAST(r.hari_ke AS CHAR) = e.hari
        WHERE r.masa = ? AND e.lokasi = ? AND r.hari_ke = ?
        ORDER BY CAST(r.ruang_ke AS UNSIGNED)
    ");
    $stmt->bind_param("ssss", $masa, $masa, $lokasi, $hari);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($d = $r->fetch_assoc()) $list[] = $d['ruang_ke'];
    return $list;
}

$ruang_h1 = getDaftarRuang($conn2, $lokasi_tpu, "1", $masa);
$ruang_h2 = getDaftarRuang($conn2, $lokasi_tpu, "2", $masa);

?>
<style>
.form-group{margin-bottom:15px;text-align:left;}
.form-group label{font-weight:bold;margin-bottom:5px;display:block;}
.form-control,.btn-submit{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;}
textarea.form-control{resize:vertical;min-height:100px;}
.btn-submit{background:#6a4ff7;color:white;border:none;cursor:pointer;}
.btn-submit:hover{background:#5a3fe5;}
.alert-success,.alert-warning,.alert-danger{
 padding:10px;border-radius:8px;margin-top:10px;text-align:center;
}
.alert-success{background:#d4edda;color:#155724;}
.alert-warning{background:#fff3cd;color:#856404;}
.alert-danger{background:#f8d7da;color:#721c24;}
.card{background:#fff;padding:20px;border-radius:15px;
 box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:15px;}
</style>

<div class="card">
    <h3>📝 Catatan Temuan</h3>
    <p>
        Lokasi: <b><?= htmlspecialchars($lokasi_tpu) ?></b><br>
        Pembuat: <b><?= htmlspecialchars($nama_pembuat) ?></b> (<?= htmlspecialchars($id_pembuat) ?>)
    </p>

    <div id="notif"></div>

    <form id="formTemuan">

        <div class="form-group">
            <label>Pilih Hari:</label>
            <select name="hari" id="hari" class="form-control" onchange="updateRuang()" required>
                <option value="">-- Pilih Hari --</option>
                <option value="1">Hari 1</option>
                <option value="2">Hari 2</option>
            </select>
        </div>

        <div class="form-group">
            <label>Pilih Ruang:</label>
            <select name="ruang" id="ruang" class="form-control" required>
                <option value="">-- Pilih Hari terlebih dahulu --</option>
            </select>
        </div>

        <div class="form-group">
            <label>Pilih Jam:</label>
            <select name="jam" class="form-control" required>
                <option value="">-- Pilih Jam --</option>
                <option value="1">Jam 1</option>
                <option value="2">Jam 2</option>
                <option value="3">Jam 3</option>
                <option value="4">Jam 4</option>
                <option value="5">Jam 5</option>
            </select>
        </div>

        <div class="form-group">
            <label>Catatan Temuan:</label>
            <textarea name="catatan" class="form-control" required></textarea>
        </div>

        <button type="submit" class="btn-submit">Simpan Catatan</button>

    </form>
</div>

<script>
const ruangMap = {
    "1": <?= json_encode($ruang_h1) ?>,
    "2": <?= json_encode($ruang_h2) ?>
};

function updateRuang(){
    const hari = document.getElementById("hari").value;
    const ru = document.getElementById("ruang");
    ru.innerHTML = '<option value="">-- Pilih Ruang --</option>';

    if(ruangMap[hari]){
        ruangMap[hari].forEach(r=>{
            let opt=document.createElement("option");
            opt.value=r;
            opt.textContent="Ruang "+r;
            ru.appendChild(opt);
        });
    }
}

document.getElementById("formTemuan").addEventListener("submit", function(e){
    e.preventDefault();

    let fd = new FormData(this);
    fd.append("ajax","1");

    fetch("catatan_temuan.php", {
        method:"POST",
        body:fd
    })
    .then(res=>res.json())
    .then(out=>{
        let n=document.getElementById("notif");

        if(out.status==="success"){
            n.innerHTML=`<div class="alert-success">${out.message}</div>`;
            document.getElementById("formTemuan").reset();
        }
        else if(out.status==="warning"){
            n.innerHTML=`<div class="alert-warning">${out.message}</div>`;
        }
        else{
            n.innerHTML=`<div class="alert-danger">${out.message}</div>`;
        }
    });
});
</script>
