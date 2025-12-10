<?php
session_start();
include "../config.php";

if (!isset($_SESSION['id_wasling'])) {
    echo "<div class='card'><div style='text-align:center; padding:20px; color:#ef4444;'>Session Wasling tidak ditemukan.</div></div>";
    exit;
}

$id_wasling = $_SESSION['id_wasling'];
$masa = "20252";

// 1. --- MANDATORY TTD CHECK & Wasling Info Fetch ---
$wasling_info = [];
$wasling_signature = null;
$q_wasling = mysqli_query($conn2, "SELECT nama_wasling, lokasi_tpu, tanda_tangan FROM wasling WHERE id_wasling='".mysqli_real_escape_string($conn2, $id_wasling)."' LIMIT 1");

if ($q_wasling && mysqli_num_rows($q_wasling) > 0) {
    $d = mysqli_fetch_assoc($q_wasling);
    $wasling_info = $d;
    $wasling_signature = $d['tanda_tangan'] ?? null;
}

if (empty($wasling_signature)) {
    echo "<style>.card {background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin: 20px;}</style>";
    echo "<div class='card'><div style='text-align:center; padding:30px; color:#ef4444;'>";
    echo "<h2>❌ Tanda Tangan Wasling Belum Terisi</h2>";
    echo "<p>Anda **wajib** mengisi tanda tangan terlebih dahulu di menu <b>Pengaturan -> Tanda Tangan Wasling</b> sebelum dapat memverifikasi Berita Acara.</p>";
    echo "<button type='button' onclick='loadPage(\"berita_acara_list.php\")' style='margin-top:20px; padding:10px 20px; background:#3b82f6; color:white; border:none; border-radius:8px; cursor:pointer;'>Kembali ke Daftar Ruang</button>";
    echo "</div></div>";
    exit;
}
// --- END MANDATORY TTD CHECK ---


$ruang = intval($_GET['ruang'] ?? 0);
$hari = trim($_GET['hari'] ?? '');

if ($ruang <= 0 || empty($hari)) {
    echo "<div class='card'><div style='text-align:center; padding:20px; color:#ef4444;'>Parameter ruang dan hari tidak valid.</div></div>";
    exit;
}

