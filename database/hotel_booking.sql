-- ============================================================
--  LuxeBooking · Full SQL Schema & Seed Data
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `luxebooking` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `luxebooking`;

DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `room_units`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `id_number` varchar(30) DEFAULT NULL,
  `role` enum('customer','admin') NOT NULL DEFAULT 'customer',
  `joined_at` date NOT NULL DEFAULT curdate(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `rooms` (
  `id` varchar(36) NOT NULL,
  `type` varchar(80) NOT NULL,
  `price_per_night` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `emoji` varchar(8) DEFAULT '?️',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

CREATE TABLE `room_units` (
  `id` varchar(36) NOT NULL,
  `room_id` varchar(36) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_number` (`room_number`),
  KEY `fk_unit_room` (`room_id`),
  CONSTRAINT `fk_unit_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `bookings` (
  `id` varchar(36) NOT NULL,
  `customer_id` varchar(36) NOT NULL,
  `room_unit_id` varchar(36) NOT NULL,
  `checkin_date` date NOT NULL,
  `checkout_date` date NOT NULL,
  `nights` smallint(6) NOT NULL,
  `guests` smallint(6) NOT NULL DEFAULT 1,
  `total_price` decimal(14,2) NOT NULL,
  `payment_status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `e_voucher` varchar(30) DEFAULT NULL,
  `metode_pembayaran` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_booking_cust` (`customer_id`),
  KEY `fk_booking_unit` (`room_unit_id`),
  CONSTRAINT `fk_booking_cust` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_booking_unit` FOREIGN KEY (`room_unit_id`) REFERENCES `room_units` (`id`)
) ENGINE=InnoDB;

CREATE TABLE `transactions` (
  `id` varchar(36) NOT NULL,
  `midtrans_id` varchar(100) DEFAULT NULL,
  `booking_id` varchar(36) NOT NULL,
  `customer_id` varchar(36) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `method` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'PENDING',
  `e_voucher` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_trx_booking` (`booking_id`),
  KEY `fk_trx_customer` (`customer_id`),
  CONSTRAINT `fk_trx_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `fk_trx_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB;

CREATE TABLE `sessions` (
  `token` varchar(128) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`token`),
  KEY `fk_sess_user` (`user_id`),
  CONSTRAINT `fk_sess_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `id_number`, `role`) VALUES
('u_demo', 'Budi Santoso', 'customer@demo.com', '$2y$10$h7QJM0e7qOKdT0x2q5J4UespXKq/.2YbYh9BmFhOaB9f5gPYZUeRm', '08123456789', '3201234567890001', 'customer'),
('u_admin', 'Admin Luxe', 'admin@luxe.com', '$2y$10$h7QJM0e7qOKdT0x2q5J4UespXKq/.2YbYh9BmFhOaB9f5gPYZUeRm', '02112345678', NULL, 'admin');

INSERT INTO `rooms` (`id`, `type`, `price_per_night`, `description`, `emoji`) VALUES
('rm_std',  'Standard Room', 500000,  'Kamar nyaman dengan AC, TV LED, dan kamar mandi privat', '🛏️'),
('rm_dlx',  'Deluxe Room',   850000,  'Pemandangan kota, bathtub, minibar, layanan 24 jam', '✨'),
('rm_suite','Suite Room',   1500000, 'Ruang tamu terpisah, VIP service, butler pribadi', '👑'),
('rm_fam',  'Family Room',  1100000, 'Dua kamar tidur, dapur kecil, ruang bermain', '🏠');

INSERT INTO `room_units` (`id`, `room_id`, `room_number`) VALUES
('std101','rm_std','101'),('std102','rm_std','102'),('std103','rm_std','103'),
('std104','rm_std','104'),('std105','rm_std','105'),
('dlx201','rm_dlx','201'),('dlx202','rm_dlx','202'),('dlx203','rm_dlx','203'),
('suite301','rm_suite','301'),('suite302','rm_suite','302'),
('fam401','rm_fam','401'),('fam402','rm_fam','402');

SET FOREIGN_KEY_CHECKS = 1;