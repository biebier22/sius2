<?php
session_start();
include "../config.php";

$id = $_SESSION['id_wasrung'] ?? '';
if ($id == "") { echo "Session kosong"; exit; }

// ================== AMBIL DATA EXISTING Awal ==================
$ttd = "";
$no_wa_wasrung = "";
$r = mysqli_query($conn2, "SELECT tanda_tangan, no_wa FROM pengawas_ruang WHERE id_pengawas='$id' LIMIT 1");
if ($r && mysqli_num_rows($r)) {
    $d = mysqli_fetch_assoc($r);
    $ttd = $d['tanda_tangan'] ?? "";
    $no_wa_wasrung = trim($d['no_wa'] ?? ""); // Ambil data dan hapus spasi
}

// Kondisi yang menunjukkan bahwa Nomor WA belum diisi (masih menggunakan placeholder '-')
$WA_BELUM_DIISI = ($no_wa_wasrung == "-");

// ================== SIMPAN NOMOR WA AKTIF WASRUNG ==================
if (isset($_POST['save_wa']) && $_POST['save_wa'] == "1") {
    
    // Cek kembali apakah no_wa sudah terisi (yaitu TIDAK sama dengan "-")
    if (!$WA_BELUM_DIISI) {
        echo "Nomor WA sudah pernah disimpan! Tidak bisa diubah lagi.";
        exit;
    }

    $no_wa = trim($_POST['no_wa']);
    
    // Validasi sederhana nomor telepon: minimal 8 digit dan hanya angka
    if (!preg_match('/^[0-9]{8,}$/', $no_wa)) {
        echo "Nomor WA tidak valid. Hanya boleh angka dan minimal 8 digit.";
        exit;
    }

    $no_wa_escaped = mysqli_real_escape_string($conn2, $no_wa);
    
    $q = mysqli_query($conn2, "
        UPDATE pengawas_ruang 
        SET no_wa = '$no_wa_escaped'
        WHERE id_pengawas = '$id'
    ");

    echo $q ? "ok_wa" : "SQL ERROR: " . mysqli_error($conn2);
    exit;
}

// ================== SIMPAN TTD ==================
if (isset($_POST['save']) && $_POST['save'] == "1") {
    
    // 1. Cek apakah no_wa SUDAH terisi (yaitu TIDAK sama dengan "-"). Jika belum, tolak.
    if ($WA_BELUM_DIISI) {
        echo "Nomor WA belum diisi. Harap isi Nomor WA aktif terlebih dahulu.";
        exit;
    }

    // 2. Cek kembali apakah user SUDAH punya tanda_tangan
    if (!empty($ttd)) {
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
            UPDATE pengawas_ruang 
            SET tanda_tangan = '$filenameEscaped'
            WHERE id_pengawas = '$id'
        ");

        echo $q ? "ok_ttd" : "SQL ERROR: " . mysqli_error($conn2);
    } else {
        echo "Gagal menyimpan file!";
    }
    exit;
}

?>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<div id="signature-container" class="card">
    <h3>Tanda Tangan WASRUNG</h3>

<?php if ($ttd != ""): ?>

    <p style="color:green; font-weight:bold;">Tanda tangan sudah tersimpan.</p>

    <img src="signature/<?= htmlspecialchars($ttd) ?>" 
            style="width:100%; max-width:350px; border:2px solid #999; border-radius:12px;" />

    <p style="margin-top:10px; color:#444;">
        Anda sudah melakukan tanda tangan dan tidak dapat mengubahnya lagi, untuk perubahan silahkan lapor ke PJTU/PJLU.
    </p>

<?php elseif ($WA_BELUM_DIISI): ?>

    <p style="color:#ef4444; font-weight:bold;">⚠️ Penting! Masukkan Nomor WhatsApp Aktif Anda</p>
    <p>Nomor WA ini akan digunakan oleh Wasling untuk koordinasi. Nomor WA yang sudah disimpan **tidak bisa diubah**.</p>
    
    <div style="margin: 20px 0;">
        <label for="input-no-hp" style="display: block; text-align: left; margin-bottom: 5px; font-weight: 500;">Nomor WA Aktif (Hanya Angka, contoh: 08123456789)</label>
        <input type="number" 
               id="input-no-hp" 
               placeholder="Contoh: 08123456789"
               style="width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px;">
    </div>

    <div style="margin-top:15px;">
        <button class="btn-ttd btn-save" style="width: 95%; background: #10b981;" onclick="saveWaNumber()">Simpan Nomor WA</button>
    </div>

<?php else: ?>

    <p style="color:#0f172a; font-weight:bold;">Nomor WA aktif (<?php echo htmlspecialchars($no_wa_wasrung); ?>) sudah tersimpan.</p>
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
</style>

<script>
// Fungsi untuk menyimpan Nomor WA
function saveWaNumber() {
    const input = document.getElementById("input-no-hp");
    const no_wa = input.value.trim();
    
    if (no_wa.length < 8 || !/^[0-9]+$/.test(no_wa)) {
        alert("Nomor WA tidak valid. Pastikan hanya angka dan minimal 8 digit.");
        return;
    }
    
    if (!confirm(`Anda yakin ingin menyimpan nomor WA: ${no_wa}? Nomor ini tidak dapat diubah lagi.`)) {
        return;
    }
    
    let form = new FormData();
    form.append("save_wa", "1");
    form.append("no_wa", no_wa);
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = "Menyimpan...";

    fetch("tandatangan.php", {
        method: "POST",
        body: form
    })
    .then(r => r.text())
    .then(res => {
        res = res.trim();
        if (res === "ok_wa") {
            alert("✔ Nomor WA berhasil disimpan! Lanjutkan dengan Tanda Tangan.");
            // loadPage() diasumsikan adalah fungsi untuk me-reload konten halaman
            if (typeof loadPage === 'function') {
                loadPage('tandatangan.php'); 
            } else {
                window.location.reload(); 
            }
        } else {
            alert(res);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(error => {
        alert("Terjadi kesalahan koneksi: " + error);
        btn.disabled = false;
        btn.textContent = originalText;
    });
}
window.saveWaNumber = saveWaNumber; // Eksponensial ke window scope

<?php 
// Hanya inisialisasi Signature Pad jika TTD belum ada DAN WA sudah terisi (bukan "-")
if ($ttd == "" && !$WA_BELUM_DIISI): 
?>
// Kode Signature Pad hanya di-initialize jika Nomor WA sudah ada DAN TTD belum ada

// ========== INIT SIGNATURE PAD ==========
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

// SAVE
function saveSignature() {
    if (!signaturePad || signaturePad.isEmpty()) {
        alert("Tanda tangan masih kosong!");
        return;
    }

    let imgData = signaturePad.toDataURL("image/png");
    let form = new FormData();
    form.append("save", "1");
    form.append("image", imgData);
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = "Menyimpan...";

    fetch("tandatangan.php", {
        method: "POST",
        body: form
    })
    .then(r => r.text())
    .then(res => {
        res = res.trim();
        if (res === "ok_ttd") {
            alert("✔ Tanda tangan berhasil disimpan!");
            // loadPage() diasumsikan adalah fungsi untuk me-reload konten halaman
            if (typeof loadPage === 'function') {
                loadPage('tandatangan.php'); 
            } else {
                window.location.reload(); 
            }
        } else {
            alert(res);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(error => {
        alert("Terjadi kesalahan koneksi: " + error);
        btn.disabled = false;
        btn.textContent = originalText;
    });
}
window.saveSignature = saveSignature;

<?php endif; ?>
</script>