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
  <style>
    /* General Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background: linear-gradient(135deg, #28a745, #218838);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .container {
      background: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 8px 18px rgba(0,0,0,0.2);
      width: 400px;
      animation: fadeIn 0.6s ease-in-out;
    }

    .container h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #218838;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: bold;
      color: #333;
    }

    .form-group input, 
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      outline: none;
      font-size: 15px;
      transition: 0.3s;
    }

    .form-group input:focus, 
    .form-group select:focus {
      border-color: #28a745;
      box-shadow: 0 0 6px rgba(40, 167, 69, 0.5);
    }

    .btn {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 8px;
      background: #28a745;
      color: white;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
    }

    .btn:hover {
      background: #218838;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 15px;
      color: #218838;
      text-decoration: none;
      font-weight: bold;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
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
