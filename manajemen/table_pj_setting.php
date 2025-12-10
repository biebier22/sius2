<?php
session_start();
include "../config.php";

// Cek akses minimal (biar aman ketika diakses langsung)
if (
    empty($_SESSION['manajemen_unlock']) ||
    !in_array($_SESSION['role'], ['MANAJER', 'DIREKTUR'])
) {
    echo "<tr><td colspan='7' style='text-align:center;color:#c33;'>Akses ditolak</td></tr>";
    exit;
}

// Ambil data
$q = mysqli_query($conn2, "SELECT * FROM pj_setting ORDER BY id DESC");

if (mysqli_num_rows($q) > 0) {
    $no = 1;

    while ($r = mysqli_fetch_assoc($q)) {
        $badge = strtolower($r['role']);

        echo "
        <tr>
            <td>{$no}</td>
            <td><b>{$r['id_pj']}</b></td>
            <td>{$r['nama']}</td>
            <td>@{$r['username']}</td>
            
            <td>
                <span class='badge-role badge-{$badge}'>
                    {$r['role']}
                </span>
            </td>

            <td>
                <span class='location-pill'>
                    <i class='fa-solid fa-location-dot'></i> {$r['lokasi']}
                </span>
            </td>

            <td>{$r['masa']}</td>
        </tr>";

        $no++;
    }

} else {
    echo "<tr><td colspan='7' class='text-center'>Belum ada data</td></tr>";
}
?>
