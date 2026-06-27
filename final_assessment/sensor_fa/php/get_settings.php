<?php
// Settings Fetch API: Retrieves configurable safety thresholds and parameters for ESP32 sync.
// Connect to the database.
include 'db_connect.php';


// Get device ID parameter from URL query string.
$device_id = $_GET['device_id'] ?? 'ESP32_SAFETY_01';


// Query database for all settings associated with the device.
$stmt = $conn->prepare("SELECT threshold_1, threshold_2, threshold_2_temp, upload_interval, actuator_status, device_ip, reboot_mode, device_name FROM device_settings WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if device settings profile exists.
if ($result->num_rows > 0) {
    
    $settings = $result->fetch_assoc();
    
    
    // If reboot_mode is queued, reset it to 0 so the reboot only occurs once.
    if (isset($settings['reboot_mode']) && (int)$settings['reboot_mode'] === 1) {
        $reset_stmt = $conn->prepare("UPDATE device_settings SET reboot_mode = 0 WHERE device_id = ?");
        $reset_stmt->bind_param("s", $device_id);
        $reset_stmt->execute();
        $reset_stmt->close();
    }
    
    // Return settings details in JSON format for the ESP32 to retrieve.
    echo json_encode([
        "status" => 200,
        "data" => $settings
    ]);
} else {
    // Return settings details in JSON format for the ESP32 to retrieve.
    echo json_encode([
        "status" => 404,
        "message" => "Device configuration profiles not found."
    ]);
}

$stmt->close();
$conn->close();
?>
