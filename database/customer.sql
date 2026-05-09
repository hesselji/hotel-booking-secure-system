-- ============================================================
--  LuxeBooking · Full SQL Schema & Seed Data
--  Database: MySQL 8.0+ / MariaDB 10.6+
--  Encoding: utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `luxebooking`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `luxebooking`;


-- ============================================================
-- DS1 · Tabel Users (Customer & Admin)
-- ============================================================
CREATE TABLE `users` (
  `id`           VARCHAR(36)   NOT NULL,                        -- UUID / 'u_<timestamp>'
  `name`         VARCHAR(100)  NOT NULL,
  `email`        VARCHAR(150)  NOT NULL,
  `password`     VARCHAR(255)  NOT NULL,                        -- bcrypt hash (production)
  `phone`        VARCHAR(20)   DEFAULT NULL,
  `id_number`    VARCHAR(30)   DEFAULT NULL,                    -- No. KTP / Passport
  `role`         ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `joined_at`    DATE          NOT NULL DEFAULT (CURRENT_DATE),
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DS1 · Database User — menyimpan data pelanggan dan admin';


-- ============================================================
-- Tabel Rooms (Kamar Hotel)
-- ============================================================
CREATE TABLE `rooms` (
  `id`              VARCHAR(36)     NOT NULL,
  `type`            VARCHAR(80)     NOT NULL,
  `price_per_night` DECIMAL(12,2)   NOT NULL,
  `stock`           SMALLINT        NOT NULL DEFAULT 1,
  `description`     TEXT            DEFAULT NULL,
  `emoji`           VARCHAR(8)      DEFAULT '🛏️',
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_rooms_type` (`type`),
  KEY `idx_rooms_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Inventori kamar hotel beserta harga dan stok';


-- ============================================================
-- DS2 · Tabel Bookings (Reservasi)
-- ============================================================
CREATE TABLE `bookings` (
  `id`               VARCHAR(36)    NOT NULL,                   -- 'BK<timestamp>'
  `customer_id`      VARCHAR(36)    NOT NULL,
  `room_id`          VARCHAR(36)    NOT NULL,
  `checkin_date`     DATE           NOT NULL,
  `checkout_date`    DATE           NOT NULL,
  `nights`           SMALLINT       NOT NULL,
  `guests`           SMALLINT       NOT NULL DEFAULT 1,
  `total_price`      DECIMAL(14,2)  NOT NULL,
  `payment_status`   ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `e_voucher`        VARCHAR(30)    DEFAULT NULL,               -- 'EV-XXXXXXXX'
  `notes`            TEXT           DEFAULT NULL,
  `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  CONSTRAINT `fk_bookings_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_room`
    FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  KEY `idx_bookings_customer`     (`customer_id`),
  KEY `idx_bookings_room`         (`room_id`),
  KEY `idx_bookings_status`       (`payment_status`),
  KEY `idx_bookings_checkin`      (`checkin_date`),
  KEY `idx_bookings_e_voucher`    (`e_voucher`),
  CONSTRAINT `chk_booking_dates`
    CHECK (`checkout_date` > `checkin_date`),
  CONSTRAINT `chk_booking_guests`
    CHECK (`guests` >= 1 AND `guests` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DS2 · Database Booking — reservasi kamar pelanggan';


-- ============================================================
-- DS3 · Tabel Transactions (Riwayat Pembayaran)
-- ============================================================
CREATE TABLE `transactions` (
  `id`             VARCHAR(36)     NOT NULL,                    -- 'TRX<timestamp>'
  `booking_id`     VARCHAR(36)     NOT NULL,
  `customer_id`    VARCHAR(36)     NOT NULL,
  `amount`         DECIMAL(14,2)   NOT NULL,
  `method`         ENUM(
                     'Kartu Kredit',
                     'Transfer Bank',
                     'QRIS',
                     'E-Wallet'
                   ) NOT NULL,
  `status`         ENUM('SUCCESS','FAILED','REFUNDED','PENDING')
                   NOT NULL DEFAULT 'PENDING',
  `e_voucher`      VARCHAR(30)     DEFAULT NULL,
  `gateway_ref`    VARCHAR(100)    DEFAULT NULL,                -- referensi payment gateway eksternal
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  CONSTRAINT `fk_trx_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_trx_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  KEY `idx_trx_booking`    (`booking_id`),
  KEY `idx_trx_customer`   (`customer_id`),
  KEY `idx_trx_status`     (`status`),
  KEY `idx_trx_method`     (`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DS3 · Database Transaksi — rekam jejak pembayaran via payment gateway';


-- ============================================================
-- Tabel Sessions (Autentikasi Token — P1.0)
-- ============================================================
CREATE TABLE `sessions` (
  `token`        VARCHAR(128)  NOT NULL,
  `user_id`      VARCHAR(36)   NOT NULL,
  `ip_address`   VARCHAR(45)   DEFAULT NULL,
  `user_agent`   TEXT          DEFAULT NULL,
  `expires_at`   DATETIME      NOT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`token`),
  CONSTRAINT `fk_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  KEY `idx_sessions_user`    (`user_id`),
  KEY `idx_sessions_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sesi login pelanggan — P1.0 Login/Register';


-- ============================================================
-- VIEW: Rekapitulasi Booking per Customer
-- ============================================================
CREATE OR REPLACE VIEW `v_customer_booking_summary` AS
SELECT
  u.id                                            AS customer_id,
  u.name                                          AS customer_name,
  u.email,
  COUNT(b.id)                                     AS total_bookings,
  SUM(CASE WHEN b.payment_status = 'paid'      THEN 1 ELSE 0 END) AS paid_count,
  SUM(CASE WHEN b.payment_status = 'pending'   THEN 1 ELSE 0 END) AS pending_count,
  SUM(CASE WHEN b.payment_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
  COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_price END), 0)
                                                  AS total_spent
FROM `users` u
LEFT JOIN `bookings` b ON b.customer_id = u.id
WHERE u.role = 'customer'
GROUP BY u.id, u.name, u.email;


-- ============================================================
-- VIEW: Detail Booking + Info Kamar + Nama Customer
-- ============================================================
CREATE OR REPLACE VIEW `v_booking_detail` AS
SELECT
  b.id                 AS booking_id,
  b.e_voucher,
  u.name               AS customer_name,
  u.email              AS customer_email,
  u.phone,
  r.type               AS room_type,
  r.price_per_night,
  b.checkin_date,
  b.checkout_date,
  b.nights,
  b.guests,
  b.total_price,
  b.payment_status,
  b.created_at         AS booked_at,
  t.method             AS payment_method,
  t.status             AS transaction_status,
  t.gateway_ref
FROM `bookings` b
JOIN `users`  u ON u.id = b.customer_id
JOIN `rooms`  r ON r.id = b.room_id
LEFT JOIN `transactions` t ON t.booking_id = b.id AND t.status = 'SUCCESS';


-- ============================================================
-- STORED PROCEDURE: Cek Ketersediaan Kamar (P2.0)
-- ============================================================
DELIMITER $$

CREATE PROCEDURE `sp_check_room_availability` (
  IN  p_room_id       VARCHAR(36),
  IN  p_checkin       DATE,
  IN  p_checkout      DATE,
  OUT p_is_available  TINYINT(1)
)
BEGIN
  DECLARE v_conflict INT DEFAULT 0;
  SELECT COUNT(*) INTO v_conflict
  FROM `bookings`
  WHERE room_id       = p_room_id
    AND payment_status = 'paid'
    AND p_checkin      < checkout_date
    AND p_checkout     > checkin_date;

  SET p_is_available = IF(v_conflict = 0, 1, 0);
END$$


-- ============================================================
-- STORED PROCEDURE: Proses Pembayaran (P3.0)
-- ============================================================
CREATE PROCEDURE `sp_process_payment` (
  IN  p_booking_id    VARCHAR(36),
  IN  p_method        VARCHAR(30),
  IN  p_gateway_ref   VARCHAR(100),
  OUT p_voucher       VARCHAR(30),
  OUT p_result        VARCHAR(50)
)
BEGIN
  DECLARE v_exists INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_result = 'ERROR';
    SET p_voucher = NULL;
  END;

  START TRANSACTION;

  -- Validasi booking ada dan masih pending
  SELECT COUNT(*) INTO v_exists
  FROM `bookings`
  WHERE id = p_booking_id AND payment_status = 'pending';

  IF v_exists = 0 THEN
    SET p_result = 'BOOKING_NOT_FOUND_OR_ALREADY_PAID';
    SET p_voucher = NULL;
    ROLLBACK;
  ELSE
    -- Generate E-Voucher
    SET p_voucher = CONCAT('EV-', UPPER(RIGHT(p_booking_id, 8)));

    -- Update status booking → paid
    UPDATE `bookings`
    SET payment_status = 'paid',
        e_voucher      = p_voucher,
        updated_at     = NOW()
    WHERE id = p_booking_id;

    -- Catat transaksi
    INSERT INTO `transactions` (id, booking_id, customer_id, amount, method, status, e_voucher, gateway_ref)
    SELECT
      CONCAT('TRX', UNIX_TIMESTAMP() * 1000),
      b.id,
      b.customer_id,
      b.total_price,
      p_method,
      'SUCCESS',
      p_voucher,
      p_gateway_ref
    FROM `bookings` b WHERE b.id = p_booking_id;

    COMMIT;
    SET p_result = 'SUCCESS';
  END IF;
END$$

DELIMITER ;


-- ============================================================
-- TRIGGER: Otomatis hitung nights sebelum insert booking
-- ============================================================
DELIMITER $$
CREATE TRIGGER `trg_booking_before_insert`
BEFORE INSERT ON `bookings`
FOR EACH ROW
BEGIN
  SET NEW.nights = DATEDIFF(NEW.checkout_date, NEW.checkin_date);
END$$
DELIMITER ;


-- ============================================================
-- SEED DATA — Demo / Development
-- ============================================================

-- Demo user (password: pass123 → plain text untuk demo; ganti dengan hash di produksi)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `id_number`, `role`, `joined_at`) VALUES
  ('u_demo',       'Budi Santoso',  'customer@demo.com', '$2y$10$demoHashPlaceholder1111111111111111111111111', '08123456789', '3201234567890001', 'customer', '2024-01-15'),
  ('u_admin_001',  'Admin Luxe',    'admin@luxe.com',    '$2y$10$demoHashPlaceholder2222222222222222222222222', '02112345678', NULL,               'admin',    '2024-01-01');

-- Kamar
INSERT INTO `rooms` (`id`, `type`, `price_per_night`, `stock`, `description`, `emoji`) VALUES
  ('rm_std',   'Standard Room', 500000.00,  10, 'Kamar nyaman dengan AC, TV LED, dan kamar mandi privat',                                          '🛏️'),
  ('rm_dlx',   'Deluxe Room',   850000.00,  6,  'Pemandangan kota, bathtub, minibar, dan layanan kamar 24 jam',                                    '✨'),
  ('rm_suite', 'Suite Room',    1500000.00, 3,  'Ruang tamu terpisah, VIP service, panoramic view, butler pribadi',                                '👑'),
  ('rm_fam',   'Family Room',   1100000.00, 4,  'Cocok untuk keluarga, 2 kamar tidur, dapur kecil, ruang bermain',                                 '🏠');

-- Contoh booking lunas
INSERT INTO `bookings`
  (`id`, `customer_id`, `room_id`, `checkin_date`, `checkout_date`, `nights`, `guests`, `total_price`, `payment_status`, `e_voucher`, `created_at`)
VALUES
  ('BK1700000001', 'u_demo', 'rm_dlx',   '2025-06-10', '2025-06-13', 3, 2, 2550000.00, 'paid',    'EV-00000001', '2025-05-01 09:00:00'),
  ('BK1700000002', 'u_demo', 'rm_suite', '2025-07-20', '2025-07-22', 2, 2, 3000000.00, 'paid',    'EV-00000002', '2025-05-05 14:30:00'),
  ('BK1700000003', 'u_demo', 'rm_std',   '2025-08-01', '2025-08-03', 2, 1, 1000000.00, 'pending', NULL,          '2025-05-09 11:00:00');

-- Transaksi untuk booking yang sudah lunas
INSERT INTO `transactions`
  (`id`, `booking_id`, `customer_id`, `amount`, `method`, `status`, `e_voucher`, `gateway_ref`, `created_at`)
VALUES
  ('TRX1700000001', 'BK1700000001', 'u_demo', 2550000.00, 'Kartu Kredit', 'SUCCESS', 'EV-00000001', 'GW-REF-ABC001', '2025-05-01 09:01:00'),
  ('TRX1700000002', 'BK1700000002', 'u_demo', 3000000.00, 'QRIS',         'SUCCESS', 'EV-00000002', 'GW-REF-ABC002', '2025-05-05 14:31:00');

SET foreign_key_checks = 1;