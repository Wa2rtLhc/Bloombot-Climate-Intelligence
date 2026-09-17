<?php

session_start();

require_once 'db_connect.php';


// Make sure user is logged in

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit();

}


$user_id = (int) $_SESSION['user_id'];


// Get logged-in user's information

$stmt = $conn->prepare("
    SELECT id, username, email, role
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


if (!$user) {

    session_unset();
    session_destroy();

    header("Location: login.php");

    exit();

}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - BloomBot</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="CSS/style.css?v=9">

</head>


<body class="profile-page">


<header class="topnav">

    <div class="brand">

        <span class="brand-mark">🌿</span>

        <span>BloomBot</span>

    </div>


    <nav class="topnav-links">

        <a href="gardener_dashboard .php">
            Dashboard
        </a>

        <a href="about.html">
            About
        </a>

        <a href="contact.html">
            Contact
        </a>

        <a href="profile.php" class="active">
            Profile
        </a>

        <a href="gardener_reports.php">
            Reports
        </a>

    </nav>


    <div class="topnav-right">

        <span class="user-greeting">
            👋 <?= htmlspecialchars($user['username']) ?>
        </span>

        <a href="logout.php" class="logout-link">
            Logout
        </a>

    </div>

</header>



<main class="profile-main">


    <section class="profile-hero">

        <div>

            <span class="eyebrow">
                ACCOUNT
            </span>

            <h1>
                My Profile
            </h1>

            <p>
                Manage your BloomBot account information.
            </p>

        </div>


        <div class="profile-status">

            <span class="status-dot"></span>

            Account active

        </div>

    </section>



    <section class="profile-card">


        <div class="profile-card-top">


            <div class="profile-avatar">

                <?= strtoupper(
                    substr($user['username'], 0, 1)
                ) ?>

            </div>


            <div>

                <span class="card-eyebrow">
                    BLOOMBOT USER
                </span>

                <h2>
                    <?= htmlspecialchars($user['username']) ?>
                </h2>

                <p>
                    <?= htmlspecialchars($user['email']) ?>
                </p>

            </div>

        </div>



        <div class="profile-details">


            <div class="profile-detail">

                <span class="detail-icon">
                    👤
                </span>

                <div>

                    <small>
                        Username
                    </small>

                    <strong>
                        <?= htmlspecialchars($user['username']) ?>
                    </strong>

                </div>

            </div>



            <div class="profile-detail">

                <span class="detail-icon">
                    ✉️
                </span>

                <div>

                    <small>
                        Email
                    </small>

                    <strong>
                        <?= htmlspecialchars($user['email']) ?>
                    </strong>

                </div>

            </div>



            <div class="profile-detail">

                <span class="detail-icon">
                    🌱
                </span>

                <div>

                    <small>
                        Role
                    </small>

                    <strong>

                        <span class="role-badge <?= htmlspecialchars($user['role']) ?>">

                            <?= ucfirst(
                                htmlspecialchars($user['role'])
                            ) ?>

                        </span>

                    </strong>

                </div>

            </div>


        </div>



        <div class="profile-actions">

            <a
                href="edit_profile.php"
                class="primary-button"
            >
                ✏️ Edit Profile
            </a>


            <a
                href="gardener_dashboard .php"
                class="secondary-button"
            >
                ← Back to Dashboard
            </a>

        </div>


    </section>


</main>



<footer class="site-footer">

    <span>
        🌿 BloomBot Climate Intelligence
    </span>

    <span>
        Monitor smarter. Grow better.
    </span>

</footer>


</body>

</html>