<?php
// EE 470 Part 2: insert a measurement using URL (GET) parameters.
require "connection.php";

// Example: insert.php?nodeId=node_1&nodeTemp=25&nodeHum=50
$node = $_GET['nodeId'] ?? '';
$temp = $_GET['nodeTemp'] ?? '';
$hum = $_GET['nodeHum'] ?? '';
$time = $_GET['timeReceived'] ?? null;

// Check the node name and accepted measurement ranges.
if (!is_string($node) || !preg_match('/^node_[0-9]+$/', $node) ||
    !is_numeric($temp) || !is_numeric($hum) ||
    $temp < -10 || $temp > 100 ||
    $hum < 0 || $hum > 100) {
    die("Invalid sensor data");
}

// A timestamp is optional for normal insertion.
if ($time !== null) {
    $date = is_string($time) ? DateTime::createFromFormat('!Y-m-d H:i:s', $time) : false;
    if (!$date || $date->format('Y-m-d H:i:s') !== $time) {
        die("Invalid timestamp");
    }
}

// Prepared statements keep user input separate from SQL commands.
// If no time was supplied, NOW() is used by MariaDB.
$sql = "INSERT INTO sensor_data
        (node_name, time_received, temperature, humidity)
        VALUES (?, COALESCE(?, NOW()), ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssdd", $node, $time, $temp, $hum);

try {
    $stmt->execute();
    echo "Data inserted successfully";
} catch (mysqli_sql_exception $e) {
    // Foreign key, duplicate timestamp, or other database constraint.
    echo "Data rejected. Check node registration or duplicate time.";
}

$stmt->close();
$conn->close();
?>
