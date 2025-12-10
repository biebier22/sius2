<?php
session_start();
include "../config.php";

$id = $_SESSION['id_wasling'] ?? '';
if ($id == "") { echo "Session kosong"; exit; }

// ================== SIMPAN TTD ==================
if (isset($_POST['save']) && $_POST['save'] == "1") {

    // Cek kembali apakah user SUDAH punya tanda_tangan
    $cek = mysqli_query($conn2, "SELECT tanda_tangan FROM wasling WHERE id_wasling='$id' LIMIT 1");
    $cek_data = mysqli_fetch_assoc($cek);

    if (!empty($cek_data['tanda_tangan'])) {
        echo "Tanda tangan sudah pernah disimpan! Tidak bisa diubah lagi.";
        exit;
    }

    $data = $_POST['image'];

    $data = str_replace('data:image/png;base64,', '', $data);
    $data = str_replace(' ', '+', $data);

    $imgData = base64_decode($data);

    $filename = $id . '_' . time() . '.png';
    $filepath = 'signature/' . $filename;

    if (file_put_contents($filepath, $imgData)) {

        $filenameEscaped = mysqli_real_escape_string($conn2, $filename);
        $q = mysqli_query($conn2, "
            UPDATE wasling 
            SET tanda_tangan = '$filenameEscaped'
            WHERE id_wasling = '$id'
        ");

        echo $q ? "ok" : "SQL ERROR: " . mysqli_error($conn2);
    } else {
        echo "Gagal menyimpan file!";
    }
    exit;
}

// ================== SIMPAN NO HP BARU ==================
if (isset($_POST['save_no_hp']) && $_POST['save_no_hp'] == "1") {
    $no_hp = trim($_POST['no_hp']);

    if (empty($no_hp)) {
        echo "Nomor HP tidak boleh kosong!";
        exit;
    }
    
    // Opsional: Validasi sisi server tambahan (misalnya hanya angka)
    if (!ctype_digit(str_replace([' ', '+', '-'], '', $no_hp))) {
        echo "Nomor HP hanya boleh berisi angka!";
        exit;
    }

    // Sanitize input
    $no_hp_escaped = mysqli_real_escape_string($conn2, $no_hp);
    
    // Cek apakah nomor HP sudah ada
    $cek = mysqli_query($conn2, "SELECT no_hp FROM wasling WHERE id_wasling='$id' LIMIT 1");
    $cek_data = mysqli_fetch_assoc($cek);

    if (!empty($cek_data['no_hp'])) {
        echo "Nomor HP sudah pernah disimpan! Tidak bisa diubah lagi.";
        exit;
    }
    
    $q = mysqli_query($conn2, "
        UPDATE wasling 
        SET no_hp = '$no_hp_escaped'
        WHERE id_wasling = '$id'
    ");

    echo $q ? "ok" : "SQL ERROR: " . mysqli_error($conn2);
    exit;
}


// ================== AMBIL DATA EXISTING ==================
$ttd = "";
$no_hp = ""; 
$r = mysqli_query($conn2, "SELECT tanda_tangan, no_hp FROM wasling WHERE id_wasling='$id' LIMIT 1");
if ($r && mysqli_num_rows($r)) {
    $d = mysqli_fetch_assoc($r);
    $ttd = $d['tanda_tangan'] ?? "";
    $no_hp = $d['no_hp'] ?? ""; 
}

$no_hp_exist = !empty($no_hp);

?>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<div id="signature-container" class="card">
    <h3>Tanda Tangan WASLING</h3>

<?php if ($ttd != ""): ?>

    <p style="color:green; font-weight:bold;">Tanda tangan sudah tersimpan.</p>

    <img src="signature/<?= htmlspecialchars($ttd) ?>" 
        style="width:100%; max-width:350px; border:2px solid #999; border-radius:12px;" />

    <p style="margin-top:10px; color:#444;">
        Anda sudah melakukan tanda tangan dan tidak dapat mengubahnya lagi, untuk perubahan silahkan lapor ke PJTU/PJLU.
    </p>

<?php elseif (!$no_hp_exist): ?>

    <p style="color:red; font-weight:bold; margin-bottom: 20px;">
        <i class="fas fa-exclamation-triangle"></i>
        Harap masukkan Nomor HP Anda terlebih dahulu sebelum melakukan tanda tangan.
    </p>

    <div style="text-align: left; max-width: 350px; margin: 0 auto;">
        <label for="input-no-hp" style="font-weight: bold; display: block; margin-bottom: 5px;">Nomor HP:</label>
        <input type="number" id="input-no-hp" placeholder="Contoh: 081234567890" 
            style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-size: 16px;" 
            value="<?= htmlspecialchars($no_hp) ?>">
    </div>
    
    <div style="margin-top:20px;">
        <button class="btn-ttd btn-save" onclick="saveNoHp()">Simpan Nomor HP</button>
    </div>
    <p id="no-hp-message" style="margin-top:15px; font-weight: bold; min-height: 20px;"></p>

<?php else: // $no_hp_exist && $ttd == "" ?>

    <p style="color:green; font-weight:bold; margin-bottom: 10px;">
        Nomor HP Anda sudah tersimpan: <?= htmlspecialchars($no_hp) ?>
    </p>
    <p>Silakan tanda tangan di area berikut:</p>

    <canvas id="signature-pad"></canvas>

    <div style="margin-top:15px;">
        <button class="btn-ttd btn-reset" onclick="resetPad()">Reset</button>
        <button class="btn-ttd btn-save" onclick="saveSignature()">Simpan Tanda Tangan</button>
    </div>

<?php endif; ?>

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
    height: 220px;
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
/* Style untuk input number di beberapa browser mobile */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type="number"] {
    -moz-appearance: textfield;
}
</style>

<script>

<?php if (!$no_hp_exist): ?>

// ========== SAVE NO HP (SKENARIO 1) ==========
function saveNoHp() {
    const noHpInput = document.getElementById('input-no-hp');
    const noHp = noHpInput.value.trim();
    const messageDiv = document.getElementById('no-hp-message');

    if (noHp === "") {
        messageDiv.textContent = "Nomor HP tidak boleh kosong!";
        messageDiv.style.color = "red";
        return;
    }
    
    // Client-side validation: must start with 0 and contain only numbers
    if (!noHp.match(/^0\d+$/) || noHp.length < 8) { 
         messageDiv.textContent = "Format Nomor HP tidak valid (harus diawali 0, minimal 8 digit, dan hanya angka).";
         messageDiv.style.color = "red";
         return;
    }

    let form = new FormData();
    form.append("save_no_hp", "1");
    form.append("no_hp", noHp);

    messageDiv.textContent = "Sedang menyimpan...";
    messageDiv.style.color = "#6a4ff7";

    fetch("tandatangan.php", {
        method: "POST",
        body: form
    })
    .then(r => r.text())
    .then(res => {
        let trimmedRes = res.trim();
        if (trimmedRes === "ok") {
            messageDiv.textContent = "✔ Nomor HP berhasil disimpan!";
            messageDiv.style.color = "green";
            // Reload the page content to show the signature pad
            setTimeout(() => {
                 if (typeof window.loadPage === 'function') {
                    window.loadPage('tandatangan.php'); 
                 } else {
                    location.reload(); 
                 }
            }, 1000);
        } else {
            messageDiv.textContent = trimmedRes;
            messageDiv.style.color = "red";
        }
    })
    .catch(error => {
        messageDiv.textContent = "Terjadi kesalahan koneksi.";
        messageDiv.style.color = "red";
        console.error('Error:', error);
    });
}
window.saveNoHp = saveNoHp;

<?php endif; ?>

<?php if ($no_hp_exist && $ttd == ""): ?>

// ========== INIT SIGNATURE PAD & SAVE (SKENARIO 2) ==========
let signaturePad;

function initPad() {
    const canvas = document.getElementById("signature-pad");
    if (!canvas) return;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width  = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
    }

    resizeCanvas();
    signaturePad = new SignaturePad(canvas, { backgroundColor: 'white' });

    window.addEventListener("resize", resizeCanvas);
}

// Dipanggil setelah HTML dimasukkan oleh loadPage()
setTimeout(initPad, 100);

// RESET
function resetPad() { if (signaturePad) signaturePad.clear(); }
window.resetPad = resetPad;

// SAVE SIGNATURE
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
        let trimmedRes = res.trim();
        if (trimmedRes === "ok") {
            alert("✔ Tanda tangan berhasil disimpan!");
            if (typeof window.loadPage === 'function') {
                window.loadPage('tandatangan.php'); // reload tampilan
            } else {
                location.reload();
            }
        } else {
            alert(trimmedRes);
        }
    });
}
window.saveSignature = saveSignature;

<?php endif; ?>
</script>