<?php
// Configure privately on Hostinger. Never commit real credentials.
$host = "localhost";
$user = "YOUR_DATABASE_USERNAME";
$password = "YOUR_DATABASE_PASSWORD";
$database = "YOUR_DATABASE_NAME";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed");
}

// TIMESTAMP data is retrieved in UTC and displayed in California time.
$conn->query("SET time_zone = '+00:00'");
$sql = "SELECT id, value, time_received
        FROM sensor_value
        ORDER BY id DESC
        LIMIT 100";
$result = $conn->query($sql);
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$rows = array_reverse($rows);
$values = [];
$timestamps = [];
foreach ($rows as $row) {
    $time = new DateTime($row["time_received"], new DateTimeZone("UTC"));
    $time->setTimezone(new DateTimeZone("America/Los_Angeles"));
    $timestamps[] = $time->format("m/d H:i:s");
    $values[] = (int)$row["value"];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MQTT Encoder Dashboard</title>
    <meta http-equiv="refresh" content="15">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; background-color: #f4f4f4; }
        .chart-container { width: 90%; max-width: 1000px; margin: 30px auto; padding: 20px; background-color: white; border-radius: 10px; box-sizing: border-box; }
    </style>
</head>
<body>
    <h1>Sonoma State University</h1>
    <h2>EE 470 - Internet of Things</h2>
    <h3>MQTT Encoder Monitoring</h3>
    <p>ESP8266 - HiveMQ - Python - MariaDB</p>
    <div class="chart-container"><canvas id="mqttChart"></canvas></div>
    <p>Time zone: California</p>
    <p>Page refreshes every 15 seconds</p>
    <script>
        const timestamps = <?php echo json_encode($timestamps); ?>;
        const values = <?php echo json_encode($values); ?>;
        new Chart(document.getElementById('mqttChart'), {
            type: 'line',
            data: {
                labels: timestamps,
                datasets: [{
                    label: 'Encoder Value',
                    data: values,
                    borderColor: 'rgb(0, 150, 75)',
                    backgroundColor: 'rgba(0, 150, 75, 0.15)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: { title: { display: true, text: 'Time Received' } },
                    y: { title: { display: true, text: 'Encoder Value' } }
                }
            }
        });
    </script>
</body>
</html>
