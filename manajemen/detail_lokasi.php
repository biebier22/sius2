<?php
session_start();
include "../config.php";

// ========== VALIDASI SESSION ==========
if (!isset($_SESSION['manajemen_unlock']) && !isset($_SESSION['pengawas_unlock'])) {
    echo "<div style='padding: 20px; text-align: center; color: #c33;'>Session tidak ditemukan.</div>";
    exit;
}

$lokasi = $_GET['lokasi'] ?? '';
$masa = '20252';

if (empty($lokasi)) {
    echo "<div style='padding: 20px; text-align: center; color: #c33;'>Lokasi tidak ditemukan.</div>";
    exit;
}

if (!isset($conn2)) {
    echo "<div style='padding: 20px; text-align: center; color: #c33;'>Koneksi database tidak ditemukan.</div>";
    exit;
}

// ========== QUERY REKAP RUANG PER LOKASI ==========
$rekapData = [];

// Query untuk mendapatkan ruangan per hari dari rekap_ujian yang di-join dengan e_lokasi_uas
$stmt = $conn2->prepare("
    SELECT DISTINCT
        r.hari_ke AS hari,
        r.ruang_ke,
        COALESCE(r.jam1, 0) AS jam1,
        COALESCE(r.jam2, 0) AS jam2,
        COALESCE(r.jam3, 0) AS jam3,
        COALESCE(r.jam4, 0) AS jam4,
        COALESCE(r.jam5, 0) AS jam5,
        COALESCE(r.total, 0) AS total,
        e.ruang_awal,
        e.ruang_akhir
    FROM rekap_ujian r
    LEFT JOIN e_lokasi_uas e
        ON r.kode_tpu = e.kode_tpu
        AND e.masa = ?
        AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED) AND CAST(e.ruang_akhir AS UNSIGNED)
        AND CAST(r.hari_ke AS CHAR) = e.hari
    WHERE r.masa = ?
        AND e.lokasi = ?
        AND r.hari_ke IS NOT NULL
        AND r.ruang_ke IS NOT NULL
    ORDER BY CAST(r.hari_ke AS UNSIGNED) ASC, CAST(r.ruang_ke AS UNSIGNED) ASC
");

