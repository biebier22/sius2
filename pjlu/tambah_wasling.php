<?php
session_start();
include "../config.php";

if (!isset($_SESSION['pjtu_unlock'])) {
    echo "Akses ditolak";
    exit;
}

if (!isset($conn2) || $conn2->connect_error) {
    echo "Koneksi database error: " . $conn2->connect_error;
    exit;
}

// Ambil lokasi dari session
$lokasi_tpu = $_SESSION['lokasi'] ?? 'Lokasi Default/Tidak Diketahui';

// =============================
//     GENERATE ID WASLING OTOMATIS
// =============================
function generateIdWasling($conn2) {
    $q = $conn2->query("SELECT id_wasling FROM wasling ORDER BY id_wasling DESC LIMIT 1");

    if ($q->num_rows == 0) {
        return "WAS001";
    }

    $d = $q->fetch_assoc();
    $last = intval(substr($d['id_wasling'], 3)); 
    $new  = $last + 1;

    return "WAS" . str_pad($new, 3, "0", STR_PAD_LEFT);
}

// =============================
//      TAMBAH WASLING (MENGGUNAKAN PREPARED STATEMENTS)
// =============================
if (isset($_POST['add_wasling'])) {
    $nama = trim($_POST['nama']);
    $nohp = trim($_POST['nohp']);
    $lokasi = trim($_POST['lokasi_hidden']); 
    
    // 1. Cek Nama Duplikat di Lokasi yang sama
    $stmt_check = $conn2->prepare("SELECT id FROM wasling WHERE nama_wasling = ? AND lokasi_tpu = ? LIMIT 1");
    $stmt_check->bind_param("ss", $nama, $lokasi);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        echo "Gagal: Nama Wasling \"$nama\" sudah terdaftar di lokasi ini.";
        exit;
    }
    $stmt_check->close();


    // 2. Generate ID dan Insert
    $id_wasling = generateIdWasling($conn2);

    $stmt_insert = $conn2->prepare("
        INSERT INTO wasling (id_wasling, nama_wasling, no_hp, lokasi_tpu)
        VALUES (?, ?, ?, ?)
    ");

    if ($stmt_insert === false) {
        echo "Gagal: Error prepare statement: " . $conn2->error;
        exit;
    }
    
    $stmt_insert->bind_param("ssss", $id_wasling, $nama, $nohp, $lokasi);

    if ($stmt_insert->execute()) {
        echo "ok";
    } else {
        echo "Gagal: " . $stmt_insert->error;
    }
    $stmt_insert->close();
    exit;
}

