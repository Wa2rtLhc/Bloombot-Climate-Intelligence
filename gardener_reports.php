<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "bloombot.");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$gardener_username = $_SESSION['username'] ?? '';

// Handle filter form
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// Prepare base SQL
$sql = "
    SELECT 
        sd.*, 
        p.name AS plant_name, 
        t.temperature_min, t.temperature_max,
        t.moisture_min, t.moisture_max,
        t.light_min, t.light_max,
        n.message AS alert_message, 
        n.timestamp AS alert_time
    FROM sensor_data sd
    JOIN plants p ON sd.plant_id = p.id
    LEFT JOIN thresholds t ON sd.plant_id = t.plant_id
    LEFT JOIN notifications n ON sd.plant_id = n.plant_id 
        AND DATE(n.timestamp) = DATE(sd.timestamp)
    WHERE p.gardener_username = ?
";

// Apply date filters
if ($from && $to) {
    $sql .= " AND DATE(sd.timestamp) BETWEEN ? AND ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sss", $gardener_username, $from, $to);
} else {
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $gardener_username);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// Handle download
if (isset($_GET['download']) && count($rows) > 0) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="gardener_report.csv"');
    $output = fopen("php://output", "w");
    fputcsv($output, [
        "Timestamp", "Plant Name", 
        "Temperature", "Moisture", "Light Level",
        "Temperature Range", "Moisture Range", "Light Range",
        "Alert Message", "Alert Time"
    ]);
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['timestamp'], $r['plant_name'], 
            $r['temperature'], $r['moisture'], $r['light_level'],
            "{$r['temperature_min']} - {$r['temperature_max']}",
            "{$r['moisture_min']} - {$r['moisture_max']}",
            "{$r['light_min']} - {$r['light_max']}",
            $r['alert_message'] ?? '', $r['alert_time'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gardener Report</title>
     <link
        rel="stylesheet"
        href="CSS/style.css?v=9"
    >
    
</head>
<body>
<a href="gardener_dashboard .php" class="btn">← Back to Dashboard</a>
<h2>Gardener Report</h2>

<form method="get">
    <label>From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
    <label>To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
    <button type="submit" class="btn">Filter</button>
    <a href="?download=1&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn">Download CSV</a>
</form>

<?php if (count($rows) === 0): ?>
    <p>No data found for the selected period.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th>Timestamp</th>
            <th>Plant</th>
            <th>Temperature (°C)</th>
            <th>Moisture (%)</th>
            <th>Light (lux)</th>
            <th>Temp Range</th>
            <th>Moisture Range</th>
            <th>Alert</th>
            <th>Alert Time</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['timestamp']) ?></td>
            <td><?= htmlspecialchars($r['plant_name']) ?></td>
            <td><?= htmlspecialchars($r['temperature']) ?></td>
            <td><?= htmlspecialchars($r['moisture']) ?></td>
            <td><?= "{$r['temperature_min']} - {$r['temperature_max']}" ?></td>
            <td><?= "{$r['moisture_min']} - {$r['moisture_max']}" ?></td>
            <td><?= htmlspecialchars($r['alert_message'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['alert_time'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</body>
</html>
