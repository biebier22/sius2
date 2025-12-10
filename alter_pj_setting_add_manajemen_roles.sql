-- Tambahkan role DIREKTUR dan MANAJER ke tabel pj_setting
ALTER TABLE `pj_setting` 
MODIFY COLUMN `role` ENUM('PJTU','PJLU','MANAJER','DIREKTUR') NOT NULL;

