<?php
session_start();
include "../config.php";

// =====================================
// CEK LOGIN - Support untuk manajemen dan pengawas
// =====================================
$id = '';
$userType = '';

if (isset($_SESSION['manajemen_unlock']) && $_SESSION['manajemen_unlock'] === true) {
    // User manajemen - untuk saat ini tidak ada tanda tangan khusus, bisa ditampilkan pesan
    echo "<div style='padding: 20px; text-align: center; color: #666;'>";
    echo "<h3>Tanda Tangan</h3>";
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

if ($id == "") { 
    echo "Session kosong"; 
    exit; 
}

if (isset($_POST['save']) && $_POST['save'] == "1") {
    $data = $_POST['image'];

    $data = str_replace('data:image/png;base64,', '', $data);
    $data = str_replace(' ', '+', $data);

    $imgData = base64_decode($data);

    $filename = $id . '_' . time() . '.png';
    $filepath = 'signature/' . $filename;

    if (file_put_contents($filepath, $imgData)) {

        $filenameEscaped = mysqli_real_escape_string($conn2, $filename);
        $q = mysqli_query($conn2, "
            UPDATE pengawas_ruang 
            SET tanda_tangan = '$filenameEscaped'
            WHERE id_pengawas = '$id'
        ");

        echo $q ? "ok" : "SQL ERROR: " . mysqli_error($conn2);
    } else {
        echo "Gagal menyimpan file!";
    }
    exit;
}
?>

<div id="signature-container" class="card">
    <h3>Tanda Tangan Pengawas</h3>
    <p>Silakan tanda tangan di area berikut:</p>

    <canvas id="signature-pad"></canvas>

    <div style="margin-top:15px;">
        <button class="btn-ttd btn-reset" onclick="resetPad()">Reset</button>
        <button class="btn-ttd btn-save" onclick="saveSignature()">Simpan</button>
    </div>
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
    height: 200px;
    border-radius: 10px;
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

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script>
// INIT SIGNATURE PAD
let signaturePad;

(function() {
    const canvas = document.getElementById("signature-pad");

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width  = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }
    resizeCanvas();

    signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255,255,255)'
    });

    window.addEventListener("resize", resizeCanvas);
})();

// RESET
function resetPad() {
    signaturePad.clear();
}
window.resetPad = resetPad;

// SAVE
function saveSignature() {
    if (signaturePad.isEmpty()) {
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
        } else {
            alert(res);
        }
    });
}
window.saveSignature = saveSignature;
</script>

