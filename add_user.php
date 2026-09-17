<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: login.php?message=Please login as admin");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link
        rel="stylesheet"
        href="CSS/style.css?v=7"
    >

  <title>Add User - Bloombot</title>
 
</head>
<body>

  <div class="container">
    <h2>Add New User</h2>
    <form action="add_user_process.php" method="POST">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <div class="form-group">
        <label for="role">Role</label>
        <select id="role" name="role" required>
          <option value="gardener">Gardener</option>
          <option value="guest">Guest</option>
          <option value="admin">Admin</option>
        </select>
      </div>

      <button type="submit" class="btn">Add User</button>
    </form>
    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
  </div>

</body>
</html>
