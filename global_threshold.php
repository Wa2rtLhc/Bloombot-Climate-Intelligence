
<?php
include 'db_connect.php'; // Database connection
session_start();

// Fetch current global thresholds
$query = "SELECT * FROM global_thresholds LIMIT 1";
$result = mysqli_query($conn, $query);
$current = mysqli_fetch_assoc($result);

// Update logic
if (isset($_POST['update'])) {
    $temp_min = $_POST['temp_min'];
    $temp_max = $_POST['temp_max'];
    $moist_min = $_POST['moist_min'];
    $moist_max = $_POST['moist_max'];
    $light_min = $_POST['light_min'];
    $light_max = $_POST['light_max'];

    mysqli_query($conn, "
        UPDATE global_thresholds SET
        temperature_min = $temp_min,
        temperature_max = $temp_max,
        moisture_min = $moist_min,
        moisture_max = $moist_max,
        light_min = $light_min,
        light_max = $light_max
        WHERE id = {$current['id']}
    ");
    echo "<script>alert('Global thresholds updated.'); window.location.href='global_threshold.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Configure Global Thresholds</title>
    <link rel="stylesheet" href="CSS/style.css?v=7">
</head>
<body>
    <h2>Global Threshold Settings</h2>
    <form method="post">
        <label>Temperature Min: <input type="number" step="0.1" name="temp_min" value="<?= $current['temperature_min'] ?>"></label><br>
        <label>Temperature Max: <input type="number" step="0.1" name="temp_max" value="<?= $current['temperature_max'] ?>"></label><br><br>

        <label>Moisture Min: <input type="number" step="0.1" name="moist_min" value="<?= $current['moisture_min'] ?>"></label><br>
        <label>Moisture Max: <input type="number" step="0.1" name="moist_max" value="<?= $current['moisture_max'] ?>"></label><br><br>

        <label>Light Min: <input type="number" step="0.1" name="light_min" value="<?= $current['light_min'] ?>"></label><br>
        <label>Light Max: <input type="number" step="0.1" name="light_max" value="<?= $current['light_max'] ?>"></label><br><br>

        <button type="submit" name="update">Update Thresholds</button>
    </form>
    <a href="gardener_dashboard .php">← Back to Dashboard</a>
</body>
</html>
