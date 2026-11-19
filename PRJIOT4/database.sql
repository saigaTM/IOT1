-- Select the existing Hostinger database in phpMyAdmin before running.
CREATE TABLE IF NOT EXISTS sensor_value (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    value INT NOT NULL,
    time_received TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS led_state (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state TINYINT NOT NULL,
    time_received TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Verification queries used during the assignment.
SHOW TABLES;
DESCRIBE sensor_value;
SELECT NOW(), UTC_TIMESTAMP(), @@global.time_zone, @@session.time_zone;
SELECT * FROM sensor_value ORDER BY id DESC;
SELECT * FROM led_state ORDER BY id DESC;
