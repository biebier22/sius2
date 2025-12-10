<?php
session_start();
include "../config.php";

// =========================================================
// VALIDASI AKSES WASLING
// =========================================================
if (!isset($_SESSION['wasling_unlock']) || $_SESSION['wasling_unlock'] !== true) {
    echo "Akses ditolak";
    exit;
}

if (!isset($conn2) || $conn2->connect_error) {
    echo "Koneksi database error";
    exit;
}

// =========================================================
// AMBIL DATA WASLING & LOKASI
// =========================================================
$id_wasling   = $_SESSION['id_wasling'];
$nama_wasling = $_SESSION['nama_wasling'] ?? "Wasling";

$stmt = $conn2->prepare("SELECT lokasi_tpu FROM wasling WHERE id_wasling = ? LIMIT 1");
$stmt->bind_param("s", $id_wasling);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$lokasi_wasling = $res['lokasi_tpu'] ?? "Tidak Ditemukan";

// =========================================================
// DATA GLOBAL
// =========================================================
$masa = "20252";
$ruang_int = 999; // ruang manual

// =========================================================
// MODE AJAX: SAVE
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? "") === "1") {

    header("Content-Type: application/json; charset=utf-8");

    $hari   = intval($_POST['hari'] ?? 0);
    $ruang  = trim($_POST['ruang_manual'] ?? "");
    $jam    = intval($_POST['jam'] ?? 0);
    $catRaw = trim($_POST['catatan'] ?? "");

    if ($hari === 0 || $ruang === "" || $jam === 0 || $catRaw === "") {
        echo json_encode(["status" => "warning", "message" => "Semua field wajib diisi!"]);
        exit;
    }

    $catatan_final = $catRaw;

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
        $masa, $hari, $lokasi_wasling, $ruang, $jam,
        $id_wasling, $nama_wasling, $catatan_final
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Catatan berhasil disimpan!"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }

    exit; // pastikan tidak render HTML
}
?>

<!-----------------------------  CSS  ------------------------------>
<style>
.card{background:#fff;padding:16px;border-radius:14px;box-shadow:0 6px 20px rgba(0,0,0,0.08);margin-bottom:18px;}
.form-group{margin-bottom:12px;}
.form-control{width:100%;padding:10px;border-radius:10px;border:1px solid #d9d9d9;}
.btn-primary{background:#2979ff;color:#fff;padding:10px 12px;border-radius:10px;border:0;cursor:pointer;}
#notif div{padding:10px;border-radius:8px;margin-bottom:10px;}
.alert-success{background:#d4edda;color:#155724;}
.alert-warning{background:#fff3cd;color:#856404;}
.alert-error{background:#f8d7da;color:#721c24;}
</style>

<!-----------------------------  FORM INPUT ------------------------------>
<div class="card">
    <h3>📝 Catatan Temuan WASLING</h3>
    <p style="color:#666;font-size:14px;">
        Lokasi: <b><?= $lokasi_wasling ?></b><br>
        Penginput: <b><?= $nama_wasling ?></b>
    </p>

    <div id="notif"></div>

    <form id="formWasling">

        <div class="form-group">
            <label>Pilih Hari</label>
            <select name="hari" class="form-control" required>
                <option value="">-- Pilih Hari --</option>
                <option value="1">Hari 1</option>
                <option value="2">Hari 2</option>
            </select>
        </div>

        <div class="form-group">
            <label>Ruang / Lokasi Manual</label>
            <input type="text" name="ruang_manual" class="form-control" required placeholder="Contoh: Aula, Kayana, Depan Posko">
        </div>

        <div class="form-group">
            <label>Jam Ujian</label>
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
            <label>Catatan Temuan</label>
            <textarea name="catatan" class="form-control" required></textarea>
        </div>

        <button class="btn-primary">Simpan Temuan</button>
    </form>
</div>

<!-----------------------------  JAVASCRIPT ------------------------------>
<script>
function notif(msg, type){
    let box = document.getElementById("notif");
    box.innerHTML = `<div class="alert-${type}">${msg}</div>`;
    setTimeout(()=> box.innerHTML="", 4000);
}


document.getElementById("formWasling").addEventListener("submit", function(e){
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
            document.getElementById("formWasling").reset();
        }
        else if(out.status==="warning"){
            n.innerHTML=`<div class="alert-warning">${out.message}</div>`;
        }
        else{
            n.innerHTML=`<div class="alert-error">${out.message}</div>`;
        }
    });
});

</script>
