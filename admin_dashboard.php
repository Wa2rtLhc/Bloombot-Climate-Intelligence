<?php
session_start();
include('db_connect.php');
// Timeout duration in seconds (e.g., 15 minutes)
$inactive = 900; 

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?message=Please log in to continue");
    exit();
}

// Check for session timeout
if (isset($_SESSION['last_activity'])) {
    $session_life = time() - $_SESSION['last_activity'];
    if ($session_life > $inactive) {
        // Destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: login.php?message=Session expired, please log in again");
        exit();
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch users and their plants
$query = "
    SELECT u.id as user_id, u.username, u.email, u.role,
           p.id as plant_id, p.name as plant_name, p.type as plant_type, p.status as plant_status
    FROM users u
    LEFT JOIN plants p ON u.username = p.gardener_username
    ORDER BY u.id DESC
";

$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

// Organize data
$users = [];
while ($row = $result->fetch_assoc()) {
    $uid = $row['user_id'];
    if (!isset($users[$uid])) {
        $users[$uid] = [
            'username' => $row['username'],
            'email' => $row['email'],
            'role' => $row['role'],
            'plants' => []
        ];
    }
    if ($row['plant_id']) {
        $users[$uid]['plants'][] = [
            'id' => $row['plant_id'],
            'name' => $row['plant_name'],
            'type' => $row['plant_type'],
            'status' => $row['plant_status']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Bloombot</title>
    <link rel="stylesheet" href="CSS/style.css?v=7">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #aaa;
            padding: 8px;
        }
        th {
            background-color: #eee;
        }
        .plant-table {
            margin-top: 10px;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>

<div class="topnav">
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="about.html">About</a>
    <a href="contact.html">Contact Us</a>
    <a href="profile.php">My Profile</a>

    <div class="topnav-right">
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="header">
    <h1>Admin Dashboard</h1>
</div>

<div class="main">
    <div class="sidebar">
        <h2>Menu</h2>
        <a class="menu-button" href="add_user.php">➕ Add New User</a>
        <a class="menu-button" href="admin_dashboard.php">🏠 Dashboard</a>
        <a class="menu-button" href="global_threshold.php">⚙ Configure Global Thresholds</a>
        <a class="menu-button" href="view_alerts.php">🔔 View Alerts</a>
        <a class="menu-button" href="profile.php">👤 Profile</a>
    </div>

    <div class="content">
        <h2>All Users and Their Plants</h2>
        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
                <th>Plants</th>
            </tr>

            <?php foreach ($users as $uid => $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td>
                        <a href="edit_user.php?id=<?=  $uid ?>">Edit</a> |
                        <a href="delete_user.php?id=<?= $uid ?>" onclick="return confirm('Delete this user?')">Delete</a>
                    </td>
                    <td>
                        <?php if (!empty($user['plants'])): ?>
                            <table class="plant-table">
                                <tr>
                                    <th>Plant Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                                <?php foreach ($user['plants'] as $plant): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($plant['name']) ?></td>
                                        <td><?= htmlspecialchars($plant['type']) ?></td>
                                        <td><?= htmlspecialchars($plant['status'] ?? 'Not Set') ?></td>
                                        <td>
                                            <a href="edit_plant.php?id=<?= $plant['id'] ?>">Edit</a> |
                                            <a href="delete_plant.php?id=<?= $plant['id'] ?>" onclick="return confirm('Delete this plant?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <em>No plants</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

        </table>
    </div>
</div>

</body>
</html>
