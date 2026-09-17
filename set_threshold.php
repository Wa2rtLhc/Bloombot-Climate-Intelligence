<?php
session_start();

// Check if user is logged in and is a gardener
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'gardener') {
    header("Location: login.html");
    exit;
}

// Connect to the database
$conn = new mysqli("localhost", "root", "", "bloombot.");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];
$message = "";

// Only process if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data with fallback
    $plant_id = $_POST['plant_id'] ?? null;
    $moisture_min = $_POST['moisture_min'] ?? null;
    $moisture_max = $_POST['moisture_max'] ?? null;
    $temperature_min = $_POST['temperature_min'] ?? null;
    $temperature_max = $_POST['temperature_max'] ?? null;

    // Basic validation
    if (!$plant_id || !$moisture_min || !$moisture_max || !$temperature_min || !$temperature_max) {
        $message = "<p style='color:red;'>All fields are required.</p>";
 } else {
        // Check if plant belongs to this gardener
        $stmt = $conn->prepare("SELECT id FROM plants WHERE id = ? AND gardener_username = ?");
        $stmt->bind_param("is", $plant_id, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $message = "<p style='color:red;'>Invalid plant selected or it doesn't belong to you.</p>";
        } else {
            // Check if threshold already exists
            $check = $conn->prepare("SELECT plant_id FROM thresholds WHERE plant_id = ?");
            $check->bind_param("i", $plant_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                // Update existing
                $update = $conn->prepare("UPDATE thresholds SET moisture_min=?, moisture_max=?, temperature_min=?, temperature_max=? WHERE plant_id=?");
                $update->bind_param("idddi", $moisture_min, $moisture_max, $temperature_min, $temperature_max, $plant_id);
                $update->execute();
                $message = "<p style='color:lightgreen;'>Thresholds updated successfully.</p>";
                $update->close();
                 } else {
                // Insert new
                $insert = $conn->prepare("INSERT INTO thresholds (plant_id, moisture_min, moisture_max, temperature_min, temperature_max) VALUES (?, ?, ?, ?, ?)");
                $insert->bind_param("idddd", $plant_id, $moisture_min, $moisture_max, $temperature_min, $temperature_max);
                $insert->execute();
                $message = "<p style='color:lightgreen;'>Thresholds set successfully.</p>";
                $insert->close();
            }

            $check->close();
        }

        $stmt->close();
    }
}

// Fetch plants owned by this gardener for the dropdown
$plants_result = $conn->prepare("SELECT id, name FROM plants WHERE gardener_username = ?");
$plants_result->bind_param("s", $username);
$plants_result->execute();
$plants = $plants_result->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Plant Thresholds</title>
    <link rel="stylesheet" href="CSS/style.css?v=8">
    <style>
        body {
            background-image: url('bg.jpg');
            background-size: cover;
            font-family: Arial, sans-serif;
            color: white;
            padding: 40px;
            text-align: center;
        }
        .container {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            margin: auto;
        }
        label, select, input {
            display: block;
            width: 100%;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #00c853;
            color: white;
            border: none;
            border-radius: 5px;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            color: #ffeb3b;
            text-decoration: none;
        }
        h2 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Set Plant Thresholds</h2>
    <?php echo $message; ?>
    <form action="set_threshold.php" method="post">
        <label for="plant_id">Select Plant:</label>
        <select id="plant_id" name="plant_id" required>
            <option value="">-- Choose Plant --</option>
            <?php while ($row = $plants->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
            <?php endwhile; ?>
        </select>

        <label for="moisture_min">Moisture Min (%):</label>
        <input type="number" id="moisture_min" name="moisture_min" min="0" max="100" required>

        <label for="moisture_max">Moisture Max (%):</label>
        <input type="number" id="moisture_max" name="moisture_max" min="0" max="100" required>

        <label for="temperature_min">Temperature Min (°C):</label>
        <input type="number" id="temperature_min" name="temperature_min" step="0.1" required>

        <label for="temperature_max">Temperature Max (°C):</label>
        <input type="number" id="temperature_max" name="temperature_max" step="0.1" required>

        <label for="light_min">Light Min (%):</label>
        <input type="number" id="light_min" name="light_min" min="0" max="100" required>
        <label for="light_max">Light Max (%):</label>
        <input type="number" id="light_max" name="light_max" min="0" max="100" required>



        <button class="btn" type="submit">Save Thresholds</button>
    </form>
    <a href="gardener_dashboard .php">← Back to Dashboard</a>
</div>
</body>
</html>

