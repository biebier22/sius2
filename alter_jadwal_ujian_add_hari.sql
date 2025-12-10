-- ============================================
-- ALTER TABLE: Tambah kolom hari ke jadwal_ujian
-- ============================================
-- Jalankan query ini jika tabel jadwal_ujian sudah ada

ALTER TABLE `jadwal_ujian`
ADD COLUMN `hari` int(11) NOT NULL COMMENT '1 untuk Hari 1, 2 untuk Hari 2' AFTER `masa`;

-- Update unique constraint
ALTER TABLE `jadwal_ujian`
DROP INDEX `unique_masa`,
ADD UNIQUE KEY `unique_masa_hari` (`masa`, `hari`);

