CREATE DATABASE hotel_booking_db;

USE hotel_booking_db;

CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255)
);

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20),
    room_type VARCHAR(50),
    price_per_night DECIMAL(10,2),
    status ENUM('available','booked','maintenance') DEFAULT 'available',
    description TEXT
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(30),

    room_id INT,

    check_in_date DATE,
    check_out_date DATE,

    total_price DECIMAL(10,2),

    booking_status ENUM(
        'confirmed',
        'checked_in',
        'checked_out',
        'cancelled'
    ) DEFAULT 'confirmed',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (room_id)
    REFERENCES rooms(id)
    ON DELETE CASCADE
);

INSERT INTO admin_users(username, password_hash)
VALUES(
    'admin',
    'HASH_HASIL_BCRYPT'
);

INSERT INTO rooms(
    room_number,
    room_type,
    price_per_night,
    status,
    description
)
VALUES
('101', 'Single', 800000, 'available', 'Single room'),
('102', 'Double', 1280000, 'available', 'Double room'),
('201', 'Suite', 2400000, 'available', 'Luxury suite');

