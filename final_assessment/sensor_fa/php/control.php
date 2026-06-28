<?php
// Control API: Updates database to manual override status (alarm and fan controls).
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => 401, "message" => "Unauthorized. Please log in first."]);
    exit;
}

// Connect to the database.
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get device ID and override action from POST request.
    $device_id = $_POST['device_id'] ?? 'ESP32_SAFETY_01';
    $action = $_POST['action'] ?? '';

    // List of allowed manual controls for buzzer and fan.
    $allowed_actions = ['alarm_on', 'alarm_off', 'alarm_mute', 'fan_on', 'fan_off', 'reboot_config'];

    // Check if the requested action is valid.
    if (empty($action) || !in_array($action, $allowed_actions)) {
        echo json_encode([
            "status" => 400,
            "message" => "Invalid or missing action."
        ]);
        exit;
    }

    // Queue reboot request to force ESP32 into configuration mode.
    if ($action === 'reboot_config') {
        $update = $conn->prepare("UPDATE device_settings SET reboot_mode = 1 WHERE device_id = ?");
        $update->bind_param("s", $device_id);
        if ($update->execute()) {
            echo json_encode([
                "status" => 200,
                "message" => "Reboot command queued successfully"
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "message" => "Failed to queue reboot command"
            ]);
        }
        $update->close();
        $conn->close();
        exit;
    }

    // Fetch current actuator status (buzzer and fan states) from database.
    $stmt = $conn->prepare("SELECT actuator_status FROM device_settings WHERE device_id = ?");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    // Insert default settings if the device is not registered yet.
    if ($result->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO device_settings (device_id, threshold_1, threshold_2, upload_interval, actuator_status) VALUES (?, 400.0, 45.0, 3, '0|0')");
        $insert->bind_param("s", $device_id);
        $insert->execute();
        $insert->close();
        $current = [0, 0];
    } else {
        $row = $result->fetch_assoc();
        $parts = explode('|', $row['actuator_status'] ?? '0|0');

        $current = [
            intval($parts[0] ?? 0),
            intval($parts[1] ?? 0)
        ];
    }

    // Update actuator state values based on the manual command.
    switch ($action) {
        case 'alarm_on':
            $current[0] = 1;
            break;
        case 'alarm_off':
            $current[0] = 0;
            break;
        case 'alarm_mute':
            $current[0] = ($current[0] == 2) ? 0 : 2;
            break;
        case 'fan_on':
            $current[1] = 1;
            break;
        case 'fan_off':
            $current[1] = 0;
            break;
    }

    // Combine the updated states back and save to the database.
    $new_status = implode('|', $current);

    $update = $conn->prepare("UPDATE device_settings SET actuator_status = ? WHERE device_id = ?");
    $update->bind_param("ss", $new_status, $device_id);

    if ($update->execute()) {
        echo json_encode([
            "status" => 200,
            "message" => "Command applied successfully",
            "actuator_status" => $new_status
        ]);
    } else {
        echo json_encode([
            "status" => 500,
            "message" => "Database update failed"
        ]);
    }

    $update->close();

} else {
    echo json_encode([
        "status" => 405,
        "message" => "Method Not Allowed"
    ]);
}

$conn->close();
?>