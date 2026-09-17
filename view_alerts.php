<?php
$mysqli = new mysqli("localhost", "root", "", "bloombot.");

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

// Capture filter inputs
$plantFilter = $_GET['plant'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

// Build query dynamically with filters
$query = "SELECT notifications.*, plants.name AS plant_name
          FROM notifications
          LEFT JOIN plants ON notifications.plant_id = plants.id
          WHERE 1=1";

$params = [];
$types = "";

// Add filters
if (!empty($plantFilter)) {
    $query .= " AND plants.name LIKE ?";
    $params[] = "%$plantFilter%";
    $types .= "s";
}

if (!empty($fromDate)) {
    $query .= " AND notifications.timestamp >= ?";
    $params[] = $fromDate . " 00:00:00";
    $types .= "s";
}

if (!empty($toDate)) {
    $query .= " AND notifications.timestamp <= ?";
    $params[] = $toDate . " 23:59:59";
    $types .= "s";
}

$query .= " ORDER BY notifications.timestamp DESC";

// Prepare and bind
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
     <link
        rel="stylesheet"
        href="CSS/style.css?v=7"
    >
    <title>All Alerts - Bloombot</title>
    
</head>
<body>

<a href="gardener_dashboard .php" class="btn">← Back to Dashboard</a>
<a href="download_alerts.php" class="btn" style="background-color: #4CAF50;">⬇ Download CSV</a>

<h2>All Alerts</h2>

<form method="GET" action="view_alerts.php">
    <input type="text" name="plant" placeholder="Search plant..." value="<?= htmlspecialchars($plantFilter) ?>">
    <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
    <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
    <button type="submit">Filter</button>
    <a href="view_alerts.php" class="btn" style="background-color:#ccc; color:black;">Reset</a>
</form>

<table>
    <thead>
        <tr>
            <th>Plant</th>
            <th>Message</th>
            <th>Timestamp</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['plant_name'] ?? "Plant #" . $row['plant_id']) ?></td>
                    <td><?= htmlspecialchars($row['message']) ?></td>
                    <td><?= htmlspecialchars($row['timestamp']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">No alerts found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
