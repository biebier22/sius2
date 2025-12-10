<?php
session_start();
include "../config.php";

// ========== VALIDASI SESSION ==========
if (!isset($_SESSION['id_wasling'])) {
    echo "<div class='card'><div class='rekap-empty'>Session Wasling tidak ditemukan.</div></div>";
    exit;
}

$id_wasling = $_SESSION['id_wasling'];
$masa = "20252";

// Fetch Wasling Name and Lokasi_TPU untuk header
$wasling_info = [];
if (isset($conn2)) {
    $q_wasling = mysqli_query($conn2, "SELECT nama_wasling, lokasi_tpu FROM wasling WHERE id_wasling='".mysqli_real_escape_string($conn2, $id_wasling)."' LIMIT 1");
    if ($q_wasling && mysqli_num_rows($q_wasling) > 0) {
        $wasling_info = mysqli_fetch_assoc($q_wasling);
    }
}
$wasling_nama = $wasling_info['nama_wasling'] ?? 'Wasling Tidak Dikenal';
$wasling_lokasi = $wasling_info['lokasi_tpu'] ?? 'Lokasi Ujian Tidak Ditemukan';

if (!isset($conn2)) {
    echo "<div class='card'><div class='rekap-empty'>Koneksi database tidak ditemukan.</div></div>";
    exit;
}

// ========== QUERY REKAP RUANG DARI WASLING_RUANG + BAST CHECK ==========
$rekapData = [];

// Query dengan subquery untuk mengecek keberadaan Berita Acara Serah Terima (BAST)
// ✅ PERBAIKAN: id_wasling diganti dengan lokasi.
$stmt = $conn2->prepare("
SELECT 
    wr.hari,
    wr.no_ruang AS ruang_ke,
    wr.lokasi,

    pr.nama_pengawas,
    pr.jam1, pr.jam2, pr.jam3, pr.jam4, pr.jam5,

    -- BAST cek jam 1 (dikatakan OK hanya jika ada hasil LJU terisi > 0)
    (SELECT 1 FROM berita_acara_serah_terima 
        WHERE lokasi = wr.lokasi           -- <<< PERBAIKAN DI SINI
        AND masa = ? 
        AND hari = wr.hari 
        AND ruang = wr.no_ruang 
        AND jam_ke = 1 
        AND hasil_lju_terisi > 0 
        LIMIT 1) AS bast_jam1_exists,

    -- BAST cek jam 2
    (SELECT 1 FROM berita_acara_serah_terima 
        WHERE lokasi = wr.lokasi           -- <<< PERBAIKAN DI SINI
        AND masa = ? 
        AND hari = wr.hari 
        AND ruang = wr.no_ruang 
        AND jam_ke = 2 
        AND hasil_lju_terisi > 0 
        LIMIT 1) AS bast_jam2_exists,

    -- BAST cek jam 3
    (SELECT 1 FROM berita_acara_serah_terima 
        WHERE lokasi = wr.lokasi           -- <<< PERBAIKAN DI SINI
        AND masa = ? 
        AND hari = wr.hari 
        AND ruang = wr.no_ruang 
        AND jam_ke = 3 
        AND hasil_lju_terisi > 0 
        LIMIT 1) AS bast_jam3_exists,

    -- BAST cek jam 4
    (SELECT 1 FROM berita_acara_serah_terima 
        WHERE lokasi = wr.lokasi           -- <<< PERBAIKAN DI SINI
        AND masa = ? 
        AND hari = wr.hari 
        AND ruang = wr.no_ruang 
        AND jam_ke = 4 
        AND hasil_lju_terisi > 0 
        LIMIT 1) AS bast_jam4_exists,

    -- BAST cek jam 5
    (SELECT 1 FROM berita_acara_serah_terima 
        WHERE lokasi = wr.lokasi           -- <<< PERBAIKAN DI SINI
        AND masa = ? 
        AND hari = wr.hari 
        AND ruang = wr.no_ruang 
        AND jam_ke = 5 
        AND hasil_lju_terisi > 0 
        LIMIT 1) AS bast_jam5_exists

FROM wasling_ruang wr

LEFT JOIN pengawas_ruang pr 
    ON pr.lokasi = wr.lokasi
    AND pr.ruang = wr.no_ruang
    AND pr.hari = wr.hari
    AND pr.masa = ?

WHERE wr.id_wasling = ?

ORDER BY wr.hari ASC, CAST(wr.no_ruang AS UNSIGNED) ASC;

");

if ($stmt) {
    // 7 parameters: 5 x $masa untuk BAST checks, 1 x $masa untuk pengawas_ruang, 1 x $id_wasling
    $stmt->bind_param(
    "sssssss",
    $masa, // bast_jam1 (untuk kolom masa)
    $masa, // bast_jam2 (untuk kolom masa)
    $masa, // bast_jam3 (untuk kolom masa)
    $masa, // bast_jam4 (untuk kolom masa)
    $masa, // bast_jam5 (untuk kolom masa)
    $masa, // pengawas_ruang.masa
    $id_wasling // where wr.id_wasling
);

    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $hari = $row['hari'];
        if (!isset($rekapData[$hari])) {
            $rekapData[$hari] = [];
        }
        // Pastikan tidak ada duplikat ruang dalam hari yang sama
        $ruang_exists = false;
        foreach ($rekapData[$hari] as $existing) {
            if ($existing['ruang_ke'] == $row['ruang_ke']) {
                $ruang_exists = true;
                break;
            }
        }
        if (!$ruang_exists) {
            // Store all fetched data, including BAST existence flags
            $rekapData[$hari][] = $row;
        }
    }
    $stmt->close();
}
?>

