<?php
session_start();
include "../config.php";

// 1. Validasi Sesi (Diubah ke id_pengawas)
if (!isset($_SESSION['id_wasrung'])) {
    echo "<div class='card'><div style='text-align:center; padding:20px; color:#ef4444;'>Session Pengawas Ruang tidak ditemukan.</div></div>";
    exit;
}

$id_pengawas = $_SESSION['id_wasrung'];
$masa = "20252";
$ruang = intval($_GET['ruang'] ?? 0);
$hari = trim($_GET['hari'] ?? '');

if ($ruang <= 0 || empty($hari)) {
    // Ambil data ruang dan hari dari sesi pengawas jika tidak ada di GET
    $q_pengawas_sess = mysqli_query($conn2, "SELECT ruang, hari FROM pengawas_ruang WHERE id_pengawas='".mysqli_real_escape_string($conn2, $id_pengawas)."' AND masa='".mysqli_real_escape_string($conn2, $masa)."' LIMIT 1");
    if($row_sess = mysqli_fetch_assoc($q_pengawas_sess)) {
        $ruang = intval($row_sess['ruang']);
        $hari = trim($row_sess['hari']);
    }

    if ($ruang <= 0 || empty($hari)) {
        echo "<div class='card'><div style='text-align:center; padding:20px; color:#ef4444;'>Parameter ruang dan hari tidak valid, dan tidak ditemukan di sesi.</div></div>";
        exit;
    }
}


