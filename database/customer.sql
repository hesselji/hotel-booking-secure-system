-- ============================================================
-- Database: luxebooking_db
-- ============================================================
CREATE DATABASE IF NOT EXISTS luxebooking_db;
USE luxebooking_db;

-- ============================================================
-- Tabel 1: users (DS1 - Database User)
-- ============================================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_uuid` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `role` ENUM('customer','admin') DEFAULT 'customer',
  `phone` VARCHAR(20) DEFAULT NULL,
  `id_number` VARCHAR(50) DEFAULT NULL,
  `joined_at` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `user_uuid` (`user_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabel 2: rooms (Master Data Kamar)
-- ============================================================
CREATE TABLE `rooms` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `room_uuid` VARCHAR(50) NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `price_per_night` DECIMAL(12,2) NOT NULL,
  `stock` INT(11) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `emoji` VARCHAR(10) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_uuid` (`room_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabel 3: bookings (DS2 - Database Booking)
-- ============================================================
CREATE TABLE `bookings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_uuid` VARCHAR(50) NOT NULL,
  `customer_email` VARCHAR(100) NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `room_id` INT(11) NOT NULL,
  `room_type` VARCHAR(100) NOT NULL,
  `checkin_date` DATE NOT NULL,
  `checkout_date` DATE NOT NULL,
  `nights` INT(11) NOT NULL,
  `guests` INT(11) DEFAULT 1,
  `total_price` DECIMAL(12,2) NOT NULL,
  `payment_status` ENUM('pending','paid','cancelled','expired') DEFAULT 'pending',
  `metode_pembayaran` VARCHAR(50) DEFAULT NULL,
  `e_voucher` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_uuid` (`booking_uuid`),
  KEY `customer_email` (`customer_email`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `fk_bookings_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`customer_email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabel 4: transactions (DS3 - Database Transaksi)
-- ============================================================
CREATE TABLE `transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transaction_uuid` VARCHAR(50) NOT NULL,
  `booking_id` INT(11) NOT NULL,
  `customer_email` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `method` VARCHAR(50) NOT NULL,
  `status` ENUM('PENDING','SUCCESS','FAILED','EXPIRED') DEFAULT 'PENDING',
  `e_voucher` VARCHAR(50) DEFAULT NULL,
  `payment_date` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_uuid` (`transaction_uuid`),
  KEY `booking_id` (`booking_id`),
  KEY `customer_email` (`customer_email`),
  CONSTRAINT `fk_trans_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_trans_user` FOREIGN KEY (`customer_email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SESSION / TOKEN (Opsional untuk authentication state)
-- ============================================================
CREATE TABLE `user_sessions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `session_token` VARCHAR(255) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_email` (`user_email`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_email`) REFERENCES `users` (`email`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATA SEED (Contoh Data Awal)
-- ============================================================

-- 1. Users (password dalam contoh menggunakan hashing MD5 atau plain, sebaiknya gunakan bcrypt di production)
--    Untuk demo, password 'pass123' -> md5 = 'cbfdac6008f9cab4083784cbd1874f76618d2a97' (contoh plain text tidak disarankan)
--    Di sini saya simpan plain text dulu untuk kemudahan demo (sebaiknya hash di aplikasi)
INSERT INTO `users` (`user_uuid`, `email`, `password`, `name`, `role`, `phone`, `id_number`, `joined_at`) VALUES
('u_demo', 'customer@demo.com', 'pass123', 'Budi Santoso', 'customer', '08123456789', '3201234567890001', '2024-01-15'),
('u_admin_1', 'admin@luxe.com', 'admin123', 'Administrator', 'admin', '082211223344', '1234567890123456', '2024-01-01');

-- 2. Rooms
INSERT INTO `rooms` (`room_uuid`, `type`, `price_per_night`, `stock`, `description`, `emoji`) VALUES
('rm_std', 'Standard Room', 500000, 10, 'Kamar nyaman dengan AC, TV LED, dan kamar mandi privat', '🛏️'),
('rm_dlx', 'Deluxe Room', 850000, 6, 'Pemandangan kota, bathtub, minibar, dan layanan kamar 24 jam', '✨'),
('rm_suite', 'Suite Room', 1500000, 3, 'Ruang tamu terpisah, VIP service, panoramic view, butler pribadi', '👑'),
('rm_fam', 'Family Room', 1100000, 4, 'Cocok untuk keluarga, 2 kamar tidur, dapur kecil, ruang bermain', '🏠');
