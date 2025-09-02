-- recurring_expenses.recurrence_interval alanına 'quarterly' seçeneğini ekle
ALTER TABLE `recurring_expenses`
MODIFY COLUMN `recurrence_interval` enum('weekly','monthly','quarterly','yearly') NOT NULL;
-- Users tablosuna role ve status alanlarını ekle
ALTER TABLE `users` 
ADD COLUMN `role` enum('admin','user') NOT NULL DEFAULT 'user' AFTER `password`,
ADD COLUMN `status` enum('active','inactive') NOT NULL DEFAULT 'active' AFTER `role`,
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`;

-- Mevcut admin kullanıcısını admin rolü yap
UPDATE `users` SET `role` = 'admin', `status` = 'active' WHERE `email` = 'admin@gmail.com'; 