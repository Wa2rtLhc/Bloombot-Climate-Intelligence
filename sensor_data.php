<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "bloombot.");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Ensure gardener is logged in
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'gardener') {
    die("Access denied.");
}

$username = $_SESSION['username'];
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$download = isset($_GET['download']) && $_GET['download'] === '1';

$query = "
    SELECT sd.id, sd.plant_id, sd.temperature, sd.moisture, sd.light_level, sd.timestamp
    FROM sensor_data sd
    JOIN plants p ON sd.plant_id = p.id
    WHERE p.gardener_username = ?
";

$params = [$username];
$types = "s";

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND sd.timestamp BETWEEN ? AND ?";
    $types .= "ss";
    $params[] = $start_date . " 00:00:00";
    $params[] = $end_date . " 23:59:59";
}

$query .= " ORDER BY sd.timestamp DESC";

$stmt = $mysqli->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($download) {
    // Force download as CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sensor_report.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Plant ID', 'Temperature', 'Moisture', 'Light Level', 'Timestamp']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gardener Reports</title>
    <link rel="stylesheet" href="CSS/style.css?v=7">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .btn {
            text-decoration: none;
            background-color:rgb(40, 243, 33);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: inline-block;
        }
        .btn:hover {
            background-color:rgb(25, 210, 34);}
        form {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #aaa;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #e1f5c4;
        }
    </style>
</head>
<body>
    <a href="gardener_dashboard .php" class="btn">← Back to Dashboard</a>
    <h2>Sensor Data Report</h2>

    <form method="GET">
        <label>Start Date: <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"></label>
        <label>End Date: <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"></label>
        <button type="submit">Filter</button>
        <button type="submit" name="download" value="1">Download CSV</button>
    </form>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Plant ID</th>
                <th>Temperature (°C)</th>
                <th>Moisture (%)</th>
                <th>Light Level (lux)</th>
                <th>Timestamp</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row['plant_id'] ?></td>
                    <td><?= $row['temperature'] ?></td>
                    <td><?= $row['moisture'] ?></td>
                    <td><?= $row['light_level'] ?></td>
                    <td><?= $row['timestamp'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No sensor data for selected period.</p>
    <?php endif; ?>
</body>
</html>
