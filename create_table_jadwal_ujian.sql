-- ============================================
-- CREATE TABLE: jadwal_ujian
-- ============================================
-- Tabel untuk menyimpan jadwal ujian global (tanggal dan jam mulai/selesai)
-- Satu record per masa (global setting untuk semua lokasi)

CREATE TABLE IF NOT EXISTS `jadwal_ujian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `masa` varchar(10) NOT NULL DEFAULT '20252',
  `hari` int(11) NOT NULL COMMENT '1 untuk Hari 1, 2 untuk Hari 2',
  `tanggal_ujian` date NOT NULL,
  `jam1_mulai` time NOT NULL,
  `jam1_selesai` time NOT NULL,
  `jam2_mulai` time NOT NULL,
  `jam2_selesai` time NOT NULL,
  `jam3_mulai` time NOT NULL,
  `jam3_selesai` time NOT NULL,
  `jam4_mulai` time NOT NULL,
  `jam4_selesai` time NOT NULL,
  `jam5_mulai` time NOT NULL,
  `jam5_selesai` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_masa_hari` (`masa`, `hari`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

