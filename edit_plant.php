<?php
session_start();

// Connect to database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bloombot.'; // removed extra dot
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get plant ID
$id = $_GET['id'] ?? '';
if (!$id) {
    die("Invalid plant ID");
}

// Fetch plant data
$result = $conn->query("SELECT * FROM plants WHERE id = $id");
if (!$result || $result->num_rows === 0) {
    die("Plant not found");
}
$plant = $result->fetch_assoc();

// Update form logic
if (isset($_POST['submit'])) {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE plants SET name = ?, type = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $type, $status, $id);

    if ($stmt->execute()) {
        // Check role from session
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            echo "<script>alert('Plant updated successfully!'); window.location.href='admin_dashboard.php';</script>";
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'gardener') {
            echo "<script>alert('Plant updated successfully!'); window.location.href='view_plants.php';</script>";
        } else {
            echo "<script>alert('Plant updated successfully!'); window.location.href='login.php';</script>";
        }
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Plant</title>
    <link rel="stylesheet" href="CSS/style.css?v=7">
</head>
<body>
    <h2>Edit Plant</h2>
    <form method="POST">
        <label>Plant Name:</label><br>
        <input type="text" name="name" value="<?= htmlspecialchars($plant['name']) ?>" required><br><br>

        <label>Type:</label><br>
        <input type="text" name="type" value="<?= htmlspecialchars($plant['type']) ?>" required><br><br>

        <label>Status:</label><br>
        <input type="text" name="status" value="<?= htmlspecialchars($plant['status']) ?>"><br><br>

        <button type="submit" name="submit">Update Plant</button>
    </form>
</body>
</html>
