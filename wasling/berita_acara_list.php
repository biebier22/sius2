<?php
session_start();
include "../config.php";

if (!isset($_SESSION['id_wasling'])) {
    echo "<div class='card'><div style='text-align:center; padding:20px; color:#ef4444;'>Session Wasling tidak ditemukan.</div></div>";
    exit;
}

$id_wasling = $_SESSION['id_wasling'];
$masa = "20252";

// ===============================================
// 1. Cek status Tanda Tangan Wasling
// ===============================================
$ttd_wasling = "";
if (isset($conn2)) {
    $stmt_ttd = $conn2->prepare("SELECT tanda_tangan FROM wasling WHERE id_wasling = ? LIMIT 1");
    if ($stmt_ttd) {
        $stmt_ttd->bind_param("s", $id_wasling);
        $stmt_ttd->execute();
        $result_ttd = $stmt_ttd->get_result();
        if ($row_ttd = $result_ttd->fetch_assoc()) {
            $ttd_wasling = $row_ttd['tanda_tangan'] ?? "";
        }
        $stmt_ttd->close();
    }
}
$is_signed = !empty($ttd_wasling);
// ===============================================

// Ambil daftar ruang hanya jika wasling sudah bertanda tangan
$ruang_list = [];
if ($is_signed && isset($conn2)) {
    $stmt = $conn2->prepare("
        SELECT DISTINCT
            wr.hari,
            wr.no_ruang,
            wr.id_pengawas,
            wr.lokasi,
            pr.nama_pengawas,
            pr.no_wa
        FROM wasling_ruang wr
        LEFT JOIN pengawas_ruang pr
            ON pr.ruang = wr.no_ruang
            AND CAST(pr.hari AS CHAR) = wr.hari
            AND pr.masa = ?
        WHERE wr.id_wasling = ?
        ORDER BY wr.hari ASC, CAST(wr.no_ruang AS UNSIGNED) ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param("ss", $masa, $id_wasling);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $ruang_list[] = $row;
        }
        $stmt->close();
    }
}
?>

<style>
/* ... (Style yang sudah ada tidak diubah) ... */
.berita-acara-list {
    padding: 0;
}

.list-header {
    margin-bottom: 20px;
}

.list-header h2 {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 600;
    color: #0f172a;
}

.list-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.ruang-card {
    background: white;
    border-radius: 16px;
    padding: 18px 20px;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(15,23,42,0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
    border-left: 4px solid transparent;
}

.ruang-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15,23,42,0.12);
}

.ruang-card.hari-1 {
    border-left-color: #0ea5e9;
}

.ruang-card.hari-2 {
    border-left-color: #10b981;
}

.ruang-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.ruang-title {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}

.ruang-badge {
    background: linear-gradient(120deg, #00bcd4, #2979ff);
    color: #fff;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
}

.ruang-info {
    font-size: 13px;
    color: #64748b;
    margin: 4px 0 0;
    line-height: 1.6; /* Tambahkan line-height agar teks tidak terlalu rapat */
}

.ruang-info strong {
    color: #475569;
}

/* Style tambahan untuk link WhatsApp */
.wa-link {
    color: #25D366; /* Warna hijau WhatsApp */
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
    margin-top: 4px; /* Sedikit jarak */
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 12px;
}
</style>

<div class="berita-acara-list">
    <div class="list-header">
        <h2>📋 Berita Acara Serah Terima</h2>
        <p>Pilih ruang untuk mengisi berita acara hasil ujian</p>
    </div>

    <?php if (!$is_signed): ?>

        <div class="card">
            <div class="empty-state" style="color: #ef4444;">
                <div class="empty-state-icon">✍️</div>
                <p style="font-weight: bold; font-size: 16px; color: #ef4444;">
                    AKSES DITOLAK
                </p>
                <p>
                    Anda harus melakukan **Tanda Tangan Wasling** terlebih dahulu untuk dapat mengakses daftar ruangan ini.
                </p>
                <button 
                    onclick="if(typeof loadPage === 'function'){ loadPage('tandatangan.php') } else { window.location.href = 'tandatangan.php'; }" 
                    style="
                        background: #6a4ff7; 
                        color: white; 
                        padding: 12px 25px; 
                        border: none; 
                        border-radius: 10px; 
                        cursor: pointer; 
                        margin-top: 15px; 
                        font-size: 15px;
                        font-weight: bold;
                        box-shadow: 0 4px 8px rgba(106, 79, 247, 0.3);
                    "
                >
                    Tanda Tangan Sekarang
                </button>
            </div>
        </div>

    <?php else: ?>

        <?php if (empty($ruang_list)): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>Belum ada ruang yang ditugaskan untuk wasling ini.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($ruang_list as $ruang): 
                // Ambil dan bersihkan nomor WA
                $no_wa = $ruang['no_wa'] ?? '';
                $wa_link = '';
                
                if (!empty($no_wa)) {
                    // Hapus semua karakter non-digit
                    $clean_wa = preg_replace('/[^0-9]/', '', $no_wa);
                    
                    // Ganti '0' di awal dengan '62' (kode negara Indonesia)
                    if (substr($clean_wa, 0, 1) === '0') {
                        $clean_wa = '62' . substr($clean_wa, 1);
                    }
                    
                    // Buat link wa.me
                    $wa_link = "https://wa.me/{$clean_wa}";
                }
            ?>
                <div class="ruang-card hari-<?php echo htmlspecialchars($ruang['hari']); ?>" 
                    onclick="loadPage('berita_acara.php?ruang=<?php echo htmlspecialchars($ruang['no_ruang']); ?>&hari=<?php echo htmlspecialchars($ruang['hari']); ?>')">
                    <div class="ruang-card-header">
                        <h3 class="ruang-title">Ruang <?php echo htmlspecialchars($ruang['no_ruang']); ?></h3>
                        <span class="ruang-badge">Hari <?php echo htmlspecialchars($ruang['hari']); ?></span>
                    </div>
                    <p class="ruang-info">
                        <strong>Pengawas:</strong> <?php echo htmlspecialchars($ruang['nama_pengawas'] ?? 'N/A'); ?><br>
                        <strong>ID Pengawas:</strong> <?php echo htmlspecialchars($ruang['id_pengawas'] ?? 'N/A'); ?><br> 
                        <strong>Lokasi:</strong> <?php echo htmlspecialchars($ruang['lokasi']); ?><br>
                        
                        <?php if (!empty($wa_link)): ?>
                            <strong>Kontak WA:</strong> 
                            <a href="<?php echo $wa_link; ?>" target="_blank" class="wa-link">
                                <?php echo htmlspecialchars($ruang['no_wa']); ?> (Klik Chat)
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>