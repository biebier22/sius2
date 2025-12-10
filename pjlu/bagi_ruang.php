<?php
session_start();
include "../config.php";

if (!isset($_SESSION['pjtu_unlock'])) {
    echo "Akses ditolak";
    exit;
}

if (!isset($conn2)) { echo "DB Error"; exit; }

$current_lokasi = $_SESSION['lokasi'];
$masa = "20252";

/*
|--------------------------------------------------------------------------
| 1️⃣ PROSES MULTIPLE INSERT
|--------------------------------------------------------------------------
*/
if (isset($_POST['insert_batch'])) {

    $rows = json_decode($_POST['data'], true);

    if (!$rows || count($rows) == 0) {
        echo "Tidak ada data yang dikirim.";
        exit;
    }

    $stmt = $conn2->prepare("
        INSERT INTO wasling_ruang (id_wasling, no_ruang, id_pengawas, hari, lokasi)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($rows as $r) {

        $id_wasling = $r['id_wasling'];
        $ruang = $r['no_ruang'];
        $hari = intval($r['hari']);

        // Cari id_pengawas untuk ruang tsb
        $q = $conn2->prepare("
            SELECT id_pengawas 
            FROM pengawas_ruang
            WHERE ruang = ? AND lokasi = ? AND masa = ?
            LIMIT 1
        ");
        $q->bind_param("iss", $ruang, $current_lokasi, $masa);
        $q->execute();
        $res = $q->get_result();

        if ($row = $res->fetch_assoc()) {
            $id_pengawas = $row['id_pengawas'];
        } else {
            continue; // skip jika tidak ditemukan
        }

        $stmt->bind_param("sssis", $id_wasling, $ruang, $id_pengawas, $hari, $current_lokasi);
        $stmt->execute();
    }

    echo "ok";
    exit;
}

/*
|--------------------------------------------------------------------------
| BAGIAN SELECT WASLING & RUANG (UI)
|--------------------------------------------------------------------------
*/

$wasling = $conn2->query("
    SELECT id_wasling, nama_wasling
    FROM wasling 
    WHERE lokasi_tpu = '$current_lokasi'
    ORDER BY nama_wasling
");

$rooms = $conn2->query("
    SELECT DISTINCT ruang
    FROM pengawas_ruang
    WHERE lokasi='$current_lokasi' AND masa='$masa'
    ORDER BY CAST(ruang AS SIGNED)
");

?>
<div class="card">
    <h3>Bagi Ruang Untuk Wasling</h3>
    <p style="color:#6a4ff7; font-weight:bold;">
        Lokasi Aktif: <?= htmlspecialchars($current_lokasi) ?>
    </p>

    <select id="wasling_select" class="input">
        <option value="">-- Pilih Wasling --</option>
        <?php while($w = $wasling->fetch_assoc()): ?>
            <option value="<?= $w['id_wasling'] ?>">
                <?= $w['nama_wasling'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <select id="hari_select" class="input">
        <option value="">-- Pilih Hari --</option>
        <option value="1">Hari 1</option>
        <option value="2">Hari 2</option>
    </select>

    <select id="ruang_select" class="input">
        <option value="">-- Pilih Ruang --</option>
        <?php while($r = $rooms->fetch_assoc()): ?>
            <option value="<?= $r['ruang'] ?>"><?= $r['ruang'] ?></option>
        <?php endwhile; ?>
    </select>

    <button class="btn" onclick="addTemp()">+ Tambah Ruang</button>
</div>

<!-- LIST PREVIEW -->
<div class="card">
    <h4>Daftar Ruang Yang Akan Disimpan</h4>

    <table id="temp_table">
        <thead>
            <tr>
                <th>Ruang</th>
                <th>Hari</th>
                <th>Act</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <button class="btn" style="margin-top:15px;" onclick="saveAll()">
        ✔ Simpan Semua
    </button>
</div>

<style>
.input { width:100%; padding:12px; margin-top:10px; border-radius:10px; }
.btn { width:100%; padding:12px; background:#6a4ff7; color:white; border:0; border-radius:10px; margin-top:10px; cursor:pointer; }
.card { background:white; padding:20px; border-radius:15px; box-shadow:0 4px 12px rgba(0,0,0,0.1); margin-bottom:20px; }
table { width:100%; border-collapse: collapse; margin-top:15px; }
th,td { border:1px solid #ccc; padding:10px; text-align:center; }
</style>

<script>
let tempList = [];

function addTemp() {
    let was = document.getElementById("wasling_select").value;
    let hari = document.getElementById("hari_select").value;
    let ruang = document.getElementById("ruang_select").value;

    if (!was || !hari || !ruang) {
        alert("Silakan pilih wasling, hari, dan ruang!");
        return;
    }

    // Tambah ke array sementara
    tempList.push({
        id_wasling: was,
        hari: hari,
        no_ruang: ruang
    });

    renderTemp();
}

function renderTemp() {
    let tbody = document.querySelector("#temp_table tbody");
    tbody.innerHTML = "";

    tempList.forEach((r, i) => {
        tbody.innerHTML += `
            <tr>
                <td>${r.no_ruang}</td>
                <td>Hari ${r.hari}</td>
                <td>
                    <button onclick="hapusTemp(${i})">Hapus</button>
                </td>
            </tr>
        `;
    });
}

function hapusTemp(i) {
    tempList.splice(i, 1);
    renderTemp();
}

function saveAll() {
    if (tempList.length === 0) {
        alert("Tidak ada data untuk disimpan.");
        return;
    }

    let f = new FormData();
    f.append("insert_batch", "1");
    f.append("data", JSON.stringify(tempList));

    fetch("bagi_ruang.php", { method:"POST", body:f })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === "ok") {
            alert("✔ Semua data berhasil disimpan!");
            tempList = [];
            renderTemp();
        } else {
            alert(res);
        }
    });
}
</script>
