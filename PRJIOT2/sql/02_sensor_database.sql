-- EE 470 - Part 1.3: Sensor database
-- Run once, on the correct database selected in phpMyAdmin.
-- Do not rerun INSERT statements if you have already entered these readings.

-- One row per registered sensor; node_name is the primary key.
CREATE TABLE sensor_register (
    node_name VARCHAR(10) PRIMARY KEY,
    manufacturer VARCHAR(10) NOT NULL,
    longitude DECIMAL(15,10),
    latitude DECIMAL(15,10)
) ENGINE=InnoDB;

-- A sensor may send many measurements, but not twice at the same time.
-- The foreign key refuses readings for unregistered nodes.
CREATE TABLE sensor_data (
    node_name VARCHAR(10) NOT NULL,
    time_received DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    temperature DECIMAL(6,2)
        CHECK (temperature BETWEEN -10 AND 100),
    humidity DECIMAL(6,2)
        CHECK (humidity BETWEEN 0 AND 100),
    PRIMARY KEY (node_name, time_received),
    FOREIGN KEY (node_name) REFERENCES sensor_register(node_name)
) ENGINE=InnoDB;

-- Five nodes with distinct manufacturer names.
-- These GPS coordinates are sample data for the exercise.
INSERT INTO sensor_register
    (node_name, manufacturer, longitude, latitude)
VALUES
    ('node_1', 'Bosch',    -122.6749, 38.3396),
    ('node_2', 'Sensirion',-122.6755, 38.3401),
    ('node_3', 'Honeywell',-122.6760, 38.3405),
    ('node_4', 'Adafruit', -122.6765, 38.3410),
    ('node_5', 'STMicro',  -122.6770, 38.3415);

-- 4 measurements per sensor, beginning at 2022-10-01 11:00:00,
-- with a 30-minute interval for each sensor.
INSERT INTO sensor_data
    (node_name, time_received, temperature, humidity)
VALUES
    ('node_1', '2022-10-01 11:00:00', 22.5, 45),
    ('node_1', '2022-10-01 11:30:00', 23.0, 46),
    ('node_1', '2022-10-01 12:00:00', 23.5, 44),
    ('node_1', '2022-10-01 12:30:00', 24.0, 43),
    ('node_2', '2022-10-01 11:00:00', 25.0, 50),
    ('node_2', '2022-10-01 11:30:00', 25.5, 51),
    ('node_2', '2022-10-01 12:00:00', 26.0, 49),
    ('node_2', '2022-10-01 12:30:00', 26.5, 48),
    ('node_3', '2022-10-01 11:00:00', 20.0, 60),
    ('node_3', '2022-10-01 11:30:00', 20.5, 61),
    ('node_3', '2022-10-01 12:00:00', 21.0, 59),
    ('node_3', '2022-10-01 12:30:00', 21.5, 58),
    ('node_4', '2022-10-01 11:00:00', 28.0, 35),
    ('node_4', '2022-10-01 11:30:00', 28.5, 36),
    ('node_4', '2022-10-01 12:00:00', 29.0, 34),
    ('node_4', '2022-10-01 12:30:00', 29.5, 33),
    ('node_5', '2022-10-01 11:00:00', 18.0, 70),
    ('node_5', '2022-10-01 11:30:00', 18.5, 71),
    ('node_5', '2022-10-01 12:00:00', 19.0, 69),
    ('node_5', '2022-10-01 12:30:00', 19.5, 68);

-- Verification (expect 5 registered sensors, 20 readings).
SELECT * FROM sensor_register ORDER BY node_name;
SELECT * FROM sensor_data ORDER BY node_name, time_received;
SELECT node_name, COUNT(*) AS total_measurements
FROM sensor_data GROUP BY node_name ORDER BY node_name;

-- Required VIEW (the assignment has a typo 'ensor_combined' in one place).
CREATE VIEW sensor_combined AS
SELECT
    d.node_name,
    r.manufacturer,
    r.longitude,
    r.latitude,
    d.time_received,
    d.temperature,
    d.humidity
FROM sensor_data AS d
INNER JOIN sensor_register AS r ON d.node_name = r.node_name;

SELECT * FROM sensor_combined ORDER BY node_name, time_received;
SHOW TABLES;
