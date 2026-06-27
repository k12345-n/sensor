/* SQL Database: Schema tables for logs history and configuration thresholds. */
/* Drop existing tables if they already exist in the database to avoid duplicate errors. */
DROP TABLE IF EXISTS sensor_data;
DROP TABLE IF EXISTS device_settings;
DROP TABLE IF EXISTS users;


/* Create the sensor logs table to store gas, temperature, flame, and motion readings over time. */
CREATE TABLE sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50),
    sensor_1 DOUBLE, 
    sensor_2 DOUBLE, 
    sensor_3 INT,    
    motion INT,      
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


/* Create the settings configuration table to store custom safety thresholds and IP/SSID metadata. */
CREATE TABLE device_settings (
    device_id VARCHAR(50) PRIMARY KEY,
    threshold_1 DOUBLE DEFAULT 250.0, 
    threshold_2 DOUBLE DEFAULT 400.0, 
    threshold_2_temp DOUBLE DEFAULT 45.0, 
    upload_interval INT DEFAULT 3,
    actuator_status VARCHAR(20) DEFAULT '0|0', 
    device_ip VARCHAR(50) DEFAULT '0.0.0.0',
    reboot_mode INT DEFAULT 0,
    device_name VARCHAR(100) DEFAULT NULL,
    wifi_ssid VARCHAR(100) DEFAULT NULL
);


CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


/* Insert a default device profile with typical threshold limits to initialize the setup. */
INSERT INTO device_settings (device_id, threshold_1, threshold_2, threshold_2_temp, upload_interval, actuator_status) 
VALUES ('ESP32_SAFETY_01', 250.0, 400.0, 45.0, 3, '0|0');