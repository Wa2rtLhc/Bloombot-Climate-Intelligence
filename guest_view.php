<?php
// 1. Start session and connect to database
session_start();

$host = "localhost";
$username = "root";
$password = "";
$database = "bloombot.";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bloombot Guest View</title>
  <link rel="stylesheet" href="CSS/styles.css?v=7"> <!-- Apply your custom CSS -->
</head>
<body>

<header>
  <h1>🌿 Welcome to Bloombot</h1>
</header>

<div class="container">
  <h2>Smart Gardening for Everyone</h2>
  <p>Bloombot helps you monitor plant health using sensor data like moisture, temperature, and light. Stay informed, stay green!</p>
  <p><strong>As a guest</strong>, you can view plant and sensor data. To unlock full features like alerts, tracking, and adding plants, <a href="login.html">log in</a> or <a href="signup.html">create an account</a>.</p>

  <!-- Search Box -->
  <form method="GET" class="search-box">
    <input type="text" name="search" placeholder="Search plant..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
  </form>

  <!-- Display Plant Table -->
  <?php
  $sql = "SELECT * FROM plants";
  if (!empty($search)) {
      $sql .= " WHERE name LIKE '%" . $conn->real_escape_string($search) . "%'";
  }
  $sql .= " ORDER BY planted_date DESC";

  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
      echo "<h3>🌱 Plant Info</h3><table>";
      echo "<tr><th>Name</th><th>Type</
      th><th>Location</th><th>Status</th><th>Planted Date</th></tr>";
      while ($row = $result->fetch_assoc()) {
          echo "<tr>
                  <td>{$row['name']}</td>
                  <td>{$row['type']}</td>
                  <td>{$row['location']}</td>
                  <td>{$row['status']}</td>
                  <td>{$row['planted_date']}</td>
                </tr>";
      }
      echo "</table>";
  } else {
      echo "<p>No plants found.</p>";
  }
  ?>

  <!-- Display Sensor Data -->
  <h3>📊 Recent Sensor Data</h3>
  <?php
  $sensor_sql = "SELECT s.*, p.name AS plant_name FROM sensor_data s 
                 JOIN plants p ON s.plant_id = p.id 
                 ORDER BY s.timestamp DESC LIMIT 10";
  $sensor_result = $conn->query($sensor_sql);
  if ($sensor_result->num_rows > 0) {
      echo "<table>
              <tr>
                <th>Plant</th><th>Moisture</th><th>Temp (°C)</th><th>Light</th><th>Time</th>
              </tr>";
      while ($s = $sensor_result->fetch_assoc()) {
          echo "<tr>
                  <td>{$s['plant_name']}</td>
                  <td>{$s['moisture']}</td>
                  <td>{$s['temperature']}</td>
                  <td>{$s['light_level']}</td>
                  <td>{$s['timestamp']}</td>
                </tr>";
      }
      echo "</table>";
  } else {
      echo "<p>No sensor data available.</p>";
  }

  $conn->close();
  ?>
</div>

<footer>
  <p>&copy; <?= date("Y") ?> Bloombot | Smart Gardening System</p>
</footer>

</body>
</html>
