<?php
session_start();
include "../config.php";

if (!isset($_SESSION['pjtu_unlock'])) {
    echo "<div class='card'>Akses ditolak.</div>";
    exit;
}

$id_wasling = $_GET['id_wasling'] ?? '';

if ($id_wasling == "") {
    echo "<div class='card'>Wasling tidak valid.</div>";
    exit;
}

$q = $conn2->prepare("
    SELECT 
        wr.id,
        wr.no_ruang,
        wr.hari
    FROM wasling_ruang wr
    WHERE wr.id_wasling = ?
    ORDER BY CAST(wr.no_ruang AS SIGNED) ASC
");
$q->bind_param("s", $id_wasling);
q->execute();
$res = $q->get_result();

?>

<style>
.table-box {
    background: white;
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.room-table {
    width: 100%;
    border-collapse: collapse;
}

.room-table th,
.room-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: center;
}

.room-table th {
    background: #f0f0f0;
}
.btn-small {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
}
.btn-edit { background: #0099ff; color: white; }
.btn-del { background: #ff4444; color: white; }
</style>

<div class="table-box">
    <h4>Daftar Ruang Yang Sudah Diset</h4>

<?php if ($res->num_rows == 0): ?>
    <div style="padding:10px; text-align:center; color:#777;">
        Belum ada ruang yang diset.
    </div>
<?php else: ?>

    <table class="room-table">
        <thead>
            <tr>
                <th>Ruang</th>
                <th>Hari</th>
                <th>Act</th>
            </tr>
        </thead>
        <tbody>
            <?php while($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($r['no_ruang']) ?></td>
                    <td>Hari <?= htmlspecialchars($r['hari']) ?></td>
                    <td>
                        <button class="btn-small btn-edit"
                            onclick="editRuang(<?= $r['id'] ?>)">
                            Edit
                        </button>

                        <button class="btn-small btn-del"
                            onclick="hapusRuang(<?= $r['id'] ?>)">
                            Hapus
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

<?php endif; ?>

</div>

<script>
function editRuang(id) {
    alert("Fitur edit ruang bisa saya buatkan jika diperlukan.");
}
</script>
