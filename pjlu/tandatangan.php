<?php
session_start();
include "../config.php";

// === Proteksi Login ===
if (!isset($_SESSION['pjtu_unlock']) || $_SESSION['pjtu_unlock'] !== true) {
    echo "<div class='card'>HARUS LOGIN TERLEBIH DAHULU!</div>";
    exit;
}

$username = $_SESSION['username'];
$role     = $_SESSION['role']; // PJTU / PJLU

// === CEK APAKAH SUDAH PUNYA TANDA TANGAN ===
$qCheck = mysqli_query($conn2, "SELECT tanda_tangan FROM pj_setting WHERE username = '$username' LIMIT 1");
$data   = mysqli_fetch_assoc($qCheck);
$existingTTD = $data['tanda_tangan'] ?? "";

// === JIKA SUDAH PUNYA TTD → BLOK PROSES SIMPAN ===
if ($existingTTD != "") {
    // tidak boleh save jika sudah ada
    if (isset($_POST['save']) && $_POST['save'] == "1") {
        echo "ERROR: Anda sudah memiliki tanda tangan. Hubungi admin untuk mengubah.";
        exit;
    }
}

// === PROSES SIMPAN TTD JIKA BELUM ADA ===
if (isset($_POST['save']) && $_POST['save'] == "1" && $existingTTD == "") {

    if (!isset($_POST['image'])) {
        echo "Tidak ada gambar!";
        exit;
    }

    $data = $_POST['image'];
    $data = str_replace('data:image/png;base64,', '', $data);
    $data = str_replace(' ', '+', $data);

    $imgData = base64_decode($data);

    $filename = $username . "_" . $role . "_" . time() . ".png";
    $filepath = "signature_pj/" . $filename;

    if (!is_dir("signature_pj")) {
        mkdir("signature_pj", 0777, true);
    }

    if (file_put_contents($filepath, $imgData)) {
        $filenameEsc = mysqli_real_escape_string($conn2, $filename);

        $q = mysqli_query($conn2, "
            UPDATE pj_setting 
            SET tanda_tangan = '$filenameEsc'
            WHERE username = '$username'
        ");

        echo ($q ? "ok" : ("SQL ERROR: " . mysqli_error($conn2)));
    } else {
        echo "Gagal menyimpan file!";
    }

    exit;
}
?>

<!-- LOADING SIGNATURE PAD -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<div id="signature-container" class="card">
    <h3>Tanda Tangan <?= htmlspecialchars($role) ?></h3>

<?php if ($existingTTD != "") { ?>
    <!-- SUDAH PUNYA TANDA TANGAN -->
    <p style="color:#444; margin-bottom:10px;">
        Anda sudah memiliki tanda tangan tersimpan.
    </p>

    <img src="signature_pj/<?= $existingTTD ?>" 
         style="width:100%; max-width:350px; border:1px solid #ccc; border-radius:8px;">

    <div style="margin-top:15px; padding:10px; background:#fee; border-radius:8px; color:#c00;">
        Untuk mengubah tanda tangan silakan hubungi admin.
    </div>

<?php } else { ?>
    <!-- BELUM PUNYA TTD → TAMPILKAN CANVAS -->
    <p>Silakan tanda tangan di area berikut:</p>

    <canvas id="signature-pad"></canvas>

    <div style="margin-top:15px;">
        <button class="btn-ttd btn-reset" onclick="resetPad()">Reset</button>
        <button class="btn-ttd btn-save" onclick="saveSignature()">Simpan</button>
    </div>
<?php } ?>

</div>

<style>
#signature-container {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    text-align: center;
}
#signature-pad {
    border: 2px dashed #888;
    width: 100%;
    height: 230px;
    border-radius: 10px;
    touch-action: none;
}
.btn-ttd {
    padding: 12px 15px;
    border: none;
    border-radius: 10px;
    margin: 8px;
    cursor: pointer;
    font-size: 15px;
    width: 45%;
}
.btn-reset { background: #bbb; color: black; }
.btn-save  { background: #6a4ff7; color: white; }
</style>

<?php if ($existingTTD == "") { ?>
<script>
// === HANYA JALAN JIKA BELUM PUNYA TTD ===
let signaturePad;

setTimeout(() => {
    const canvas = document.getElementById("signature-pad");
    if (!canvas) return;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }
    resizeCanvas();

    signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255,255,255)'
    });

    window.addEventListener("resize", resizeCanvas);
}, 100);

function resetPad() {
    if (signaturePad) signaturePad.clear();
}

function saveSignature() {
    if (!signaturePad || signaturePad.isEmpty()) {
        alert("Tanda tangan masih kosong!");
        return;
    }

    let imgData = signaturePad.toDataURL("image/png");
    let form = new FormData();
    form.append("save", "1");
    form.append("image", imgData);

    fetch("tandatangan.php", {
        method: "POST",
        body: form
    })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === "ok") {
            alert("✔ Tanda tangan berhasil disimpan!");
            loadPage("tandatangan.php"); // reload halaman
        } else {
            alert(res);
        }
    });
}
</script>
<?php } ?>
