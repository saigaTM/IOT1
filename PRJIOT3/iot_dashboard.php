<?php
/*
 * EE470 - ESP8266 Sensor Dashboard
 * File: iot_dashboard.php
 * Version: 1.0
 * Inputs: registered nodes, stored measurements, sensor activity
 * Output: statistics, node selection, graphs, and latest records
 * Dependencies: config.php in the same directory; Chart.js via CDN
 * Usage: upload to public_html beside config.php and insert.php
 */

declare(strict_types=1);
date_default_timezone_set('America/Los_Angeles');

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function n($value, string $unit = ''): string {
    return $value === null ? '—' : number_format((float)$value, 2) . $unit;
}

try {
    $config = require __DIR__ . '/config.php';
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // List ONLY nodes registered in the database, including nodes with zero measurements.
    $nodes = $pdo->query(
        'SELECT r.node_name, COALESCE(a.valid_count, 0) AS valid_count
         FROM sensor_register AS r
         LEFT JOIN sensor_activity AS a ON a.node_name = r.node_name
         ORDER BY r.node_name'
    )->fetchAll();

    $registered = array_column($nodes, 'node_name');
    $selected = (string)($_GET['node'] ?? 'all');
    if ($selected !== 'all' && !in_array($selected, $registered, true)) {
        $selected = 'all';
    }

    // Always calculate statistics from the complete selection, not just the plotted 250 rows.
    $where = $selected === 'all'
        ? ' WHERE node_name IN (SELECT node_name FROM sensor_register)'
        : ' WHERE node_name = :node';
    $statsQuery =
        'SELECT COUNT(*) AS record_count,
          AVG(CASE WHEN node_name <> \'node_2\' THEN temperature END) AS temp_avg,
          MIN(CASE WHEN node_name <> \'node_2\' THEN temperature END) AS temp_min,
          MAX(CASE WHEN node_name <> \'node_2\' THEN temperature END) AS temp_max,
          AVG(CASE WHEN node_name <> \'node_2\' THEN humidity END) AS hum_avg,
          MIN(CASE WHEN node_name <> \'node_2\' THEN humidity END) AS hum_min,
          MAX(CASE WHEN node_name <> \'node_2\' THEN humidity END) AS hum_max,
          AVG(CASE WHEN node_name = \'node_2\' THEN temperature2 END) AS hw_avg,
          MIN(CASE WHEN node_name = \'node_2\' THEN temperature2 END) AS hw_min,
          MAX(CASE WHEN node_name = \'node_2\' THEN temperature2 END) AS hw_max
         FROM sensor_data' . $where;
    $stmt = $pdo->prepare($statsQuery);
    if ($selected !== 'all') $stmt->bindValue(':node', $selected);
    $stmt->execute();
    $stats = $stmt->fetch();

    // Only the most recent 250 records are plotted to keep the page fast.
    $stmt = $pdo->prepare(
        'SELECT node_name, time_received, temperature, humidity, temperature2, time_zone
         FROM sensor_data' . $where . '
         ORDER BY time_received DESC LIMIT 250'
    );
    if ($selected !== 'all') $stmt->bindValue(':node', $selected);
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll());
    $recent = array_reverse(array_slice($rows, -15));

} catch (Throwable $e) {
    error_log('EE470 dashboard error: ' . $e->getMessage());
    http_response_code(500);
    exit('The dashboard could not load. Check config.php, the database tables, and the server error log.');
}

$graphs = [
    ['label' => 'Temperature (DHT11 / legacy)', 'avg' => $stats['temp_avg'], 'min' => $stats['temp_min'], 'max' => $stats['temp_max'], 'unit' => ' °C'],
    ['label' => 'Humidity (DHT11 / legacy)', 'avg' => $stats['hum_avg'], 'min' => $stats['hum_min'], 'max' => $stats['hum_max'], 'unit' => ' %'],
    ['label' => 'HW-506 temperature', 'avg' => $stats['hw_avg'], 'min' => $stats['hw_min'], 'max' => $stats['hw_max'], 'unit' => ' °C'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="refresh" content="30">
  <title>EE470 | ESP8266 Sensor Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
  <style>
    :root{font-family:Arial,Helvetica,sans-serif;color:#17233c;background:#f4f7fb}
    *{box-sizing:border-box}body{margin:0}main{max-width:1100px;margin:auto;padding:25px 20px 50px}
    header{background:#12284b;color:white;padding:25px 20px}header>div{max-width:1100px;margin:auto}
    h1{font-size:25px;margin:0 0 8px}h2{font-size:18px;margin:0 0 14px}
    p{line-height:1.5}small,.muted{color:#67768b}.meta{color:#c8d5e7;margin:0}
    .toolbar{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin:22px 0}
    label{font-weight:600;font-size:14px;display:block;margin-bottom:6px}
    select,.btn{border:1px solid #c7d2e2;border-radius:9px;padding:10px 14px;background:white;color:#132746;font-size:14px}
    .btn{display:inline-block;text-decoration:none;cursor:pointer}.btn:hover{background:#e8eef8}
    .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}
    .card,.panel{background:white;border:1px solid #e1e7f0;border-radius:13px;padding:18px;box-shadow:0 2px 9px #162b4c0a}
    .number{font-size:25px;font-weight:700;margin:9px 0}.stats{font-size:14px;line-height:1.9;color:#51647e}
    .section{margin-top:24px}.chart-wrap{position:relative;height:280px}
    .charts{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:14px}
    th,td{text-align:left;padding:10px;border-bottom:1px solid #e8edf4;white-space:nowrap}
    th{background:#f2f5fa;color:#334962}tr:last-child td{border-bottom:0}
    .chip{background:#edf5ff;color:#17497b;border-radius:18px;padding:4px 9px;display:inline-block}
    @media(max-width:760px){.grid,.charts{grid-template-columns:1fr}.chart-wrap{height:255px}}
  </style>
</head>
<body>
<header><div>
  <h1>EE470 — ESP8266 Sensor Dashboard</h1>
  <p class="meta">Node 1: DHT11 temperature + humidity &nbsp; | &nbsp; Node 2: HW-506 temperature</p>
</div></header>
<main>
  <form method="get" class="toolbar">
    <div>
      <label for="node">Registered node</label>
      <select id="node" name="node" onchange="this.form.submit()">
        <option value="all" <?= $selected === 'all' ? 'selected' : '' ?>>All registered nodes</option>
        <?php foreach ($nodes as $node): ?>
          <option value="<?= h($node['node_name']) ?>" <?= $selected === $node['node_name'] ? 'selected' : '' ?>><?= h($node['node_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <a class="btn" href="?node=<?= rawurlencode($selected) ?>">Refresh now</a>
    <small>Auto-refresh every 30 seconds · <?= h(date('Y-m-d H:i:s T')) ?></small>
  </form>

  <section class="grid">
    <div class="card"><div class="muted">Stored measurements (selected)</div><div class="number"><?= (int)$stats['record_count'] ?></div><small>Includes earlier assignment records</small></div>
    <div class="card"><div class="muted">Registered nodes</div><div class="number"><?= count($nodes) ?></div><small>From sensor_register</small></div>
    <div class="card"><div class="muted">Selected view</div><div class="number"><?= h($selected === 'all' ? 'All nodes' : $selected) ?></div><small>Charts and statistics follow this filter</small></div>
  </section>

  <section class="section">
    <h2>Measurement statistics</h2>
    <div class="grid">
      <?php foreach ($graphs as $metric): ?>
        <div class="card">
          <strong><?= h($metric['label']) ?></strong>
          <div class="stats">Average: <b><?= h(n($metric['avg'], $metric['unit'])) ?></b><br>
          Minimum: <b><?= h(n($metric['min'], $metric['unit'])) ?></b><br>
          Maximum: <b><?= h(n($metric['max'], $metric['unit'])) ?></b></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="muted">A dash means this node has no measurements of that sensor type. Older records may contain legacy measurements.</p>
  </section>

  <section class="section charts">
    <div class="panel"><h2>Temperature measurements</h2><div class="chart-wrap"><canvas id="temperatureChart"></canvas></div></div>
    <div class="panel"><h2>Humidity measurements</h2><div class="chart-wrap"><canvas id="humidityChart"></canvas></div></div>
  </section>

  <section class="section panel">
    <h2>Unique accepted measurements by registered node</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Registered node</th><th>Unique accepted count</th></tr></thead>
      <tbody>
      <?php foreach ($nodes as $node): ?>
        <tr><td><?= h($node['node_name']) ?></td><td><span class="chip"><?= (int)$node['valid_count'] ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="section panel">
    <h2>Recent measurements — <?= h($selected === 'all' ? 'all registered nodes' : $selected) ?></h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Node</th><th>Timestamp</th><th>Temperature (°C)</th><th>Humidity (%)</th><th>HW-506 (°C)</th><th>Time zone</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= h($r['node_name']) ?></td><td><?= h($r['time_received']) ?></td>
          <td><?= h(n($r['temperature'])) ?></td><td><?= h(n($r['humidity'])) ?></td>
          <td><?= h(n($r['temperature2'])) ?></td><td><?= h($r['time_zone'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="6">No measurements for this node yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</main>
<script>
const records = <?= json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const labels = records.map(r => `${r.node_name} · ${r.time_received}`);
const options = {responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}},scales:{x:{ticks:{maxTicksLimit:8}}}};
new Chart(document.getElementById('temperatureChart'), {
    type:'line',
    data:{labels,datasets:[
      {label:'DHT11 / legacy (°C)',data:records.map(r => r.node_name === 'node_2' ? null : r.temperature),borderColor:'#2563eb',backgroundColor:'#2563eb',spanGaps:false,tension:0.2},
      {label:'HW-506 (°C)',data:records.map(r => r.node_name === 'node_2' ? r.temperature2 : null),borderColor:'#059669',backgroundColor:'#059669',spanGaps:false,tension:0.2}
    ]},options
});
new Chart(document.getElementById('humidityChart'), {
    type:'line',
    data:{labels,datasets:[
      {label:'DHT11 / legacy humidity (%)',data:records.map(r => r.node_name === 'node_2' ? null : r.humidity),borderColor:'#9333ea',backgroundColor:'#9333ea',spanGaps:false,tension:0.2}
    ]},options:{...options,scales:{...options.scales,y:{min:0,max:100}}}
});
</script>
</body>
</html>
