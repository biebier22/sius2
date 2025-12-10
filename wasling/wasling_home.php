<?php
session_start();
include "../config.php";

$showDashboard = false;
$notif = "";

// Default role hanya untuk tampilan judul ketika belum login
$role = "Wasling";

// =============================
//         LOGIN PROCESS
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {

    if (!isset($conn2) || $conn2->connect_error) {
        $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Database connection (conn2) tidak ditemukan.</div>";
    } else {

        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Ambil wasling dari tabel wasling menggunakan id_wasling sebagai username
        $stmt = $conn2->prepare("SELECT * FROM wasling WHERE id_wasling = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $q = $stmt->get_result();

        if ($q && $q->num_rows > 0) {
            $d = $q->fetch_assoc();
            $stmt->close();

            // Cek password (default 123456, atau dari kolom password jika ada)
            $stored_password = isset($d['password']) ? trim($d['password']) : '123456';
            
            // Jika password kosong di database, gunakan default 123456
            if (empty($stored_password)) {
                $stored_password = '123456';
            }

            if ($stored_password === $password) {
                $namaWasling = $d['nama_wasling'] ?? $username;

                // Set session
                $_SESSION['wasling_unlock'] = true;
                $_SESSION['id_wasling'] = $d['id_wasling'];
                $_SESSION['nama_wasling'] = $namaWasling;
                $_SESSION['lokasi_tpu'] = $d['lokasi_tpu'] ?? '';

                header("Location: wasling_home.php");
                exit;
            } else {
                $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Login Gagal: Password salah.</div>";
            }
        } else {
            $notif = "<div style='color:red;margin-top:10px;font-weight:bold;'>Login Gagal: Username (ID Wasling) tidak ditemukan.</div>";
        }
    }
}

// =============================
//         SESSION CHECK
// =============================
if (isset($_SESSION['wasling_unlock']) && $_SESSION['wasling_unlock'] === true) {
    $showDashboard = true;
    $namaWasling = $_SESSION['nama_wasling'];
    $idWasling = $_SESSION['id_wasling'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $showDashboard ? "Dashboard Wasling" : "Login Wasling"; ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(160deg, #0066b2, #00bcd4);
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #102a43;
        }
        .app-shell {
            width: 100%;
            max-width: 420px;
        }
        .app-header {
            text-align: center;
            color: white;
            margin-bottom: 32px;
        }
        .app-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .app-header p {
            font-size: 14px;
            margin: 0;
            opacity: 0.95;
        }
        .login-box {
            background: white;
            border-radius: 24px;
            padding: 32px 24px;
            box-shadow: 0 20px 60px rgba(15,23,42,0.3);
        }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 8px;
            text-align: center;
        }
        .login-subtitle {
            font-size: 13px;
            color: #64748b;
            text-align: center;
            margin: 0 0 24px;
        }
        .input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #cbd5f5;
            border-radius: 12px;
            font-size: 15px;
            margin-bottom: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }
        .input:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 0 4px rgba(41,121,255,0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(120deg, #0066b2, #00bcd4);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 8px;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .notif {
            margin-top: 16px;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
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
        @media (max-width: 520px) {
            body { padding: 0; }
            .app-shell {
                height: 100vh;
                max-height: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

<?php if(!$showDashboard): ?>
<div class="app-shell">
    <header class="app-header">
        <div class="app-header-main">
            <h1>Wasling</h1>
            <p>Masuk menggunakan akun Wasling Anda</p>
        </div>
    </header>

    <main class="app-body">
        <div class="login-box">
            <h2 class="login-title">Login</h2>
            <p class="login-subtitle">Masukkan ID Wasling dan password untuk mengakses dashboard.</p>

            <form method="POST" action="wasling_home.php">
                <label style="font-size:13px;color:#475569;display:block;margin-bottom:6px;">ID Wasling (Username)</label>
                <input type="text" name="username" class="input" placeholder="Masukkan ID Wasling" required autofocus>

                <label style="font-size:13px;color:#475569;display:block;margin-bottom:6px;">Password</label>
                <input type="password" name="password" class="input" placeholder="Masukkan Password" required>

                <button type="submit" class="btn">
                    Login
                    <span style="font-size:14px;">➜</span>
                </button>
            </form>

            <?php if (!empty($notif)): ?>
                <div class="notif"><?php echo $notif; ?></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php else: ?>
<div class="app-shell">
    <header class="app-header">
        <button class="menu-toggle" type="button" id="menu-toggle" aria-label="Buka menu">
            <span></span>
        </button>
        <div class="app-header-main">
            <h1>Dashboard Wasling</h1>
            <p>Ringkasan & pengawasan ruang ujian</p>
        </div>
    </header>

    <main class="app-body">
        <section>
            <div class="dash-header">
                <div>
                    <h2 class="dash-title">Selamat Datang</h2>
                    <p class="dash-sub">
                        <?php echo htmlspecialchars($_SESSION['nama_wasling'] ?? 'Wasling'); ?><br>
                        ID: <b><?php echo htmlspecialchars($_SESSION['id_wasling'] ?? 'N/A'); ?></b>
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
                        <div class="purple-item" onclick="loadPage('rekap_ruang.php')">
                            📄
                            <small>Rekap Ruang</small>
                        </div>
                        <div class="purple-item" onclick="loadPage('jam_ujian.php')">
                            ⏱️
                            <small>Jam Ujian</small>
                        </div>
                        <div class="purple-item" onclick="loadPage('catatan_temuan.php')">
                            📝
                            <small>Catatan Temuan</small>
                        </div>
                        <div class="purple-item" onclick="loadPage('tandatangan.php')">
                            ✍️
                            <small>Tanda Tangan</small>
                        </div>
                        <div class="purple-item" onclick="loadPage('berita_acara_list.php')">
                            📋
                            <small>Berita Acara</small>
                        </div>
                    </div>
                </div>
            </div>

            <div id="content-area">
                <div class="card">Silakan pilih menu</div>
            </div>
        </section>
    </main>
</div>

<?php if ($showDashboard): ?>
<div class="backdrop" id="drawer-backdrop"></div>
<nav class="side-drawer" id="side-drawer" aria-label="Menu utama">
    <h2 class="drawer-title">Menu Utama</h2>
    <p class="drawer-sub"><?php echo htmlspecialchars($_SESSION['nama_wasling'] ?? 'Wasling'); ?> (<?php echo htmlspecialchars($_SESSION['id_wasling'] ?? 'N/A'); ?>)</p>
    <ul class="drawer-menu">
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();location.reload();">
                <span class="icon">🏠</span>
                <span>Beranda Dashboard</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('rekap_ruang.php');">
                <span class="icon">📄</span>
                <span>Rekap Ruang</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('jam_ujian.php');">
                <span class="icon">⏱️</span>
                <span>Jam Ujian</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('catatan_temuan.php');">
                <span class="icon">📝</span>
                <span>Catatan Temuan</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('tandatangan.php');">
                <span class="icon">✍️</span>
                <span>Tanda Tangan</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="closeDrawer();loadPage('berita_acara_list.php');">
                <span class="icon">📋</span>
                <span>Berita Acara</span>
            </button>
        </li>
        <li>
            <button class="drawer-link" type="button" onclick="window.location.href='logout.php';">
                <span class="icon">↩</span>
                <span>Logout</span>
            </button>
        </li>
    </ul>
    <div class="drawer-footer">
        SIUS &mdash; Panel Wasling
    </div>
</nav>
<?php endif; ?>
<?php endif; ?>

<script>
function loadPage(page){
  document.getElementById("content-area").innerHTML =
      '<div class="card">Memuat...</div>';

  fetch(page)
    .then(res => res.text())
    .then(html => {
      document.getElementById("content-area").innerHTML = html;

      // Hapus script lama
      document.querySelectorAll(".dynamic-script").forEach(e => e.remove());

      // Jalankan script dari halaman yang diload
      const scripts = document.querySelectorAll("#content-area script");
      scripts.forEach(scr => {
        const s = document.createElement("script");
        s.classList.add("dynamic-script");
        if (scr.src) s.src = scr.src;
        else s.textContent = scr.innerHTML;
        document.body.appendChild(s);
      });
    })
    .catch(() => {
      document.getElementById("content-area").innerHTML =
          '<div class="card">Gagal memuat halaman</div>';
    });
}

// Side Drawer Functions
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

// Menu Card Toggle
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
</script>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

</body>
</html>