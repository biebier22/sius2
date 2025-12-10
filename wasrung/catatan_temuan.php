<?php
// Asumsi: Variabel koneksi $conn2 dan session sudah tersedia dari file utama
// $id_wasling dan $masa harusnya sudah tersedia dari sesi Wasling
$id_pengawas = $_SESSION['id_pengawas'] ?? 'PGW001';
$nama_pengawas = $_SESSION['nama'] ?? 'Pengisi Catatan (Wasrung)';
$masa = "20252"; // Sesuaikan jika 'masa' juga disimpan di session
$notif = "";

// ID Placeholder untuk Ruang Manual (karena kolom ruang adalah INT)
$MANUAL_RUANG_ID = 999; 

// =====================================
// 1. LOGIKA SIMPAN DATA
// =====================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_temuan'])) {
    
    // Ambil Ruang sebagai TEXT input
    $ruang_input_text = mysqli_real_escape_string($conn2, $_POST['ruang_manual']); 
    $jam = mysqli_real_escape_string($conn2, $_POST['jam']);
    $catatan = mysqli_real_escape_string($conn2, $_POST['catatan']);
    
    // MENENTUKAN ID DAN NAMA PEMBUAT
    $id_pembuat = $id_pengawas; 
    $nama_pembuat = $nama_pengawas; 
    
    // Siapkan data untuk database
    $ruang_db = $MANUAL_RUANG_ID; // Kolom ruang (INT) diisi dengan ID placeholder
    
    // Gabungkan nama ruang manual ke Catatan agar teksnya tetap tersimpan
    $catatan_final = "RUANG/LOKASI: " . strtoupper($ruang_input_text) . " | " . $catatan;


    // Validasi sederhana
    if (!empty($ruang_input_text) && !empty($jam) && !empty($catatan)) {
        
        $insert_sql = "
            INSERT INTO catatan_temuan 
            (masa, ruang, jam_ujian, id_pembuat_temuan, nama_pembuat, catatan)
            VALUES (
                '$masa', 
                '$ruang_db', 
                '$jam', 
                '$id_pembuat', 
                '$nama_pembuat',
                '$catatan_final' -- Menggunakan catatan yang sudah digabung
            )
        ";
        
        if (mysqli_query($conn2, $insert_sql)) {
            $notif = "<div class='alert-success'>✅ Catatan temuan untuk **$ruang_input_text** berhasil disimpan oleh **" . htmlspecialchars($nama_pembuat) . "**</div>";
        } else {
            $notif = "<div class='alert-danger'>❌ Gagal menyimpan catatan: " . mysqli_error($conn2) . "</div>";
        }
    } else {
        $notif = "<div class='alert-warning'>⚠️ Ruang/Lokasi, Jam, dan Catatan wajib diisi.</div>";
    }
}

// Catatan: Query untuk mengambil daftar ruang ($ruang_list) DIHAPUS karena tidak lagi digunakan.
?>

<style>
/* Style tambahan khusus untuk halaman ini */
.form-group {
    margin-bottom: 15px;
    text-align: left;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}
.form-control, .btn-submit {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    box-sizing: border-box; 
}
textarea.form-control {
    resize: vertical;
    min-height: 100px;
}
.btn-submit {
    background: #6a4ff7;
    color: white;
    border: none;
    cursor: pointer;
    font-size: 16px;
}
.btn-submit:hover {
    background: #5a3fe5;
}
.alert-success {
    padding: 10px;
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
}
.alert-danger, .alert-warning {
    padding: 10px;
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
}
.card {
    background: #fff;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}
</style>

<div class="card">
    <h3>📝 Catatan Temuan</h3>
    <p style="color:#666; font-size:14px;">Diisi oleh: **<?= htmlspecialchars($nama_pengawas) ?>** (ID: <?= htmlspecialchars($id_pengawas) ?>)</p>

    <?= $notif ?>

    <form method="post">
        
        <div class="form-group">
            <label for="ruang_manual">Ruang:</label>
            <input type="text" 
                   name="ruang_manual" 
                   id="ruang_manual" 
                   class="form-control" 
                   placeholder="Contoh: Kayana, Aula Utama, Ruang 10" 
                   required>
            <!-- <small style="color:#666;">
                *Catatan: Ruang akan disimpan sebagai teks di dalam kolom catatan. ID database: **<?= $MANUAL_RUANG_ID ?>**
            </small> -->
        </div>

        <div class="form-group">
            <label for="jam">Pilih Jam Ujian:</label>
            <select name="jam" id="jam" class="form-control" required>
                <option value="">-- Pilih Jam --</option>
                <option value="1">Jam 1</option>
                <option value="2">Jam 2</option>
                <option value="3">Jam 3</option>
                <option value="4">Jam 4</option>
                <option value="5">Jam 5</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="catatan">Catatan Temuan:</label>
            <textarea name="catatan" id="catatan" class="form-control" required placeholder="Tulis informasi secara lengkap, jika ada NIM masukkan saja"></textarea>
        </div>
        
        <button type="submit" name="simpan_temuan" class="btn-submit">Simpan Catatan</button>
        
    </form>
</div>