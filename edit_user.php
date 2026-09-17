<?php
session_start();

// DB connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bloombot.';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID
$id = $_GET['id'] ?? '';
if (!$id) {
    die("Invalid user ID");
}

// Fetch user
$result = $conn->query("SELECT * FROM users WHERE id = $id");
if (!$result || $result->num_rows === 0) {
    die("User not found");
}
$user = $result->fetch_assoc();

// Handle form submission
if (isset($_POST['submit'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($username && $email && $role) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $username, $email, $role, $id);

        if ($stmt->execute()) {
            echo "<script>alert('User updated successfully!'); window.location.href='admin_dashboard.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "<script>alert('Please fill all fields.');</script>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="CSS/style.css?v=7">
</head>
<body>
    <h2>Edit User</h2>
    <form method="POST">
        <label>Username:</label><br>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><br><br>

        <label>Role:</label><br>
        <select name="role" required>
            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="gardener" <?= $user['role'] == 'gardener' ? 'selected' : '' ?>>Gardener</option>
            <option value="guest" <?= $user['role'] == 'guest' ? 'selected' : '' ?>>Guest</option>
        </select><br><br>

        <button type="submit" name="submit">Update User</button>
    </form>
</body>
</html>
