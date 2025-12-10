<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard Awal</title>

<link href="dist/css/style.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root {
    color-scheme: light;
}
* {
    box-sizing: border-box;
}
body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(160deg, #0066b2, #00bcd4);
    font-family: 'Segoe UI', sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    color: #102a43;
}
.app-shell {
    width: 100%;
    max-width: 420px;
    height: 90vh;
    max-height: 820px;
    background: #f9fbff;
    border-radius: 28px;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.app-header {
    padding: 24px 28px 16px;
    background: linear-gradient(120deg, #00bcd4, #2979ff);
    color: #fff;
}
.app-header h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}
.app-header p {
    margin: 6px 0 0;
    font-size: 14px;
    opacity: 0.85;
}
.content-area {
    flex: 1;
    padding: 28px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.module-card {
    background: #fff;
    border-radius: 22px;
    padding: 26px;
    box-shadow: 0 15px 40px rgba(74, 106, 149, 0.12);
    text-align: center;
    animation: fadeIn 200ms ease-in;
}
.module-icon {
    width: 84px;
    height: 84px;
    border-radius: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    color: #fff;
    margin-bottom: 18px;
}
.module-title {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
}
.module-desc {
    margin: 8px 0 26px;
    color: #5f6b7b;
    font-size: 15px;
    line-height: 1.5;
}
.cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 26px;
    border-radius: 999px;
    background: #00796b;
    color: #fff;
    text-decoration: none;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.cta-btn:hover {
    background: #00695c;
}
.tab-bar {
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 10px 12px;
    background: rgba(248, 250, 252, 0.98);
    border-top: 1px solid rgba(15, 23, 42, 0.05);
}
.tab-btn {
    flex: 1;
    border: none;
    background: none;
    padding: 8px 0;
    text-align: center;
    color: #64748b;
    font-size: 12px;
    letter-spacing: 0.4px;
    transition: color 0.2s;
}
.tab-btn i {
    font-size: 18px;
    display: block;
    margin-bottom: 4px;
}
.tab-btn[aria-selected="true"] {
    color: #0066b2;
    font-weight: 600;
}
.tab-btn[aria-selected="true"] i {
    transform: scale(1.1);
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 520px) {
    body {
        padding: 0;
    }
    .app-shell {
        height: 100vh;
        border-radius: 0;
        max-height: none;
    }
}
</style>

</head>
<body>

<div class="app-shell">
    <header class="app-header">
        <h1>Dashboard SIUS ver.2</h1>
        <p>Pilih peran Anda untuk masuk</p>
    </header>

    <main id="module-content" class="content-area" role="tabpanel" aria-live="polite">
        </main>

    <nav class="tab-bar" role="tablist" aria-label="Pilih peran">
    <button class="tab-btn" role="tab" aria-selected="true" data-module="pj">
        <i class="fa-solid fa-user-tie"></i>
        PJTU/PJLU
    </button>
    <button class="tab-btn" role="tab" aria-selected="false" data-module="wasling">
        <i class="fa-solid fa-user-shield"></i>
        Wasling
    </button>
    <button class="tab-btn" role="tab" aria-selected="false" data-module="wasrung">
        <i class="fa-solid fa-cash-register"></i>
        Wasrung
    </button>
    <button class="tab-btn" role="tab" aria-selected="false" data-module="manajemen">
        <i class="fa-solid fa-utensils"></i>
        Manajemen
    </button>
    <button class="tab-btn" role="tab" aria-selected="false" data-module="petunjuk">
    <i class="fa-solid fa-book"></i>
    Petunjuk Kerja
</button>
</nav>

</div>

<script>
const modules = {
    pj: {
        title: 'PJTU / PJLU',
        desc: 'Silakan login sebagai PJTU atau PJLU untuk mengelola lokasi dan laporan ujian.',
        link: 'pjlu/pjlu_home.php',
        iconClass: 'fa-user-tie',
        color: '#ff7043',
        type: 'form'
    },
    wasling: {
        title: 'Wasling',
        desc: 'Masuk sebagai Wasling untuk mengatur Pengawas Ruang ujian Anda.',
        link: 'wasling/wasling_home.php',
        iconClass: 'fa-user-shield',
        color: '#42a5f5',
        type: 'form'
    },
    manajemen: {
        title: 'Manajemen',
        desc: 'Masuk ke portal Manajemen untuk transaksi dan laporan.',
        link: 'manajemen/manajemen_home.php',
        iconClass: 'fa-utensils',
        color: '#8e24aa',
        type: 'form'
    },
    wasrung: {
        title: 'Wasrung',
        desc: 'Masuk sebagai Pengawas Ruang Ujian.',
        link: 'wasrung/wasrung_home.php',
        iconClass: 'fa-cash-register',
        color: '#fb8c00',
        type: 'form'
    },
    // PERUBAHAN UTAMA DI SINI: type diubah menjadi 'direct_link' atau 'link_list'
    petunjuk: {
        title: 'Petunjuk Kerja',
        desc: 'Dokumen panduan, prosedur, dan peraturan kerja.',
        // Kita akan menggunakan array untuk menampung beberapa link PDF
        links: [
            { name: 'PJTU', file: 'pjlu/iso/4.pdf' },
            { name: 'PJLU', file: 'pjlu/iso/3.pdf' },
            { name: 'Pengawas Ruang', file: 'pjlu/iso/1.pdf' },
            { name: 'Pengawas Keliling', file: 'pjlu/iso/2.pdf' }
        ],
        iconClass: 'fa-book',
        color: '#00bcd4',
        type: 'link_list' // Jenis baru untuk tampilan langsung
    }
};


const contentArea = document.getElementById('module-content');
const buttons = Array.from(document.querySelectorAll('.tab-btn'));

function renderModule(key) {
    const data = modules[key];
    if (!data) return;

    // --- LOGIKA TAMPILAN BARU ---
    if (data.type === 'form') {
        // Tampilan untuk PJTU/Wasling/Wasrung/Manajemen (Login Form)
        contentArea.innerHTML = `
            <article class="module-card">
                <div class="module-icon" style="background:${data.color}">
                    <i class="fa-solid ${data.iconClass}"></i>
                </div>
                <h2 class="module-title">${data.title}</h2>
                <p class="module-desc">${data.desc}</p>
                <form action="${data.link}" method="POST" style="text-align:left;margin-top:10px;">
                    <label style="font-size:13px;color:#475569;">Username</label>
                    <input type="text" name="username" required
                            style="width:100%;padding:10px 12px;margin-top:4px;margin-bottom:10px;
                                     border-radius:10px;border:1px solid #cbd5f5;font-size:14px;">

                    <label style="font-size:13px;color:#475569;">Password</label>
                    <input type="password" name="password" required
                            style="width:100%;padding:10px 12px;margin-top:4px;margin-bottom:16px;
                                     border-radius:10px;border:1px solid #cbd5f5;font-size:14px;">

                    <button type="submit" class="cta-btn" style="width:100%;justify-content:center;">
                        Login
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            </article>
        `;
    } else if (data.type === 'link_list') {
        // Tampilan untuk Petunjuk Kerja (List Tautan PDF/Dokumen)
        let linksHTML = data.links.map(link => `
            <a href="${link.file}" target="_blank" class="cta-btn" 
               style="width:100%; justify-content:flex-start; margin-bottom:10px; background:${data.color};">
                <i class="fa-solid fa-file-pdf"></i>
                ${link.name}
            </a>
        `).join('');

        contentArea.innerHTML = `
            <article class="module-card" style="text-align:left;">
                <div class="module-icon" style="background:${data.color}; margin-left: auto; margin-right: auto;">
                    <i class="fa-solid ${data.iconClass}"></i>
                </div>
                <h2 class="module-title" style="text-align:center;">${data.title}</h2>
                <p class="module-desc" style="text-align:center;">${data.desc}</p>
                <div style="margin-top: 20px;">
                    ${linksHTML}
                </div>
            </article>
        `;
    }
    // --- AKHIR LOGIKA TAMPILAN BARU ---
}

buttons.forEach(btn => {
    btn.addEventListener('click', () => {
        const target = btn.dataset.module;
        buttons.forEach(b => b.setAttribute('aria-selected', b === btn ? 'true' : 'false'));
        renderModule(target);
    });
});

renderModule('pj');
</script>

</body>
</html>