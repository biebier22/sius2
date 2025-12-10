<?php
session_start();
include "../config.php"; 

$showDashboard = false;
$notif = "";

// Default role hanya untuk tampilan judul ketika belum login
$role = "PJLU";

// =============================
//         LOGIN PROCESS
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {

    if (!isset($conn2) || $conn2->connect_error) {
        $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Database connection (conn2) tidak ditemukan.</div>";
    } else {

        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Ambil user dari pj_setting
        $stmt = $conn2->prepare("SELECT * FROM pj_setting WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $q = $stmt->get_result();

        if ($q && $q->num_rows > 0) {
            $d = $q->fetch_assoc();
            $stmt->close();

            // Cek password (plain text sesuai permintaan)
            if (trim($d['password']) === $password) {
                $role = $d['role'];
                $namaUser = $d['nama'] ?? $username;

                // Set session
                $_SESSION['pjtu_unlock'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['nama_user'] = $namaUser;
                $_SESSION['role'] = $role;
                $_SESSION['lokasi'] = $d['lokasi'] ?? '';
                $_SESSION['kode_tpu'] = $d['kode_tpu'] ?? '';

                header("Location: pjlu_home.php");
                exit;
            } else {
                $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Login Gagal: Password salah.</div>";
            }
        } else {
            $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Login Gagal: Username tidak ditemukan.</div>";
        }
    }
}

// =============================
//         SESSION CHECK
// =============================
if (isset($_SESSION['pjtu_unlock'])) {
    $showDashboard = true;
    $namaUser = $_SESSION['nama_user'];
    $role = $_SESSION['role'];
    $current_lokasi = $_SESSION['lokasi'];
    
    // =============================
//     FETCH REKAP UJIAN DATA
// =============================
$rekapData = [];
if (isset($conn2) && !$conn2->connect_error && !empty($current_lokasi)) {

    $masa = '20252';

    $stmt = $conn2->prepare("
        SELECT 
            r.hari_ke,
            r.ruang_ke,
            r.jam1,
            r.jam2,
            r.jam3,
            r.jam4,
            r.jam5,
            r.total,
            e.lokasi,
            e.alamat,
            e.pos_ujian
        FROM 
            rekap_ujian r
        LEFT JOIN 
            e_lokasi_uas e 
            ON r.kode_tpu = e.kode_tpu 
            AND e.masa = ?
            AND CAST(r.ruang_ke AS UNSIGNED) BETWEEN CAST(e.ruang_awal AS UNSIGNED) AND CAST(e.ruang_akhir AS UNSIGNED)
            AND CAST(r.hari_ke AS CHAR) = e.hari
        WHERE 
            r.masa = ? 
            AND e.lokasi = ?
        ORDER BY r.hari_ke, r.ruang_ke ASC
    ");

    $stmt->bind_param("sss", $masa, $masa, $current_lokasi);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $hari = $row['hari_ke'];
        if (!isset($rekapData[$hari])) {
            $rekapData[$hari] = [];
        }
        $rekapData[$hari][] = $row;
    }
    $stmt->close();
}

}

// =============================
//     FETCH DATA BAST
// =============================
$bast = [];
$qBast = mysqli_query($conn2, "
    SELECT lokasi, hari, ruang, jam_ke
    FROM berita_acara_serah_terima
    WHERE masa = '$masa'
");

while ($b = mysqli_fetch_assoc($qBast)) {
    $loc  = $b['lokasi'];
    $hari = $b['hari'];
    $ruang = $b['ruang'];
    $jam  = $b['jam_ke'];

    // tanda bahwa jam ini sudah BAST
    $bast[$loc][$hari][$ruang][$jam] = true;
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showDashboard ? "Dashboard $role" : "Login $role" ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(160deg, #0066b2, #00bcd4);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .app-shell {
            width: 100%;
            max-width: 420px;
            height: 90vh;
            max-height: 820px;
            background: #f9fbff;
            border-radius: 28px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .app-header {
            padding: 18px 20px 12px;
            background: linear-gradient(120deg, #00bcd4, #2979ff);
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .app-header-main {
            flex: 1;
        }
        .app-header h1 {
            margin: 0;
            font-size: 19px;
            font-weight: 600;
        }
        .app-header p {
            margin: 4px 0 0;
            font-size: 13px;
            opacity: .9;
        }
        .menu-toggle {
            background: rgba(15, 23, 42, 0.2);
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            cursor: pointer;
        }
        .menu-toggle span {
            display: block;
            width: 16px;
            height: 2px;
            border-radius: 999px;
            background: #fff;
            position: relative;
        }
        .menu-toggle span::before,
        .menu-toggle span::after {
            content: "";
            position: absolute;
            left: 0;
            width: 16px;
            height: 2px;
            border-radius: 999px;
            background: #fff;
        }
        .menu-toggle span::before { top: -5px; }
        .menu-toggle span::after { top: 5px; }
        .app-body {
            flex: 1;
            padding: 22px 24px 24px;
            overflow-y: auto;
        }
        /* LOGIN CARD */
        .login-box {
            background: #fff;
            border-radius: 22px;
            padding: 26px 22px 24px;
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.10);
            text-align: left;
        }
        .login-title {
            margin: 0 0 6px;
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
        }
        .login-subtitle {
            margin: 0 0 18px;
            font-size: 13px;
            color: #64748b;
        }
        .input {
            width: 100%;
            padding: 10px 12px;
            margin-top: 6px;
            margin-bottom: 10px;
            border-radius: 10px;
            border: 1px solid #cbd5f5;
            font-size: 14px;
        }
        .input:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 0 1px rgba(41,121,255,0.15);
        }
        .btn {
            width: 100%;
            padding: 11px 16px;
            background: #00796b;
            color: #fff;
            border-radius: 999px;
            border: 0;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn:hover { background: #00695c; }
        .notif {
            margin-top: 12px;
            font-size: 13px;
        }
        /* DASHBOARD */
        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .dash-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
        }
        .dash-sub {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }
        .logout-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 7px 12px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 12px;
        }
        .nav-menu {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 8px;
            margin-bottom: 16px;
        }
        .purple-item {
            background: #fff;
            padding: 8px 6px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            color: #4253ff;
            font-size: 16px;
            box-shadow: 0 4px 12px rgba(15,23,42,0.08);
            transition: transform .16s ease, box-shadow .16s ease;
            line-height: 1.2;
        }
        .purple-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(15,23,42,0.12);
        }
        .purple-item small { 
            display:block; 
            margin-top:3px; 
            color:#64748b;
            font-size: 10px;
            line-height: 1.3;
        }
        .menu-card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 10px 26px rgba(15,23,42,0.08);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .menu-card-header {
            padding: 16px 18px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
            transition: background-color 0.2s;
            border-bottom: 1px solid #e2e8f0;
        }
        .menu-card-header:hover {
            background-color: #f8fafc;
        }
        .menu-card-header h3 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }
        .menu-card-header p {
            margin: 0;
        }
        .menu-card-icon {
            font-size: 14px;
            color: #64748b;
            transition: transform 0.3s ease;
            margin-left: 12px;
        }
        .menu-card-content {
            max-height: 500px;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
            opacity: 1;
        }
        .menu-card-content.collapsed {
            max-height: 0;
            opacity: 0;
            padding: 0;
        }
        .menu-card-content.collapsed * {
            display: none;
        }
        .card {
            background: white;
            padding: 18px 16px;
            border-radius: 18px;
            box-shadow: 0 10px 26px rgba(15,23,42,0.08);
            font-size: 14px;
            color: #334155;
        }
        #content-area { min-height: 220px; }
        /* SIDE DRAWER */
        .backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
            z-index: 40;
        }
        .backdrop.show {
            opacity: 1;
            pointer-events: auto;
        }
        .side-drawer {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            max-width: 80%;
            height: 100vh;
            background: #ffffff;
            box-shadow: 3px 0 24px rgba(15,23,42,0.25);
            transform: translateX(-100%);
            transition: transform .22s ease-out;
            z-index: 50;
            padding: 18px 18px 24px;
            display: flex;
            flex-direction: column;
        }
        .side-drawer.show {
            transform: translateX(0);
        }
        .drawer-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 8px;
            color: #0f172a;
        }
        .drawer-sub {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 18px;
        }
        .drawer-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }
        .drawer-menu li {
            margin-bottom: 6px;
        }
        .drawer-link {
            width: 100%;
            text-align: left;
            border: none;
            background: transparent;
            padding: 9px 8px;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .drawer-link span.icon {
            width: 22px;
            text-align: center;
        }
        .drawer-link:hover {
            background: #eef2ff;
        }
        .drawer-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 8px;
            font-size: 12px;
            color: #94a3b8;
        }
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
            background: #eef2ff;
            color: #4253ff;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            min-width: 36px;
            text-align: center;
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

        @media (max-width: 520px) {
            body { padding: 0; }
            .app-shell {
                height: 100vh;
                max-height: none;
                border-radius: 0;
            }
        }
       .rekap-jam {
    background: #eef2ff;
    color: #4253ff;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    min-width: 36px;
    text-align: center;
}