// =============================
//   TAMBAH WASLING + BAGI RUANG SEKALIGUS
// =============================
if (isset($_POST['add_wasling_with_rooms'])) {
    header('Content-Type: application/json');
    $conn2->begin_transaction();
    
    try {
        $mode = $_POST['mode'] ?? 'new'; // 'new' or 'existing'
        $hari = trim($_POST['hari'] ?? ''); // String karena kolom hari adalah varchar(5)
        $ruang_input = trim($_POST['ruang_input'] ?? '');
        $lokasi = trim($_POST['lokasi_hidden'] ?? '');
        $masa = '20252';
        
        // Validasi
        if (empty($hari) || !in_array($hari, ['1', '2'])) {
            throw new Exception("Hari harus dipilih (Hari 1 atau Hari 2)!");
        }
        if (empty($ruang_input)) {
            throw new Exception("Input ruang tidak boleh kosong!");
        }
        
        // Parse ruang input (support list dan range)
        $ruang_list = parseRuangInput($ruang_input);
        if (empty($ruang_list)) {
            throw new Exception("Format input ruang tidak valid!");
        }
        
        // Handle wasling
        if ($mode === 'new') {
            $nama = trim($_POST['nama'] ?? '');
            $nohp = trim($_POST['nohp'] ?? '');
            
            if (empty($nama)) {
                throw new Exception("Nama wasling wajib diisi!");
            }
            
            // Cek duplikat nama
            $stmt_check = $conn2->prepare("SELECT id FROM wasling WHERE nama_wasling = ? AND lokasi_tpu = ? LIMIT 1");
            $stmt_check->bind_param("ss", $nama, $lokasi);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $stmt_check->close();
                throw new Exception("Nama Wasling \"$nama\" sudah terdaftar di lokasi ini.");
            }
            $stmt_check->close();
            
            // Generate ID dan insert wasling baru
            $id_wasling = generateIdWasling($conn2);
            $stmt_wasling = $conn2->prepare("INSERT INTO wasling (id_wasling, nama_wasling, no_hp, lokasi_tpu) VALUES (?, ?, ?, ?)");
            $stmt_wasling->bind_param("ssss", $id_wasling, $nama, $nohp, $lokasi);
            if (!$stmt_wasling->execute()) {
                throw new Exception("Gagal menambah wasling: " . $stmt_wasling->error);
            }
            $stmt_wasling->close();
        } else {
            $id_wasling = trim($_POST['id_wasling_existing'] ?? '');
            if (empty($id_wasling)) {
                throw new Exception("Wasling harus dipilih!");
            }
        }
        
        // Insert ruang ke wasling_ruang (dengan lokasi)
        $stmt_ruang = $conn2->prepare("INSERT INTO wasling_ruang (id_wasling, no_ruang, id_pengawas, hari, lokasi) VALUES (?, ?, ?, ?, ?)");
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($ruang_list as $ruang) {
            // Cek apakah sudah ada (duplikat) - dengan lokasi juga
            // Binding: id_wasling(s), no_ruang(i), hari(s), lokasi(s)
            $stmt_check_dup = $conn2->prepare("SELECT id FROM wasling_ruang WHERE id_wasling = ? AND no_ruang = ? AND hari = ? AND lokasi = ? LIMIT 1");
            $stmt_check_dup->bind_param("siss", $id_wasling, $ruang, $hari, $lokasi);
            $stmt_check_dup->execute();
            if ($stmt_check_dup->get_result()->num_rows > 0) {
                $stmt_check_dup->close();
                $skipped++;
                $errors[] = "Ruang $ruang hari $hari sudah ada";
                continue;
            }
            $stmt_check_dup->close();
            
            // Cari id_pengawas untuk ruang
            // Coba cari dengan lokasi dan masa dulu
            $stmt_pengawas = $conn2->prepare("SELECT id_pengawas FROM pengawas_ruang WHERE ruang = ? AND lokasi = ? AND masa = ? LIMIT 1");
            $stmt_pengawas->bind_param("iss", $ruang, $lokasi, $masa);
            $stmt_pengawas->execute();
            $res_pengawas = $stmt_pengawas->get_result();
            
            $id_pengawas = null;
            if ($row_pengawas = $res_pengawas->fetch_assoc()) {
                $id_pengawas = $row_pengawas['id_pengawas'];
            } else {
                // Coba cari tanpa filter lokasi (hanya ruang dan masa)
                $stmt_pengawas2 = $conn2->prepare("SELECT id_pengawas FROM pengawas_ruang WHERE ruang = ? AND masa = ? LIMIT 1");
                $stmt_pengawas2->bind_param("is", $ruang, $masa);
                $stmt_pengawas2->execute();
                $res_pengawas2 = $stmt_pengawas2->get_result();
                
                if ($row_pengawas2 = $res_pengawas2->fetch_assoc()) {
                    $id_pengawas = $row_pengawas2['id_pengawas'];
                    $errors[] = "Pengawas untuk ruang $ruang ditemukan tanpa filter lokasi";
                } else {
                    // Jika masih tidak ditemukan, gunakan placeholder
                    $id_pengawas = 'PGW000'; // Placeholder default
                    $errors[] = "Pengawas untuk ruang $ruang tidak ditemukan, menggunakan placeholder PGW000";
                }
                $stmt_pengawas2->close();
            }
            $stmt_pengawas->close();
            
            // Jika masih null, skip insert ini
            if (!$id_pengawas) {
                $skipped++;
                $errors[] = "Tidak dapat menemukan pengawas untuk ruang $ruang";
                continue;
            }
            
            // Binding: id_wasling(s), no_ruang(i), id_pengawas(s), hari(s), lokasi(s)
            $stmt_ruang->bind_param("sisss", $id_wasling, $ruang, $id_pengawas, $hari, $lokasi);
            if ($stmt_ruang->execute()) {
                $inserted++;
            } else {
                // Log error untuk debugging
                $error_msg = "Insert error untuk ruang $ruang: " . $stmt_ruang->error;
                error_log($error_msg);
                $errors[] = $error_msg;
                $skipped++;
            }
        }
        $stmt_ruang->close();
        
        if ($inserted > 0) {
            $conn2->commit();
            $message = "Berhasil! $inserted ruang ditambahkan";
            if ($skipped > 0) {
                $message .= ", $skipped ruang dilewati";
            }
            if (!empty($errors)) {
                $message .= ". Info: " . implode(", ", array_slice($errors, 0, 3));
            }
            echo json_encode([
                'status' => 'ok',
                'message' => $message
            ]);
        } else {
            $conn2->rollback();
            $error_message = "Tidak ada ruang yang berhasil ditambahkan. ";
            if (!empty($errors)) {
                $error_message .= implode(", ", $errors);
            } else {
                $error_message .= "Pastikan ruang ada di tabel pengawas_ruang untuk lokasi ini.";
            }
            echo json_encode([
                'status' => 'error',
                'message' => $error_message
            ]);
        }
        
    } catch (Exception $e) {
        $conn2->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Helper function untuk parse input ruang
function parseRuangInput($input) {
    $ruang_list = [];
    $input = preg_replace('/\s+/', '', $input); // Hapus spasi
    
    // Split by comma
    $parts = explode(',', $input);
    
    foreach ($parts as $part) {
        if (empty($part)) continue;
        
        // Cek apakah range (format: 57-61)
        if (strpos($part, '-') !== false) {
            $range = explode('-', $part);
            if (count($range) === 2) {
                $start = intval($range[0]);
                $end = intval($range[1]);
                if ($start > 0 && $end >= $start) {
                    for ($i = $start; $i <= $end; $i++) {
                        $ruang_list[] = $i;
                    }
                }
            }
        } else {
            // Single number
            $num = intval($part);
            if ($num > 0) {
                $ruang_list[] = $num;
            }
        }
    }
    
    // Remove duplicates dan sort
    $ruang_list = array_unique($ruang_list);
    sort($ruang_list);
    
    return $ruang_list;
}

// =============================
//      HAPUS WASLING (MENGGUNAKAN PREPARED STATEMENTS)
// =============================
if (isset($_POST['delete_wasling'])) {
    $id_wasling = trim($_POST['id']);

    // Mulai transaksi untuk memastikan konsistensi
    $conn2->begin_transaction();
    $success = true;

    try {
        // Hapus penugasan ruang terlebih dahulu
        $stmt_del_ruang = $conn2->prepare("DELETE FROM wasling_ruang WHERE id_wasling = ?");
        $stmt_del_ruang->bind_param("s", $id_wasling);
        if (!$stmt_del_ruang->execute()) {
            throw new Exception("Gagal menghapus ruang Wasling: " . $stmt_del_ruang->error);
        }
        $stmt_del_ruang->close();

        // Hapus Wasling
        $stmt_del_wasling = $conn2->prepare("DELETE FROM wasling WHERE id_wasling = ?");
        $stmt_del_wasling->bind_param("s", $id_wasling);
        if (!$stmt_del_wasling->execute()) {
            throw new Exception("Gagal menghapus Wasling: " . $stmt_del_wasling->error);
        }
        $stmt_del_wasling->close();

        $conn2->commit();
        echo "ok";

    } catch (Exception $e) {
        $conn2->rollback();
        echo "Gagal: " . $e->getMessage();
    }

    exit;
}

// =============================
//      TAMPILAN UTAMA
// =============================

// Ambil data Wasling berdasarkan lokasi (untuk dropdown)
$stmt_select = $conn2->prepare("SELECT id_wasling, nama_wasling, no_hp FROM wasling WHERE lokasi_tpu = ? ORDER BY nama_wasling ASC");
$stmt_select->bind_param("s", $lokasi_tpu);
$stmt_select->execute();
$wasling_list_dropdown = $stmt_select->get_result();
$stmt_select->close();

// Ambil data Wasling untuk daftar (query ulang)
$stmt_select2 = $conn2->prepare("SELECT id_wasling, nama_wasling, no_hp FROM wasling WHERE lokasi_tpu = ? ORDER BY nama_wasling ASC");
$stmt_select2->bind_param("s", $lokasi_tpu);
$stmt_select2->execute();
$wasling_list = $stmt_select2->get_result();
$stmt_select2->close();

// Query untuk menampilkan data lengkap wasling dengan ruang, lokasi, dan hari
// Query langsung dari wasling_ruang untuk wasling di lokasi ini
$wasling_detail = null;
try {
    // Query sederhana: ambil semua data wasling_ruang untuk wasling di lokasi ini
    $query = "
        SELECT 
            w.id_wasling,
            w.nama_wasling,
            w.lokasi_tpu,
            wr.hari,
            wr.no_ruang,
            COALESCE(wr.lokasi, w.lokasi_tpu) as lokasi
        FROM wasling w
        LEFT JOIN wasling_ruang wr ON w.id_wasling = wr.id_wasling
        WHERE w.lokasi_tpu = ?
        ORDER BY w.nama_wasling ASC, wr.hari ASC, wr.no_ruang ASC
    ";
    
    $stmt_detail = $conn2->prepare($query);
    if ($stmt_detail) {
        $stmt_detail->bind_param("s", $lokasi_tpu);
        $stmt_detail->execute();
        $wasling_detail = $stmt_detail->get_result();
        $stmt_detail->close();
    }
} catch (Exception $e) {
    // Jika ada error, coba query tanpa kolom lokasi
    try {
        $query_fallback = "
            SELECT 
                w.id_wasling,
                w.nama_wasling,
                w.lokasi_tpu,
                wr.hari,
                wr.no_ruang,
                w.lokasi_tpu as lokasi
            FROM wasling w
            LEFT JOIN wasling_ruang wr ON w.id_wasling = wr.id_wasling
            WHERE w.lokasi_tpu = ?
            ORDER BY w.nama_wasling ASC, wr.hari ASC, wr.no_ruang ASC
        ";
        $stmt_detail = $conn2->prepare($query_fallback);
        if ($stmt_detail) {
            $stmt_detail->bind_param("s", $lokasi_tpu);
            $stmt_detail->execute();
            $wasling_detail = $stmt_detail->get_result();
            $stmt_detail->close();
        }
    } catch (Exception $e2) {
        $wasling_detail = null;
    }
}

?>

<div class="form-card">
    <div class="form-header collapsible-header" onclick="toggleCollapse('form-content')">
        <div>
            <h3>➕ Tambah Wasling & Bagi Ruang</h3>
            <p class="form-subtitle">Lokasi: <b><?= htmlspecialchars($lokasi_tpu) ?></b></p>
        </div>
        <span class="collapse-icon" id="icon-form-content">▼</span>
    </div>
    
    <div class="collapsible-content" id="form-content">
    <!-- Toggle Mode -->
    <div class="mode-toggle">
        <button type="button" class="mode-btn active" data-mode="new" onclick="switchMode('new')">
            <span>🆕</span> Wasling Baru
        </button>
        <button type="button" class="mode-btn" data-mode="existing" onclick="switchMode('existing')">
            <span>👤</span> Wasling Existing
        </button>
    </div>
    
    <!-- Form Wasling Baru -->
    <div id="form-new" class="form-section">
        <label class="form-label">Nama Wasling</label>
        <input type="text" id="nama" class="form-input" placeholder="Masukkan nama wasling" required>
        
        <label class="form-label">Nomor HP</label>
        <input type="text" id="nohp" class="form-input" placeholder="Masukkan nomor HP" required>
    </div>
    
    <!-- Form Wasling Existing -->
    <div id="form-existing" class="form-section" style="display:none;">
        <label class="form-label">Pilih Wasling</label>
        <select id="id_wasling_existing" class="form-input">
            <option value="">-- Pilih Wasling --</option>
            <?php 
            // Reset pointer untuk dropdown
            $wasling_list_dropdown->data_seek(0);
            while($w = $wasling_list_dropdown->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($w['id_wasling']) ?>">
                    <?= htmlspecialchars($w['nama_wasling']) ?> (<?= htmlspecialchars($w['id_wasling']) ?>)
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    
    <!-- Pilih Hari -->
    <label class="form-label">Pilih Hari</label>
    <select id="hari" class="form-input">
        <option value="">-- Pilih Hari --</option>
        <option value="1">Hari 1</option>
        <option value="2">Hari 2</option>
    </select>
    
    <!-- Input Ruang -->
    <label class="form-label">
        Input Ruang
        <small class="form-hint">Format: 57,58,59 atau 57-61</small>
    </label>
    <input type="text" id="ruang_input" class="form-input" placeholder="Contoh: 57,58,59,60,61 atau 57-61" oninput="updatePreview()">
    
    <!-- Preview Ruang -->
    <div id="preview-section" class="preview-card" style="display:none;">
        <div class="preview-header">
            <span>📋 Preview Ruang</span>
            <span id="preview-count" class="preview-badge">0</span>
        </div>
        <div id="preview-list" class="preview-list"></div>
    </div>
    
    <input type="hidden" id="lokasi_hidden" value="<?= htmlspecialchars($lokasi_tpu) ?>">
    
    <button class="form-btn" onclick="simpanWaslingDanRuang()">
        <span>💾</span> Simpan Wasling & Ruang
    </button>
    
    <div id="form-message" class="form-message" style="display:none;">    </div>
    </div>
</div>

<!-- Detail Wasling dengan Ruang, Lokasi, dan Hari -->
<div class="card">
    <div class="form-header collapsible-header" onclick="toggleCollapse('detail-content')">
        <div>
            <h3>📋 Detail Wasling & Ruang Pengawasan</h3>
            <p style="font-size:13px; color:#64748b; margin-bottom:0;">
                Informasi lengkap wasling: ruang yang diawasi, lokasi, dan hari ujian
            </p>
        </div>
        <span class="collapse-icon" id="icon-detail-content">▼</span>
    </div>
    
    <div class="collapsible-content" id="detail-content">
    
    <?php
    // Group data by wasling
    $wasling_groups = [];
    if ($wasling_detail) {
        if ($wasling_detail->num_rows > 0) {
            $wasling_detail->data_seek(0);
            while ($row = $wasling_detail->fetch_assoc()) {
                $id = $row['id_wasling'];
                if (!isset($wasling_groups[$id])) {
                    $wasling_groups[$id] = [
                        'nama' => $row['nama_wasling'],
                        'lokasi_tpu' => $row['lokasi_tpu'],
                        'ruang' => []
                    ];
                }
                // Pastikan no_ruang dan hari ada (tidak NULL)
                if (isset($row['no_ruang']) && $row['no_ruang'] !== null && $row['no_ruang'] !== '' && $row['no_ruang'] != 0) {
                    $wasling_groups[$id]['ruang'][] = [
                        'hari' => $row['hari'] ?? '',
                        'ruang' => $row['no_ruang'],
                        'lokasi' => $row['lokasi'] ?? $row['lokasi_tpu'] ?? ''
                    ];
                }
            }
        }
    }
    ?>
    
    <?php 
    // Pastikan semua wasling di lokasi ini ditampilkan, bahkan yang belum punya ruang
    // Ambil daftar wasling yang belum ada di $wasling_groups
    $wasling_list->data_seek(0);
    while ($w = $wasling_list->fetch_assoc()) {
        $id = $w['id_wasling'];
        if (!isset($wasling_groups[$id])) {
            $wasling_groups[$id] = [
                'nama' => $w['nama_wasling'],
                'lokasi_tpu' => $lokasi_tpu,
                'ruang' => []
            ];
        }
    }
    
    // Query langsung dari wasling_ruang untuk memastikan data diambil
    // Query alternatif jika query utama tidak mengambil data
    if (empty($wasling_groups) || (count($wasling_groups) > 0 && empty(array_filter(array_column($wasling_groups, 'ruang'))))) {
        try {
            $query_direct = "
                SELECT 
                    wr.id_wasling,
                    wr.hari,
                    wr.no_ruang,
                    wr.lokasi,
                    w.nama_wasling,
                    w.lokasi_tpu
                FROM wasling_ruang wr
                INNER JOIN wasling w ON wr.id_wasling = w.id_wasling
                WHERE w.lokasi_tpu = ?
                ORDER BY wr.id_wasling ASC, wr.hari ASC, wr.no_ruang ASC
            ";
            $stmt_direct = $conn2->prepare($query_direct);
            if ($stmt_direct) {
                $stmt_direct->bind_param("s", $lokasi_tpu);
                $stmt_direct->execute();
                $result_direct = $stmt_direct->get_result();
                
                if ($result_direct && $result_direct->num_rows > 0) {
                    // Reset wasling_groups dan isi ulang dari query langsung
                    $wasling_groups = [];
                    while ($row = $result_direct->fetch_assoc()) {
                        $id = $row['id_wasling'];
                        if (!isset($wasling_groups[$id])) {
                            $wasling_groups[$id] = [
                                'nama' => $row['nama_wasling'],
                                'lokasi_tpu' => $row['lokasi_tpu'],
                                'ruang' => []
                            ];
                        }
                        if ($row['no_ruang']) {
                            $wasling_groups[$id]['ruang'][] = [
                                'hari' => $row['hari'],
                                'ruang' => $row['no_ruang'],
                                'lokasi' => $row['lokasi'] ?? $row['lokasi_tpu']
                            ];
                        }
                    }
                }
                $stmt_direct->close();
            }
        } catch (Exception $e) {
            // Ignore error
        }
    }
    ?>
    
    <?php if (empty($wasling_groups)): ?>
        <p style="text-align:center; color:#999; padding:20px;">
            Belum ada data wasling dengan ruang pengawasan.
        </p>
    <?php else: ?>
        <div class="wasling-table-wrapper">
            <table class="wasling-detail-table">
                <thead>
                    <tr>
                        <th>Nama Wasling</th>
                        <th>ID</th>
                        <th>Lokasi</th>
                        <th>Hari 1</th>
                        <th>Hari 2</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wasling_groups as $id_wasling => $data): ?>
                        <?php
                        // Group by hari
                        $ruang_by_hari = [];
                        foreach ($data['ruang'] as $r) {
                            $hari = $r['hari'];
                            if (!isset($ruang_by_hari[$hari])) {
                                $ruang_by_hari[$hari] = [];
                            }
                            $ruang_by_hari[$hari][] = $r['ruang'];
                        }
                        ksort($ruang_by_hari);
                        
                        // Format ruang untuk hari 1 dan hari 2
                        $hari1_ruang = isset($ruang_by_hari['1']) ? $ruang_by_hari['1'] : [];
                        $hari2_ruang = isset($ruang_by_hari['2']) ? $ruang_by_hari['2'] : [];
                        ?>
                        <tr>
                            <td class="wasling-name"><?= htmlspecialchars($data['nama']) ?></td>
                            <td class="wasling-id"><?= htmlspecialchars($id_wasling) ?></td>
                            <td class="wasling-lokasi"><?= htmlspecialchars($data['lokasi_tpu']) ?></td>
                            <td class="wasling-ruang-cell">
                                <?php if (!empty($hari1_ruang)): ?>
                                    <div class="wasling-ruang-list">
                                        <?php foreach ($hari1_ruang as $ruang): ?>
                                            <span class="wasling-ruang-badge">R<?= htmlspecialchars($ruang) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="wasling-empty">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="wasling-ruang-cell">
                                <?php if (!empty($hari2_ruang)): ?>
                                    <div class="wasling-ruang-list">
                                        <?php foreach ($hari2_ruang as $ruang): ?>
                                            <span class="wasling-ruang-badge">R<?= htmlspecialchars($ruang) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="wasling-empty">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="wasling-aksi-cell">
                                <div class="wasling-action-buttons">
                                    <button class="wasling-edit-btn" onclick="editWasling('<?= htmlspecialchars($id_wasling) ?>')" title="Edit">
                                        ✏️
                                    </button>
                                    <button class="wasling-delete-btn" onclick="hapusWasling('<?= htmlspecialchars($id_wasling) ?>')" title="Hapus">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    </div>
</div>

<style>
/* Mobile Native Form Styles */
.form-card {
    background: #fff;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(15,23,42,0.08);
    margin-bottom: 20px;
}
.form-header {
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}
.collapsible-header {
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
    transition: background-color 0.2s;
    padding: 12px 20px !important;
    margin: -20px -20px 12px -20px !important;
    margin-bottom: 12px !important;
    border-radius: 12px 12px 0 0;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 16px !important;
}
.collapsible-header:hover {
    background-color: #f8fafc;
}
.collapsible-header > div {
    flex: 1;
}
.collapse-icon {
    font-size: 16px;
    color: #64748b;
    transition: transform 0.3s ease;
    margin-left: 12px;
}
.collapsible-content {
    max-height: 2000px;
    overflow: hidden;
    transition: max-height 0.3s ease, opacity 0.3s ease;
    opacity: 1;
    padding-top: 8px;
}
.collapsible-content.collapsed {
    max-height: 0;
    opacity: 0;
    margin: 0;
    padding: 0;
}
.collapsible-content.collapsed * {
    display: none;
}
.form-header h3 {
    margin: 0 0 6px;
    font-size: 18px;
    font-weight: 600;
    color: #0f172a;
}
.form-subtitle {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}
.mode-toggle {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}
.mode-btn {
    padding: 12px;
    border: 2px solid #e2e8f0;
    background: #fff;
    border-radius: 12px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}
.mode-btn.active {
    background: linear-gradient(120deg, #00bcd4, #2979ff);
    color: #fff;
    border-color: transparent;
}
.mode-btn span {
    font-size: 16px;
}
.form-section {
    margin-bottom: 16px;
}
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    margin-bottom: 6px;
}
.form-hint {
    display: block;
    font-size: 11px;
    color: #94a3b8;
    font-weight: normal;
    margin-top: 2px;
}
.form-input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #cbd5f5;
    border-radius: 12px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color 0.2s;
    margin-bottom: 16px;
}
.form-input:focus {
    outline: none;
    border-color: #2979ff;
    box-shadow: 0 0 0 3px rgba(41,121,255,0.1);
}
.form-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(120deg, #00796b, #00bcd4);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.form-btn:active {
    transform: scale(0.98);
}
.preview-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 16px;
}
.preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}
.preview-badge {
    background: #2979ff;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}
.preview-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.preview-item {
    background: #fff;
    border: 1px solid #cbd5f5;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 12px;
    color: #4253ff;
    font-weight: 500;
}
.form-message {
    margin-top: 12px;
    padding: 12px;
    border-radius: 10px;
    font-size: 13px;
    text-align: center;
}
.form-message.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}
.form-message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
/* Table untuk daftar wasling */
.card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
th, td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
    font-size: 14px;
}
th {
    background: #f0f0f0;
}
/* Detail Wasling Styles */
.wasling-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.wasling-detail-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(15,23,42,0.05);
}
.wasling-detail-table thead {
    background: #f8fafc;
    color: #0f172a;
    border-bottom: 2px solid #e2e8f0;
}
.wasling-detail-table th {
    padding: 12px 10px;
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}
.wasling-detail-table tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: background-color 0.2s;
}
.wasling-detail-table tbody tr:hover {
    background-color: #f8fafc;
}
.wasling-detail-table tbody tr:last-child {
    border-bottom: none;
}
.wasling-detail-table td {
    padding: 12px 10px;
    font-size: 13px;
    vertical-align: top;
}
.wasling-name {
    font-weight: 600;
    color: #0f172a;
}
.wasling-id {
    color: #64748b;
    font-size: 12px;
    font-family: monospace;
}
.wasling-lokasi {
    color: #475569;
    font-size: 12px;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.wasling-ruang-cell {
    min-width: 120px;
}
.wasling-ruang-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}
.wasling-ruang-badge {
    display: inline-block;
    background: #eef2ff;
    color: #4253ff;
    padding: 3px 6px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid #cbd5f5;
    white-space: nowrap;
}
.wasling-empty {
    color: #94a3b8;
    font-size: 12px;
    font-style: italic;
}
.wasling-aksi-cell {
    text-align: center;
    width: 100px;
}
.wasling-action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
    align-items: center;
}
.wasling-edit-btn {
    background: linear-gradient(120deg, #00bcd4, #2979ff);
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 4px rgba(41,121,255,0.2);
}
.wasling-edit-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(41,121,255,0.3);
}
.wasling-edit-btn:active {
    transform: translateY(0);
}
.wasling-delete-btn {
    background: linear-gradient(120deg, #f44336, #e91e63);
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 4px rgba(244,67,54,0.2);
}
.wasling-delete-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(244,67,54,0.3);
}
.wasling-delete-btn:active {
    transform: translateY(0);
}
</style>

