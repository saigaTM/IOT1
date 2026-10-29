<?php
// Part 2, Section 3.3: Show one node's measurements in JSON.
require "connection.php";
header('Content-Type: application/json; charset=utf-8');

$node = $_GET['node'] ?? 'node_1';
$stmt = $conn->prepare(
    "SELECT node_name, time_received, temperature, humidity
     FROM sensor_data WHERE node_name = ? ORDER BY time_received"
);
$stmt->bind_param("s", $node);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data, JSON_PRETTY_PRINT);
$stmt->close();
$conn->close();
?>