.rekap-jam-bast-ok {
    background: #d1fae5 !important;
    color: #059669 !important;
    font-weight: 700;
    border: 1px solid #34d399;
}



    </style>
</head>
<body>
<div class="app-shell">
    <header class="app-header">
        <?php if ($showDashboard): ?>
            <button class="menu-toggle" type="button" id="menu-toggle" aria-label="Buka menu">
                <span></span>
            </button>
        <?php endif; ?>
        <div class="app-header-main">
            <h1><?= $showDashboard ? "Dashboard $role" : "PJTU / PJLU" ?></h1>
            <p><?= $showDashboard ? "Ringkasan & pengaturan lokasi ujian" : "Masuk menggunakan akun PJTU / PJLU Anda" ?></p>
        </div>
    </header>

    <main class="app-body">
<?php if (!$showDashboard): ?>
        <!-- LOGIN PAGE -->
<div class="login-box">
            <h2 class="login-title">Login</h2>
            <p class="login-subtitle">Masukkan username dan password untuk mengelola lokasi ujian.</p>

    <form method="POST" action="pjlu_home.php">
                <label style="font-size:13px;color:#475569;">Username</label>
        <input type="text" name="username" class="input" placeholder="Masukkan Username" required>

                <label style="font-size:13px;color:#475569;">Password</label>
                <input type="password" name="password" class="input" placeholder="Masukkan Password" required>

                <button type="submit" class="btn">
                    Login
                    <span style="font-size:14px;">➜</span>
                </button>
    </form>

            <?php if (!empty($notif)): ?>
                <div class="notif"><?= $notif ?></div>
            <?php endif; ?>
