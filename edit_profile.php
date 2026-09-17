<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);

    // Simple validation (you can expand this)
    if (!empty($username) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $email, $user_id);
        $stmt->execute();

        $success = "Profile updated successfully!";
    } else {
        $error = "Please enter a valid username and email.";
    }
}

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - Bloombot</title>
    <link rel="stylesheet" href="style.css?v=7">
    <style>
        .edit-container {
            max-width: 600px;
            margin: 60px auto;
            background: #fff;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }
        h2 {
            text-align: center;
            color: #2c7a5d;
        }
        form {
            margin-top: 20px;
        }
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        .btn-save {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .success {
            color: green;
            text-align: center;
        }
        .error {
            color: red;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="edit-container">
    <h2>Edit Profile</h2>

    <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST" action="">
        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <button type="submit" class="btn-save">Save Changes</button>
    </form>

    <div class="back-link">
        <a href="profile.php">← Back to Profile</a>
    </div>
</div>

</body>
</html>