if ($stmt) {
    $stmt->bind_param("sss", $masa, $masa, $lokasi);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $hari = $row['hari'];
        $ruang_ke = $row['ruang_ke'];
        
        if (!isset($rekapData[$hari])) {
            $rekapData[$hari] = [
                'ruang_awal' => $row['ruang_awal'] ?? 0,
                'ruang_akhir' => $row['ruang_akhir'] ?? 0,
                'ruangs' => []
            ];
        }
        
        // Update ruang_awal dan ruang_akhir jika lebih kecil/besar
        if (!empty($row['ruang_awal']) && ($rekapData[$hari]['ruang_awal'] == 0 || $row['ruang_awal'] < $rekapData[$hari]['ruang_awal'])) {
            $rekapData[$hari]['ruang_awal'] = $row['ruang_awal'];
        }
        if (!empty($row['ruang_akhir']) && $row['ruang_akhir'] > $rekapData[$hari]['ruang_akhir']) {
            $rekapData[$hari]['ruang_akhir'] = $row['ruang_akhir'];
        }
        
        // Query data berita_acara untuk mendapatkan LJU dan BJU per jam
        $berita_acara_data = [];
        $stmt_ba = $conn2->prepare("
            SELECT 
                jam_ke,
                COALESCE(hasil_lju_terisi, 0) AS lju,
                COALESCE(hasil_bju_terisi, 0) AS bju,
                COALESCE(hasil_naskah_eksp, 0) AS naskah
            FROM berita_acara_serah_terima
            WHERE masa = ? 
                AND hari = ? 
                AND ruang = ?
                AND lokasi = ? /* <--- PENAMBAHAN FILTER LOKASI */
            ORDER BY jam_ke ASC
        ");
        
        if ($stmt_ba) {
            $hari_str = (string)$hari;
            /* Binding parameter diubah dari "ssi" menjadi "ssis" untuk $masa, $hari_str, $ruang_ke, $lokasi */
            $stmt_ba->bind_param("ssis", $masa, $hari_str, $ruang_ke, $lokasi);
            $stmt_ba->execute();
            $result_ba = $stmt_ba->get_result();
            
            while ($row_ba = $result_ba->fetch_assoc()) {
                $berita_acara_data[$row_ba['jam_ke']] = [
                    'lju' => $row_ba['lju'],
                    'bju' => $row_ba['bju'],
                    'naskah' => $row_ba['naskah']
                ];
            }
            $stmt_ba->close();
        }
        
        // Tambahkan ruang jika ada
        if (!empty($ruang_ke)) {
            $ruang_exists = false;
            foreach ($rekapData[$hari]['ruangs'] as $existing) {
                if ($existing['ruang_ke'] == $ruang_ke) {
                    $ruang_exists = true;
                    break;
                }
            }
            if (!$ruang_exists) {
                // Data per jam dengan LJU, BJU, Naskah
                $jam_data = [];
                for ($j = 1; $j <= 5; $j++) {
                    $jam_key = 'jam' . $j;
                    $naskah = $row[$jam_key] ?? 0;
                    
                    // Cek apakah record BAST ada untuk jam ini
                    $is_bast_present = isset($berita_acara_data[$j]); // <-- FLAG BARU
                    
                    if ($is_bast_present) {
                        $jam_data[$j] = [
                            'lju' => $berita_acara_data[$j]['lju'],
                            'bju' => $berita_acara_data[$j]['bju'],
                            'naskah' => $berita_acara_data[$j]['naskah'] > 0 ? $berita_acara_data[$j]['naskah'] : $naskah,
                            'bast_present' => true // <-- Set flag menjadi TRUE
                        ];
                    } else {
                        $jam_data[$j] = [
                            'lju' => 0,
                            'bju' => 0,
                            'naskah' => $naskah,
                            'bast_present' => false // <-- Set flag menjadi FALSE
                        ];
                    }
                }
                
                // Hitung total
                $total_lju = 0;
                $total_bju = 0;
                $total_naskah = 0;
                foreach ($jam_data as $jd) {
                    $total_lju += $jd['lju'];
                    $total_bju += $jd['bju'];
                    $total_naskah += $jd['naskah'];
                }
                
                $rekapData[$hari]['ruangs'][] = [
                    'ruang_ke' => $ruang_ke,
                    'jam_data' => $jam_data,
                    'total' => [
                        'lju' => $total_lju,
                        'bju' => $total_bju,
                        'naskah' => $total_naskah
                    ],
                    'jumlah_amplop' => 1 // Jumlah amplop = total naskah
                ];
            }
        }
    }
    $stmt->close();
}
?>