// Validasi bahwa wasling memiliki akses ke ruang ini & ambil info dasar
$stmt_check = $conn2->prepare("
    SELECT wr.id_pengawas, wr.lokasi, pr.nama_pengawas
    FROM wasling_ruang wr
    LEFT JOIN pengawas_ruang pr
        ON pr.id_pengawas = wr.id_pengawas
        AND CAST(pr.hari AS CHAR) = wr.hari
        AND pr.ruang = wr.no_ruang
        AND pr.masa = ?
    WHERE wr.id_wasling = ? AND wr.no_ruang = ? AND wr.hari = ?
    LIMIT 1
");

$pengawas_info = null;
if ($stmt_check) {
    $stmt_check->bind_param("ssis", $masa, $id_wasling, $ruang, $hari);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($row = $result_check->fetch_assoc()) {
        $pengawas_info = $row;
    }
    $stmt_check->close();
}

if (!$pengawas_info) {
    echo "<div class='card'><div style='text-align:center; padding:20px; color:#ef4444;'>Anda tidak memiliki akses ke ruang ini.</div></div>";
    exit;
}

// Ambil data bahan ujian per jam (logika tetap)
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

// Jika nama_pengawas masih NULL, coba ambil langsung dari pengawas_ruang (logika tetap)
if (empty($pengawas_info['nama_pengawas']) || $pengawas_info['nama_pengawas'] == 'N/A') {
    $stmt_pengawas = $conn2->prepare("
        SELECT nama_pengawas
        FROM pengawas_ruang
        WHERE ruang = ? AND CAST(hari AS CHAR) = ? AND masa = ?
        LIMIT 1
    ");
    
    if ($stmt_pengawas) {
        $hari_int = intval($hari);
        $stmt_pengawas->bind_param("iis", $ruang, $hari_int, $masa);
        $stmt_pengawas->execute();
        $result_pengawas = $stmt_pengawas->get_result();
        if ($row = $result_pengawas->fetch_assoc()) {
            $pengawas_info['nama_pengawas'] = $row['nama_pengawas'];
        }
        $stmt_pengawas->close();
    }
}

// Ambil data existing per jam (BAST yang sudah diisi Pengawas Ruang)
$existing_data_per_jam = [];
$stmt_existing = $conn2->prepare("
    SELECT * FROM berita_acara_serah_terima
    WHERE lokasi = ? AND hari = ? AND ruang = ? AND masa = ?
    ORDER BY jam_ke ASC
");

if ($stmt_existing) {
    $stmt_existing->bind_param("sisi", $pengawas_info['lokasi'], $hari, $ruang, $masa);
    $stmt_existing->execute();
    $result_existing = $stmt_existing->get_result();
    while ($row = $result_existing->fetch_assoc()) {
        $existing_data_per_jam[$row['jam_ke']] = $row;
    }
    $stmt_existing->close();
}

// Cek apakah minimal ada 1 record jam yang sudah diverifikasi Wasling
$is_room_verified = false;
$verified_data = null;
foreach ($existing_data_per_jam as $data) {
    if (!empty($data['ttd_wasling'])) {
        $is_room_verified = true;
        $verified_data = $data;
        break; // Cukup satu jam terverifikasi, maka seluruh ruang dianggap sudah
    }
}

// Flag untuk memastikan tombol verifikasi hanya muncul sekali
$verification_button_rendered = false;

// Semua section dimulai collapsed (tertutup)
?>

<style>
/* ... (Gaya CSS tetap sama) ... */
.berita-acara-form { padding: 0; }
.form-header { margin-bottom: 20px; }
.form-header h2 { margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #0f172a; }
.form-header p { margin: 0; font-size: 13px; color: #64748b; }
.info-card { background: linear-gradient(135deg, #e0f2fe, #e0e7ff); border-radius: 12px; padding: 16px; margin-bottom: 20px; }
.info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
.info-label { color: #64748b; font-weight: 500; }
.info-value { color: #0f172a; font-weight: 600; }
.jam-section { background: white; border-radius: 16px; padding: 0; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(15,23,42,0.08); border-left: 4px solid #2979ff; overflow: hidden; }
.jam-section.jam-1 { border-left-color: #0ea5e9; }
.jam-section.jam-2 { border-left-color: #ef4444; }
.jam-section.jam-3 { border-left-color: #f59e0b; }
.jam-section.jam-4 { border-left-color: #10b981; }
.jam-section.jam-5 { border-left-color: #3b82f6; }
.jam-section.pending { border-left-color: #f59e0b; }
.jam-section.verified { border-left-color: #10b981; }
.jam-header { padding: 16px 20px; cursor: pointer; user-select: none; display: flex; align-items: center; justify-content: space-between; transition: background-color 0.2s; border-bottom: 1px solid #e2e8f0; }
.jam-header:hover { background-color: #f8fafc; }
.jam-title { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
.jam-chevron { font-size: 14px; color: #64748b; transition: transform 0.3s ease; display: inline-block; }
.jam-content { padding: 20px; max-height: 10000px; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease, opacity 0.3s ease; opacity: 1; }
.jam-section.collapsed .jam-content { max-height: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important; opacity: 0; overflow: hidden; }
.jam-badge { background: linear-gradient(120deg, #00bcd4, #2979ff); color: #fff; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; }
.bahan-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 13px; background: #f8fafc; border-radius: 10px; overflow: hidden; }
.bahan-table th { background: #e2e8f0; color: #475569; font-weight: 600; padding: 10px 8px; text-align: center; font-size: 12px; border-right: 1px solid #cbd5e1; }
.bahan-table td { padding: 10px 8px; text-align: center; color: #64748b; font-weight: 500; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px; }
.form-input { width: 100%; padding: 12px 14px; border: 1.5px solid #cbd5f5; border-radius: 12px; font-size: 14px; box-sizing: border-box; transition: border-color 0.2s; font-family: inherit; }
.form-input[readonly] { background: #f8fafc; color: #475569; cursor: default; border-color: #e2e8f0; }
.signature-section { margin-top: 20px; }
.btn { flex: 1; padding: 12px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
.btn:active { transform: scale(0.98); }
.btn-save { background: linear-gradient(120deg, #00bcd4, #2979ff); color: white; width: 100%; margin-top: 20px; }
.btn-save[disabled] { background: #94a3b8; cursor: not-allowed; }
.btn-cancel { background: #f1f5f9; color: #475569; width: 100%; margin-top: 10px; }
.message { padding: 12px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; font-weight: 500; display: none; }
.message.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.signature-saved { margin-top: 12px; padding: 12px; border-radius: 8px; }
.signature-saved.wasrung { background: #d1fae5; }
.signature-saved.wasrung p { margin: 0 0 8px; font-size: 12px; color: #065f46; }
.signature-saved.wasling-warning { background: #fef3c7; }
.signature-saved.wasling-warning p { margin: 0 0 8px; font-size: 12px; color: #a16207; }
.signature-saved img { max-width: 100%; border-radius: 8px; }
</style>

<div class="berita-acara-form">
    <div class="form-header">
        <h2>✅ Verifikasi Berita Acara Serah Terima</h2>
        <p>Verifikasi kesesuaian hasil ujian yang diserahkan pengawas ruang dengan data yang diinput.</p>
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
            <span class="info-label">Lokasi:</span>
            <span class="info-value"><?php echo htmlspecialchars($pengawas_info['lokasi']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Wasling:</span>
            <span class="info-value"><?php echo htmlspecialchars($wasling_info['nama_wasling'] ?? 'N/A'); ?></span>
        </div>
    </div>

    <div id="form-message" class="message"></div>

    <form id="berita-acara-form">
        <input type="hidden" name="ruang" value="<?php echo htmlspecialchars($ruang); ?>">
        <input type="hidden" name="hari" value="<?php echo htmlspecialchars($hari); ?>">
        <input type="hidden" name="id_pengawas" value="<?php echo htmlspecialchars($pengawas_info['id_pengawas']); ?>">
        <input type="hidden" name="id_wasling" value="<?php echo htmlspecialchars($id_wasling); ?>">
        <input type="hidden" name="lokasi" value="<?php echo htmlspecialchars($pengawas_info['lokasi']); ?>">


        <?php for ($jam = 1; $jam <= 5; $jam++): 
            $existing = $existing_data_per_jam[$jam] ?? null;
            $bahan_naskah = $bahan_per_jam[$jam] ?? 0;
            
            // Logika Status
            $status_text = '';
            $jam_status_class = '';
            $is_verified = ($existing && !empty($existing['ttd_wasling']));

            if ($existing) {
                if ($is_verified) {
                    $status_text = '<span style="font-size: 12px; color: #059669; margin-left: 8px;">✓ Terverifikasi</span>';
                    $jam_status_class = 'verified'; 
                } else {
                    $status_text = '<span style="font-size: 12px; color: #f59e0b; margin-left: 8px;">Silahkan Cek Fisik</span>';
                    $jam_status_class = 'pending'; 
                }
            } else {
                $status_text = '<span style="font-size: 12px; color: #64748b; margin-left: 8px;">Data Wasrung Belum Ada</span>';
                $jam_status_class = '';
            }
            
            // Semua section dimulai collapsed (tertutup)
            $is_collapsed = 'collapsed';
        ?>
        <div class="jam-section jam-<?php echo $jam; ?> <?php echo $jam_status_class; ?> <?php echo $is_collapsed; ?>" data-jam="<?php echo $jam; ?>" id="jam-section-<?php echo $jam; ?>">
            
            <div class="jam-header" data-jam="<?php echo $jam; ?>">
                <h3 class="jam-title">
                    <span class="jam-badge">Jam <?php echo $jam; ?></span>
                    <?php echo $status_text; ?>
                </h3>
                <span class="jam-chevron" id="jam-chevron-<?php echo $jam; ?>">▶</span>
            </div>
            
            <div class="jam-content" id="jam-content-<?php echo $jam; ?>">
                
                <?php if ($existing): ?>
                    <div style="margin-bottom: 20px;">
                        <h4 style="font-size: 14px; font-weight: 600; color: #475569; margin: 0 0 12px;">📦 Bahan Ujian yang Diserahkan (Jadwal)</h4>
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
                        <h4 style="font-size: 14px; font-weight: 600; color: #475569; margin: 0 0 12px;">📥 Hasil Ujian yang Diterima (Input Wasrung)</h4>

                        <div class="form-group">
                            <label class="form-label">Naskah (eksp. per amplop) *</label>
                            <input type="number" 
                                id="hasil_naskah_<?php echo $jam; ?>"
                                name="hasil_naskah[<?php echo $jam; ?>]" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($existing['hasil_naskah'] ?? $bahan_naskah); ?>" 
                                min="0" 
                                required 
                                readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">LJU Terisi *</label>
                            <input type="number" 
                                id="hasil_lju_terisi_<?php echo $jam; ?>"
                                name="hasil_lju_terisi[<?php echo $jam; ?>]" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($existing['hasil_lju_terisi'] ?? '0'); ?>" 
                                min="0" 
                                required 
                                readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label">LJU Kosong (Otomatis)</label>
                            <input type="number" 
                                id="hasil_lju_kosong_<?php echo $jam; ?>"
                                name="hasil_lju_kosong[<?php echo $jam; ?>]" 
                                class="form-input"
                                value="<?php echo htmlspecialchars($existing['hasil_lju_kosong'] ?? '0'); ?>" 
                                min="0" 
                                required 
                                readonly>
                        </div>
                    </div>
                    
                    <!-- <div style="margin-bottom: 20px;">
                        <h4 style="font-size: 14px; font-weight: 600; color: #475569; margin: 0 0 12px;">✍️ Status Pengisian Wasrung</h4>
                        <div class="signature-saved wasrung">
                            <p style="margin: 0;">✓ Data hasil ujian **telah diinput** oleh Pengawas Ruang.</p>
                            </div>
                    </div> -->
                    <?php 
                    // Tampilkan tombol Verifikasi hanya di bagian yang belum terverifikasi
                    // DAN hanya jika tombol belum pernah ditampilkan (untuk memastikan hanya 1 tombol)
                    if (!$is_verified && !$verification_button_rendered): 
                        $verification_button_rendered = true; // Set flag setelah dirender
                    ?>
                        <hr style="margin: 30px 0; border: 0; border-top: 2px dashed #e2e8f0;">
                        <div class="verification-section">
                            <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 12px;">✅ Verifikasi Akhir Wasling Ruang <?php echo htmlspecialchars($ruang); ?></h3>
                            
                            <div class="signature-saved wasling-warning">
                                <p style="font-weight: 600;">⚠️ Verifikasi ini akan mengunci data BAST untuk **SEMUA JAM** di ruang ini.</p>
                                <p style="font-weight: 500; margin: 0;">Pastikan Anda sudah mengkroscek TANDA TANGAN pada LJU dan Kesesuaian JUMLAH yang diterima.</p>
                            </div>
                            
                            <button 
                                type="button" 
                                class="btn btn-save" 
                                onclick="verifyData(event)"
                                style="margin-top: 20px;">
                                💾 Verifikasi Data & Simpan
                            </button>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div style="text-align:center; padding: 30px; color:#64748b; font-size: 14px; background: #f8fafc; border-radius: 10px;">
                        Data Pengawas Ruang (Wasrung) belum tersedia untuk Jam <?php echo $jam; ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endfor; ?>
        
        <?php 
        // Tampilkan kotak status terverifikasi jika sudah diverifikasi dan tombol belum dirender
        if ($is_room_verified && !$verification_button_rendered): 
        ?>
            <hr style="margin: 30px 0; border: 0; border-top: 2px dashed #e2e8f0;">
            <div class="verification-section">
                <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 12px;">✅ Ruang Sudah Diverifikasi</h3>
                <div class="signature-saved wasrung">
                    <p style="color: #065f46;">✓ Berita Acara sudah **Terverifikasi** oleh Wasling.</p>
                    <?php 
                    // Tanda tangan Wasling yang sudah tersimpan (Base64 dari kolom tanda_tangan tabel wasling)
                    if (!empty($wasling_signature)): 
                    ?>
                        <img src="data:image/png;base64,<?php echo htmlspecialchars($wasling_signature); ?>" alt="Tanda Tangan Wasling">
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-save" disabled style="background:#059669; margin-top: 20px;">
                    Terverifikasi
                </button>
            </div>
        <?php endif; ?>

        <button type="button" class="btn btn-cancel" onclick="loadPage('berita_acara_list.php')" style="width: 100%; margin-top: 10px;">❌ Kembali ke Daftar Ruang</button>
    </form>
</div>

<script>
function toggleJamSection(jam) {
    try {
        const section = document.getElementById('jam-section-' + jam);
        const chevron = document.getElementById('jam-chevron-' + jam);
        
        if (!section) return false;
        
        const isCollapsed = section.classList.contains('collapsed');
        
        // Tutup semua section, lalu buka yang diklik
        document.querySelectorAll('.jam-section').forEach(sec => {
            if (sec.id !== section.id) {
                 sec.classList.add('collapsed');
                 const chev = sec.querySelector('.jam-chevron');
                 if (chev) chev.textContent = '▶';
            }
        });
        
        if (isCollapsed) {
            section.classList.remove('collapsed');
            if (chevron) chevron.textContent = '▼';
        } else {
            section.classList.add('collapsed');
            if (chevron) chevron.textContent = '▶';
        }
        return true;
    } catch (error) {
        console.error('Error in toggleJamSection:', error);
        return false;
    }
}

function showMessage(message, type) {
    const messageDiv = document.getElementById('form-message');
    messageDiv.textContent = message;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'block';
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

// Fungsi Simpan Verifikasi Wasling Otomatis (verifyData)
async function verifyData(event) {
    // Mencegah submit form
    if (event) event.preventDefault();
    
    const form = document.getElementById('berita-acara-form');
    const formData = new FormData();
    
    // Data dasar
    formData.append('ruang', form.querySelector('input[name="ruang"]').value);
    formData.append('hari', form.querySelector('input[name="hari"]').value);
    formData.append('lokasi', form.querySelector('input[name="lokasi"]').value);
    formData.append('id_wasling', form.querySelector('input[name="id_wasling"]').value);
    formData.append('action', 'verifikasi_data_wasling'); 
    
    // Dapatkan tombol yang diklik
    const btn = form.querySelector('.verification-section .btn-save');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Memverifikasi...';
    }

    try {
        // Asumsi endpoint server sama (save_berita_acara.php)
        const response = await fetch('save_berita_acara.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'ok') {
            showMessage(result.message, 'success');
            // Reload halaman setelah 1.5 detik untuk refresh data (status Terverifikasi)
            setTimeout(() => {
                const ruang = form.querySelector('input[name="ruang"]').value;
                const hari = form.querySelector('input[name="hari"]').value;
                // Memuat ulang halaman saat ini (pastikan fungsi loadPage ada)
                if (typeof loadPage === 'function') {
                    loadPage(`berita_acara.php?ruang=${ruang}&hari=${hari}`);
                } else {
                    window.location.reload();
                }
            }, 1500);
        } else {
            showMessage(result.message, 'error');
        }
    } catch (error) {
        showMessage('Terjadi kesalahan koneksi saat menyimpan: ' + error, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = `💾 Verifikasi Data & Simpan`;
        }
    }
}


// Pastikan fungsi tersedia di window scope
window.toggleJamSection = toggleJamSection;
window.verifyData = verifyData;
window.showMessage = showMessage;


// Tambahkan event delegation untuk toggle
(function() {
    const form = document.getElementById('berita-acara-form');
    if (form) {
        form.addEventListener('click', function(e) {
            const header = e.target.closest('.jam-header');
            // Pastikan yang diklik adalah header dan bukan elemen di dalamnya yang punya fungsi lain
            if (header && !e.target.closest('a') && !e.target.closest('button') && !e.target.closest('input')) {
                const jam = parseInt(header.getAttribute('data-jam') || header.closest('.jam-section')?.getAttribute('data-jam'));
                if (jam && jam >= 1 && jam <= 5) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Panggil fungsi toggle
                    toggleJamSection(jam);
                }
            }
        });
    }
})();
</script>