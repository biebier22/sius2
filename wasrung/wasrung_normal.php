<?php
session_start();
include "../config.php";

$showDashboard = false;
$notifPassword = "";
$lokasi = "";
$ruang  = "";
$jamData = [];

// ====================
// LOGIN MENGGUNAKAN ID PENGAWAS
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['passcode']) && !isset($_POST['action'])) {
    $input = trim($_POST['passcode']);
    $q = mysqli_query($conn2, "SELECT * FROM pengawas_ruang WHERE id_pengawas='".mysqli_real_escape_string($conn2,$input)."' LIMIT 1");
    if (mysqli_num_rows($q) > 0) {
        $d = mysqli_fetch_assoc($q);
        $_SESSION['pengawas_unlock'] = true;
        $_SESSION['nama']           = $d['nama_pengawas'];
        $_SESSION['id_pengawas']    = $d['id_pengawas'];
        $showDashboard = true;

        $lokasi = $d['lokasi'] ?? '';
        $ruang  = $d['ruang'] ?? '';
        $jamData = [
            ['JAM 1', (int)$d['jam1'], 'row-jam-1', 1],
            ['JAM 2', (int)$d['jam2'], 'row-jam-2', 2],
            ['JAM 3', (int)$d['jam3'], 'row-jam-3', 3],
            ['JAM 4', (int)$d['jam4'], 'row-jam-4', 4],
            ['JAM 5', (int)$d['jam5'], 'row-jam-5', 5],
        ];
    } else {
        $notifPassword = "<div style='color:red;font-weight:bold;margin-top:10px;'>ID Pengawas tidak ditemukan!</div>";
    }
}

// ====================
// Ambil data jika sudah login
// ====================
if (isset($_SESSION['pengawas_unlock']) && $_SESSION['pengawas_unlock'] === true) {
    $showDashboard = true;
    $id_pengawas = $_SESSION['id_pengawas'];
    $res = mysqli_query($conn2, "SELECT lokasi,ruang,jam1,jam2,jam3,jam4,jam5 FROM pengawas_ruang WHERE id_pengawas='".mysqli_real_escape_string($conn2,$id_pengawas)."' LIMIT 1");
    if(mysqli_num_rows($res) > 0){
        $row = mysqli_fetch_assoc($res);
        $lokasi = $row['lokasi'] ?? '';
        $ruang  = $row['ruang'] ?? '';
        $jamData = [
            ['JAM 1', (int)$row['jam1'], 'row-jam-1', 1],
            ['JAM 2', (int)$row['jam2'], 'row-jam-2', 2],
            ['JAM 3', (int)$row['jam3'], 'row-jam-3', 3],
            ['JAM 4', (int)$row['jam4'], 'row-jam-4', 4],
            ['JAM 5', (int)$row['jam5'], 'row-jam-5', 5],
        ];
    }
}

// =====================
// Ambil data laporan yang sudah ada
// =====================
function getLaporan($conn, $id_pengawas){
    $laporan = [];
    $lap = mysqli_query($conn, "SELECT jam,jumlah_hadir,cek_status,keterangan,ttd_pengawas FROM laporan_hadir WHERE id_pengawas='".mysqli_real_escape_string($conn,$id_pengawas)."'");
    while($r = mysqli_fetch_assoc($lap)){
        $laporan[(int)$r['jam']] = [
            'jumlah_hadir' => (int)$r['jumlah_hadir'],
            'cek_status'   => $r['cek_status'],
            'keterangan'   => $r['keterangan'],
            'ttd_pengawas' => $r['ttd_pengawas']
        ];
    }
    return $laporan;
}