<style>
/* ... CSS yang sudah ada ... */
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
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.rekap-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 12px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.rekap-card-header {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 14px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background 0.2s;
}
.rekap-card-header:hover {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
}
.rekap-card-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.rekap-card-chevron {
    font-size: 12px;
    color: #64748b;
    transition: transform 0.3s;
}
.rekap-card.collapsed .rekap-card-chevron {
    transform: rotate(-90deg);
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
.rekap-card-content {
    padding: 16px;
    max-height: 10000px;
    overflow: hidden;
    transition: max-height 0.4s ease, padding 0.3s ease;
}
.rekap-card.collapsed .rekap-card-content {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
}
.rekap-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 8px;
}
.rekap-row:last-child {
    margin-bottom: 0;
}
.rekap-row-label {
    font-weight: 600;
    color: #0f172a;
    font-size: 14px;
}
.rekap-row-value {
    display: flex;
    gap: 12px;
}
.rekap-jam {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 6px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
    min-width: 36px;
    text-align: center;
}
.rekap-jam-label {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 2px;
    text-align: center;
}
.rekap-empty {
    text-align: center;
    padding: 24px;
    color: #94a3b8;
    font-size: 14px;
}
.rekap-table-container {
    overflow-x: auto;
    margin: -16px;
    padding: 16px;
}
.rekap-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    background: white;
    border: 1px solid #e2e8f0;
}
.rekap-table thead {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}
.rekap-table th {
    padding: 8px 6px;
    text-align: center;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 11px;
}
.rekap-table th[rowspan] {
    vertical-align: middle;
}
.rekap-table tbody tr {
    border-bottom: 1px solid #e2e8f0;
}
.rekap-table tbody tr:hover {
    background: #f8fafc;
}
.rekap-table tbody tr:nth-child(even) {
    background: #fafbfc;
}
.rekap-table tbody tr:nth-child(even):hover {
    background: #f1f5f9;
}
.rekap-table td {
    padding: 8px 6px;
    text-align: center;
    border: 1px solid #e2e8f0;
    font-size: 11px;
}
.rekap-table td:first-child {
    font-weight: 600;
    color: #475569;
}
.rekap-table td:nth-child(2) {
    font-weight: 600;
    color: #0f172a;
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
/* Warna latar khusus untuk kolom LJU */
.rekap-table td.lju-col {
    background: rgba(102, 126, 234, 0.2); /* biru muda transparan */
    font-weight: 600;
    color: #1e40af; /* biru gelap teks */
}

/* ============================================= */
/* CSS BARU UNTUK SEL YANG SUDAH TERISI BAST */
/* ============================================= */
.rekap-table td.bast-data-present {
    background-color: #d1fae5; /* Light Green for BAST data */
    color: #065f46; /* Dark Green Text */
    font-weight: 700;
    /* Optional: Add a subtle border to highlight the group */
    border-left: 2px solid #34d399; 
    border-right: 2px solid #34d399;
}
.rekap-table td.bast-data-present:nth-child(3n) { /* Kolom Naskah (3, 6, 9, 12, 15) */
    border-right: 1px solid #34d399; /* Border kanan lebih tipis untuk Naskah */
}
/* Hapus border kiri untuk kolom LJU pertama */
.rekap-table td.bast-data-present:nth-child(3n+1) { /* Kolom LJU (1, 4, 7, 10, 13) */
    border-left: 1px solid #34d399;
}

/* CSS BARU untuk Tombol Cetak */
.btn-cetak-rekap {
    background: #10b981; /* Hijau */
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    transition: background 0.2s;
    white-space: nowrap; /* Pastikan teks tidak patah */
    line-height: 1; /* Rata tengah vertikal */
}

.btn-cetak-rekap:hover {
    background: #059669;
}

</style>

<div style="margin-bottom: 20px;">
    <button id="btn-back-lokasi" style="background: #667eea; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-bottom: 15px;">
        <i class="fa-solid fa-arrow-left"></i> Kembali
    </button>
    <h2 style="margin: 0; color: #333; font-size: 20px;">
        <i class="fa-solid fa-school"></i> <?php echo htmlspecialchars($lokasi); ?>
    </h2>
</div>

<?php if (!empty($rekapData)): ?>
    <div class="rekap-section">
        <?php
        $totalRuang = 0;
        $totalNaskah = 0;
        foreach ($rekapData as $hari => $data) {
            $totalRuang += count($data['ruangs']);
            foreach ($data['ruangs'] as $r) {
                $totalNaskah += intval($r['total']['naskah'] ?? 0);
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
                <p class="rekap-summary-value"><?php echo $totalNaskah; ?></p>
                <p class="rekap-summary-label">Total Naskah</p>
            </div>
        </div>
        
        <?php foreach ($rekapData as $hari => $data): ?>
            <div class="rekap-card" data-hari="<?php echo htmlspecialchars($hari); ?>">
                <div class="rekap-card-header" onclick="toggleRekapCard(this)">
                    <div class="rekap-card-header-left">
                        <span class="rekap-card-chevron">▼</span>
                        <div>
                            <h3 class="rekap-card-title">Hari Ke-<?php echo htmlspecialchars($hari); ?></h3>
                            <p class="rekap-card-subtitle"><?php echo count($data['ruangs']); ?> ruang ujian (R<?php echo $data['ruang_awal']; ?>-<?php echo $data['ruang_akhir']; ?>)</p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button class="btn-cetak-rekap" data-hari="<?php echo htmlspecialchars($hari); ?>" data-lokasi="<?php echo htmlspecialchars($lokasi); ?>">
                            <i class="fa-solid fa-print"></i> Cetak
                        </button>
                    <span class="rekap-badge">H<?php echo $hari; ?></span>
                </div>
                </div>
                
                <div class="rekap-card-content">
                    <?php if (!empty($data['ruangs'])): ?>
                        <div class="rekap-table-container">
                            <table class="rekap-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2">No</th>
                                        <th rowspan="2">No Ruang Ujian</th>
                                        <th colspan="3">Jam 1</th>
                                        <th colspan="3">Jam 2</th>
                                        <th colspan="3">Jam 3</th>
                                        <th colspan="3">Jam 4</th>
                                        <th colspan="3">Jam 5</th>
                                        <th colspan="3">Total</th>
                                        <th rowspan="2">Jumlah Amplop</th>
                                    </tr>
                                    <tr>
                                        <?php for ($j = 1; $j <= 5; $j++): ?>
                                            <th>LJU</th>
                                            <th>BJU</th>
                                            <th>Naskah</th>
                                        <?php endfor; ?>
                                        <th>LJU</th>
                                        <th>BJU</th>
                                        <th>Naskah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($data['ruangs'] as $r): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($r['ruang_ke']); ?></td>
                                            <?php for ($j = 1; $j <= 5; $j++): ?>
                                                <?php $class_status = ($r['jam_data'][$j]['bast_present'] ?? false) ? 'bast-data-present' : ''; ?>
                                                <td class="<?php echo $class_status; ?>"><?php echo htmlspecialchars($r['jam_data'][$j]['lju'] ?? 0); ?></td>
                                                <td class="<?php echo $class_status; ?>"><?php echo htmlspecialchars($r['jam_data'][$j]['bju'] ?? 0); ?></td>
                                                <td class="<?php echo $class_status; ?>"><?php echo htmlspecialchars($r['jam_data'][$j]['naskah'] ?? 0); ?></td>
                                            <?php endfor; ?>
                                            <td><?php echo htmlspecialchars($r['total']['lju'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($r['total']['bju'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($r['total']['naskah'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($r['jumlah_amplop'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="rekap-empty">Tidak ada data ruang untuk hari ini</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="rekap-empty">Tidak ada data untuk lokasi ini</div>
<?php endif; ?>

<script>
function toggleRekapCard(header) {
    const card = header.closest('.rekap-card');
    card.classList.toggle('collapsed');
}
window.toggleRekapCard = toggleRekapCard;

// Event listener untuk tombol kembali
(function() {
    function setupBackButton() {
        const backButton = document.getElementById('btn-back-lokasi');
        if (backButton) {
            // Hapus event listener lama jika ada
            const newButton = backButton.cloneNode(true);
            backButton.parentNode.replaceChild(newButton, backButton);
            
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                // Coba akses fungsi loadLokasiList yang seharusnya ada di manajemen_home.php
                if (typeof window.loadLokasiList === 'function') {
                    window.loadLokasiList();
                } else if (window.top && typeof window.top.loadLokasiList === 'function') {
                    window.top.loadLokasiList();
                } else {
                    // Fallback: reload halaman
                    if (window.top && window.top.location) {
                        window.top.location.reload();
                    } else {
                        location.reload();
                    }
                }
            });
        } else {
            // Jika button belum ada, coba lagi setelah delay
            setTimeout(setupBackButton, 100);
        }
    }
    
    // Coba setup segera, atau tunggu DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupBackButton);
    } else {
        // Tunggu sedikit untuk memastikan DOM sudah siap
        setTimeout(setupBackButton, 50);
    }
})();


// ========== KODE BARU UNTUK TOMBOL CETAK ==========
(function() {
    function setupCetakButton() {
        // Pilih semua tombol dengan class .btn-cetak-rekap
        const printButtons = document.querySelectorAll('.btn-cetak-rekap');
        
        printButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault(); 
                
                // Ambil data dari attribute tombol
                const hari = this.getAttribute('data-hari');
                const lokasi = this.getAttribute('data-lokasi');

                if (hari && lokasi) {
                    // Buat URL untuk cetak_rekap_ruang.php
                    // Gunakan encodeURIComponent untuk memastikan nilai lokasi ditangani dengan benar
                    const url = 'cetak_rekap_ruang.php?lokasi=' + encodeURIComponent(lokasi) + '&hari=' + encodeURIComponent(hari);
                    
                    // Buka di tab/jendela baru
                    window.open(url, '_blank');
                } else {
                    console.error('Data hari atau lokasi tidak ditemukan pada tombol cetak.');
                    alert('Gagal mencetak: Data Hari atau Lokasi tidak lengkap.');
                }
            });
        });
    }

    // Panggil setupCetakButton setelah DOM dimuat
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupCetakButton);
    } else {
        setupCetakButton();
    }
})();
// ===============================================

</script>