</div>

<?php else: ?>
        <!-- DASHBOARD -->
        <section>
            <div class="dash-header">
                <div>
                    <h2 class="dash-title">Selamat Datang</h2>
                    <p class="dash-sub">
                        <?= htmlspecialchars($namaUser) ?> (<?= htmlspecialchars($role) ?>)<br>
                        Lokasi: <b><?= htmlspecialchars($current_lokasi) ?></b>
                    </p>
                </div>
        <button class="logout-btn" onclick="window.location.href='logout.php';">Logout</button>
    </div>

    <div class="menu-card">
        <div class="menu-card-header" onclick="toggleMenuCard()">
            <div>
                <h3>📋 Menu Utama</h3>
                <p style="font-size:13px; color:#64748b; margin:0;">Akses cepat ke fitur utama</p>
            </div>
            <span class="menu-card-icon" id="menu-card-icon">▼</span>
        </div>
        <div class="menu-card-content" id="menu-card-content">
    <div class="nav-menu">
        <div class="purple-item" onclick="loadPage('tandatangan.php')">
                ✍️
                <small>Tanda Tangan</small>
            </div>
        <div class="purple-item" onclick="loadPage('jam_ujian.php')">
            ⏱️
            <small>Jam Ujian</small>
        </div>
        <!-- <div class="purple-item" onclick="loadPage('tambah_wasling.php')">
            👥
            <small>Wasling</small>
        </div> -->
        <!-- <div class="purple-item" onclick="loadPage('tambah_pengawas_ruang.php')">
            👨‍🏫
            <small>Pengawas Ruang</small>
        </div> -->
        <div class="purple-item" onclick="loadPage('catatan_temuan.php')">
            📝
            <small>Catatan Temuan</small>
        </div>
        <div class="purple-item" onclick="loadPage('tanya_ai.php')">
            🤖
            <small>Tanya AI</small>
        </div>
        <!-- Menu baru Cetak ISO -->
        <!-- <div class="purple-item" onclick="loadPage('cetak_iso.php')">
            📠
            <small>Cetak ISO</small>
        </div> -->
    </div>