// =====================
// HANDLE AJAX
// =====================
if(isset($_POST['action'])){
    if(!isset($_SESSION['id_pengawas'])){
        echo json_encode(['success'=>false,'message'=>'Not logged in']);
        exit;
    }
    $id_pengawas = $_SESSION['id_pengawas'];

    // === SIMPAN LAPORAN KEHADIRAN ===
    if($_POST['action'] === 'laporkan'){
        $jam = (int)$_POST['jam'];
        $jumlah_hadir = (int)$_POST['jumlah'];
        $keterangan = '';
        $status = 'on cek';

        $check = mysqli_prepare($conn2, "SELECT id FROM laporan_hadir WHERE id_pengawas=? AND jam=? LIMIT 1");
        mysqli_stmt_bind_param($check, "si", $id_pengawas, $jam);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if(mysqli_stmt_num_rows($check) > 0){
            $update = mysqli_prepare($conn2, "UPDATE laporan_hadir SET jumlah_hadir=?, cek_status=?, keterangan=?, updated_at=NOW() WHERE id_pengawas=? AND jam=?");
            mysqli_stmt_bind_param($update, "isssi", $jumlah_hadir, $status, $keterangan, $id_pengawas, $jam);
            $exec = mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
        } else {
            $insert = mysqli_prepare($conn2, "INSERT INTO laporan_hadir (id_pengawas,jam,jumlah_hadir,cek_status,keterangan,created_at) VALUES (?,?,?,?,?,NOW())");
            mysqli_stmt_bind_param($insert, "siiss", $id_pengawas, $jam, $jumlah_hadir, $status, $keterangan);
            $exec = mysqli_stmt_execute($insert);
            mysqli_stmt_close($insert);
        }
        mysqli_stmt_close($check);

        echo json_encode([
            'success'=>$exec, 
            'status'=>$status, 
            'keterangan'=>$keterangan, 
            'jumlah_hadir'=>$jumlah_hadir
        ]);
        exit;

    }

    // === AMBIL DATA LAPORAN ===
    elseif($_POST['action'] === 'get_laporan'){ 
        $laporan = getLaporan($conn2, $id_pengawas);
        echo json_encode($laporan);
        exit;
    }

    // === TTD PENGAWAS ===
    elseif($_POST['action'] === 'save_ttd'){ // === TTD ===
        $jam = (int)$_POST['jam'];
        $img = $_POST['img'];

        $save = mysqli_prepare($conn2, "
            UPDATE laporan_hadir 
            SET ttd_pengawas=?, updated_at=NOW() 
            WHERE id_pengawas=? AND jam=?
        ");
        mysqli_stmt_bind_param($save, "ssi", $img, $id_pengawas, $jam);
        $ok = mysqli_stmt_execute($save);
        mysqli_stmt_close($save);

        echo json_encode(['success'=>$ok]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Warung Home - Live Update</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: #f1f1f1; }
.lock-box { max-width: 360px; margin: 80px auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 3px 12px rgba(0,0,0,0.15); text-align: center; }
.lock-box h3 { font-weight: bold; }
.lock-input { font-size: 18px; padding: 10px 15px; width: 100%; border-radius: 8px; border: 1px solid #999; text-align: center; }
.btn-lock { width: 100%; padding: 10px; background: #0d6efd; color: white; border: none; border-radius: 8px; font-size: 18px; margin-top: 15px; }

.card-schedule, .table-naskah { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; }
.card-schedule h2, .table-naskah h2 { margin-bottom: 15px; color: #0277bd; }
.card-schedule table, .table-naskah table { width: 100%; border-collapse: collapse; }
.card-schedule th, .card-schedule td, .table-naskah th, .table-naskah td { border: 1px solid #ccc; padding: 10px; text-align: center; vertical-align: middle; }

.btn-laporkan { padding: 6px 12px; background: #42a5f5; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
.participant-form { margin-top: 10px; display: none; }
.participant-input { width: 60px; padding: 5px; margin-right: 5px; }

.row-jam-1 { background-color: #ffffff; }  
.row-jam-2 { background-color: #ff4d4d; } 
.row-jam-3 { background-color: #ffff66; } 
.row-jam-4 { background-color: #66ff66; } 
.row-jam-5 { background-color: #66b3ff; } 

/* Modal TTD */
#modalTTD {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  z-index: 9999;
  justify-content: center;
  align-items: center;
}
.tok-box {
  background: #fff;
  padding: 16px;
  border-radius: 8px;
  max-width: 420px;
  width: 95%;
}
.tok-canvas { width: 100%; border:1px solid #ccc; border-radius:6px; touch-action: none; }
.ttd-thumb { max-width: 110px; display:block; margin: auto; }
</style>
</head>
<body>

<?php if(!$showDashboard): ?>
<div class="lock-box">
    <h3>Masukkan ID Pengawas</h3>
    <p>Halaman ini dilindungi menggunakan ID Pengawas.</p>
    <form method="post">
        <input type="password" name="passcode" class="lock-input" placeholder="Masukkan ID Pengawas" autofocus required>
        <button type="submit" class="btn-lock">Masuk</button>
    </form>
    <?= $notifPassword ?>
</div>

<?php else: ?>
<h3>Lokasi: <b><?= htmlspecialchars($lokasi) ?></b></h3>
<h3>Selamat datang, <b><?= htmlspecialchars($_SESSION['nama']) ?></b></h3>

<div class="card-schedule">
    <h2>WAKTU UJIAN (WIB)</h2>
    <table>
        <tr><th>JAM 1</th><td>07:00 - 08:30</td></tr>
        <tr><th>JAM 2</th><td>08:45 - 10:15</td></tr>
        <tr><th>JAM 3</th><td>10:30 - 12:00</td></tr>
        <tr><th>ISTIRAHAT</th><td>-</td></tr>
        <tr><th>JAM 4</th><td>12:45 - 14:15</td></tr>
        <tr><th>JAM 5</th><td>14:30 - 16:00</td></tr>
    </table>
</div>

<div class="table-naskah">
    <h2>RUANG: <?= htmlspecialchars($ruang) ?></h2>
    <table id="table-laporan">
        <tr>
            <th>Jam</th>
            <th>Jumlah Naskah</th>
            <th>Laporkan Kehadiran</th>
            <th>Status</th>
            <th>Keterangan</th>
            <th>Tanda Tangan</th> <!-- kolom TTD ditambahkan -->
        </tr>
        <?php foreach($jamData as $jam): ?>
        <tr class="<?= $jam[2] ?>" data-jam="<?= $jam[3] ?>">
            <td><?= $jam[0] ?></td>
            <td><?= $jam[1] ?></td>
            <td>
                <button type="button" class="btn-laporkan" onclick="showForm(this)">Laporkan</button>
                <div class="participant-form">
                    <input type="number" class="participant-input" min="0" max="<?= $jam[1] ?>" placeholder="Hadir">
                    <button type="button" onclick="submitAttendance(this, <?= $jam[3] ?>)">OK</button>
                </div>
            </td>
            <td class="status-cell">-</td>
            <td class="keterangan-cell">-</td>
            <td class="ttd-cell">-</td> <!-- sel kosong untuk ttd -->
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- ==================== MODAL TTD ==================== -->
<div id="modalTTD">
  <div class="tok-box">
    <h5 style="text-align:center;margin-bottom:8px;">Tanda Tangan Pengawas</h5>
    <canvas id="ttdCanvas" class="tok-canvas" width="800" height="400"></canvas>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;">
        <button id="btnClear" class="btn" style="background:#6c757d;color:#fff;border-radius:6px;padding:6px 12px;">Bersihkan</button>
        <button id="btnSave" class="btn" style="background:#198754;color:#fff;border-radius:6px;padding:6px 12px;">Simpan</button>
        <button id="btnClose" class="btn" style="background:#dc3545;color:#fff;border-radius:6px;padding:6px 12px;">Batal</button>
    </div>
    <p style="font-size:12px;text-align:center;margin-top:8px;color:#666;">Gambar tanda tangan pada area di atas lalu tekan Simpan</p>
  </div>
</div>

<script src="../assets/libs/jquery/dist/jquery.min.js"></script>
<script>
/* ========== Form show/hide ========== */
function showForm(btn){
    btn.style.display = 'none';
    btn.nextElementSibling.style.display = 'block';
}

/* ========== Submit attendance ========== */
function submitAttendance(btn, jam){
    const input = btn.previousElementSibling;
    const val = parseInt(input.value);
    if(isNaN(val) || val < 0){
        alert("Masukkan jumlah peserta hadir valid!");
        return;
    }

    $.post('', {action:'laporkan', jam:jam, jumlah:val}, function(resp){
        try{
            const data = JSON.parse(resp);
            if(data.success){
                updateRow(jam, data.jumlah_hadir, data.status, data.keterangan);
            } else {
                alert('Gagal simpan data!');
            }
        }catch(e){
            alert('Error: ' + resp);
        }
    });
}

/* ========== Update single row (termasuk TTD logic) ========== */
function updateRow(jam, hadir, status, keterangan, ttd_img = null){
    const row = document.querySelector('tr[data-jam="'+jam+'"]');
    if(!row) return;

    row.querySelector('.status-cell').innerText = status ?? '-';
    row.querySelector('.keterangan-cell').innerText = keterangan ?? '-';
    row.querySelector('td:nth-child(3)').innerHTML = hadir + ' peserta hadir';

    const ttdCell = row.querySelector('.ttd-cell');

    // Jika sudah ada gambar ttd di DB, tampilkan
    if(ttd_img && ttd_img !== null && ttd_img !== ''){
        ttdCell.innerHTML = `<img src="${ttd_img}" class="ttd-thumb" alt="TTD">`;
        return;
    }

    // Jika status = sudah sesuai -> tampilkan tombol TTD (jika belum ada)
    if(status === 'sudah sesuai'){
        ttdCell.innerHTML = `<button class="btn-ttd" onclick="openTTD(${jam})" style="padding:6px 10px;background:#0d6efd;color:#fff;border:none;border-radius:6px;">Tanda Tangan</button>`;
    } else {
        ttdCell.innerHTML = '-';
    }
}

/* ========== Live polling setiap 5 detik ========== */
function pollLaporan(){
    $.post('', {action:'get_laporan'}, function(resp){
        try{
            const data = JSON.parse(resp);
            for(const jam in data){
                const item = data[jam];
                updateRow(jam, item.jumlah_hadir, item.cek_status, item.keterangan, item.ttd_pengawas);
            }
        }catch(e){
            console.error('Error live update:', e);
        }
    });
}

// Mulai polling
setInterval(pollLaporan, 5000);

/* Load data awal */
$(document).ready(function(){
    pollLaporan();
});
</script>

<?php endif; ?>
<script>
// =====================
// BAGIAN 3/3 - Canvas TTD & Handler
// =====================

let currentJamForTTD = null;

// Ambil elemen canvas & konteks
const modal = document.getElementById('modalTTD');
const canvas = document.getElementById('ttdCanvas');
const ctx = canvas.getContext('2d');

// Resize canvas untuk kualitas di layar perangkat (retina-aware)
function resizeCanvasToDisplaySize() {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    if (canvas.width !== Math.floor(w * ratio) || canvas.height !== Math.floor(h * ratio)) {
        const prev = ctx.getImageData(0,0,canvas.width,canvas.height);
        canvas.width = Math.floor(w * ratio);
        canvas.height = Math.floor(h * ratio);
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        ctx.scale(ratio, ratio);
        // clear after resize to avoid weirdness (we usually open modal with cleared canvas)
        ctx.clearRect(0,0,canvas.width,canvas.height);
    }
}

// Panggil resize saat dibuka dan saat ukuran jendela berubah
window.addEventListener('resize', resizeCanvasToDisplaySize);

// Pointer drawing (works for mouse & touch)
let drawing = false;
let lastX = 0;
let lastY = 0;

function pointerDown(e){
    e.preventDefault();
    drawing = true;
    const p = getPointerPos(e);
    lastX = p.x; lastY = p.y;
}
function pointerUp(e){
    e.preventDefault();
    drawing = false;
    ctx.beginPath();
}
function pointerMove(e){
    if(!drawing) return;
    e.preventDefault();
    const p = getPointerPos(e);
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    lastX = p.x; lastY = p.y;
}
function getPointerPos(e){
    const rect = canvas.getBoundingClientRect();
    let clientX, clientY;
    if(e.touches && e.touches.length){
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }
    // convert to canvas CSS pixels (we scaled ctx for DPR)
    return { x: clientX - rect.left, y: clientY - rect.top };
}

// Attach pointer events
canvas.addEventListener('pointerdown', pointerDown);
canvas.addEventListener('pointermove', pointerMove);
canvas.addEventListener('pointerup', pointerUp);
canvas.addEventListener('pointercancel', pointerUp);
canvas.addEventListener('pointerout', pointerUp);
canvas.addEventListener('pointerleave', pointerUp);

// Buttons
document.getElementById('btnClear').addEventListener('click', function(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
});
document.getElementById('btnClose').addEventListener('click', function(){
    closeTTDModal();
});
document.getElementById('btnSave').addEventListener('click', function(){
    saveTTD();
});

// Open modal — dipanggil dari tombol Tanda Tangan
function openTTD(jam){
    currentJamForTTD = jam;
    // pastikan canvas sizing sesuai
    // show modal then resize to use clientWidth
    modal.style.display = 'flex';
    // small delay to ensure CSS applied
    setTimeout(function(){
        resizeCanvasToDisplaySize();
        ctx.clearRect(0,0,canvas.width,canvas.height);
    }, 50);
}

// Close
function closeTTDModal(){
    modal.style.display = 'none';
    currentJamForTTD = null;
    // clear to free memory
    ctx.clearRect(0,0,canvas.width,canvas.height);
}

// Save TTD to server (base64 PNG)
function saveTTD(){
    // generate dataURL in CSS pixel size (use canvas.toDataURL)
    // ensure there is something drawn (simple check: not blank)
    // We'll check a small region for any non-empty pixel
    try {
        // quick check: convert to data URL and check length
        const dataURL = canvas.toDataURL('image/png');
        if(!dataURL || dataURL.length < 2000){
            // very small length => probably empty
            if(!confirm('Tanda tangan tampak kosong. Simpan tetap?')) return;
        }

        // kirim ke server
        $.post('', { action:'save_ttd', jam: currentJamForTTD, img: dataURL }, function(resp){
            try {
                const r = JSON.parse(resp);
                if(r.success){
                    // tampilkan thumbnail di cell
                    const cell = document.querySelector('tr[data-jam="'+currentJamForTTD+'"] .ttd-cell');
                    if(cell){
                        cell.innerHTML = `<img src="${dataURL}" class="ttd-thumb" alt="TTD">`;
                    }
                    closeTTDModal();
                } else {
                    alert('Gagal menyimpan tanda tangan.');
                }
            } catch(e){
                console.error('save_ttd response parse error', e, resp);
                alert('Terjadi kesalahan saat menyimpan tanda tangan.');
            }
        });
    } catch(err){
        console.error(err);
        alert('Gagal mengambil data tanda tangan.');
    }
}

// Prevent page scroll when drawing on mobile (pointer events handle this, but keep safeguard)
document.body.addEventListener('touchstart', function(e){
    if(e.target === canvas) e.preventDefault();
}, {passive:false});
document.body.addEventListener('touchmove', function(e){
    if(e.target === canvas) e.preventDefault();
}, {passive:false});

// initial resize in case modal is open
resizeCanvasToDisplaySize();
</script>
<script src="../assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="../assets/extra-libs/taskboard/js/jquery.ui.touch-punch-improved.js"></script>
    <script src="../assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <script src="../assets/libs/popper.js/dist/umd/popper.min.js"></script>
    <script src="../assets/libs/bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="../dist/js/app-style-switcher.js"></script>
    <script src="../dist/js/feather.min.js"></script>
    <script src="../assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
    <script src="../dist/js/sidebarmenu.js"></script>
    <script src="../dist/js/custom.min.js"></script>
</body>
</html>
