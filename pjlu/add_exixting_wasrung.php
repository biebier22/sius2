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
$masa = '20252';

// =============================
//     GENERATE ID PENGAWAS OTOMATIS
// =============================
function generateIdPengawas($conn2) {
    $q = $conn2->query("SELECT id_pengawas FROM pengawas_ruang ORDER BY id_pengawas DESC LIMIT 1");

    if ($q->num_rows == 0) {
        return "PGW001";
    }

    $d = $q->fetch_assoc();
    $last = intval(substr($d['id_pengawas'], 3)); 
    $new  = $last + 1;

    return "PGW" . str_pad($new, 3, "0", STR_PAD_LEFT);
}

// =============================
//   GET KODE TPU BERDASARKAN LOKASI
// =============================
function getKodeTpu($conn2, $lokasi, $masa) {
    $stmt = $conn2->prepare("SELECT DISTINCT kode_tpu FROM e_lokasi_uas WHERE lokasi = ? AND masa = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $lokasi, $masa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['kode_tpu'];
        }
        $stmt->close();
    }
    // Fallback ke session jika tidak ditemukan
    return $_SESSION['kode_tpu'] ?? '';
}

// =============================
//   GET NILAI JAM DARI REKAP_UJIAN
// =============================
function getJamFromRekap($conn2, $hari, $ruang, $lokasi, $masa) {
    // Default values
    $jam1 = 0;
    $jam2 = 0;
    $jam3 = 0;
    $jam4 = 0;
    $jam5 = 0;
    
    $stmt = $conn2->prepare("
        SELECT 
            r.jam1,
            r.jam2,
            r.jam3,
            r.jam4,
            r.jam5
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
    
    if ($stmt) {
        $hari_str = (string)$hari;
        $ruang_str = (string)$ruang;
        $stmt->bind_param("sssss", $masa, $masa, $lokasi, $hari_str, $ruang_str);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $jam1 = intval($row['jam1'] ?? 0);
            $jam2 = intval($row['jam2'] ?? 0);
            $jam3 = intval($row['jam3'] ?? 0);
            $jam4 = intval($row['jam4'] ?? 0);
            $jam5 = intval($row['jam5'] ?? 0);
        }
        $stmt->close();
    }
    
    return [
        'jam1' => $jam1,
        'jam2' => $jam2,
        'jam3' => $jam3,
        'jam4' => $jam4,
        'jam5' => $jam5
    ];
}

// =============================
//   GET DAFTAR RUANG BERDASARKAN HARI DAN LOKASI
// =============================
function getDaftarRuang($conn2, $lokasi, $hari, $masa) {
    $ruang_list = [];
    $stmt = $conn2->prepare("
        SELECT DISTINCT r.ruang_ke
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
        ORDER BY CAST(r.ruang_ke AS UNSIGNED) ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param("ssss", $masa, $masa, $lokasi, $hari);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ruang_list[] = $row['ruang_ke'];
        }
        $stmt->close();
    }
    return $ruang_list;
}

