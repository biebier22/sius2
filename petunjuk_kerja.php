<?php
// File: petunjuk_kerjaa.php (Asumsi file ini akan dimuat di content area)

// Tidak perlu koneksi database jika ini adalah halaman statik panduan, 
// tetapi kita tambahkan style container agar sesuai template
?>

<style>
/* Style tambahan untuk konten panduan */
.panduan-container {
    padding: 20px;
}

.panduan-section {
    margin-bottom: 30px;
    padding: 20px;
    border-left: 5px solid #2979ff;
    background-color: #f8fafc;
    border-radius: 8px;
}

.panduan-section h3 {
    margin-top: 0;
    color: #0f172a;
    font-size: 18px;
    font-weight: 600;
}

.panduan-section p {
    color: #475569;
    font-size: 14px;
    line-height: 1.6;
}

.panduan-section ul {
    list-style-type: disc;
    padding-left: 20px;
    margin-top: 10px;
}

.panduan-section li {
    margin-bottom: 8px;
    color: #475569;
    font-size: 14px;
}

.warning {
    background-color: #fef3c7;
    border-left-color: #f59e0b;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.warning p {
    color: #92400e;
    font-weight: 500;
}
</style>

<div class="card">
    <div class="panduan-container">
        <h2 style="color: #0f172a; font-size: 24px; margin-bottom: 10px;">
            📘 Petunjuk Kerja Aplikasi Ujian
        </h2>
        <p style="color: #64748b; font-size: 15px; margin-bottom: 30px;">
            Dokumen ini berisi panduan, alur kerja, dan prosedur standar operasional untuk setiap peran dalam sistem.
        </p>

        <div class="panduan-section">
            <h3>1. Panduan Umum</h3>
            <p>
                Pastikan Anda selalu menggunakan *browser* yang ter-*update* (Chrome/Firefox) dan *refresh* halaman (*hard reload*) jika menemukan masalah tampilan.
            </p>
            <ul>
                <li>**Masa Ujian:** <?php echo "20252"; ?></li>
                <li>**Tanggung Jawab:** Jaga kerahasiaan *username* dan *password* Anda.</li>
                <li>**Pelaporan *Error*:** Segera laporkan kepada Koordinator Ujian jika terjadi *error* fatal.</li>
            </ul>
        </div>

        <div class="panduan-section">
            <h3>2. Alur Kerja Wasling (Pengawas Keliling)</h3>
            <p>
                Peran Wasling adalah memverifikasi data serah terima dari Pengawas Ruang (Wasrung).
            </p>
            <ul>
                <li>Buka menu **Verifikasi Berita Acara**.</li>
                <li>Pilih Ruang dan Hari Ujian yang akan diperiksa.</li>
                <li>Pastikan jumlah **Naskah**, **LJU Terisi**, dan **LJU Kosong** sudah benar sesuai yang diserahkan Wasrung.</li>
                <li>Isi Tanda Tangan Wasling, lalu tekan **Verifikasi Data & Simpan**.</li>
                <li>Setelah tersimpan, status di sisi Wasrung akan berubah menjadi **✔️ OK**.</li>
            </ul>
        </div>
        
        <div class="panduan-section">
            <h3>3. Alur Kerja Wasrung (Pengawas Ruang)</h3>
            <p>
                Peran Wasrung adalah menginput data hasil ujian per jam.
            </p>
            <ul>
                <li>Buka halaman **Berita Acara Penyerahan Hasil Ujian**.</li>
                <li>Pilih *section* **Jam Ujian** yang baru selesai.</li>
                <li>Input jumlah **Naskah**, **LJU Terisi**, dan **LJU Kosong** yang dikumpulkan.</li>
                <li>Pastikan jumlah **LJU Terisi** + **LJU Kosong** sama dengan jumlah **Naskah** yang disediakan.</li>
                <li>Tekan **Simpan & Serahkan Jam X**.</li>
            </ul>
        </div>

        <div class="warning">
            <p>⚠️ **Penting:** Semua data yang sudah diverifikasi oleh Wasling tidak dapat diubah lagi oleh Wasrung.</p>
        </div>

    </div>
</div>