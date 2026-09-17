<?php
session_start();
include('db_connect.php'); 

// Ensure gardener is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$gardener_username = $_SESSION['username'];

// Fetch plants + thresholds + latest sensor values
$query = "
    SELECT p.id, p.name, p.type, p.status,
           t.moisture_min, t.moisture_max, t.temperature_min, t.temperature_max,
           s.temperature AS latest_temp, s.moisture AS latest_moisture, s.light_level, s.timestamp
    FROM plants p
    LEFT JOIN thresholds t ON p.id = t.plant_id
    LEFT JOIN (
        SELECT sd1.*
        FROM sensor_data sd1
        INNER JOIN (
            SELECT plant_id, MAX(timestamp) AS latest_time
            FROM sensor_data
            GROUP BY plant_id
        ) sd2
        ON sd1.plant_id = sd2.plant_id AND sd1.timestamp = sd2.latest_time
    ) s ON p.id = s.plant_id
    WHERE p.gardener_username = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $gardener_username);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Plants - Bloombot</title>
    <link rel="stylesheet" href="CSS/style.css?v=7">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .plant-list {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    .plant {
        border: 1px solid #ce4242;
        padding: 15px;
        width: 300px;
        border-radius: 8px;
        background-color: #1d1717;
    }
    .plant h3 {
        margin-top: 0;
    }
    .no-plants {
        font-size: 18px;
        color: #555;
    }
    </style>
</head>
<body>
<div class="topnav">
    <a href="gardener_dashboard .php">Dashboard</a>  
    <a href="about.html">About</a>
    <a href="contact.html">Contact Us</a>   
    <a href="profile.php">My Profile</a>
    <a href="view_plants.php" class="active">My Plants</a>
    <div class="topnav-right">
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="header">
    <h1>My Plants</h1>
    <p>View all plants you're monitoring</p>
</div>

<div class="plant-list">
<?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
        <div class="plant">
            <h3><?= htmlspecialchars($row['name']) ?></h3>
            <p><strong>Type:</strong> <?= htmlspecialchars($row['type']) ?></p>
            <p><strong>Status:</strong> <?= !empty($row['status']) ? htmlspecialchars($row['status']) : 'Not Set' ?></p>
            
            <p><strong>Latest Sensor Data:</strong>
                Moisture: <?= $row['latest_moisture'] !== null ? $row['latest_moisture'] . "%" : "No data" ?>, 
                Temperature: <?= $row['latest_temp'] !== null ? $row['latest_temp'] . "°C" : "No data" ?>,
                Light: <?= $row['light_level'] !== null ? $row['light_level'] : "No data" ?>
            </p>

            <p><strong>Thresholds:</strong>
                Moisture: <?= $row['moisture_min'] ?>% - <?= $row['moisture_max'] ?>%, 
                Temperature: <?= $row['temperature_min'] ?>°C - <?= $row['temperature_max'] ?>°C
            </p>

            <a href="edit_plant.php?id=<?= $row['id'] ?>">Edit</a> | 
            <a href="delete_plant.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this plant?')">Delete</a>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div class="no-plants">You have not added any plants yet.</div>
<?php endif; ?>
</div>
</body>
</html>
