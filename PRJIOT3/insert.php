<?php
/* EE470 IoT - node-specific sensor insertion endpoint
 * node_1: DHT11 temperature + humidity
 * node_2: HW-506 DS18B20 temperature2 only
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reply(int $code, string $status, bool $accepted, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['status' => $status, 'accepted' => $accepted], $extra));
    exit;
}

if (!is_file(__DIR__ . '/config.php')) reply(500, 'configuration_error', false);
$config = require __DIR__ . '/config.php';
$expected = (string)($config['api_token'] ?? '');
$received = (string)($_SERVER['HTTP_X_IOT_KEY'] ?? '');
if ($expected === '' || !hash_equals($expected, $received)) reply(401, 'unauthorized', false);
if ($_SERVER['REQUEST_METHOD'] !== 'GET') reply(405, 'method_not_allowed', false);

$node = $_GET['nodeId'] ?? '';
$stamp = $_GET['timeReceived'] ?? '';
$zone = $_GET['timeZone'] ?? '';
if (!is_string($node) || !in_array($node, ['node_1','node_2'], true)) reply(400, 'invalid_node', false);

$validZones = ['America/New_York', 'America/Chicago', 'America/Denver',
               'America/Los_Angeles', 'America/Anchorage', 'Pacific/Honolulu',
               'America/Puerto_Rico'];
if (!is_string($zone) || !in_array($zone, $validZones, true)) reply(400, 'invalid_timezone', false);
if (!is_string($stamp) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}$/D', $stamp)) {
    reply(400, 'invalid_timestamp', false);
}
$dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.v', $stamp, new DateTimeZone('UTC'));
if (!$dt || $dt->format('Y-m-d H:i:s.v') !== $stamp) reply(400, 'invalid_timestamp', false);

// Nullable fields are intentional: one physical sensor is associated with each node.
$temp = null;
$humidity = null;
$temp2 = null;
if ($node === 'node_1') {
    if (!isset($_GET['nodeTemp'], $_GET['nodeHum'])) reply(400, 'missing_dht11_values', false);
    $temp = filter_var($_GET['nodeTemp'], FILTER_VALIDATE_FLOAT);
    $humidity = filter_var($_GET['nodeHum'], FILTER_VALIDATE_FLOAT);
    if ($temp === false || $humidity === false || $temp < -10 || $temp > 100 || $humidity < 0 || $humidity > 100) {
        reply(400, 'invalid_dht11_values', false);
    }
} else {
    if (!isset($_GET['nodeTemp2'])) reply(400, 'missing_hw506_value', false);
    $temp2 = filter_var($_GET['nodeTemp2'], FILTER_VALIDATE_FLOAT);
    if ($temp2 === false || $temp2 < -55 || $temp2 > 125) reply(400, 'invalid_hw506_value', false);
}

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
    $q = $pdo->prepare('SELECT 1 FROM sensor_register WHERE node_name = ?');
    $q->execute([$node]);
    if (!$q->fetchColumn()) reply(403, 'unregistered_node', false);

    $pdo->beginTransaction();
    $q = $pdo->prepare('INSERT INTO sensor_data
        (node_name, time_received, temperature, humidity, temperature2, time_zone)
        VALUES (?, ?, ?, ?, ?, ?)');
    $q->execute([$node, $stamp, $temp, $humidity, $temp2, $zone]);

    $q = $pdo->prepare('INSERT INTO sensor_activity (node_name, valid_count)
        VALUES (?, 1) ON DUPLICATE KEY UPDATE valid_count = valid_count + 1');
    $q->execute([$node]);
    $pdo->commit();
    reply(201, 'inserted', true, ['node' => $node, 'time_received' => $stamp]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if ((int)($e->errorInfo[1] ?? 0) === 1062 && isset($pdo)) {
        try {
            $q = $pdo->prepare('SELECT 1 FROM sensor_data WHERE node_name = ? AND time_received = ?');
            $q->execute([$node, $stamp]);
            if ($q->fetchColumn()) {
                reply(200, 'duplicate', true, ['node' => $node, 'time_received' => $stamp]);
            }
        } catch (PDOException $unused) {}
    }
    error_log('EE470 insert: ' . $e->getMessage());
    reply(500, 'database_error', false);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('EE470 unexpected: ' . $e->getMessage());
    reply(500, 'server_error', false);
}
