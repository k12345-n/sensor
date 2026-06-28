<?php
// Scanner API: Calculates online status of extensions using check-in logs and socket checks.
header('Content-Type: application/json');
// Connect to the database.
include 'db_connect.php';

// Select registered device details and compute the duration since their last update.
$query = "
    SELECT 
        ds.device_id, 
        ds.device_name,
        ds.device_ip, 
        ds.wifi_ssid,
        ds.actuator_status,
        (SELECT MAX(created_at) FROM sensor_data sd WHERE sd.device_id = ds.device_id) as last_seen,
        (SELECT UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(MAX(created_at)) FROM sensor_data sd WHERE sd.device_id = ds.device_id) as seconds_ago
    FROM device_settings ds
";
$result = $conn->query($query);

$discovered_devices = [];

// Iterate through all registered nodes to verify their connection state.
while ($row = $result->fetch_assoc()) {
    $ip = $row['device_ip'] ?? '0.0.0.0';
    $is_online = false;

    // Online Check 1: If the last database check-in was within 15 seconds, the node is online.
    $seconds_ago = $row['seconds_ago'];
    if ($seconds_ago !== null && $seconds_ago <= 15) {
        $is_online = true;
    }

    // Online Check 2: Try checking port 8080 directly with a socket timeout of 0.8 seconds.
    if (!$is_online && $ip !== '0.0.0.0' && !empty($ip)) {

        $connection = @fsockopen($ip, 8080, $errno, $errstr, 0.8);
        if ($connection) {
            $is_online = true;
            fclose($connection);
        }
    }

    // Build the list of active extension devices with their status labels.
    $discovered_devices[] = [
        "device_id" => $row['device_id'],
        "device_name" => $row['device_name'] ?? '',
        "device_ip" => $ip,
        "wifi_ssid" => $row['wifi_ssid'] ?? '',
        "status" => $is_online ? "Connected" : "Disconnected",
        "actuator" => $row['actuator_status']
    ];
}

// Return the scanned device details as a JSON response.
echo json_encode([
    "status" => 200,
    "devices" => $discovered_devices
]);

$conn->close();
?>