<script>
let currentMode = 'new';

function switchMode(mode) {
    currentMode = mode;
    
    // Update button states
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.mode-btn[data-mode="${mode}"]`).classList.add('active');
    
    // Show/hide form sections
    document.getElementById('form-new').style.display = mode === 'new' ? 'block' : 'none';
    document.getElementById('form-existing').style.display = mode === 'existing' ? 'block' : 'none';
    
    // Clear preview
    updatePreview();
}

function parseRuangInput(input) {
    if (!input) return [];
    
    let ruang_list = [];
    input = input.replace(/\s+/g, ''); // Remove spaces
    
    let parts = input.split(',');
    
    parts.forEach(part => {
        if (!part) return;
        
        // Check if range (format: 57-61)
        if (part.includes('-')) {
            let range = part.split('-');
            if (range.length === 2) {
                let start = parseInt(range[0]);
                let end = parseInt(range[1]);
                if (start > 0 && end >= start) {
                    for (let i = start; i <= end; i++) {
                        ruang_list.push(i);
                    }
                }
            }
        } else {
            // Single number
            let num = parseInt(part);
            if (num > 0) {
                ruang_list.push(num);
            }
        }
    });
    
    // Remove duplicates and sort
    ruang_list = [...new Set(ruang_list)].sort((a, b) => a - b);
    
    return ruang_list;
}

function updatePreview() {
    let input = document.getElementById('ruang_input').value.trim();
    let ruang_list = parseRuangInput(input);
    let previewSection = document.getElementById('preview-section');
    let previewList = document.getElementById('preview-list');
    let previewCount = document.getElementById('preview-count');
    
    if (ruang_list.length > 0) {
        previewSection.style.display = 'block';
        previewCount.textContent = ruang_list.length;
        previewList.innerHTML = ruang_list.map(r => 
            `<span class="preview-item">Ruang ${r}</span>`
        ).join('');
    } else {
        previewSection.style.display = 'none';
    }
}

function simpanWaslingDanRuang() {
    let hari = document.getElementById('hari').value;
    let ruang_input = document.getElementById('ruang_input').value.trim();
    let lokasi = document.getElementById('lokasi_hidden').value;
    let messageDiv = document.getElementById('form-message');
    
    // Validasi
    if (currentMode === 'new') {
        let nama = document.getElementById('nama').value.trim();
        let nohp = document.getElementById('nohp').value.trim();
        
        if (!nama || !nohp) {
            showMessage('Nama dan nomor HP wajib diisi!', 'error');
            return;
        }
    } else {
        let id_wasling = document.getElementById('id_wasling_existing').value;
        if (!id_wasling) {
            showMessage('Pilih wasling terlebih dahulu!', 'error');
            return;
        }
    }
    
    if (!hari) {
        showMessage('Pilih hari terlebih dahulu!', 'error');
        return;
    }
    
    if (!ruang_input) {
        showMessage('Input ruang tidak boleh kosong!', 'error');
        return;
    }
    
    let ruang_list = parseRuangInput(ruang_input);
    if (ruang_list.length === 0) {
        showMessage('Format input ruang tidak valid!', 'error');
        return;
    }
    
    // Konfirmasi
    if (!confirm(`Yakin ingin menyimpan?\n\n${currentMode === 'new' ? 'Wasling baru akan ditambahkan.\n' : ''}${ruang_list.length} ruang akan dibagi ke wasling ini.`)) {
        return;
    }
    
    // Prepare form data
    let f = new FormData();
    f.append('add_wasling_with_rooms', '1');
    f.append('mode', currentMode);
    f.append('hari', hari);
    f.append('ruang_input', ruang_input);
    f.append('lokasi_hidden', lokasi);
    
    if (currentMode === 'new') {
        f.append('nama', document.getElementById('nama').value.trim());
        f.append('nohp', document.getElementById('nohp').value.trim());
    } else {
        f.append('id_wasling_existing', document.getElementById('id_wasling_existing').value);
    }
    
    // Disable button
    let btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span> Menyimpan...';
    
    fetch('tambah_wasling.php', { method: 'POST', body: f })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            showMessage(data.message, 'success');
            
            // Reset form
            setTimeout(() => {
                document.getElementById('nama').value = '';
                document.getElementById('nohp').value = '';
                document.getElementById('id_wasling_existing').value = '';
                document.getElementById('hari').value = '';
                document.getElementById('ruang_input').value = '';
                updatePreview();
                
                // Reload untuk update daftar wasling
                setTimeout(() => location.reload(), 1500);
            }, 1000);
        } else {
            showMessage(data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<span>💾</span> Simpan Wasling & Ruang';
        }
    })
    .catch(err => {
        showMessage('Terjadi kesalahan: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<span>💾</span> Simpan Wasling & Ruang';
    });
}

function showMessage(message, type) {
    let messageDiv = document.getElementById('form-message');
    messageDiv.textContent = message;
    messageDiv.className = 'form-message ' + type;
    messageDiv.style.display = 'block';
    
    if (type === 'success') {
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 3000);
    }
}

// Function untuk tambah wasling saja (legacy, tetap ada untuk kompatibilitas)
function tambahWasling(){
    let nama = document.getElementById("nama").value.trim();
    let nohp = document.getElementById("nohp").value.trim();
    let lokasi_tpu = document.getElementById("lokasi_hidden").value;

    if(nama=="" || nohp==""){
        alert("Nama dan nomor HP wajib diisi!");
        return;
    }

    let f = new FormData();
    f.append("add_wasling", "1");
    f.append("nama", nama);
    f.append("nohp", nohp);
    f.append("lokasi_hidden", lokasi_tpu); 

    fetch("tambah_wasling.php", { method:"POST", body:f })
    .then(r=>r.text())
    .then(res=>{
        if(res.trim().startsWith("Gagal")){
             alert(res);
        } else if(res.trim()=="ok"){
            alert("Wasling berhasil ditambahkan!");
            document.getElementById("nama").value = "";
            document.getElementById("nohp").value = "";
            setTimeout(() => location.reload(), 500);
        } else { 
            alert("Terjadi kesalahan saat menyimpan data: " + res); 
        }
    });
}

function editWasling(id_wasling) {
    // Switch ke mode existing dan pilih wasling yang dipilih
    switchMode('existing');
    document.getElementById('id_wasling_existing').value = id_wasling;
    
    // Scroll ke form
    document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Highlight form existing
    const formExisting = document.getElementById('form-existing');
    formExisting.style.background = '#f0f9ff';
    formExisting.style.border = '2px solid #2979ff';
    formExisting.style.borderRadius = '12px';
    formExisting.style.padding = '12px';
    setTimeout(() => {
        formExisting.style.background = '';
        formExisting.style.border = '';
        formExisting.style.borderRadius = '';
        formExisting.style.padding = '';
    }, 2000);
}

function hapusWasling(id_wasling){
    if(!confirm("Yakin ingin menghapus Wasling ini? Semua pembagian ruang yang terkait juga akan dihapus.")) return;
    
    let f = new FormData();
    f.append("delete_wasling", "1");
    f.append("id", id_wasling);

    fetch("tambah_wasling.php", { method:"POST", body:f })
    .then(r=>r.text())
    .then(res=>{
        if(res.trim()=="ok"){
            alert("Wasling berhasil dihapus!");
            location.reload();
        } else {
            alert(res);
        }
    });
}

function toggleCollapse(contentId) {
    const content = document.getElementById(contentId);
    const icon = document.getElementById('icon-' + contentId);
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        if (icon) icon.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('collapsed');
        if (icon) icon.style.transform = 'rotate(-90deg)';
    }
}
</script>