// =============================
//   TAMBAH PENGAWAS RUANG
// =============================
if (isset($_POST['add_pengawas_ruang'])) {
    header('Content-Type: application/json');
    
    $nama_pengawas = trim($_POST['nama_pengawas'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $institusi = trim($_POST['institusi'] ?? '');
    $hari = intval($_POST['hari'] ?? 0);
    $ruang = intval($_POST['ruang'] ?? 0);
    $lokasi = trim($_POST['lokasi_hidden'] ?? '');
    
    // Validasi
    if (empty($nama_pengawas)) {
        echo json_encode(['status' => 'error', 'message' => 'Nama pengawas tidak boleh kosong!']);
        exit;
    }
    if (empty($no_wa)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor WhatsApp tidak boleh kosong!']);
        exit;
    }
    if (empty($institusi)) {
        echo json_encode(['status' => 'error', 'message' => 'Institusi tidak boleh kosong!']);
        exit;
    }
    if ($hari < 1 || $hari > 2) {
        echo json_encode(['status' => 'error', 'message' => 'Hari harus dipilih (Hari 1 atau Hari 2)!']);
        exit;
    }
    if ($ruang <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ruang harus dipilih!']);
        exit;
    }
    if (empty($lokasi)) {
        echo json_encode(['status' => 'error', 'message' => 'Lokasi tidak ditemukan!']);
        exit;
    }
    
    // Cek duplikasi: ruang + hari + lokasi tidak boleh duplikat
    $stmt_check = $conn2->prepare("
        SELECT id FROM pengawas_ruang 
        WHERE ruang = ? AND hari = ? AND lokasi = ? AND masa = ?
        LIMIT 1
    ");
    $stmt_check->bind_param("iiss", $ruang, $hari, $lokasi, $masa);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        echo json_encode(['status' => 'error', 'message' => "Ruang $ruang untuk Hari $hari di lokasi ini sudah memiliki pengawas!"]);
        exit;
    }
    $stmt_check->close();
    
    // Generate ID pengawas
    $id_pengawas = generateIdPengawas($conn2);
    
    // Get kode_tpu
    $kode_tpu = getKodeTpu($conn2, $lokasi, $masa);
    
    // Get nilai jam dari rekap_ujian
    $jam_data = getJamFromRekap($conn2, $hari, $ruang, $lokasi, $masa);
    
    // Insert ke pengawas_ruang
    $stmt_insert = $conn2->prepare("
        INSERT INTO pengawas_ruang 
        (masa, id_pengawas, nama_pengawas, hari, ruang, kode_tpu, lokasi, no_wa, institusi, jam1, jam2, jam3, jam4, jam5)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt_insert === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement: ' . $conn2->error]);
        exit;
    }
    
    $stmt_insert->bind_param("sssiissssiiiii", 
        $masa, 
        $id_pengawas, 
        $nama_pengawas, 
        $hari, 
        $ruang, 
        $kode_tpu, 
        $lokasi, 
        $no_wa, 
        $institusi,
        $jam_data['jam1'],
        $jam_data['jam2'],
        $jam_data['jam3'],
        $jam_data['jam4'],
        $jam_data['jam5']
    );
    
    if ($stmt_insert->execute()) {
        echo json_encode(['status' => 'ok', 'message' => 'Pengawas ruang berhasil ditambahkan!', 'id_pengawas' => $id_pengawas]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $stmt_insert->error]);
    }
    $stmt_insert->close();
    exit;
}

// =============================
//   EDIT PENGAWAS RUANG
// =============================
if (isset($_POST['edit_pengawas_ruang'])) {
    header('Content-Type: application/json');
    
    $id_pengawas = trim($_POST['id_pengawas'] ?? '');
    $nama_pengawas = trim($_POST['nama_pengawas'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $institusi = trim($_POST['institusi'] ?? '');
    $hari = intval($_POST['hari'] ?? 0);
    $ruang = intval($_POST['ruang'] ?? 0);
    $lokasi = trim($_POST['lokasi_hidden'] ?? '');
    
    // Validasi
    if (empty($id_pengawas)) {
        echo json_encode(['status' => 'error', 'message' => 'ID pengawas tidak ditemukan!']);
        exit;
    }
    if (empty($nama_pengawas)) {
        echo json_encode(['status' => 'error', 'message' => 'Nama pengawas tidak boleh kosong!']);
        exit;
    }
    if (empty($no_wa)) {
        echo json_encode(['status' => 'error', 'message' => 'Nomor WhatsApp tidak boleh kosong!']);
        exit;
    }
    if (empty($institusi)) {
        echo json_encode(['status' => 'error', 'message' => 'Institusi tidak boleh kosong!']);
        exit;
    }
    if ($hari < 1 || $hari > 2) {
        echo json_encode(['status' => 'error', 'message' => 'Hari harus dipilih (Hari 1 atau Hari 2)!']);
        exit;
    }
    if ($ruang <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ruang harus dipilih!']);
        exit;
    }
    
    // Cek apakah pengawas ada
    $stmt_check = $conn2->prepare("SELECT id FROM pengawas_ruang WHERE id_pengawas = ? AND masa = ? LIMIT 1");
    $stmt_check->bind_param("ss", $id_pengawas, $masa);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows == 0) {
        $stmt_check->close();
        echo json_encode(['status' => 'error', 'message' => 'Pengawas tidak ditemukan!']);
        exit;
    }
    $stmt_check->close();
    
    // Cek duplikasi: ruang + hari + lokasi tidak boleh duplikat (kecuali untuk id_pengawas yang sama)
    $stmt_check_dup = $conn2->prepare("
        SELECT id FROM pengawas_ruang 
        WHERE ruang = ? AND hari = ? AND lokasi = ? AND masa = ? AND id_pengawas != ?
        LIMIT 1
    ");
    $stmt_check_dup->bind_param("iisss", $ruang, $hari, $lokasi, $masa, $id_pengawas);
    $stmt_check_dup->execute();
    $result_check_dup = $stmt_check_dup->get_result();
    
    if ($result_check_dup->num_rows > 0) {
        $stmt_check_dup->close();
        echo json_encode(['status' => 'error', 'message' => "Ruang $ruang untuk Hari $hari di lokasi ini sudah memiliki pengawas lain!"]);
        exit;
    }
    $stmt_check_dup->close();
    
    // Get nilai jam dari rekap_ujian (jika ruang atau hari berubah)
    $jam_data = getJamFromRekap($conn2, $hari, $ruang, $lokasi, $masa);
    
    // Update pengawas_ruang
    $stmt_update = $conn2->prepare("
        UPDATE pengawas_ruang 
        SET nama_pengawas = ?, no_wa = ?, institusi = ?, hari = ?, ruang = ?, 
            jam1 = ?, jam2 = ?, jam3 = ?, jam4 = ?, jam5 = ?
        WHERE id_pengawas = ? AND masa = ?
    ");
    
    if ($stmt_update === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement: ' . $conn2->error]);
        exit;
    }
    
    $stmt_update->bind_param("sssiiiiiiiss", 
        $nama_pengawas, 
        $no_wa, 
        $institusi, 
        $hari, 
        $ruang,
        $jam_data['jam1'],
        $jam_data['jam2'],
        $jam_data['jam3'],
        $jam_data['jam4'],
        $jam_data['jam5'],
        $id_pengawas,
        $masa
    );
    
    if ($stmt_update->execute()) {
        echo json_encode(['status' => 'ok', 'message' => 'Data pengawas ruang berhasil diupdate!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate: ' . $stmt_update->error]);
    }
    $stmt_update->close();
    exit;
}

// =============================
//   DELETE PENGAWAS RUANG
// =============================
if (isset($_POST['delete_pengawas_ruang'])) {
    header('Content-Type: application/json');
    
    $id_pengawas = trim($_POST['id_pengawas'] ?? '');
    
    if (empty($id_pengawas)) {
        echo json_encode(['status' => 'error', 'message' => 'ID pengawas tidak ditemukan!']);
        exit;
    }
    
    // Cek apakah pengawas ada
    $stmt_check = $conn2->prepare("SELECT id FROM pengawas_ruang WHERE id_pengawas = ? AND masa = ? LIMIT 1");
    $stmt_check->bind_param("ss", $id_pengawas, $masa);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows == 0) {
        $stmt_check->close();
        echo json_encode(['status' => 'error', 'message' => 'Pengawas tidak ditemukan!']);
        exit;
    }
    $stmt_check->close();
    
    // Delete pengawas_ruang
    $stmt_delete = $conn2->prepare("DELETE FROM pengawas_ruang WHERE id_pengawas = ? AND masa = ?");
    
    if ($stmt_delete === false) {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement: ' . $conn2->error]);
        exit;
    }
    
    $stmt_delete->bind_param("ss", $id_pengawas, $masa);
    
    if ($stmt_delete->execute()) {
        echo json_encode(['status' => 'ok', 'message' => 'Pengawas ruang berhasil dihapus!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus: ' . $stmt_delete->error]);
    }
    $stmt_delete->close();
    exit;
}

// =============================
//   GET DATA PENGAWAS UNTUK EDIT
// =============================
if (isset($_GET['get_pengawas'])) {
    header('Content-Type: application/json');
    
    $id_pengawas = trim($_GET['id_pengawas'] ?? '');
    
    if (empty($id_pengawas)) {
        echo json_encode(['status' => 'error', 'message' => 'ID pengawas tidak ditemukan!']);
        exit;
    }
    
    $stmt = $conn2->prepare("
        SELECT id_pengawas, nama_pengawas, no_wa, institusi, hari, ruang, lokasi
        FROM pengawas_ruang
        WHERE id_pengawas = ? AND masa = ?
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("ss", $id_pengawas, $masa);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['status' => 'ok', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Pengawas tidak ditemukan!']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error prepare statement']);
    }
    exit;
}

// =============================
//   GET DAFTAR RUANG UNTUK HARI 1 (DEFAULT)
// =============================
$ruang_hari1 = getDaftarRuang($conn2, $lokasi_tpu, '1', $masa);
$ruang_hari2 = getDaftarRuang($conn2, $lokasi_tpu, '2', $masa);

?>

<div class="form-card">
    <div class="form-header collapsible-header" onclick="toggleCollapse('form-pengawas-content')">
        <div>
            <h3>➕ Tambah Pengawas Ruang</h3>
            <p class="form-subtitle">Lokasi: <b><?= htmlspecialchars($lokasi_tpu) ?></b></p>
        </div>
        <span class="collapse-icon" id="icon-form-pengawas-content">▼</span>
    </div>
    
    <div class="collapsible-content" id="form-pengawas-content">

        <!-- =========================== -->
        <!--       MENU WASRUNG           -->
        <!-- =========================== -->
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-12" style="display: flex; gap: 10px;">

                <!-- WASRUNG BARU -->
                <button type="button"
                    onclick="showForm('baru')"
                    class="btn"
                    style="
                        background: linear-gradient(90deg,#009FFD,#2A2A72);
                        border-radius: 10px;
                        color: #fff;
                        padding: 10px 18px;
                        font-weight: 600;
                        display: inline-flex;
                        align-items: center;
                        border:none;
                        font-size: 14px;
                    ">
                    <i class="ti-plus" style="margin-right:7px;"></i> Wasrung Baru
                </button>

                <!-- WASRUNG EXISTING -->
                <button type="button"
                    onclick="showForm('existing')"
                    class="btn"
                    style="
                        background: #fff;
                        border: 2px solid #e0e0e0;
                        border-radius: 10px;
                        padding: 10px 18px;
                        font-weight: 600;
                        display: inline-flex;
                        align-items: center;
                        color: #333;
                        font-size: 14px;
                    ">
                    <i class="ti-user" style="margin-right:7px;"></i> Wasrung Existing
                </button>

            </div>
        </div>

        <!-- PESAN -->
        <div id="form-message" class="form-message" style="display:none;"></div>


        <!-- ====================================================== -->
        <!--               FORM WASRUNG BARU (default)              -->
        <!-- ====================================================== -->
        <div id="form-wasrung-baru">

            <label class="form-label">Nama Pengawas</label>
            <input type="text" id="nama_pengawas" class="form-input" placeholder="Masukkan nama pengawas" required>

            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" id="no_wa" class="form-input" placeholder="Masukkan nomor WhatsApp" required>

            <label class="form-label">Institusi</label>
            <input type="text" id="institusi" class="form-input" placeholder="Masukkan nama institusi" required>

            <label class="form-label">Pilih Hari</label>
            <select id="hari" class="form-input" onchange="updateRuangDropdown()">
                <option value="">-- Pilih Hari --</option>
                <option value="1">Hari 1</option>
                <option value="2">Hari 2</option>
            </select>

            <label class="form-label">Pilih Ruang</label>
            <select id="ruang" class="form-input">
                <option value="">-- Pilih Hari terlebih dahulu --</option>
            </select>

            <input type="hidden" id="lokasi_hidden" value="<?= htmlspecialchars($lokasi_tpu) ?>">

            <button class="form-btn" onclick="simpanPengawasRuang()">
                <span>💾</span>
                Simpan Pengawas Ruang
            </button>
        </div>


        <!-- ====================================================== -->
        <!--              FORM WASRUNG EXISTING (BARU!!)            -->
        <!-- ====================================================== -->
        <div id="form-wasrung-existing" style="display:none;">

            <label class="form-label">Cari Nama Pengawas</label>
            <input type="text" id="search_existing" class="form-input" placeholder="Ketik nama pengawas..." onkeyup="cariExisting()">

            <div id="result_existing"
                 style="
                    margin-top:10px; 
                    padding:10px; 
                    background:#f7f7f7; 
                    border-radius:8px; 
                    display:none;">
            </div>

            <button class="form-btn" onclick="gunakanExisting()">
                <span>✔</span>
                Gunakan Data Ini
            </button>
        </div>

    </div>
</div>


<!-- Detail Pengawas Ruang -->
<div class="card">
    <div class="form-header collapsible-header" onclick="toggleCollapse('detail-pengawas-content')">
        <div>
            <h3>📋 Detail Pengawas Ruang</h3>
            <p style="font-size:13px; color:#64748b; margin-bottom:0;">
                Daftar pengawas ruang di lokasi ini
            </p>
        </div>
        <span class="collapse-icon" id="icon-detail-pengawas-content">▼</span>
    </div>
    
    <div class="collapsible-content" id="detail-pengawas-content">
        <?php
        // Query untuk menampilkan daftar pengawas ruang
        $stmt_list = $conn2->prepare("
            SELECT id_pengawas, nama_pengawas, no_wa, institusi, hari, ruang, lokasi
            FROM pengawas_ruang
            WHERE lokasi = ? AND masa = ?
            ORDER BY hari ASC, CAST(ruang AS UNSIGNED) ASC
        ");
        
        $pengawas_list = [];
        if ($stmt_list) {
            $stmt_list->bind_param("ss", $lokasi_tpu, $masa);
            $stmt_list->execute();
            $result_list = $stmt_list->get_result();
            while ($row = $result_list->fetch_assoc()) {
                $pengawas_list[] = $row;
            }
            $stmt_list->close();
        }
        ?>
        
        <?php if (empty($pengawas_list)): ?>
            <p style="text-align:center; color:#999; padding:20px;">
                Belum ada pengawas ruang yang terdaftar.
            </p>
        <?php else: ?>
            <div class="wasling-table-wrapper">
                <table class="wasling-detail-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Ruang</th>
                            <th>Nama Pengawas</th>
                            <th>Hari</th>
                            <th>ID</th>
                            <th>No WA</th>
                            <th>Institusi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($pengawas_list as $p): ?>
                            <tr>
                                <td class="wasling-no" style="text-align:center; font-weight:500;"><?= $no++ ?></td>
                                <td class="wasling-ruang-cell">R<?= htmlspecialchars($p['ruang']) ?></td>
                                <td class="wasling-name"><?= htmlspecialchars($p['nama_pengawas']) ?></td>
                                <td class="wasling-ruang-cell">Hari <?= htmlspecialchars($p['hari']) ?></td>
                                <td class="wasling-id"><?= htmlspecialchars($p['id_pengawas']) ?></td>
                                <td class="wasling-lokasi"><?= htmlspecialchars($p['no_wa']) ?></td>
                                <td class="wasling-lokasi"><?= htmlspecialchars($p['institusi']) ?></td>
                                <td class="wasling-aksi-cell">
                                    <div class="wasling-action-buttons">
                                        <button class="wasling-edit-btn" onclick="editPengawas('<?= htmlspecialchars($p['id_pengawas']) ?>')" title="Edit">
                                            ✏️
                                        </button>
                                        <button class="wasling-delete-btn" onclick="hapusPengawas('<?= htmlspecialchars($p['id_pengawas']) ?>')" title="Hapus">
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

<!-- Modal Edit Pengawas -->
<div id="editModal" class="modal" style="display:none;" onclick="if(event.target === this) closeEditModal();">
    <div class="modal-content" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h3>✏️ Edit Pengawas Ruang</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="edit-message" class="form-message" style="display:none;"></div>
            
            <input type="hidden" id="edit_id_pengawas">
            
            <label class="form-label">Nama Pengawas</label>
            <input type="text" id="edit_nama_pengawas" class="form-input" placeholder="Masukkan nama pengawas" required>
            
            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" id="edit_no_wa" class="form-input" placeholder="Masukkan nomor WhatsApp" required>
            
            <label class="form-label">Institusi</label>
            <input type="text" id="edit_institusi" class="form-input" placeholder="Masukkan nama institusi" required>
            
            <label class="form-label">Pilih Hari</label>
            <select id="edit_hari" class="form-input" onchange="updateEditRuangDropdown()">
                <option value="">-- Pilih Hari --</option>
                <option value="1">Hari 1</option>
                <option value="2">Hari 2</option>
            </select>
            
            <label class="form-label">Pilih Ruang</label>
            <select id="edit_ruang" class="form-input">
                <option value="">-- Pilih Hari terlebih dahulu --</option>
            </select>
            
            <input type="hidden" id="edit_lokasi_hidden" value="<?= htmlspecialchars($lokasi_tpu) ?>">
            
            <div class="modal-actions">
                <button class="form-btn" onclick="simpanEditPengawas()" style="background: linear-gradient(120deg, #2979ff, #1e40af);">
                    <span>💾</span>
                    Simpan Perubahan
                </button>
                <button class="form-btn" onclick="closeEditModal()" style="background: linear-gradient(120deg, #64748b, #475569); margin-top: 10px;">
                    <span>❌</span>
                    Batal
                </button>
            </div>
        </div>
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
.form-message {
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 13px;
    font-weight: 500;
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
.card {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.wasling-table-wrapper {
    overflow-x: auto;
    margin-top: 16px;
}
.wasling-detail-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.wasling-detail-table thead {
    background: linear-gradient(120deg, #e0e7ff, #c3dafe);
    color: #0f172a;
}
.wasling-detail-table th {
    padding: 12px 10px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
.wasling-detail-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #e2e8f0;
}
.wasling-name {
    font-weight: 500;
    color: #0f172a;
}
.wasling-id {
    color: #64748b;
    font-size: 12px;
}
.wasling-lokasi {
    color: #475569;
    font-size: 12px;
}
.wasling-ruang-cell {
    text-align: center;
}
.wasling-aksi-cell {
    text-align: center;
}
.wasling-action-buttons {
    display: flex;
    gap: 6px;
    justify-content: center;
}
.wasling-edit-btn, .wasling-delete-btn {
    background: #2979ff;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 4px rgba(41,121,255,0.2);
}
.wasling-delete-btn {
    background: #ef4444;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
}
.wasling-edit-btn:hover, .wasling-delete-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.wasling-edit-btn:active, .wasling-delete-btn:active {
    transform: translateY(0);
}
/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
    box-sizing: border-box;
}
.modal:hover {
    cursor: pointer;
}
.modal-content {
    background: #fff;
    border-radius: 20px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(15,23,42,0.3);
    cursor: default;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
}
.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #0f172a;
}
.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #64748b;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: background-color 0.2s;
}
.modal-close:hover {
    background-color: #f1f5f9;
}
.modal-body {
    padding: 20px;
}
.modal-actions {
    margin-top: 20px;
}
</style>

<script>
// Data ruang untuk hari 1 dan hari 2
const ruangData = {
    '1': <?= json_encode($ruang_hari1) ?>,
    '2': <?= json_encode($ruang_hari2) ?>
};

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

function updateRuangDropdown() {
    const hari = document.getElementById('hari').value;
    const ruangSelect = document.getElementById('ruang');
    
    // Clear existing options
    ruangSelect.innerHTML = '<option value="">-- Pilih Ruang --</option>';
    
    if (hari && ruangData[hari]) {
        ruangData[hari].forEach(function(ruang) {
            const option = document.createElement('option');
            option.value = ruang;
            option.textContent = 'Ruang ' + ruang;
            ruangSelect.appendChild(option);
        });
    } else {
        ruangSelect.innerHTML = '<option value="">-- Pilih Hari terlebih dahulu --</option>';
    }
}

function simpanPengawasRuang() {
    const nama_pengawas = document.getElementById('nama_pengawas').value.trim();
    const no_wa = document.getElementById('no_wa').value.trim();
    const institusi = document.getElementById('institusi').value.trim();
    const hari = document.getElementById('hari').value;
    const ruang = document.getElementById('ruang').value;
    const lokasi = document.getElementById('lokasi_hidden').value;
    
    // Validasi client-side
    if (!nama_pengawas || !no_wa || !institusi || !hari || !ruang) {
        showMessage('Semua field harus diisi!', 'error');
        return;
    }
    
    // Disable button
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span> Menyimpan...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('add_pengawas_ruang', '1');
    formData.append('nama_pengawas', nama_pengawas);
    formData.append('no_wa', no_wa);
    formData.append('institusi', institusi);
    formData.append('hari', hari);
    formData.append('ruang', ruang);
    formData.append('lokasi_hidden', lokasi);
    
    // Send request
    fetch('tambah_pengawas_ruang.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showMessage(data.message, 'success');
            // Reset form
            document.getElementById('nama_pengawas').value = '';
            document.getElementById('no_wa').value = '';
            document.getElementById('institusi').value = '';
            document.getElementById('hari').value = '';
            document.getElementById('ruang').innerHTML = '<option value="">-- Pilih Hari terlebih dahulu --</option>';
            // Reload page after 1.5 seconds
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('Terjadi kesalahan: ' + error, 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<span>💾</span> Simpan Pengawas Ruang';
    });
}

function showMessage(message, type) {
    const messageDiv = document.getElementById('form-message');
    messageDiv.textContent = message;
    messageDiv.className = 'form-message ' + type;
    messageDiv.style.display = 'block';
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

function editPengawas(id_pengawas) {
    // Fetch data pengawas
    fetch(`tambah_pengawas_ruang.php?get_pengawas=1&id_pengawas=${encodeURIComponent(id_pengawas)}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                const pengawas = data.data;
                
                // Isi form edit
                document.getElementById('edit_id_pengawas').value = pengawas.id_pengawas;
                document.getElementById('edit_nama_pengawas').value = pengawas.nama_pengawas;
                document.getElementById('edit_no_wa').value = pengawas.no_wa;
                document.getElementById('edit_institusi').value = pengawas.institusi;
                document.getElementById('edit_hari').value = pengawas.hari;
                
                // Update dropdown ruang berdasarkan hari
                updateEditRuangDropdown();
                
                // Set ruang setelah dropdown ter-update
                setTimeout(() => {
                    document.getElementById('edit_ruang').value = pengawas.ruang;
                }, 100);
                
                // Tampilkan modal
                document.getElementById('editModal').style.display = 'flex';
            } else {
                alert('Gagal mengambil data pengawas: ' + data.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error);
        });
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.getElementById('edit-message').style.display = 'none';
}

function updateEditRuangDropdown() {
    const hari = document.getElementById('edit_hari').value;
    const ruangSelect = document.getElementById('edit_ruang');
    
    // Clear existing options
    ruangSelect.innerHTML = '<option value="">-- Pilih Ruang --</option>';
    
    if (hari && ruangData[hari]) {
        ruangData[hari].forEach(function(ruang) {
            const option = document.createElement('option');
            option.value = ruang;
            option.textContent = 'Ruang ' + ruang;
            ruangSelect.appendChild(option);
        });
    } else {
        ruangSelect.innerHTML = '<option value="">-- Pilih Hari terlebih dahulu --</option>';
    }
}

function simpanEditPengawas() {
    const id_pengawas = document.getElementById('edit_id_pengawas').value;
    const nama_pengawas = document.getElementById('edit_nama_pengawas').value.trim();
    const no_wa = document.getElementById('edit_no_wa').value.trim();
    const institusi = document.getElementById('edit_institusi').value.trim();
    const hari = document.getElementById('edit_hari').value;
    const ruang = document.getElementById('edit_ruang').value;
    const lokasi = document.getElementById('edit_lokasi_hidden').value;
    
    // Validasi client-side
    if (!nama_pengawas || !no_wa || !institusi || !hari || !ruang) {
        showEditMessage('Semua field harus diisi!', 'error');
        return;
    }
    
    // Disable button
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span> Menyimpan...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('edit_pengawas_ruang', '1');
    formData.append('id_pengawas', id_pengawas);
    formData.append('nama_pengawas', nama_pengawas);
    formData.append('no_wa', no_wa);
    formData.append('institusi', institusi);
    formData.append('hari', hari);
    formData.append('ruang', ruang);
    formData.append('lokasi_hidden', lokasi);
    
    // Send request
    fetch('tambah_pengawas_ruang.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            showEditMessage(data.message, 'success');
            // Reload page after 1.5 seconds
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showEditMessage(data.message, 'error');
        }
    })
    .catch(error => {
        showEditMessage('Terjadi kesalahan: ' + error, 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function showEditMessage(message, type) {
    const messageDiv = document.getElementById('edit-message');
    messageDiv.textContent = message;
    messageDiv.className = 'form-message ' + type;
    messageDiv.style.display = 'block';
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
}

function hapusPengawas(id_pengawas) {
    if (!confirm('Yakin ingin menghapus pengawas ini? Data yang terkait juga akan dihapus.')) return;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('delete_pengawas_ruang', '1');
    formData.append('id_pengawas', id_pengawas);
    
    // Send request
    fetch('tambah_pengawas_ruang.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            alert(data.message);
            location.reload();
        } else {
            alert('Gagal menghapus: ' + data.message);
        }
    })
    .catch(error => {
        alert('Terjadi kesalahan: ' + error);
    });
}

//tambahan ane
function showForm(type) {
    document.getElementById("form-wasrung-baru").style.display =
        (type === "baru") ? "block" : "none";

    document.getElementById("form-wasrung-existing").style.display =
        (type === "existing") ? "block" : "none";
}

function updateRuangExisting() {
    let hari = document.getElementById("hari_w").value;
    let lokasi = document.getElementById("lokasi_hidden").value;

    if (hari === "") {
        document.getElementById('ruang_w').innerHTML =
            "<option value=''>-- Pilih Hari terlebih dahulu --</option>";
        return;
    }

    fetch("ajax_get_ruang.php?hari=" + hari + "&lokasi=" + lokasi)
        .then(res => res.text())
        .then(data => {
            document.getElementById('ruang_w').innerHTML = data;
        });
}

function simpanWasrungExisting() {

    let wasrung_id = document.getElementById("wasrung_id").value;
    let hari = document.getElementById("hari_w").value;
    let ruang = document.getElementById("ruang_w").value;
    let lokasi = document.getElementById("lokasi_hidden").value;
    let msg = document.getElementById("msg-wasrung-existing");

    if (wasrung_id === "" || hari === "" || ruang === "") {
        msg.style.display = "block";
        msg.innerHTML = "<div style='color:red;'>Semua field wajib diisi.</div>";
        return;
    }

    let formData = new FormData();
    formData.append("save_existing", "1");
    formData.append("wasrung_id", wasrung_id);
    formData.append("hari", hari);
    formData.append("ruang", ruang);
    formData.append("lokasi", lokasi);

    fetch("ajax_simpan_wasrung_existing.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        msg.style.display = "block";
        msg.innerHTML = data;
    });
}


</script>

