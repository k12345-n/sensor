<?php
// Logs Fetch API: Retrieves the last 20 event log records for the selected ESP32.
// Connect to the database.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(["status" => 401, "message" => "Unauthorized. Please log in first."]);
    exit;
}
include 'db_connect.php';

// Read targeted device ID parameter from request.
$device_id = $_GET['device_id'] ?? '';

// Retrieve the last 20 logged entries for this device, or fetch globally.
if (!empty($device_id)) {
    $stmt = $conn->prepare("SELECT * FROM sensor_data WHERE device_id = ? ORDER BY id DESC LIMIT 20");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT * FROM sensor_data ORDER BY id DESC LIMIT 20";
    $result = $conn->query($query);
}

$logs = [];
// Convert database result rows into an array.
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Return log list to the dashboard in JSON format.
echo json_encode([
    "status" => 200,
    "data" => $logs
]);

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>