</div>
    </div>

    <div id="content-area">
                <?php if (!empty($rekapData)): ?>
                    <div class="rekap-section">
                        <?php
                        $totalRuang = 0;
                        $totalJam = 0;
                        foreach ($rekapData as $hari => $ruangs) {
                            $totalRuang += count($ruangs);
                            foreach ($ruangs as $r) {
                                $totalJam += ($r['jam1'] + $r['jam2'] + $r['jam3'] + $r['jam4'] + $r['jam5']);
                            }
                        }
                        ?>
                        <div class="rekap-summary">
                            <div class="rekap-summary-item">
                                <p class="rekap-summary-value"><?= count($rekapData) ?></p>
                                <p class="rekap-summary-label">Hari Ujian</p>
                            </div>
                            <div class="rekap-summary-item">
                                <p class="rekap-summary-value"><?= $totalRuang ?></p>
                                <p class="rekap-summary-label">Total Ruang</p>
                            </div>
                            <div class="rekap-summary-item">
                                <p class="rekap-summary-value"><?= $totalJam ?></p>
                                <p class="rekap-summary-label">Total Naskah</p>
                            </div>
                        </div>
                        
                        <?php foreach ($rekapData as $hari => $ruangs): ?>
                            <div class="rekap-card" data-hari="<?= htmlspecialchars($hari) ?>">
                                <div class="rekap-card-header" onclick="toggleRekapCard(this)">
                                    <div class="rekap-card-header-left">
                                        <span class="rekap-card-chevron">▼</span>
                                        <div>
                                            <h3 class="rekap-card-title">Hari Ke-<?= htmlspecialchars($hari) ?></h3>
                                            <p class="rekap-card-subtitle"><?= count($ruangs) ?> ruang ujian</p>
                                        </div>
                                    </div>
                                    <span class="rekap-badge">Hari <?= $hari ?></span>
                                </div>
                                
                                <div class="rekap-card-content">
<?php foreach ($ruangs as $r): ?>
    <?php 
        $lokasi = $r['lokasi'];
        $hari   = $r['hari_ke'];
        $ruang  = $r['ruang_ke'];
    ?>
    <div class="rekap-row">
        <div class="rekap-row-label">Ruang <?= htmlspecialchars($ruang) ?></div>
        <div class="rekap-row-value">

            <div>
                <div class="rekap-jam <?= isset($bast[$lokasi][$hari][$ruang][1]) ? 'rekap-jam-bast-ok' : '' ?>">
                    <?= htmlspecialchars($r['jam1']) ?>
                </div>
                <div class="rekap-jam-label">Jam 1</div>
            </div>

            <div>
                <div class="rekap-jam <?= isset($bast[$lokasi][$hari][$ruang][2]) ? 'rekap-jam-bast-ok' : '' ?>">
                    <?= htmlspecialchars($r['jam2']) ?>
                </div>
                <div class="rekap-jam-label">Jam 2</div>
            </div>

            <div>
                <div class="rekap-jam <?= isset($bast[$lokasi][$hari][$ruang][3]) ? 'rekap-jam-bast-ok' : '' ?>">
                    <?= htmlspecialchars($r['jam3']) ?>
                </div>
                <div class="rekap-jam-label">Jam 3</div>
            </div>

            <div>
                <div class="rekap-jam <?= isset($bast[$lokasi][$hari][$ruang][4]) ? 'rekap-jam-bast-ok' : '' ?>">
                    <?= htmlspecialchars($r['jam4']) ?>
                </div>
                <div class="rekap-jam-label">Jam 4</div>
            </div>

            <div>
                <div class="rekap-jam <?= isset($bast[$lokasi][$hari][$ruang][5]) ? 'rekap-jam-bast-ok' : '' ?>">
                    <?= htmlspecialchars($r['jam5']) ?>
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
                            Belum ada data rekap ujian untuk lokasi ini.
                        </div>
                    </div>
                <?php endif; ?>
    </div>
        </section>
    <?php endif; ?>
    </main>
