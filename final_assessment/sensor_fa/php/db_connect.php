<?php
// DB Connection: Establishes database connection and auto-migrates columns.
$host = "localhost"; 
$user = "canortxw_khor_ken_joo";
$password = "031008-kkj()";
$dbname = "canortxw_ken_sensor_fa";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => 500, "message" => "Database Connection Failed"]));
}
?>
