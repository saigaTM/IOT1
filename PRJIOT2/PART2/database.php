<?php
// EE 470 - Database Assignment, Part 2, Section 3
// This uses your existing connection.php file.
require "connection.php";

// Get the two tables from Part 1.
$sensors = $conn->query("SELECT * FROM sensor_register ORDER BY node_name");
$readings = $conn->query("SELECT * FROM sensor_data ORDER BY node_name, time_received");

// Keep the node_1 average from Section 2.
$average = $conn->query(
    "SELECT AVG(temperature) AS avg_temp, AVG(humidity) AS avg_hum
     FROM sensor_data WHERE node_name = 'node_1'"
)->fetch_assoc();

// Get all registered node names for the dropdown menu.
$nodes = [];
$result = $conn->query("SELECT node_name FROM sensor_register ORDER BY node_name");
while ($row = $result->fetch_assoc()) {
    $nodes[] = $row['node_name'];
}

// Read which node the user selected. Default is node_1.
$selectedNode = $_GET['node'] ?? 'node_1';
if (!in_array($selectedNode, $nodes, true)) {
    $selectedNode = $nodes[0] ?? '';
}

// Get the graph's temperature and time values for the selected node.
$stmt = $conn->prepare(
    "SELECT time_received, temperature, humidity
     FROM sensor_data WHERE node_name = ? ORDER BY time_received"
);
$stmt->bind_param("s", $selectedNode);
$stmt->execute();
$chartRows = $stmt->get_result();

$times = [];
$temperatures = [];
$humidities = [];
while ($row = $chartRows->fetch_assoc()) {
    $times[] = $row['time_received'];
    $temperatures[] = (float) $row['temperature'];
    $humidities[] = (float) $row['humidity'];
}
$stmt->close();

// Calculate the four statistics required by the assignment.
$count = count($temperatures);
$avgTemp = $count ? array_sum($temperatures) / $count : null;
$avgHum = $count ? array_sum($humidities) / $count : null;
$maxTemp = $count ? max($temperatures) : null;
$minHum = $count ? min($humidities) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EE 470 Sensor Database</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 25px; }
        table { border-collapse: collapse; margin-bottom: 25px; }
        th, td { border: 1px solid #777; padding: 7px 12px; text-align: left; }
        th { background: #e0f0e0; }
        canvas { max-width: 900px; }
        p { margin: 8px 0; }
    </style>
</head>
<body>
    <h1>EE 470 - Sensor Database</h1>

    <h2>Registered Sensors</h2>
    <table>
        <tr><th>Node</th><th>Manufacturer</th><th>Longitude</th><th>Latitude</th></tr>
        <?php while ($row = $sensors->fetch_assoc()) { ?>
        <tr>
            <td><?= htmlspecialchars($row['node_name']) ?></td>
            <td><?= htmlspecialchars($row['manufacturer']) ?></td>
            <td><?= htmlspecialchars($row['longitude']) ?></td>
            <td><?= htmlspecialchars($row['latitude']) ?></td>
        </tr>
        <?php } ?>
    </table>

    <h2>Sensor Measurements</h2>
    <table>
        <tr><th>Node</th><th>Time</th><th>Temperature (°C)</th><th>Humidity (%)</th></tr>
        <?php while ($row = $readings->fetch_assoc()) { ?>
        <tr>
            <td><?= htmlspecialchars($row['node_name']) ?></td>
            <td><?= htmlspecialchars($row['time_received']) ?></td>
            <td><?= htmlspecialchars($row['temperature']) ?></td>
            <td><?= htmlspecialchars($row['humidity']) ?></td>
        </tr>
        <?php } ?>
    </table>

    <h2>Node 1 Averages</h2>
    <p>Average Temperature: <?= $average['avg_temp'] === null ? 'N/A' : number_format($average['avg_temp'], 2) . ' °C' ?></p>
    <p>Average Humidity: <?= $average['avg_hum'] === null ? 'N/A' : number_format($average['avg_hum'], 2) . ' %' ?></p>

    <h2>Temperature Graph</h2>
    <form method="GET" action="database.php">
        <label for="node">Select sensor node:</label>
        <select name="node" id="node" onchange="this.form.submit()">
            <?php foreach ($nodes as $node) { ?>
            <option value="<?= htmlspecialchars($node) ?>" <?= $node === $selectedNode ? 'selected' : '' ?>>
                <?= htmlspecialchars($node) ?>
            </option>
            <?php } ?>
        </select>
    </form>

    <canvas id="sensorChart"></canvas>
    <?php if ($count === 0) { ?>
        <p>No measurements for this node.</p>
    <?php } ?>

    <h2>Statistics for <?= htmlspecialchars($selectedNode) ?></h2>
    <p>Average Temperature: <?= $avgTemp === null ? 'N/A' : number_format($avgTemp, 2) . ' °C' ?></p>
    <p>Average Humidity: <?= $avgHum === null ? 'N/A' : number_format($avgHum, 2) . ' %' ?></p>
    <p>Maximum Temperature: <?= $maxTemp === null ? 'N/A' : number_format($maxTemp, 2) . ' °C' ?></p>
    <p>Minimum Humidity: <?= $minHum === null ? 'N/A' : number_format($minHum, 2) . ' %' ?></p>

    <p><a href="sensor_json.php?node=<?= urlencode($selectedNode) ?>">Show data for this node in JSON</a></p>

    <!-- Chart.js draws the graph using the data PHP loaded from MariaDB. -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const times = <?= json_encode($times) ?>;
        const temperatures = <?= json_encode($temperatures) ?>;
        const nodeName = <?= json_encode($selectedNode) ?>;

        new Chart(document.getElementById('sensorChart'), {
            type: 'line', // Change to 'bar' for a bar graph.
            data: {
                labels: times,
                datasets: [{
                    label: 'Temperature (°C)',
                    data: temperatures,
                    borderColor: 'green',
                    backgroundColor: 'rgba(0, 128, 0, 0.2)',
                    tension: 0.2
                }]
            },
            options: {
                plugins: { title: { display: true, text: 'Sensor Node ' + nodeName } },
                scales: {
                    x: { title: { display: true, text: 'Time' } },
                    y: { title: { display: true, text: 'Temperature (°C)' } }
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
