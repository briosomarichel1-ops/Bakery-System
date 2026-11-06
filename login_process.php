<?php
session_start();

include '../../conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // CHECK USER ACCOUNT
    $stmt = $conn->prepare("
        SELECT user_id, fullname, username, password, role
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        // VERIFY PASSWORD
        if (password_verify($password, $user['password'])) {

            // REGENERATE SESSION ID
            session_regenerate_id(true);

            // SAVE USER SESSION
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // REDIRECT BASED ON ROLE
            if ($user['role'] === 'admin') {
                header("Location: ../dashboard.php");
                exit;
            } elseif ($user['role'] === 'staff') {
                header("Location: ../dashboard.php");
                exit;
            } else {
                session_unset();
                session_destroy();

                session_start();
                $_SESSION['login_error'] = "Invalid user role.";
                header("Location: ../login.php");
                exit;
            }
        } else {

            $_SESSION['login_error'] = "Incorrect username or password.";
            header("Location: ../login.php");
            exit;
        }
    } else {

        $_SESSION['login_error'] = "Incorrect username or password.";
        header("Location: ../login.php");
        exit;
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: ../login.php");
    exit;
}
