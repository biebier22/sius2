-- Tambahkan kolom password ke tabel wasling
ALTER TABLE `wasling`
ADD COLUMN `password` VARCHAR(100) NOT NULL DEFAULT '123456' AFTER `id_wasling`;

-- Update semua wasling yang sudah ada dengan password default 123456
UPDATE `wasling` SET `password` = '123456' WHERE `password` = '' OR `password` IS NULL;

