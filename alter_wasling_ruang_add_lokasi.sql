-- ============================================
-- ALTER TABLE: Tambah kolom lokasi ke wasling_ruang
-- ============================================
-- Jalankan query ini di phpMyAdmin atau MySQL client
-- untuk menambahkan kolom lokasi ke tabel wasling_ruang

ALTER TABLE `wasling_ruang`
ADD COLUMN `lokasi` varchar(255) NOT NULL AFTER `id_pengawas`;

-- ============================================
-- UPDATE DATA EXISTING (Opsional)
-- ============================================
-- Jika ada data existing di wasling_ruang, update lokasi dari tabel wasling
-- Uncomment query di bawah ini jika perlu update data existing

-- UPDATE wasling_ruang wr
-- INNER JOIN wasling w ON wr.id_wasling = w.id_wasling
-- SET wr.lokasi = w.lokasi_tpu
-- WHERE wr.lokasi = '' OR wr.lokasi IS NULL;

