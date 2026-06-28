<?php
// Logs Upload API: Inserts incoming ESP32 sensor logs and tracks Wi-Fi network SSID/IP changes.
// Connect to the database.
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Extract sensor metrics and device identification from POST request.
    $device_id = $_POST['device_id'] ?? '';
    $sensor_1 = $_POST['sensor_1'] ?? 0;
    $sensor_2 = $_POST['sensor_2'] ?? 0;
    $sensor_3 = $_POST['sensor_3'] ?? 1;
    $motion = $_POST['motion'] ?? 0;
    $status = $_POST['status'] ?? 'Safe';

    if (!empty($device_id)) {

        // Identify IP address and connection SSID from the client device.
        $client_ip = $_POST['device_ip'] ?? '';
        if (empty($client_ip) || $client_ip === '0.0.0.0') {
            $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        $wifi_ssid = $_POST['wifi_ssid'] ?? null;

        // Update network IP and connected SSID details of the active device profile.
        if ($wifi_ssid !== null) {
            $ip_stmt = $conn->prepare("UPDATE device_settings SET device_ip = ?, wifi_ssid = ? WHERE device_id = ?");
            $ip_stmt->bind_param("sss", $client_ip, $wifi_ssid, $device_id);
        } else {
            $ip_stmt = $conn->prepare("UPDATE device_settings SET device_ip = ? WHERE device_id = ?");
            $ip_stmt->bind_param("ss", $client_ip, $device_id);
        }
        $ip_stmt->execute();
        $ip_stmt->close();

        // Insert log containing current gas, temp, flame, motion, and status values.
        $stmt = $conn->prepare("INSERT INTO sensor_data (device_id, sensor_1, sensor_2, sensor_3, motion, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sddiis", $device_id, $sensor_1, $sensor_2, $sensor_3, $motion, $status);

        // Execute the statement and output confirmation.
        if ($stmt->execute()) {
            echo json_encode(["status" => 200, "message" => "Sensor data logged successfully as " . $status]);
        } else {
            echo json_encode(["status" => 500, "message" => "Database insertion failed: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => 400, "message" => "Incomplete details. Missing device_id."]);
    }
} else {
    echo json_encode(["status" => 405, "message" => "Method Not Allowed. You must use POST."]);
}

$conn->close();
?>