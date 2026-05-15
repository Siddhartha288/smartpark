-- ================================================
-- SmartPark Database Schema
-- ICT312 Advanced Web Information Systems
-- ================================================
-- Run this script to set up the SmartPark database.
-- Usage: mysql -u root -p < sql/smartpark.sql

CREATE DATABASE IF NOT EXISTS smartpark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartpark;

-- ---- 1. Users ----
CREATE TABLE IF NOT EXISTS users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)         NOT NULL,
    email       VARCHAR(150)         NOT NULL UNIQUE,
    password_hash VARCHAR(255)       NOT NULL,
    role        ENUM('driver','admin') NOT NULL DEFAULT 'driver',
    phone       VARCHAR(20),
    created_at  DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME,
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ---- 2. Car Parks ----
CREATE TABLE IF NOT EXISTS car_parks (
    park_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)         NOT NULL,
    address     VARCHAR(255)         NOT NULL,
    suburb      VARCHAR(100)         NOT NULL,
    total_spots INT                  NOT NULL DEFAULT 0,
    active      TINYINT(1)           NOT NULL DEFAULT 1,
    created_at  DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_suburb (suburb)
) ENGINE=InnoDB;

-- ---- 3. Parking Zones ----
CREATE TABLE IF NOT EXISTS parking_zones (
    zone_id     INT AUTO_INCREMENT PRIMARY KEY,
    park_id     INT                  NOT NULL,
    zone_label  VARCHAR(50)          NOT NULL,
    spot_count  INT                  NOT NULL DEFAULT 10,
    rate_per_hour DECIMAL(6,2)       NOT NULL DEFAULT 5.00,
    FOREIGN KEY (park_id) REFERENCES car_parks(park_id) ON DELETE CASCADE,
    INDEX idx_park (park_id)
) ENGINE=InnoDB;

-- ---- 4. Bookings ----
CREATE TABLE IF NOT EXISTS bookings (
    booking_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT                  NOT NULL,
    zone_id     INT                  NOT NULL,
    spot_number VARCHAR(20)          NOT NULL,
    start_time  DATETIME             NOT NULL,
    end_time    DATETIME             NOT NULL,
    status      ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'confirmed',
    total_amount DECIMAL(8,2)        NOT NULL DEFAULT 0.00,
    created_at  DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES parking_zones(zone_id),
    INDEX idx_user (user_id),
    INDEX idx_zone (zone_id),
    INDEX idx_times (start_time, end_time)
) ENGINE=InnoDB;

-- ---- 5. Payments ----
CREATE TABLE IF NOT EXISTS payments (
    payment_id  INT AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT                  NOT NULL UNIQUE,
    amount      DECIMAL(8,2)         NOT NULL,
    method      ENUM('card','paypal','cash') NOT NULL DEFAULT 'card',
    status      ENUM('pending','completed','refunded') NOT NULL DEFAULT 'completed',
    timestamp   DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---- 6. Audit Log ----
CREATE TABLE IF NOT EXISTS audit_log (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    event_type  VARCHAR(50)          NOT NULL,
    description TEXT,
    ip_address  VARCHAR(45),
    logged_at   DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_type (event_type)
) ENGINE=InnoDB;

-- ====== SAMPLE DATA ======

-- Admin user (password: Admin@1234)
INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
('Admin User',   'admin@smartpark.com', '$2y$12$TG2XVe5HoLuELrU4JxnFEOEVOi0b0NedFn8YN7tG7nKOJGT4zFdMK', 'admin'),
('Jane Smith',   'jane@example.com',    '$2y$12$TG2XVe5HoLuELrU4JxnFEOEVOi0b0NedFn8YN7tG7nKOJGT4zFdMK', 'driver'),
('Mark Johnson', 'mark@example.com',    '$2y$12$TG2XVe5HoLuELrU4JxnFEOEVOi0b0NedFn8YN7tG7nKOJGT4zFdMK', 'driver'),
('Lucy Chen',    'lucy@example.com',    '$2y$12$TG2XVe5HoLuELrU4JxnFEOEVOi0b0NedFn8YN7tG7nKOJGT4zFdMK', 'driver');
-- Note: all demo passwords are: Admin@1234

-- Car Parks
INSERT IGNORE INTO car_parks (name, address, suburb, total_spots, active) VALUES
('Sydney CBD Parking Centre',   '100 George Street',          'Sydney',       80, 1),
('Parramatta Westfield Parking','Corner Church & Market St',  'Parramatta',   60, 1),
('Bondi Junction Carpark',      '500 Oxford Street',          'Bondi Junction',40, 1),
('Chatswood Station Parking',   'Victoria Ave & Railway St',  'Chatswood',    50, 1),
('Newtown Community Parking',   '80 King Street',             'Newtown',      30, 1);

-- Parking Zones (2 zones per park)
INSERT IGNORE INTO parking_zones (park_id, zone_label, spot_count, rate_per_hour) VALUES
(1, 'Zone A - Ground', 40, 8.00),
(1, 'Zone B - Level 1', 40, 6.50),
(2, 'Zone A - Short Stay', 30, 5.00),
(2, 'Zone B - Long Stay',  30, 3.50),
(3, 'Zone A - Street Level', 20, 7.00),
(3, 'Zone B - Undercover',  20, 9.00),
(4, 'Zone A - Station Entry', 25, 4.50),
(4, 'Zone B - Daily',         25, 3.00),
(5, 'Zone A - Standard',      30, 4.00);

-- Sample Bookings
INSERT IGNORE INTO bookings (user_id, zone_id, spot_number, start_time, end_time, status, total_amount) VALUES
(2, 1, 'A-05', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY) + INTERVAL 2 HOUR, 'completed', 16.00),
(2, 3, 'A-12', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 3 HOUR, 'completed', 15.00),
(2, 5, 'A-03', DATE_ADD(NOW(), INTERVAL 1 DAY),  DATE_ADD(NOW(), INTERVAL 1 DAY)  + INTERVAL 2 HOUR, 'confirmed', 14.00),
(3, 2, 'B-07', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 4 HOUR, 'completed', 26.00),
(3, 4, 'B-02', DATE_ADD(NOW(), INTERVAL 2 DAY),  DATE_ADD(NOW(), INTERVAL 2 DAY)  + INTERVAL 1 HOUR, 'confirmed', 3.50);

-- Sample Payments
INSERT IGNORE INTO payments (booking_id, amount, method, status) VALUES
(1, 16.00, 'card', 'completed'),
(2, 15.00, 'card', 'completed'),
(3, 14.00, 'card', 'completed'),
(4, 26.00, 'paypal', 'completed'),
(5, 3.50,  'card', 'completed');
