<?php
// login.php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Crumb & Co.</title>

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
            --danger: #cf6656;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--background);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
        }

        .login-wrapper {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        /* LEFT SIDE */
        .login-banner {
            background: var(--primary-dark);
            color: white;
            padding: 55px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .login-banner::before {
            content: "";
            position: absolute;
            width: 450px;
            height: 450px;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 50%;
            top: 20%;
            left: -180px;
        }

        .login-banner::after {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 50%;
            bottom: -150px;
            right: -100px;
        }

        .banner-content,
        .banner-footer,
        .banner-brand {
            position: relative;
            z-index: 1;
        }

        .banner-brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 13px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 25px;
        }

        .brand-logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
            flex-shrink: 0;
            border-radius: 50%;
            background: #ffffff;
            padding: 5px;
            border: 2px solid var(--accent);
        }

        .mobile-brand .brand-logo {
            width: 42px;
            height: 42px;
        }

        .banner-brand h4 {
            font-family: 'Playfair Display', serif;
            font-size: 23px;
            margin: 0;
        }

        .banner-brand small {
            color: #c6afa0;
            font-size: 11px;
        }

        .banner-content {
            max-width: 480px;
            margin: auto 0;
            padding: 60px 0;
        }

        .banner-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(216, 137, 76, .15);
            color: #e9b486;
            border: 1px solid rgba(216, 137, 76, .25);
            padding: 8px 13px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 25px;
        }

        .banner-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(35px, 4vw, 55px);
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .banner-content h1 span {
            color: var(--accent);
        }

        .banner-content p {
            color: #c6afa0;
            font-size: 14px;
            line-height: 1.9;
            max-width: 390px;
        }

        .banner-decoration {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 40px;
            color: #d9c8bd;
            font-size: 11px;
        }

        .decoration-line {
            width: 45px;
            height: 1px;
            background: var(--accent);
        }

        .banner-footer {
            color: #aa9586;
            font-size: 11px;
        }

        /* RIGHT SIDE */
        .login-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 45px;
        }

        .login-box {
            width: 100%;
            max-width: 410px;
        }

        .mobile-brand {
            display: none;
            align-items: center;
            gap: 10px;
            margin-bottom: 40px;
        }

        .mobile-brand .brand-icon {
            width: 42px;
            height: 42px;
            font-size: 21px;
        }

        .mobile-brand h4 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            margin: 0;
        }

        .mobile-brand small {
            font-size: 10px;
            color: var(--muted);
        }

        .welcome-text {
            margin-bottom: 32px;
        }

        .welcome-text .eyebrow {
            color: var(--accent);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .welcome-text h2 {
            font-family: 'Playfair Display', serif;
            font-size: 31px;
            font-weight: 700;
            margin: 0 0 9px;
        }

        .welcome-text p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
            line-height: 1.7;
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;
            color: #655449;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 21px;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 16px;
            z-index: 2;
        }

        .form-control {
            height: 48px;
            border: 1px solid var(--border);
            border-radius: 9px !important;
            padding: 10px 43px;
            font-size: 12px;
            color: var(--text);
            background: white;
        }

        .form-control::placeholder {
            color: #b4a79e;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(216, 137, 76, .12);
        }

        .password-toggle {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--muted);
            font-size: 16px;
            z-index: 3;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: -5px;
            margin-bottom: 25px;
        }

        .form-check-label {
            color: #75665c;
            font-size: 11px;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .forgot-link {
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            height: 48px;
            background: var(--primary);
            color: white;
            border: 0;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .btn-login:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(76, 44, 32, .15);
        }

        .btn-login:disabled {
            opacity: .7;
            cursor: not-allowed;
            transform: none;
        }

        .login-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0;
            color: #b4a79e;
            font-size: 10px;
        }

        .login-divider::before,
        .login-divider::after {
            content: "";
            height: 1px;
            background: var(--border);
            flex: 1;
        }

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px;
            background: #f7f3ee;
            border-radius: 9px;
        }

        .security-note i {
            color: var(--success, #478765);
            font-size: 15px;
        }

        .security-note p {
            color: #8c7c70;
            font-size: 10px;
            line-height: 1.6;
            margin: 0;
        }

        .login-bottom {
            text-align: center;
            color: var(--muted);
            font-size: 10px;
            margin-top: 35px;
        }

        .alert {
            border-radius: 9px;
            font-size: 11px;
            padding: 12px 14px;
        }

        @media(max-width:991px) {
            .login-wrapper {
                grid-template-columns: 42% 58%;
            }

            .login-banner {
                padding: 35px;
            }

            .login-panel {
                padding: 35px;
            }
        }

        @media(max-width:767px) {
            .login-wrapper {
                display: block;
            }

            .login-banner {
                display: none;
            }

            .login-panel {
                min-height: 100vh;
                padding: 30px 22px;
            }

            .mobile-brand {
                display: flex;
            }

            .login-box {
                max-width: 430px;
            }

            .welcome-text h2 {
                font-size: 28px;
            }
        }

        @media(max-width:380px) {
            .login-panel {
                padding: 25px 17px;
            }

            .login-options {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">

        <!-- LEFT BANNER -->
        <section class="login-banner">

            <div class="banner-brand">
                <img src="../assets/logo.png" alt="Sweet Crumb Logo" class="brand-logo">

                <div>
                    <h4>Sweet Crumb</h4>
                    <small>Bakery Management</small>
                </div>
            </div>

            <div class="banner-content">

                <div class="banner-tag">
                    <i class="bi bi-stars"></i>
                    BAKERY SALES & INVENTORY SYSTEM
                </div>

                <h1>
                    Freshly baked.<br>
                    <span>Simply managed.</span>
                </h1>

                <p>
                    Welcome to your bakery management workspace.
                    Keep track of your products, inventory, daily sales,
                    and business performance in one place.
                </p>

                <div class="banner-decoration">
                    <div class="decoration-line"></div>
                    <span>Made with care, managed with ease.</span>
                </div>

            </div>

        </section>

        <!-- LOGIN PANEL -->
        <section class="login-panel">

            <div class="login-box">

                <!-- MOBILE BRAND -->
                <div class="mobile-brand">
                    <img src="../assets/logo.png" alt="Sweet Crumb Logo" class="brand-logo">

                    <div>
                        <h4>Sweet Crumb</h4>
                        <small>Bakery Management</small>
                    </div>
                </div>

                <div class="welcome-text">
                    <div class="eyebrow">Welcome Back</div>
                    <h2>Sign in to your account</h2>
                    <p>Enter your login credentials to access your dashboard.</p>
                </div>

                <!-- LOGIN ERROR PLACEHOLDER -->
                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php
                        echo htmlspecialchars($_SESSION['login_error']);
                        unset($_SESSION['login_error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form id="loginForm" action="actions/login_process.php" method="POST">

                    <div class="mb-3">
                        <label class="form-label">Username</label>

                        <div class="input-group-custom">
                            <i class="bi bi-person input-icon"></i>

                            <input
                                type="text"
                                name="username"
                                class="form-control"
                                placeholder="Enter your username"
                                autocomplete="username"
                                required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>

                        <div class="input-group-custom">
                            <i class="bi bi-lock input-icon"></i>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                id="togglePassword"
                                aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" id="loginButton">
                        <span id="buttonText">
                            Sign In <i class="bi bi-arrow-right ms-1"></i>
                        </span>
                    </button>

                </form>

                <div class="login-bottom">
                    &copy; 2026 Sweet Crumb Bakery Sales & Inventory System
                </div>

            </div>

        </section>

    </div>

    <script>
        // SHOW / HIDE PASSWORD
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function() {
            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
                this.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
                this.setAttribute('aria-label', 'Show password');
            }
        });

        // LOGIN BUTTON LOADING STATE
        document.getElementById('loginForm').addEventListener('submit', function() {
            const button = document.getElementById('loginButton');

            button.disabled = true;
            document.getElementById('buttonText').innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span> Signing in...';
        });
    </script>

</body>

</html>