-- ============================================
-- ALTER TABLE: Tambah kolom jam_ke ke berita_acara_serah_terima
-- ============================================
-- Jalankan query ini di phpMyAdmin atau MySQL client
-- untuk menambahkan kolom jam_ke ke tabel berita_acara_serah_terima

ALTER TABLE `berita_acara_serah_terima`
ADD COLUMN `jam_ke` int(11) NOT NULL DEFAULT 1 AFTER `ruang`;

-- Update unique constraint untuk include jam_ke
ALTER TABLE `berita_acara_serah_terima`
DROP INDEX `unique_ruang_hari`,
ADD UNIQUE KEY `unique_ruang_hari_jam` (`id_wasling`, `id_pengawas`, `hari`, `ruang`, `jam_ke`, `masa`);

