<?php
// Settings Save API: Updates thresholds, upload frequency, and custom names in database.
header('Content-Type: application/json');
// Connect to the database.
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Extract post parameters (thresholds, interval, preferred name).
    $device_id        = $_POST['device_id'] ?? 'ESP32_SAFETY_01';
    $threshold_1      = $_POST['threshold_1'] ?? null;
    $threshold_2      = $_POST['threshold_2'] ?? null;
    $threshold_2_temp = $_POST['threshold_2_temp'] ?? null;
    $upload_interval  = $_POST['upload_interval'] ?? null;
    $device_name      = $_POST['device_name'] ?? null;
    // Validate that at least one config value is provided.
    if ($threshold_1 === null && $threshold_2 === null && $threshold_2_temp === null && $upload_interval === null && $device_name === null) {
        echo json_encode(["status" => 400, "message" => "No data provided"]);
        exit;
    }

    // Query database to check if the device profile already exists.
    $check = $conn->prepare("SELECT device_id FROM device_settings WHERE device_id = ?");
    $check->bind_param("s", $device_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    // If the device exists, dynamically construct and run an UPDATE query.
    if ($exists) {

        $fields = [];
        $types = "";
        $values = [];

        if ($threshold_1 !== null) {
            $fields[] = "threshold_1 = ?";
            $types .= "d";
            $values[] = floatval($threshold_1);
        }

        if ($threshold_2 !== null) {
            $fields[] = "threshold_2 = ?";
            $types .= "d";
            $values[] = floatval($threshold_2);
        }

        if ($threshold_2_temp !== null) {
            $fields[] = "threshold_2_temp = ?";
            $types .= "d";
            $values[] = floatval($threshold_2_temp);
        }

        if ($upload_interval !== null) {
            $fields[] = "upload_interval = ?";
            $types .= "i";
            $values[] = intval($upload_interval);
        }

        if ($device_name !== null) {
            $fields[] = "device_name = ?";
            $types .= "s";
            $values[] = trim($device_name);
        }

        if (count($fields) == 0) {
            echo json_encode(["status" => 400, "message" => "Nothing to update"]);
            exit;
        }

        $sql = "UPDATE device_settings SET " . implode(", ", $fields) . " WHERE device_id = ?";
        $types .= "s";
        $values[] = $device_id;

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            echo json_encode(["status" => 500, "message" => "SQL prepare failed: " . $conn->error]);
            exit;
        }
        $stmt->bind_param($types, ...$values);

    // If the device is not registered, insert a new settings profile.
    } else {
        $t1  = $threshold_1 ?? 250.0;
        $t2  = $threshold_2 ?? 400.0;
        $t2t = $threshold_2_temp ?? 45.0;
        $ui  = $upload_interval ?? 3;
        $dn  = $device_name ?? null;

        $stmt = $conn->prepare("
            INSERT INTO device_settings
            (device_id, threshold_1, threshold_2, threshold_2_temp, upload_interval, actuator_status, device_name)
            VALUES (?, ?, ?, ?, ?, '0|0', ?)
        ");
        if ($stmt === false) {
            echo json_encode(["status" => 500, "message" => "SQL prepare failed: " . $conn->error]);
            exit;
        }

        $stmt->bind_param("sdddis", $device_id, $t1, $t2, $t2t, $ui, $dn);
    }

    // Execute the database statement and output JSON result.
    if ($stmt->execute()) {
        echo json_encode(["status" => 200, "message" => "Settings updated"]);
    // If the device is not registered, insert a new settings profile.
    } else {
        echo json_encode(["status" => 500, "message" => "Update failed: " . $stmt->error]);
    }

    $stmt->close();

// If the device is not registered, insert a new settings profile.
} else {
    echo json_encode(["status" => 405, "message" => "Method Not Allowed"]);
}

$conn->close();
?>