<style>
/* REKAP UJIAN STYLES */
.rekap-section {
    margin-bottom: 20px;
}
.rekap-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.rekap-title {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rekap-badge {
    background: linear-gradient(120deg, #00bcd4, #2979ff);
    color: #fff;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.rekap-card {
    background: #fff;
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: 0 8px 24px rgba(15,23,42,0.08);
}
.rekap-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s ease;
}
.rekap-card-header:hover {
    background-color: #f8fafc;
    border-radius: 12px;
    margin: -4px -4px 0;
    padding: 12px 12px 16px;
}
.rekap-card-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}
.rekap-card-chevron {
    font-size: 12px;
    color: #64748b;
    transition: transform 0.3s ease;
    display: inline-block;
    min-width: 16px;
    text-align: center;
}
.rekap-card.expanded .rekap-card-chevron {
    transform: rotate(180deg);
    color: #2979ff;
}
.rekap-card-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease;
    padding-top: 0;
    opacity: 0;
}
.rekap-card.expanded .rekap-card-content {
    max-height: 5000px;
    padding-top: 12px;
    opacity: 1;
}
.rekap-card-title {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}
.rekap-card-subtitle {
    font-size: 12px;
    color: #64748b;
    margin: 2px 0 0;
}
.rekap-row {
    display: grid;
    grid-template-columns: 50px 1fr;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}