// Ambil data pengawas ruang yang sedang login (Ditambahkan kolom tanda_tangan)
$pengawas_info = null;
$stmt_pengawas = $conn2->prepare("
    SELECT id_pengawas, nama_pengawas, lokasi, tanda_tangan
    FROM pengawas_ruang
    WHERE id_pengawas = ? AND ruang = ? AND CAST(hari AS CHAR) = ? AND masa = ?
    LIMIT 1
");

if ($stmt_pengawas) {
    $stmt_pengawas->bind_param("siis", $id_pengawas, $ruang, $hari, $masa);
    $stmt_pengawas->execute();
    $result_pengawas = $stmt_pengawas->get_result();
    if ($row = $result_pengawas->fetch_assoc()) {
        $pengawas_info = $row;
    }
    $stmt_pengawas->close();
}

if (!$pengawas_info) {
    echo "<div class='card'><div style='text-align:center; padding:20px; color:#ef4444;'>Data Pengawas Ruang tidak ditemukan untuk ruang/hari ini.</div></div>";
    exit;
}

// =======================================================
// START: BLOCKING LOGIC - Jika Tanda Tangan belum ada
// =======================================================
if (empty($pengawas_info['tanda_tangan'])) {
    // Sisipkan CSS minimal agar tampilan peringatan menarik
    echo '<style>
        .block-card {
            max-width: 400px; 
            margin: 50px auto; 
            text-align: center; 
            padding: 30px; 
            border: 1px solid #fca5a5; /* Red border for warning */
            border-radius: 15px; 
            background: #fef2f2; /* Light red background */
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .block-card h2 { color: #dc2626; margin-top: 0; }
        .block-card p { color: #475569; margin-bottom: 20px; }
        .block-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706); /* Orange gradient */
            color: white; 
            width: 100%; 
            padding: 12px; 
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .block-btn:hover { background: #d97706; }
    </style>';

    // Tampilkan pesan blokir dan tombol untuk ke halaman Tanda Tangan
    echo "
        <div class='block-card'>
            <h2>❌ Akses Diblokir!</h2>
            <p>Anda harus melengkapi **Tanda Tangan Pengawas Ruang** terlebih dahulu sebelum dapat mengakses Berita Acara.</p>
            <button class='block-btn' onclick=\"window.loadPage('tandatangan.php')\">
                Lengkapi Tanda Tangan Sekarang
            </button>
        </div>
    ";
    
    // Hentikan eksekusi kode di bawah ini (HTML/form Berita Acara)
    exit;
}
// =======================================================
// END: BLOCKING LOGIC
// =======================================================


// =======================================================
// START: TAMBAHAN LOGIKA UNTUK MENGAMBIL DATA WASLING
// ... (Kode PHP untuk Wasling dan Rekap Ujian di sini) ...
// (Semua kode PHP lainnya yang ada sebelumnya diletakkan di bawah baris ini)
// ...
// =======================================================

$wasling_info = null;
$stmt_wasling = $conn2->prepare("
    SELECT
        w.nama_wasling,
        w.no_hp
    FROM wasling_ruang wr
    JOIN wasling w ON wr.id_wasling = w.id_wasling
    WHERE 
        wr.no_ruang = ? AND 
        CAST(wr.hari AS CHAR) = ? AND 
        wr.lokasi = ? 
    LIMIT 1
");

if ($stmt_wasling) {
    $stmt_wasling->bind_param("iss", $ruang, $hari, $pengawas_info['lokasi']);
    $stmt_wasling->execute();
    $result_wasling = $stmt_wasling->get_result();
    if ($row = $result_wasling->fetch_assoc()) {
        $wasling_info = $row;
    }
    $stmt_wasling->close();
}

// Persiapan Link WhatsApp Wasling
$wasling_wa_link = '';
$wasling_no_hp = $wasling_info['no_hp'] ?? '';

if (!empty($wasling_no_hp)) {
    // Hapus semua karakter non-digit
    $clean_wa = preg_replace('/[^0-9]/', '', $wasling_no_hp);
    
    // Ganti '0' di awal dengan '62' (kode negara Indonesia)
    if (substr($clean_wa, 0, 1) === '0') {
        $clean_wa = '62' . substr($clean_wa, 1);
    } elseif (substr($clean_wa, 0, 2) !== '62' && strlen($clean_wa) > 5) {
        // Jika tidak diawali 0 atau 62, tambahkan 62 (asumsi nomor Indonesia)
        $clean_wa = '62' . $clean_wa;
    }
    
    // Buat link wa.me
    $wasling_wa_link = "https://wa.me/{$clean_wa}";
}

// =======================================================
// END: TAMBAHAN LOGIKA UNTUK MENGAMBIL DATA WASLING
// =======================================================


// Ambil data bahan ujian per jam dari rekap_ujian (sama dengan kode Wasling)
$bahan_per_jam = [];
$stmt_bahan = $conn2->prepare("
    SELECT 
        r.jam1, r.jam2, r.jam3, r.jam4, r.jam5
    FROM rekap_ujian r
    LEFT JOIN e_lokasi_uas e 
        ON r.kode_tpu = e.kode_tpu 
        AND e.masa = ?
        AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED) AND CAST(e.ruang_akhir AS UNSIGNED)
        AND CAST(r.hari_ke AS CHAR) = e.hari
    WHERE 
        r.masa = ? 
        AND e.lokasi = ?
        AND r.hari_ke = ?
        AND CAST(r.ruang_ke AS UNSIGNED) = CAST(? AS UNSIGNED)
    LIMIT 1
");

if ($stmt_bahan) {
    $hari_str = (string)$hari;
    $ruang_str = (string)$ruang;
    $stmt_bahan->bind_param("sssss", $masa, $masa, $pengawas_info['lokasi'], $hari_str, $ruang_str);
    $stmt_bahan->execute();
    $result_bahan = $stmt_bahan->get_result();
    if ($row = $result_bahan->fetch_assoc()) {
        $bahan_per_jam = [
            1 => intval($row['jam1'] ?? 0),
            2 => intval($row['jam2'] ?? 0),
            3 => intval($row['jam3'] ?? 0),
            4 => intval($row['jam4'] ?? 0),
            5 => intval($row['jam5'] ?? 0)
        ];
    }
    $stmt_bahan->close();
}

// Fallback: jika tidak ada data di rekap_ujian, ambil dari pengawas_ruang
if (empty($bahan_per_jam) || array_sum($bahan_per_jam) == 0) {
    $stmt_bahan_fallback = $conn2->prepare("
        SELECT jam1, jam2, jam3, jam4, jam5
        FROM pengawas_ruang
        WHERE id_pengawas = ? AND CAST(hari AS CHAR) = ? AND ruang = ? AND masa = ?
        LIMIT 1
    ");
    
    if ($stmt_bahan_fallback) {
        $stmt_bahan_fallback->bind_param("ssis", $pengawas_info['id_pengawas'], $hari, $ruang, $masa);
        $stmt_bahan_fallback->execute();
        $result_bahan_fallback = $stmt_bahan_fallback->get_result();
        if ($row = $result_bahan_fallback->fetch_assoc()) {
            $bahan_per_jam = [
                1 => intval($row['jam1'] ?? 0),
                2 => intval($row['jam2'] ?? 0),
                3 => intval($row['jam3'] ?? 0),
                4 => intval($row['jam4'] ?? 0),
                5 => intval($row['jam5'] ?? 0)
            ];
        }
        $stmt_bahan_fallback->close();
    }
}


// Ambil data existing per jam jika ada (hanya berdasarkan id_pengawas)
$existing_data_per_jam = [];
$stmt_existing = $conn2->prepare("
    SELECT * FROM berita_acara_serah_terima
    WHERE id_pengawas = ? AND hari = ? AND ruang = ? AND masa = ?
    ORDER BY jam_ke ASC
");

if ($stmt_existing) {
    $stmt_existing->bind_param("ssis", $pengawas_info['id_pengawas'], $hari, $ruang, $masa);
    $stmt_existing->execute();
    $result_existing = $stmt_existing->get_result();
    while ($row = $result_existing->fetch_assoc()) {
        $existing_data_per_jam[$row['jam_ke']] = $row;
    }
    $stmt_existing->close();
}
?>

<style>
/* ... (SEMUA STYLE CSS ANDA YANG SEBELUMNYA) ... */
.berita-acara-form {
    padding: 0;
}

.form-header {
    margin-bottom: 20px;
}

.form-header h2 {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 600;
    color: #0f172a;
}

.form-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.info-card {
    background: linear-gradient(135deg, #e0f2fe, #e0e7ff);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-label {
    color: #64748b;
    font-weight: 500;
}

.info-value {
    color: #0f172a;
    font-weight: 600;
}

/* Style tambahan untuk link WhatsApp */
.wa-link {
    color: #25D366; /* Warna hijau WhatsApp */
    text-decoration: none;
    font-weight: bold;
}

.jam-section {
    background: white;
    border-radius: 16px;
    padding: 0;
    margin-bottom: 16px;
    box-shadow: 0 4px 12px rgba(15,23,42,0.08);
    border-left: 4px solid #2979ff;
    overflow: hidden;
}

.jam-section.jam-1 { border-left-color: #0ea5e9; }
.jam-section.jam-2 { border-left-color: #ef4444; }
.jam-section.jam-3 { border-left-color: #f59e0b; }
.jam-section.jam-4 { border-left-color: #10b981; }
.jam-section.jam-5 { border-left-color: #3b82f6; }

.jam-header {
    padding: 16px 20px;
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background-color 0.2s;
    border-bottom: 1px solid #e2e8f0;
}

.jam-header:hover {
    background-color: #f8fafc;
}

.jam-title {
    font-size: 18px;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.jam-chevron {
    font-size: 14px;
    color: #64748b;
    transition: transform 0.3s ease;
    display: inline-block;
}

.jam-content {
    padding: 20px;
    max-height: 10000px;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease, opacity 0.3s ease;
    opacity: 1;
}

.jam-section.collapsed .jam-content {
    max-height: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    opacity: 0;
    overflow: hidden;
}

.jam-badge {
    background: linear-gradient(120deg, #00bcd4, #2979ff);
    color: #fff;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

.readonly-field {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 14px;
    color: #64748b;
    margin-bottom: 12px;
}

.bahan-table {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0;
    font-size: 13px;
    background: #f8fafc;
    border-radius: 10px;
    overflow: hidden;
}

.bahan-table th {
    background: #e2e8f0;
    color: #475569;
    font-weight: 600;
    padding: 10px 8px;
    text-align: center;
    font-size: 12px;
    border-right: 1px solid #cbd5e1;
}

.bahan-table th:last-child {
    border-right: none;
}

.bahan-table td {
    padding: 10px 8px;
    text-align: center;
    color: #64748b;
    font-weight: 500;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}

.bahan-table td:last-child {
    border-right: none;
}

.bahan-table tr:last-child td {
    border-bottom: none;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    margin-bottom: 6px;
}

.form-input {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #cbd5f5;
    border-radius: 12px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color 0.2s;
    font-family: inherit;
}

.form-input:focus {
    outline: none;
    border-color: #2979ff;
    box-shadow: 0 0 0 3px rgba(41,121,255,0.1);
}

.btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn:active {
    transform: scale(0.98);
}

.btn-clear {
    background: #f1f5f9;
    color: #475569;
}

.btn-save {
    background: linear-gradient(120deg, #00bcd4, #2979ff);
    color: white;
    width: 100%;
    margin-top: 20px;
}

.btn-edit {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
}

.btn-cancel {
    background: #f1f5f9;
    color: #475569;
    width: 100%;
    margin-top: 10px;
}

.message {
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 500;
    display: none;
}

.message.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

</style>

<div class="berita-acara-form">
    <div class="form-header">
        <h2>📝 Berita Acara Penyerahan Hasil Ujian</h2>
        <p>Form penyerahan naskah dan LJU per jam dari Pengawas Ruang</p>
    </div>

    <div class="info-card">
        <div class="info-row">
            <span class="info-label">Ruang:</span>
            <span class="info-value">Ruang <?php echo htmlspecialchars($ruang); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Hari:</span>
            <span class="info-value">Hari <?php echo htmlspecialchars($hari); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Pengawas Ruang:</span>
            <span class="info-value"><?php echo htmlspecialchars($pengawas_info['nama_pengawas'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Lokasi Ujian:</span>
            <span class="info-value"><?php echo htmlspecialchars($pengawas_info['lokasi']); ?></span>
        </div>
        
        <?php if ($wasling_info): // START: TAMBAHAN INFO WASLING ?>
        <div class="info-row">
            <span class="info-label">Wasling Bertugas:</span>
            <span class="info-value"><?php echo htmlspecialchars($wasling_info['nama_wasling'] ?? 'N/A'); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Kontak Wasling:</span>
            <span class="info-value">
                <?php if (!empty($wasling_wa_link)): ?>
                    <a href="<?php echo $wasling_wa_link; ?>" target="_blank" class="wa-link">
                        <?php echo htmlspecialchars($wasling_no_hp); ?> (Chat)
                    </a>
                <?php else: ?>
                    N/A
                <?php endif; ?>
            </span>
        </div>
        <?php endif; // END: TAMBAHAN INFO WASLING ?>

    </div>

    <div id="form-message" class="message"></div>

    <form id="berita-acara-form">
        <input type="hidden" name="ruang" value="<?php echo htmlspecialchars($ruang); ?>">
        <input type="hidden" name="hari" value="<?php echo htmlspecialchars($hari); ?>">
        <input type="hidden" name="id_pengawas" value="<?php echo htmlspecialchars($pengawas_info['id_pengawas']); ?>">
        <input type="hidden" name="lokasi" value="<?php echo htmlspecialchars($pengawas_info['lokasi']); ?>">
        <?php for ($jam = 1; $jam <= 5; $jam++): 
            $existing = $existing_data_per_jam[$jam] ?? null;
            $bahan_naskah = $bahan_per_jam[$jam] ?? 0;
        ?>
        <div class="jam-section jam-<?php echo $jam; ?> collapsed" data-jam="<?php echo $jam; ?>" id="jam-section-<?php echo $jam; ?>">
            <div class="jam-header" data-jam="<?php echo $jam; ?>" onclick="window.toggleJamSection && window.toggleJamSection(<?php echo $jam; ?>)">
                <h3 class="jam-title">
                    <span class="jam-badge">Jam <?php echo $jam; ?></span>
                    <?php 
                        // Logika baru untuk cek status Wasling (bersih dari non-breaking spaces)
                        $is_wasling_checked = $existing && !empty($existing['id_wasling']) && !empty($existing['ttd_wasling']);
                    ?>
                    <?php if ($is_wasling_checked): ?>
                        <span style="font-size: 12px; color: #10b981; margin-left: 8px; font-weight: 700;">
                            ✔️ OK (Diperiksa Wasling)
                        </span>
                    <?php elseif ($existing): ?>
                        <span style="font-size: 12px; color: #f59e0b; margin-left: 8px;">
                            Data Tersimpan (Belum Diperiksa Wasling)
                        </span>
                    <?php endif; ?>
                </h3>
                <span class="jam-chevron" id="jam-chevron-<?php echo $jam; ?>">▶</span>
            </div>
            
            <div class="jam-content" id="jam-content-<?php echo $jam; ?>">
                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; font-weight: 600; color: #475569; margin: 0 0 12px;">📦 Bahan Ujian yang Disediakan</h4>
                    <table class="bahan-table">
                        <thead>
                            <tr>
                                <th>Naskah<br><small style="font-weight: 400;">(amplop)</small></th>
                                <th>LJU<br><small style="font-weight: 400;">(lembar)</small></th>
                                <th>BJU<br><small style="font-weight: 400;">(buku)</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo $bahan_naskah; ?></td>
                                <td><?php echo $bahan_naskah; ?></td>
                                <td>0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; font-weight: 600; color: #475569; margin: 0 0 12px;">📤 Hasil Ujian yang Anda Serahkan</h4>

                    <div class="form-group">
                        <label class="form-label">Naskah (eksp. per amplop) *</label>
                        <input type="number" 
                                id="hasil_naskah_<?php echo $jam; ?>"
                                name="hasil_naskah[<?php echo $jam; ?>]" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($existing['hasil_naskah'] ?? $bahan_naskah); ?>" 
                                min="0" 
                                oninput="hitungKosong('<?php echo $jam; ?>')" 
                                required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">LJU Terisi (lembar) *</label>
                        <input type="number" 
                                id="hasil_lju_terisi_<?php echo $jam; ?>"
                                name="hasil_lju_terisi[<?php echo $jam; ?>]" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($existing['hasil_lju_terisi'] ?? '0'); ?>" 
                                min="0" 
                                oninput="hitungKosong('<?php echo $jam; ?>')" 
                                required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">LJU Kosong (lembar) *</label>
                        <input type="number" 
                                id="hasil_lju_kosong_<?php echo $jam; ?>"
                                name="hasil_lju_kosong[<?php echo $jam; ?>]" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($existing['hasil_lju_kosong'] ?? ($bahan_naskah - ($existing['hasil_lju_terisi'] ?? 0))); ?>" 
                                min="0" 
                                required 
                                readonly style="background:#f0f0f0;">
                    </div>
                </div>
                
                <button type="button" class="btn btn-save" onclick="saveJam(<?php echo $jam; ?>)" style="width: 100%; margin-top: 20px;">
                    💾 Simpan & Serahkan Jam <?php echo $jam; ?>
                </button>
            </div>
        </div>
        <?php endfor; ?>

        <button type="button" class="btn btn-cancel" onclick="window.history.back()" style="width: 100%; margin-top: 10px;">❌ Kembali ke Halaman Utama</button>
    </form>
</div>

<script>
// Logika hitungKosong tetap digunakan
function hitungKosong(jam) {
    let naskah = parseInt(document.getElementById("hasil_naskah_" + jam).value) || 0;
    let terisi = parseInt(document.getElementById("hasil_lju_terisi_" + jam).value) || 0;

    let kosong = naskah - terisi;
    if (kosong < 0) kosong = 0; // jaga agar tidak minus

    document.getElementById("hasil_lju_kosong_" + jam).value = kosong;
}


// Fungsi untuk membuka/menutup section tetap digunakan
function toggleJamSection(jam) {
    const section = document.getElementById('jam-section-' + jam);
    const chevron = document.getElementById('jam-chevron_' + jam);
    
    if (!section) return;
    
    const isCollapsed = section.classList.contains('collapsed');
    
    // Logika untuk memastikan hanya chevron yang diupdate
    if (isCollapsed) {
        // Buka
        section.classList.remove('collapsed');
        if (chevron) chevron.textContent = '▼';
    } else {
        // Tutup
        section.classList.add('collapsed');
        if (chevron) chevron.textContent = '▶';
    }
}

// Fungsi untuk menampilkan pesan tetap digunakan
function showMessage(message, type) {
    const messageDiv = document.getElementById('form-message');
    messageDiv.textContent = message;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'block';
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

async function saveJam(jam) {
    const form = document.getElementById('berita-acara-form');
    const formData = new FormData();
    
    // Validasi input
    const hasilNaskahInput = form.querySelector(`input[name="hasil_naskah[${jam}]"]`);
    const hasilLjuTerisiInput = form.querySelector(`input[name="hasil_lju_terisi[${jam}]"]`);

    if (parseInt(hasilNaskahInput.value) < 0 || parseInt(hasilLjuTerisiInput.value) < 0) {
        showMessage(`Nilai tidak boleh negatif pada Jam ${jam}.`, 'error');
        return;
    }

    if (parseInt(hasilLjuTerisiInput.value) > parseInt(hasilNaskahInput.value)) {
        showMessage(`LJU Terisi tidak boleh lebih dari Naskah pada Jam ${jam}.`, 'error');
        return;
    }

    // Ambil data dasar
    formData.append('ruang', form.querySelector('input[name="ruang"]').value);
    formData.append('hari', form.querySelector('input[name="hari"]').value);
    formData.append('jam_ke', jam);
    
    // ✅ PERBAIKAN: Tambahkan data LOKASI dari hidden input
    formData.append('lokasi', form.querySelector('input[name="lokasi"]').value);
    
    // Ambil data untuk jam ini
    formData.append('hasil_naskah', hasilNaskahInput.value);
    formData.append('hasil_lju_terisi', hasilLjuTerisiInput.value);
    formData.append('hasil_lju_kosong', form.querySelector(`input[name="hasil_lju_kosong[${jam}]"]`).value);
    
    // **Tanda Tangan Dihilangkan**
    
    const btn = event.target;
    // ...
    btn.disabled = true;
    btn.textContent = 'Memproses...';

    try {
        // Ubah endpoint ke save_berita_acara_pengawas.php
        const response = await fetch('save_berita_acara_pengawas.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'ok') {
            showMessage(result.message, 'success');
            // Reload halaman untuk refresh data
            setTimeout(() => {
                const ruang = form.querySelector('input[name="ruang"]').value;
                const hari = form.querySelector('input[name="hari"]').value;
                window.location.href = `wasrung_home.php?ruang=${ruang}&hari=${hari}`;
            }, 1500);
        } else {
            showMessage(result.message, 'error');
        }
    } catch (error) {
        showMessage('Terjadi kesalahan: ' + error, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = `💾 Simpan & Serahkan Jam ${jam}`;
    }
}

// Inisialisasi setelah DOM siap
window.addEventListener('load', () => {
    // Tidak ada lagi inisialisasi signature pad
});


// Pastikan fungsi tersedia di window scope
window.toggleJamSection = toggleJamSection;
window.saveJam = saveJam;
window.showMessage = showMessage;
window.hitungKosong = hitungKosong; // Pastikan fungsi ini tersedia

// Tambahkan event listener untuk semua jam header (Sama seperti sebelumnya)
(function() {
    function attachListeners() {
        const form = document.getElementById('berita-acara-form');
        if (form) {
            form.addEventListener('click', function(e) {
                const header = e.target.closest('.jam-header');
                if (header) {
                    const jam = parseInt(header.getAttribute('data-jam') || header.closest('.jam-section')?.getAttribute('data-jam'));
                    if (jam && jam >= 1 && jam <= 5) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleJamSection(jam);
                    }
                }
            });
        }
    }
    attachListeners();
    setTimeout(attachListeners, 500);
})();
</script>