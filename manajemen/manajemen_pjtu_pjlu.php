<?php
session_start();
include "../config.php";

// =====================================================
// VALIDASI AKSES
// =====================================================
if (!isset($_SESSION['manajemen_unlock'])) {
    echo "<div style='padding:20px;text-align:center;color:#c33;'>Akses ditolak</div>";
    exit;
}

// =====================================================
// SIMPAN DATA (INSERT / UPDATE)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = $_POST['id'] ?? "";
    $nama      = $_POST['nama'] ?? "";
    $username  = $_POST['username'] ?? "";
    $password  = $_POST['password'] ?? "";
    $role      = $_POST['role'] ?? "";
    $lokasi    = $_POST['lokasi'] ?? "";

    if ($id == "") {
        // INSERT
        $stmt = $conn2->prepare("
            INSERT INTO pj_setting (nama, username, password, role, lokasi)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $nama, $username, $password, $role, $lokasi);
    } else {
        // UPDATE
        $stmt = $conn2->prepare("
            UPDATE pj_setting 
            SET nama=?, username=?, password=?, role=?, lokasi=? 
            WHERE id = ?
        ");
        $stmt->bind_param("sssssi", $nama, $username, $password, $role, $lokasi, $id);
    }

    if ($stmt->execute()) {
        echo "OK";
    } else {
        echo "ERROR: " . $stmt->error;
    }

    exit;
}

// ======================================================================
// AMBIL DATA UNTUK TABEL
// ======================================================================
$q = mysqli_query($conn2, "SELECT * FROM pj_setting ORDER BY id DESC");
?>

<style>
.table-box {
    width: 100%;
    margin-top: 20px;
}
.input-box {
    padding: 10px;
    width: 100%;
    margin-bottom: 10px;
}
.btn {
    padding: 8px 14px;
    border: none;
    background: #667eea;
    color: white;
    border-radius: 6px;
    cursor: pointer;
}
.btn:hover {
    opacity: .8;
}
.btn-sm {
    padding: 6px 12px;
}
</style>

<h2 style="margin-bottom:20px;"><i class="fa-solid fa-users-gear"></i> Manajemen PJTU / PJLU</h2>

<div id="notif" style="margin-bottom:15px;"></div>

<!-- FORM -->
<div style="background:#fff;padding:20px;border-radius:10px;margin-bottom:25px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
    <h3>Form Input</h3>

    <input type="hidden" id="id">

    <label>Nama</label>
    <input type="text" id="nama" class="input-box">

    <label>Username</label>
    <input type="text" id="username" class="input-box">

    <label>Password</label>
    <input type="text" id="password" class="input-box">

    <label>Role</label>
    <select id="role" class="input-box">
        <option value="">-- pilih role --</option>
        <option value="DIREKTUR">DIREKTUR</option>
        <option value="MANAJER">MANAJER</option>
    </select>

    <label>Lokasi</label>
    <input type="text" id="lokasi" class="input-box">

    <button class="btn" onclick="simpanData()">Simpan</button>
</div>

<!-- TABEL -->
<div class="table-box">
    <table width="100%" border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
        <tr style="background:#667eea;color:white;">
            <th width="50">ID</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Password</th>
            <th>Role</th>
            <th>Lokasi</th>
            <th width="80">Edit</th>
        </tr>

        <?php while ($d = mysqli_fetch_assoc($q)): ?>
        <tr>
            <td><?= $d['id'] ?></td>
            <td><?= htmlspecialchars($d['nama']) ?></td>
            <td><?= htmlspecialchars($d['username']) ?></td>
            <td><?= htmlspecialchars($d['password']) ?></td>
            <td><?= $d['role'] ?></td>
            <td><?= $d['lokasi'] ?></td>
            <td>
                <button class="btn-sm" onclick='editData(<?= json_encode($d) ?>)'>Edit</button>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<script>
function simpanData() {
    let formData = new FormData();
    formData.append("id", document.getElementById("id").value);
    formData.append("nama", document.getElementById("nama").value);
    formData.append("username", document.getElementById("username").value);
    formData.append("password", document.getElementById("password").value);
    formData.append("role", document.getElementById("role").value);
    formData.append("lokasi", document.getElementById("lokasi").value);

    fetch("manajemen_pjtu_pjlu.php", {
        method: "POST",
        body: formData,
        credentials: "include"
    })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === "OK") {
            document.getElementById("notif").innerHTML =
                "<div style='padding:10px;background:#c6f6d5;color:#22543d;border-radius:6px;'>Data berhasil disimpan</div>";
            loadPage("manajemen_pjtu_pjlu.php");
        } else {
            document.getElementById("notif").innerHTML =
                "<div style='padding:10px;background:#fed7d7;color:#c53030;border-radius:6px;'>"+res+"</div>";
        }
    });
}

function editData(d) {
    document.getElementById("id").value = d.id;
    document.getElementById("nama").value = d.nama;
    document.getElementById("username").value = d.username;
    document.getElementById("password").value = d.password;
    document.getElementById("role").value = d.role;
    document.getElementById("lokasi").value = d.lokasi;
}
</script>