.rekap-row:last-child {
    border-bottom: none;
}
.rekap-row-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}
.rekap-row-value {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.rekap-jam {
    background: #eef2ff; /* Default: Light blue/indigo (BAST belum terkonfirmasi/LJU terisi 0) */
    color: #4253ff;      /* Default: Blue/indigo */
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    min-width: 36px;
    text-align: center;
}
/* NEW STYLE: Warna hijau untuk BAST yang sudah diserahkan DENGAN hasil_lju_terisi > 0 */
.rekap-jam.rekap-jam-bast-ok {
    background: #d1fae5; /* Light green */
    color: #059669;      /* Green */
}
.rekap-jam-label {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 2px;
}
.rekap-empty {
    text-align: center;
    padding: 24px;
    color: #94a3b8;
    font-size: 14px;
}
.rekap-summary {
    background: linear-gradient(135deg, #e0f2fe, #e0e7ff);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-around;
    text-align: center;
}
.rekap-summary-item {
    flex: 1;
}
.rekap-summary-value {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.rekap-summary-label {
    font-size: 11px;
    color: #64748b;
    margin: 2px 0 0;
}
</style>

<?php if (!empty($rekapData)): ?>
    <div class="rekap-section">
        <?php
        $totalRuang = 0;
        $totalJam = 0;
        foreach ($rekapData as $hari => $ruangs) {
            $totalRuang += count($ruangs);
            foreach ($ruangs as $r) {
                // Total jam dihitung dari kolom jam1 sampai jam5 di pengawas_ruang
                $totalJam += (intval($r['jam1'] ?? 0) + intval($r['jam2'] ?? 0) + intval($r['jam3'] ?? 0) + intval($r['jam4'] ?? 0) + intval($r['jam5'] ?? 0));
            }
        }
        ?>
        <div class="rekap-summary">
            <div class="rekap-summary-item">
                <p class="rekap-summary-value"><?php echo count($rekapData); ?></p>
                <p class="rekap-summary-label">Hari Ujian</p>
            </div>
            <div class="rekap-summary-item">
                <p class="rekap-summary-value"><?php echo $totalRuang; ?></p>
                <p class="rekap-summary-label">Total Ruang</p>
            </div>
            <div class="rekap-summary-item">
                <p class="rekap-summary-value"><?php echo $totalJam; ?></p>
                <p class="rekap-summary-label">Total Naskah</p>
            </div>
        </div>
        
        <?php foreach ($rekapData as $hari => $ruangs): ?>
            <div class="rekap-card" data-hari="<?php echo htmlspecialchars($hari); ?>">
                <div class="rekap-card-header" onclick="toggleRekapCard(this)">
                    <div class="rekap-card-header-left">
                        <span class="rekap-card-chevron">▼</span>
                        <div>
                            <h3 class="rekap-card-title">Hari Ke-<?php echo htmlspecialchars($hari); ?></h3>
                            <p class="rekap-card-subtitle"><?php echo count($ruangs); ?> ruang ujian</p>
                        </div>
                    </div>
                    <span class="rekap-badge">Hari <?php echo $hari; ?></span>
                </div>
                
                <div class="rekap-card-content">
                    <?php foreach ($ruangs as $r): ?>
                        <div class="rekap-row">
                            <div class="rekap-row-label">Ruang <?php echo htmlspecialchars($r['ruang_ke']); ?></div>
                            <div class="rekap-row-value">
                                <div>
                                    <div class="rekap-jam<?php echo ($r['bast_jam1_exists'] > 0) ? ' rekap-jam-bast-ok' : ''; ?>">
                                        <?php echo htmlspecialchars($r['jam1'] ?? 0); ?>
                                    </div>
                                    <div class="rekap-jam-label">Jam 1</div>
                                </div>
                                <div>
                                    <div class="rekap-jam<?php echo ($r['bast_jam2_exists'] > 0) ? ' rekap-jam-bast-ok' : ''; ?>">
                                        <?php echo htmlspecialchars($r['jam2'] ?? 0); ?>
                                    </div>
                                    <div class="rekap-jam-label">Jam 2</div>
                                </div>
                                <div>
                                    <div class="rekap-jam<?php echo ($r['bast_jam3_exists'] > 0) ? ' rekap-jam-bast-ok' : ''; ?>">
                                        <?php echo htmlspecialchars($r['jam3'] ?? 0); ?>
                                    </div>
                                    <div class="rekap-jam-label">Jam 3</div>
                                </div>
                                <div>
                                    <div class="rekap-jam<?php echo ($r['bast_jam4_exists'] > 0) ? ' rekap-jam-bast-ok' : ''; ?>">
                                        <?php echo htmlspecialchars($r['jam4'] ?? 0); ?>
                                    </div>
                                    <div class="rekap-jam-label">Jam 4</div>
                                </div>
                                <div>
                                    <div class="rekap-jam<?php echo ($r['bast_jam5_exists'] > 0) ? ' rekap-jam-bast-ok' : ''; ?>">
                                        <?php echo htmlspecialchars($r['jam5'] ?? 0); ?>
                                    </div>
                                    <div class="rekap-jam-label">Jam 5</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="rekap-empty">
            📊<br>
            Belum ada ruang yang ditugaskan untuk wasling ini.
        </div>
    </div>
<?php endif; ?>

<script>
function toggleRekapCard(header) {
    const card = header.closest('.rekap-card');
    if (card) {
        card.classList.toggle('expanded');
    }
}
</script>