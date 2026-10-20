<?php
/**
 * EE 470 - IoT Database Assignment, Part 2
 * Read and display registered sensors and their measurements.
 *
 * Before uploading: create a private db_config.php from db_config.example.php.
 * Do NOT commit db_config.php with real credentials to GitHub.
 */

// The private db_config.php is intentionally excluded from GitHub.
$configPath = __DIR__ . '/db_config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Database configuration missing. Copy db_config.example.php to db_config.php and fill in the credentials.');
}
$config = require $configPath;
$dbHost = $config['host'];
$dbName = $config['database'];
$dbUser = $config['username'];
$dbPass = $config['password'];

// Use a specific registered node for the assignment's average calculations.
$averageNode = 'node_1';

$registeredSensors = [];
$sensorReadings = [];
$averageReading = null;
$errorMessage = null;

try {
    // PDO uses parameterized queries and can report database errors as exceptions.
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // First table: registered nodes and their geographic information.
    $registeredSensors = $pdo->query(
        'SELECT node_name, manufacturer, longitude, latitude
         FROM sensor_register
         ORDER BY node_name ASC'
    )->fetchAll();

    // Second table: sensor values, ordered by node first and time second.
    $sensorReadings = $pdo->query(
        'SELECT node_name, time_received, temperature, humidity
         FROM sensor_data
         ORDER BY node_name ASC, time_received ASC'
    )->fetchAll();

    // Calculate the average of each numeric sensor reading for node_1.
    $averageStmt = $pdo->prepare(
        'SELECT AVG(temperature) AS avg_temperature,
                AVG(humidity) AS avg_humidity,
                COUNT(*) AS num_readings
         FROM sensor_data
         WHERE node_name = :node_name'
    );
    $averageStmt->execute(['node_name' => $averageNode]);
    $averageReading = $averageStmt->fetch();
} catch (PDOException $exception) {
    // Do not show database credentials or internal SQL errors to website visitors.
    error_log('IoT database page: ' . $exception->getMessage());
    http_response_code(500);
    $errorMessage = 'Unable to load sensor data. Check the database name, username, password, and table names.';
}

// Escape output before inserting database strings into HTML.
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Sensor Database | EE 470</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f3f6fb; color: #192b41; }
        header { background: #12365c; color: white; padding: 32px 18px; text-align: center; }
        header h1 { margin: 0 0 10px; font-size: 1.9rem; }
        header p { margin: 0; color: #d8e4f0; }
        main { max-width: 1050px; margin: 28px auto; padding: 0 18px 32px; }
        section { background: white; border-radius: 12px; padding: 24px; margin-bottom: 22px; box-shadow: 0 2px 12px rgba(29, 53, 82, 0.08); }
        h2 { margin: 0 0 14px; font-size: 1.3rem; color: #12365c; }
        p { line-height: 1.5; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.96rem; }
        th, td { padding: 12px 14px; border-bottom: 1px solid #dde5ee; white-space: nowrap; }
        th { background: #dbe8f5; font-weight: 700; }
        tr:nth-child(even) td { background: #f7f9fc; }
        .note { color: #4a5d70; margin-top: 0; }
        .error { border-left: 5px solid #ba3345; color: #842734; }
        .average { font-size: 1.05rem; margin: 9px 0; }
        .back-link { display: inline-block; margin-bottom: 18px; color: #145990; }
        footer { text-align: center; padding: 12px; color: #5b6b7c; font-size: 0.9rem; }
    </style>
</head>
<body>
<header>
    <h1>Welcome to SSU IoT Lab</h1>
    <p>EE 470 &middot; Internet of Things &middot; Sensor Database</p>
</header>
<main>
    <a class="back-link" href="index.html">&larr; Back to IoT homepage</a>

    <?php if ($errorMessage !== null): ?>
    <section class="error">
        <h2>Database Connection Error</h2>
        <p><?= e($errorMessage) ?></p>
    </section>
    <?php else: ?>
    <section>
        <h2>Registered Sensor Nodes</h2>
        <p class="note"><?= count($registeredSensors) ?> registered sensors</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Node Name</th><th>Manufacturer</th><th>Longitude</th><th>Latitude</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($registeredSensors as $sensor): ?>
                    <tr>
                        <td><?= e($sensor['node_name']) ?></td>
                        <td><?= e($sensor['manufacturer']) ?></td>
                        <td><?= e($sensor['longitude']) ?></td>
                        <td><?= e($sensor['latitude']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$registeredSensors): ?>
                    <tr><td colspan="4">No registered sensors found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2>Data Received</h2>
        <p class="note"><?= count($sensorReadings) ?> readings, sorted by node name and time</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Node Name</th><th>Time Received</th><th>Temperature (&deg;C)</th><th>Humidity (%)</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($sensorReadings as $reading): ?>
                    <tr>
                        <td><?= e($reading['node_name']) ?></td>
                        <td><?= e($reading['time_received']) ?></td>
                        <td><?= e($reading['temperature']) ?></td>
                        <td><?= e($reading['humidity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$sensorReadings): ?>
                    <tr><td colspan="4">No sensor readings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2>Average Readings for <?= e($averageNode) ?></h2>
        <?php if ($averageReading && (int) $averageReading['num_readings'] > 0): ?>
        <p class="average">Average Temperature: <strong><?= e(number_format((float) $averageReading['avg_temperature'], 2)) ?> &deg;C</strong></p>
        <p class="average">Average Humidity: <strong><?= e(number_format((float) $averageReading['avg_humidity'], 2)) ?>%</strong></p>
        <p class="note">Based on <?= e($averageReading['num_readings']) ?> readings.</p>
        <?php else: ?>
        <p>No readings found for <?= e($averageNode) ?>.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
<footer>Sonoma State University &middot; EE 470 Database Assignment</footer>
</body>
</html>