</div>

<?php if ($showDashboard): ?>
<div class="backdrop" id="drawer-backdrop"></div>
<nav class="side-drawer" id="side-drawer" aria-label="Menu utama">
    <h2 class="drawer-title">Menu Utama</h2>
    <p class="drawer-sub"><?= htmlspecialchars($namaUser) ?> (<?= htmlspecialchars($role) ?>)</p>
    <ul class="drawer-menu">
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();location.reload();">
                <span class="icon">🏠</span>
                <span>Beranda Dashboard</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('tambah_wasling.php');">
                <span class="icon">👥</span>
                <span>Tambah Wasling</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('tambah_pengawas_ruang.php');">
                <span class="icon">👨‍🏫</span>
                <span>Tambah Pengawas Ruang</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('cetak_iso.php');">
                <span class="icon">📄</span>
                <span>Cetak ISO</span>
            </button>
        </li>
        <!-- <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('pjtu_laporan_ujian.php');">
                <span class="icon">📄</span>
                <span>Laporan Ujian</span>
            </button>
        </li> -->

        <!-- <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('catatan_temuan.php');">
                <span class="icon">📝</span>
                <span>Catatan Temuan</span>
            </button>
        </li> -->
        <!-- <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('tanya_ai.php');">
                <span class="icon">🤖</span>
                <span>Tanya AI</span>
            </button>
        </li> -->
        <li>
            <button class="drawer-link" type="button" onclick="window.location.href='logout.php';">
                <span class="icon">↩</span>
                <span>Logout</span>
            </button>
        </li>
    </ul>
    <div class="drawer-footer">
        SIUS &mdash; Panel PJTU/PJLU
    </div>
</nav>

<script>
function loadPage(page){
    document.getElementById("content-area").innerHTML = '<div class="card">Memuat...</div>';

    fetch(page)
      .then(res => res.text())
      .then(html => {
        document.getElementById("content-area").innerHTML = html;

        // Eksekusi ulang JS dari halaman yang di-load
        document.querySelectorAll(".dynamic-script").forEach(e => e.remove());
        document.querySelectorAll("#content-area script").forEach(scr => {
            let s = document.createElement("script");
            s.classList.add("dynamic-script");
            if (scr.src) s.src = scr.src;
            else s.textContent = scr.innerHTML;
            document.body.appendChild(s);
        });
      })
      .catch(err => {
        document.getElementById("content-area").innerHTML =
            '<div class="card" style="color:red;">Gagal memuat halaman.</div>';
      });
}

const drawer = document.getElementById('side-drawer');
const backdrop = document.getElementById('drawer-backdrop');
const toggleBtn = document.getElementById('menu-toggle');

function openDrawer() {
    if (!drawer || !backdrop) return;
    drawer.classList.add('show');
    backdrop.classList.add('show');
}
function closeDrawer() {
    if (!drawer || !backdrop) return;
    drawer.classList.remove('show');
    backdrop.classList.remove('show');
}

if (toggleBtn) {
    toggleBtn.addEventListener('click', openDrawer);
}
if (backdrop) {
    backdrop.addEventListener('click', closeDrawer);
}

function toggleMenuCard() {
    const content = document.getElementById('menu-card-content');
    const icon = document.getElementById('menu-card-icon');
    
    if (content.classList.contains('collapsed')) {
        content.classList.remove('collapsed');
        if (icon) icon.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('collapsed');
        if (icon) icon.style.transform = 'rotate(-90deg)';
    }
}

// Toggle rekap card collapse/expand
function toggleRekapCard(headerElement) {
    const card = headerElement.closest('.rekap-card');
    if (card) {
        card.classList.toggle('expanded');
    }
}
</script>
<?php endif; ?>

</body>
</html>
