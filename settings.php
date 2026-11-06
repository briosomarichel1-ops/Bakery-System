<?php
session_start();
date_default_timezone_set('Asia/Manila');

include '../conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ADD NEW USER
if (isset($_POST['add_user'])) {

    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'staff';

    $allowed_roles = ['admin', 'cashier', 'staff'];

    if (
        empty($fullname) ||
        empty($username) ||
        empty($password) ||
        empty($confirm_password) ||
        !in_array($role, $allowed_roles)
    ) {
        header("Location: settings.php?error=required");
        exit;
    }

    if (strlen($password) < 8) {
        header("Location: settings.php?error=password");
        exit;
    }

    if ($password !== $confirm_password) {
        header("Location: settings.php?error=mismatch");
        exit;
    }

    // CHECK IF USERNAME ALREADY EXISTS
    $check_stmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE username = ?
    ");

    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();

    $existing_user = $check_stmt->get_result();

    if ($existing_user->num_rows > 0) {
        header("Location: settings.php?error=exists");
        exit;
    }

    // HASH PASSWORD
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // INSERT USER
    $insert_stmt = $conn->prepare("
        INSERT INTO users (fullname, username, password, role)
        VALUES (?, ?, ?, ?)
    ");

    $insert_stmt->bind_param(
        "ssss",
        $fullname,
        $username,
        $hashed_password,
        $role
    );

    if ($insert_stmt->execute()) {
        header("Location: settings.php?added=1");
        exit;
    } else {
        header("Location: settings.php?error=failed");
        exit;
    }
}

$users_query = "
    SELECT user_id, fullname, username, role
    FROM users
    ORDER BY user_id DESC
";

$users_result = $conn->query($users_query);

$users = [];

if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// CHANGE USER PASSWORD
if (isset($_POST['change_password'])) {

    $user_id = (int) $_POST['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (
        empty($user_id) ||
        empty($new_password) ||
        empty($confirm_password)
    ) {
        header("Location: settings.php?error=change_required");
        exit;
    }

    if (strlen($new_password) < 8) {
        header("Location: settings.php?error=change_password");
        exit;
    }

    if ($new_password !== $confirm_password) {
        header("Location: settings.php?error=change_mismatch");
        exit;
    }

    $check_user = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE user_id = ?
    ");

    $check_user->bind_param("i", $user_id);
    $check_user->execute();

    $user_result = $check_user->get_result();

    if ($user_result->num_rows === 0) {
        header("Location: settings.php?error=user_not_found");
        exit;
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $update_stmt = $conn->prepare("
        UPDATE users
        SET password = ?
        WHERE user_id = ?
    ");

    $update_stmt->bind_param("si", $hashed_password, $user_id);

    if ($update_stmt->execute()) {
        header("Location: settings.php?password_changed=1");
        exit;
    } else {
        header("Location: settings.php?error=change_failed");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Bakery Sales & Inventory</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #75452e;
            --primary-dark: #4c2c20;
            --primary-light: #f4e9df;
            --accent: #d8894c;
            --background: #faf8f4;
            --white: #fff;
            --text: #332820;
            --muted: #95877d;
            --border: #eee7df;
            --success: #478765;
            --warning: #d99b38;
            --danger: #cf6656;
            --sidebar-width: 255px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-dark);
            color: white;
            padding: 25px 18px;
            z-index: 1050;
            transition: transform .3s ease;
            overflow-y: auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, .12);
            margin-bottom: 25px;
        }

        .brand-icon {
            width: 75px;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: transparent;
        }

        .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter:
                drop-shadow(1px 0 0 white) drop-shadow(-1px 0 0 white) drop-shadow(0 1px 0 white) drop-shadow(0 -1px 0 white);
        }

        .brand h5 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            margin: 0;
        }

        .brand small {
            color: #c6afa0;
            font-size: 11px;
        }

        .menu-label {
            color: #aa9586;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.3px;
            padding: 0 12px;
            margin: 23px 0 10px;
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;
            color: #d9c8bd;
            padding: 11px 13px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
            transition: .2s;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            font-size: 17px;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, .09);
            color: white;
        }

        .sidebar .nav-link.active {
            background: var(--accent);
            color: white;
            font-weight: 700;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255, 255, 255, .12);
            margin-top: 35px;
            padding: 20px 10px 0;
            color: #c6afa0;
            font-size: 11px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 25px 30px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 27px;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 12px;
            margin-top: 5px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: white;
            color: var(--text);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-dot {
            position: absolute;
            width: 7px;
            height: 7px;
            background: var(--danger);
            border-radius: 50%;
            top: 7px;
            right: 8px;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-avatar {
            width: 37px;
            height: 37px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .profile-info strong {
            display: block;
            font-size: 12px;
        }

        .profile-info small {
            color: var(--muted);
            font-size: 10px;
        }

        .mobile-toggle {
            display: none;
        }

        .settings-layout {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 22px;
            align-items: start;
        }

        .settings-menu {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 12px;
            position: sticky;
            top: 20px;
        }

        .settings-menu-title {
            font-size: 10px;
            color: var(--muted);
            font-weight: 700;
            letter-spacing: 1px;
            padding: 10px;
        }

        .settings-menu a {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 12px;
            color: #75665c;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            margin-bottom: 4px;
        }

        .settings-menu a i {
            font-size: 16px;
        }

        .settings-menu a:hover,
        .settings-menu a.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .settings-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 0;
        }

        .settings-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
            scroll-margin-top: 20px;
        }

        .card-header-custom {
            padding: 19px 22px;
            border-bottom: 1px solid var(--border);
        }

        .card-header-custom h6 {
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .card-header-custom p {
            color: var(--muted);
            font-size: 11px;
            margin: 0;
        }

        .card-body-custom {
            padding: 22px;
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;
            color: #655449;
            margin-bottom: 7px;
        }

        .form-control {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12px;
            color: var(--text);
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(216, 137, 76, .12);
        }

        .btn-primary-custom {
            background: var(--primary);
            color: white;
            border: 0;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: white;
        }

        .btn-light-custom {
            background: white;
            color: #75665c;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 11px;
            font-weight: 600;
        }

        .user-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 27px;
            height: 27px;
            padding: 0 8px;
            border-radius: 20px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            margin-left: 7px;
        }

        .search-box {
            position: relative;
            max-width: 280px;
            width: 100%;
        }

        .search-box i {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
        }

        .search-box input {
            padding-left: 35px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .users-table {
            min-width: 700px;
            margin-bottom: 0;
        }

        .users-table thead th {
            background: #faf8f4;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 14px 15px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .users-table tbody td {
            padding: 14px 15px;
            font-size: 11px;
            vertical-align: middle;
            border-bottom: 1px solid #f2eee9;
        }

        .users-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 700;
            color: var(--text);
            display: block;
        }

        .user-username {
            color: var(--muted);
            font-size: 10px;
        }

        .role-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #f3eee9;
            color: #75665c;
            font-size: 10px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-active {
            background: #e8f4ed;
            color: var(--success);
        }

        .status-inactive {
            background: #fcecea;
            color: var(--danger);
        }

        .btn-change-password {
            background: var(--primary-light);
            color: var(--primary);
            border: 0;
            border-radius: 7px;
            padding: 7px 10px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .btn-change-password:hover {
            background: var(--primary);
            color: white;
        }

        .security-info {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .security-icon {
            width: 43px;
            height: 43px;
            border-radius: 11px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .security-info h6 {
            font-size: 13px;
            font-weight: 700;
            margin: 2px 0 5px;
        }

        .security-info p {
            font-size: 11px;
            color: var(--muted);
            margin: 0;
            line-height: 1.6;
        }

        .modal-content {
            border: 0;
            border-radius: 13px;
            overflow: hidden;
        }

        .modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .modal-body {
            padding: 22px;
        }

        .modal-footer {
            padding: 15px 22px;
            border-top: 1px solid var(--border);
        }

        .password-input {
            position: relative;
        }

        .password-input .form-control {
            padding-right: 42px;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--muted);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            z-index: 1040;
        }

        @media(max-width:991px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .mobile-toggle {
                display: inline-flex;
            }
        }

        @media(max-width:767px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }

            .settings-menu {
                position: static;
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .settings-menu-title {
                width: 100%;
            }

            .settings-menu a {
                margin: 0;
            }
        }

        @media(max-width:576px) {
            .main-content {
                padding: 17px 13px;
            }

            .page-title {
                font-size: 23px;
            }

            .profile-info {
                display: none;
            }

            .card-body-custom {
                padding: 17px;
            }

            .card-header-custom {
                padding: 17px;
            }

            .topbar-right {
                gap: 10px;
            }

            .account-toolbar {
                align-items: stretch !important;
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <img src="../assets/logo.png" alt="Crumb & Co. Logo">
            </div>

            <div>
                <h5>Sweet Crumb</h5>
                <small>Bakery Management</small>
            </div>
        </div>

        <div class="menu-label">MAIN MENU</div>
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="bi bi-grid-fill"></i> Dashboard</a>
            <a href="pos.php" class="nav-link"><i class="bi bi-cart3"></i> POS / New Sale</a>
        </nav>

        <div class="menu-label">MANAGEMENT</div>
        <nav class="nav flex-column">
            <a href="products.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a>
            <a href="categories.php" class="nav-link"><i class="bi bi-tags"></i> Categories</a>
            <a href="inventory.php" class="nav-link"><i class="bi bi-clipboard-data"></i> Inventory</a>
            <a href="stock-movements.php" class="nav-link"><i class="bi bi-arrow-left-right"></i> Stock Movements</a>
        </nav>

        <div class="menu-label">SALES & REPORTS</div>
        <nav class="nav flex-column">
            <a href="sales.php" class="nav-link"><i class="bi bi-receipt"></i> Sales History</a>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
        </nav>

        <div class="menu-label">SYSTEM</div>
        <nav class="nav flex-column">
            <a href="settings.php" class="nav-link active"><i class="bi bi-gear"></i> Settings</a>
            <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <div class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="icon-btn mobile-toggle" id="menuToggle">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="page-title">Settings</h1>
                    <div class="page-subtitle">Manage user accounts and security</div>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['added'])): ?>

            <div class="alert alert-success alert-dismissible fade show small" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                User account successfully created!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif (isset($_GET['password_changed'])): ?>

            <div class="alert alert-success alert-dismissible fade show small" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                User password successfully updated!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

        <?php elseif (isset($_GET['error'])): ?>

            <div class="alert alert-danger alert-dismissible fade show small" role="alert">

                <?php
                $error_messages = [
                    'required' => 'Please complete all required fields.',
                    'password' => 'Password must contain at least 8 characters.',
                    'mismatch' => 'Password and confirmation do not match.',
                    'exists' => 'Username already exists. Please choose another username.',
                    'failed' => 'Unable to create user. Please try again.',
                    'change_required' => 'Please complete all password fields.',
                    'change_password' => 'New password must contain at least 8 characters.',
                    'change_mismatch' => 'New password and confirmation do not match.',
                    'user_not_found' => 'Selected user account was not found.',
                    'change_failed' => 'Unable to update password. Please try again.'
                ];

                echo htmlspecialchars(
                    $error_messages[$_GET['error']] ?? 'Something went wrong.'
                );
                ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>

        <div class="settings-layout">

            <!-- SETTINGS NAVIGATION -->
            <div class="settings-menu">
                <div class="settings-menu-title">SETTINGS MENU</div>
                <a href="#account" class="active">
                    <i class="bi bi-person-circle"></i> Account Settings
                </a>
                <a href="#security">
                    <i class="bi bi-shield-lock"></i> Security
                </a>
            </div>

            <div class="settings-content">

                <!-- ACCOUNT SETTINGS -->
                <section class="settings-card" id="account">

                    <div class="card-header-custom">
                        <h6>
                            <i class="bi bi-people me-2" style="color:var(--primary)"></i>
                            Account Settings
                            <span class="user-count"><?php echo count($users); ?></span>
                        </h6>
                        <p>View all registered users and manage their account passwords.</p>
                    </div>

                    <div class="card-body-custom">

                        <div class="d-flex justify-content-between align-items-center gap-3 mb-4 account-toolbar">

                            <div>
                                <strong style="font-size:12px;">Registered Users</strong>
                                <div style="font-size:10px;color:var(--muted);">
                                    List of all system accounts
                                </div>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">

                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="form-control" id="userSearch" placeholder="Search users...">
                                </div>

                                <button
                                    type="button"
                                    class="btn-primary-custom"
                                    data-bs-toggle="modal"
                                    data-bs-target="#addUserModal">

                                    <i class="bi bi-person-plus me-1"></i>
                                    Add User
                                </button>

                            </div>

                        </div>
                        <div class="table-responsive">
                            <table class="table users-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody id="usersTableBody">

                                    <?php if (count($users) > 0): ?>

                                        <?php foreach ($users as $user): ?>

                                            <tr class="user-row">

                                                <td>
                                                    <div class="user-info">

                                                        <div class="user-avatar">
                                                            <?= strtoupper(substr($user['fullname'], 0, 1)); ?>
                                                        </div>

                                                        <div>
                                                            <span class="user-name">
                                                                <?= htmlspecialchars($user['fullname']); ?>
                                                            </span>

                                                            <span class="user-username">
                                                                @<?= htmlspecialchars($user['username']); ?>
                                                            </span>
                                                        </div>

                                                    </div>
                                                </td>

                                                <td>
                                                    <button
                                                        type="button"
                                                        class="btn-change-password"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#changePasswordModal"
                                                        data-user-id="<?= (int) $user['user_id']; ?>"
                                                        data-user-name="<?= htmlspecialchars($user['fullname'], ENT_QUOTES); ?>"
                                                        data-username="<?= htmlspecialchars($user['username'], ENT_QUOTES); ?>">

                                                        <i class="bi bi-key me-1"></i>
                                                        Change Password
                                                    </button>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <tr>
                                            <td colspan="4" class="text-center py-4" style="color:var(--muted);">
                                                No registered users found.
                                            </td>
                                        </tr>

                                    <?php endif; ?>

                                    <tr id="noUsersFound" style="display:none;">
                                        <td colspan="4" class="text-center py-4" style="color:var(--muted);">
                                            <i class="bi bi-search me-1"></i>
                                            No users found.
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <section class="settings-card" id="security">

                    <div class="card-header-custom">
                        <h6>
                            <i class="bi bi-shield-lock me-2" style="color:var(--primary)"></i>
                            Security
                        </h6>
                        <p>Manage account passwords and help keep your system secure.</p>
                    </div>

                    <div class="card-body-custom">

                        <div class="security-info">
                            <div class="security-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>

                            <div>
                                <h6>Password Management</h6>
                                <p>
                                    Administrators can change the password of registered users
                                    through the Account Settings section. Make sure to use a
                                    strong password with at least 8 characters.
                                </p>
                            </div>
                        </div>

                        <hr style="border-color:var(--border);margin:22px 0;">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <strong style="font-size:12px;">User Passwords</strong>
                                <div style="font-size:10px;color:var(--muted);margin-top:4px;">
                                    Passwords should be kept private and updated when necessary.
                                </div>
                            </div>

                            <a href="#account" class="btn-primary-custom text-decoration-none">
                                <i class="bi bi-people me-1"></i> Manage Accounts
                            </a>
                        </div>

                    </div>
                </section>

            </div>
        </div>

    </main>

    <!-- ADD USER MODAL -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">
                                <i class="bi bi-person-plus me-2" style="color:var(--primary)"></i>
                                Add New User
                            </h5>

                            <small style="color:var(--muted);font-size:10px;">
                                Create a new system account.
                            </small>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>

                            <input
                                type="text"
                                class="form-control"
                                name="fullname"
                                placeholder="Enter full name"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Username</label>

                            <input
                                type="text"
                                class="form-control"
                                name="username"
                                placeholder="Enter username"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>

                            <div class="password-input">

                                <input
                                    type="password"
                                    class="form-control"
                                    id="addPassword"
                                    name="password"
                                    placeholder="Enter password"
                                    minlength="8"
                                    required>

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="addPassword">

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>

                            <small style="font-size:10px;color:var(--muted);">
                                Password must contain at least 8 characters.
                            </small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Confirm Password</label>

                            <div class="password-input">

                                <input
                                    type="password"
                                    class="form-control"
                                    id="addConfirmPassword"
                                    name="confirm_password"
                                    placeholder="Confirm password"
                                    minlength="8"
                                    required>

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="addConfirmPassword">

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn-light-custom"
                            data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button
                            type="submit"
                            name="add_user"
                            class="btn-primary-custom">

                            <i class="bi bi-check-lg me-1"></i>
                            Create User

                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="changePasswordForm">
                    <input type="hidden" name="user_id" id="selectedUserId">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">
                                <i class="bi bi-key me-2" style="color:var(--primary)"></i>
                                Change User Password
                            </h5>

                            <small style="color:var(--muted);font-size:10px;">
                                Update the password of the selected account.
                            </small>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>

                            <input
                                type="text"
                                class="form-control"
                                id="selectedUserName"
                                readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Username</label>

                            <input
                                type="text"
                                class="form-control"
                                id="selectedUsername"
                                readonly>
                        </div>

                        <hr style="border-color:var(--border);">

                        <div class="mb-3">
                            <label class="form-label">New Password</label>

                            <div class="password-input">

                                <input
                                    type="password"
                                    class="form-control"
                                    id="newPassword"
                                    name="new_password"
                                    placeholder="Enter new password"
                                    minlength="8"
                                    required>

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="newPassword">

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>

                            <small style="font-size:10px;color:var(--muted);">
                                Password must contain at least 8 characters.
                            </small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Confirm New Password</label>

                            <div class="password-input">

                                <input
                                    type="password"
                                    class="form-control"
                                    id="confirmNewPassword"
                                    name="confirm_password"
                                    placeholder="Confirm new password"
                                    minlength="8"
                                    required>

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="confirmNewPassword">

                                    <i class="bi bi-eye"></i>

                                </button>

                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn-light-custom"
                            data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button
                            type="submit"
                            name="change_password"
                            class="btn-primary-custom">

                            <i class="bi bi-check-lg me-1"></i>
                            Update Password

                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        document.querySelectorAll('.settings-menu a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('.settings-menu a').forEach(item => {
                    item.classList.remove('active');
                });

                this.classList.add('active');
            });
        });

        const userSearch = document.getElementById('userSearch');
        const userRows = document.querySelectorAll('.user-row');
        const noUsersFound = document.getElementById('noUsersFound');

        userSearch.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase().trim();
            let visibleCount = 0;

            userRows.forEach(row => {
                const matches = row.textContent.toLowerCase().includes(searchValue);

                row.style.display = matches ? '' : 'none';

                if (matches) {
                    visibleCount++;
                }
            });

            noUsersFound.style.display = visibleCount === 0 ? '' : 'none';
        });

        // OPEN CHANGE PASSWORD MODAL
        const changePasswordModal = document.getElementById('changePasswordModal');

        changePasswordModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;

            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            const username = button.getAttribute('data-username');

            document.getElementById('selectedUserId').value = userId;
            document.getElementById('selectedUserName').value = userName;
            document.getElementById('selectedUsername').value = username;

            document.getElementById('changePasswordForm').reset();

            document.getElementById('selectedUserId').value = userId;
            document.getElementById('selectedUserName').value = userName;
            document.getElementById('selectedUsername').value = username;
        });

        // SHOW / HIDE PASSWORD
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const target = document.getElementById(this.dataset.target);
                const icon = this.querySelector('i');

                if (target.type === 'password') {
                    target.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    target.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
        });

        // ADD USER PASSWORD VALIDATION
        document.querySelector('#addUserModal form').addEventListener('submit', function(e) {

            const password = document.getElementById('addPassword').value;
            const confirmPassword = document.getElementById('addConfirmPassword').value;

            if (password.length < 8) {
                e.preventDefault();
                alert('Password must contain at least 8 characters.');
                return;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password and confirmation do not match.');
                return;
            }

        });

        // CHANGE PASSWORD VALIDATION
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {

            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmNewPassword').value;

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must contain at least 8 characters.');
                return;
            }

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return;
            }

        });
    </script>

</body>

</html>