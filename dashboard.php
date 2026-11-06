<?php
session_start();
date_default_timezone_set('Asia/Manila');

include '../conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Use Philippine time for MySQL date functions
$conn->query("SET time_zone = '+08:00'");

// TODAY'S SALES SUMMARY
$today_sales_query = "
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_sales,
        COUNT(*) AS total_transactions
    FROM sales
    WHERE DATE(created_at) = CURDATE()
    AND status = 'Completed'
";

$today_sales_result = $conn->query($today_sales_query);
$today_sales = $today_sales_result->fetch_assoc();

$total_sales = $today_sales['total_sales'];
$total_transactions = $today_sales['total_transactions'];


// PRODUCT INVENTORY SUMMARY
$inventory_query = "
    SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN stock_quantity > stock_limit THEN 1 ELSE 0 END) AS in_stock,
        SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= stock_limit THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
    FROM products
";

$inventory_result = $conn->query($inventory_query);
$inventory = $inventory_result->fetch_assoc();

$total_products = (int) $inventory['total_products'];
$in_stock = (int) $inventory['in_stock'];
$low_stock = (int) $inventory['low_stock'];
$out_of_stock = (int) $inventory['out_of_stock'];

$in_stock_percent = $total_products > 0 ? ($in_stock / $total_products) * 100 : 0;
$low_stock_percent = $total_products > 0 ? ($low_stock / $total_products) * 100 : 0;
$out_of_stock_percent = $total_products > 0 ? ($out_of_stock / $total_products) * 100 : 0;


// TODAY'S RECENT SALES
$recent_sales_query = "
    SELECT
        s.receipt_number,
        s.total_amount,
        s.status,
        s.created_at,
        u.fullname AS cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE DATE(s.created_at) = CURDATE()
    ORDER BY s.created_at DESC
    LIMIT 5
";

$recent_sales = $conn->query($recent_sales_query);


// TODAY'S TOP SELLING PRODUCTS
$top_products_query = "
    SELECT
        p.product_name,
        p.product_image,
        SUM(si.quantity) AS total_sold
    FROM sale_items si
    INNER JOIN sales s ON si.sale_id = s.sale_id
    INNER JOIN products p ON si.product_id = p.product_id
    WHERE DATE(s.created_at) = CURDATE()
    AND s.status = 'Completed'
    GROUP BY p.product_id, p.product_name
    ORDER BY total_sold DESC
    LIMIT 5
";

$top_products = $conn->query($top_products_query);


// TODAY AND YESTERDAY'S HOURLY SALES CHART
$hourly_sales_query = "
    SELECT
        DATE(created_at) AS sale_date,
        HOUR(created_at) AS sale_hour,
        SUM(total_amount) AS hourly_total
    FROM sales
    WHERE DATE(created_at) IN (CURDATE(), DATE_SUB(CURDATE(), INTERVAL 1 DAY))
    AND status = 'Completed'
    GROUP BY DATE(created_at), HOUR(created_at)
    ORDER BY sale_date ASC, sale_hour ASC
";

$hourly_sales_result = $conn->query($hourly_sales_query);

$chart_labels = [];
$today_chart_values = [];
$yesterday_chart_values = [];

for ($hour = 0; $hour < 24; $hour++) {
    $chart_labels[$hour] = date('h A', strtotime("$hour:00"));
    $today_chart_values[$hour] = 0;
    $yesterday_chart_values[$hour] = 0;
}

while ($row = $hourly_sales_result->fetch_assoc()) {

    $hour = (int) $row['sale_hour'];

    if ($row['sale_date'] == date('Y-m-d')) {
        $today_chart_values[$hour] = (float) $row['hourly_total'];
    } else {
        $yesterday_chart_values[$hour] = (float) $row['hourly_total'];
    }
}

$chart_labels = array_values($chart_labels);
$today_chart_values = array_values($today_chart_values);
$yesterday_chart_values = array_values($yesterday_chart_values);


// PAGE TITLE
$page_title = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Bakery Sales & Inventory</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #75452e;
            --primary-dark: #4c2c20;
            --primary-light: #f4e9df;
            --accent: #d8894c;
            --background: #faf8f4;
            --white: #ffffff;
            --text: #332820;
            --muted: #95877d;
            --border: #eee7df;
            --success: #478765;
            --warning: #d99b38;
            --danger: #cf6656;
            --sidebar-width: 255px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--background);
            color: var(--text);
            overflow-x: hidden;
        }

        /* SIDEBAR */

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

        /* MAIN */

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 25px 30px;
            min-height: 100vh;
        }

        /* TOPBAR */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-heading h4 {
            font-weight: 700;
            font-size: 22px;
            margin-bottom: 3px;
        }

        .page-heading p {
            font-size: 12px;
            color: var(--muted);
            margin: 0;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .icon-button {
            width: 39px;
            height: 39px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
            font-size: 17px;
            position: relative;
        }

        .notification-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            background: var(--danger);
            border-radius: 50%;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-avatar {
            width: 39px;
            height: 39px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
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
            border: 0;
            background: none;
            font-size: 24px;
            color: var(--primary);
        }

        /* WELCOME */

        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .welcome-section h3 {
            font-family: 'Playfair Display', serif;
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .welcome-section p {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }

        .date-badge {
            background: white;
            border: 1px solid var(--border);
            padding: 10px 15px;
            border-radius: 9px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
        }

        /* STAT CARDS */

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 20px;
            height: 100%;
            transition: .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(65, 43, 28, .05);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 17px;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-orange {
            background: #fbefe3;
            color: #c47a3e;
        }

        .icon-green {
            background: #e8f4ed;
            color: #478765;
        }

        .icon-blue {
            background: #eaf0f8;
            color: #6685ac;
        }

        .icon-red {
            background: #faece9;
            color: #cf6656;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 25px;
            font-weight: 700;
            letter-spacing: -.5px;
        }

        .stat-note {
            font-size: 11px;
            color: var(--muted);
            margin-top: 7px;
        }

        .stat-note i {
            color: var(--success);
        }

        /* CONTENT CARDS */

        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 21px;
            height: 100%;
        }

        .card-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-heading h6 {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
        }

        .card-heading small {
            font-size: 11px;
            color: var(--muted);
        }

        .filter-select {
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 11px;
            color: var(--text);
            outline: none;
            background: white;
        }

        /* CHART */

        .chart-container {
            position: relative;
            height: 265px;
        }

        /* INVENTORY STATUS */

        .inventory-item {
            margin-bottom: 22px;
        }

        .inventory-item:last-child {
            margin-bottom: 0;
        }

        .inventory-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 9px;
        }

        .inventory-info span:last-child {
            font-weight: 700;
        }

        .progress {
            height: 8px;
            background: #f1ede8;
            border-radius: 20px;
        }

        .progress-bar {
            border-radius: 20px;
        }

        .bg-bakery {
            background: var(--accent);
        }

        .bg-green {
            background: var(--success);
        }

        .bg-yellow {
            background: var(--warning);
        }

        .bg-red {
            background: var(--danger);
        }

        /* TABLE */

        .table {
            margin: 0;
            white-space: nowrap;
        }

        .table thead th {
            background: #fbf9f6;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 13px 12px;
            border-bottom: 1px solid var(--border);
        }

        .table tbody td {
            font-size: 12px;
            padding: 14px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f3eee9;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .invoice {
            font-weight: 700;
            color: var(--primary);
        }

        .status-badge {
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-completed {
            background: #e8f4ed;
            color: #478765;
        }

        .status-pending {
            background: #fff4df;
            color: #b98229;
        }

        /* TOP PRODUCTS */

        .product-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3eee9;
        }

        .product-row:last-child {
            border-bottom: none;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-image {
            width: 43px;
            height: 43px;
            background: #f7eee5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            font-size: 21px;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info strong {
            display: block;
            font-size: 12px;
        }

        .product-info small {
            color: var(--muted);
            font-size: 10px;
        }

        .product-sold {
            text-align: right;
        }

        .product-sold strong {
            font-size: 12px;
        }

        .product-sold small {
            display: block;
            color: var(--muted);
            font-size: 10px;
        }

        .section-gap {
            margin-top: 22px;
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
        }

        /* MOBILE OVERLAY */

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            z-index: 1040;
        }

        @media(max-width: 991px) {
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
                display: inline-block;
            }
        }

        @media(max-width: 576px) {
            .main-content {
                padding: 17px 13px;
            }

            .topbar {
                margin-bottom: 23px;
            }

            .page-heading h4 {
                font-size: 18px;
            }

            .profile-info {
                display: none;
            }

            .welcome-section {
                align-items: flex-start;
                flex-direction: column;
                gap: 13px;
            }

            .welcome-section h3 {
                font-size: 22px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 21px;
            }

            .content-card {
                padding: 15px;
            }

            .chart-container {
                height: 220px;
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

            <a href="dashboard.php" class="nav-link active">
                <i class="bi bi-grid-fill"></i>
                Dashboard
            </a>

            <a href="pos.php" class="nav-link">
                <i class="bi bi-cart3"></i>
                POS / New Sale
            </a>

        </nav>

        <div class="menu-label">MANAGEMENT</div>

        <nav class="nav flex-column">

            <a href="products.php" class="nav-link">
                <i class="bi bi-box-seam"></i>
                Products
            </a>

            <a href="categories.php" class="nav-link">
                <i class="bi bi-tags"></i>
                Categories
            </a>

            <a href="inventory.php" class="nav-link">
                <i class="bi bi-clipboard-data"></i>
                Inventory
            </a>

            <a href="stock-movements.php" class="nav-link">
                <i class="bi bi-arrow-left-right"></i>
                Stock Movements
            </a>

        </nav>

        <div class="menu-label">SALES & REPORTS</div>

        <nav class="nav flex-column">

            <a href="sales.php" class="nav-link">
                <i class="bi bi-receipt"></i>
                Sales History
            </a>

            <a href="reports.php" class="nav-link">
                <i class="bi bi-bar-chart-line"></i>
                Reports
            </a>

        </nav>

        <div class="menu-label">SYSTEM</div>

        <nav class="nav flex-column">
            <a href="settings.php" class="nav-link">
                <i class="bi bi-gear"></i>
                Settings
            </a>

            <a href="logout.php" class="nav-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="menuToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div class="page-heading">
                    <h4>Dashboard</h4>
                    <p>Overview of your bakery business</p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-badge">
                    <i class="bi bi-calendar3 me-2"></i>
                    <span id="currentDate"></span>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-orange">
                            <i class="bi bi-currency-exchange"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">Today's Sales</div>
                    <div class="stat-value">
                        ₱<?= number_format($total_sales, 2) ?>
                    </div>

                    <div class="stat-note">
                        Sales from completed transactions today
                    </div>

                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-green">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">Transactions Today</div>
                    <div class="stat-value">
                        <?= number_format($total_transactions) ?>
                    </div>

                    <div class="stat-note">
                        Completed transactions today
                    </div>

                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-blue">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">Total Products</div>
                    <div class="stat-value">
                        <?= number_format($total_products) ?>
                    </div>

                    <div class="stat-note">
                        Available bakery products
                    </div>

                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-red">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">Low Stock Products</div>
                    <div class="stat-value">
                        <?= number_format($low_stock) ?>
                    </div>

                    <div class="stat-note">
                        <span style="color:var(--danger)">Needs restocking</span>
                    </div>

                </div>
            </div>
        </div>

        <div class="row g-3 section-gap">
            <div class="col-lg-8">
                <div class="content-card">
                    <div class="card-heading">
                        <div>
                            <h6>Sales Overview</h6>
                            <small>Monitor your bakery's sales performance</small>
                        </div>

                        <select class="filter-select" id="salesFilter">
                            <option value="both">Today & Yesterday</option>
                            <option value="today">Today Only</option>
                            <option value="yesterday">Yesterday Only</option>
                        </select>

                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="content-card">
                    <div class="card-heading">
                        <div>
                            <h6>Inventory Status</h6>
                            <small>Current product availability</small>
                        </div>
                        <i class="bi bi-boxes text-muted"></i>
                    </div>

                    <div class="inventory-item">
                        <div class="inventory-info">
                            <span>In Stock</span>
                            <span><?= $in_stock ?> Products</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-green"
                                style="width:<?= $in_stock_percent ?>%">
                            </div>
                        </div>
                    </div>

                    <div class="inventory-item">
                        <div class="inventory-info">
                            <span>Low Stock</span>
                            <span><?= $low_stock ?> Products</span>
                        </div>

                        <div class="progress">
                            <div class="progress-bar bg-yellow"
                                style="width:<?= $low_stock_percent ?>%">
                            </div>
                        </div>
                    </div>

                    <div class="inventory-item">
                        <div class="inventory-info">
                            <span>Out of Stock</span>
                            <span><?= $out_of_stock ?> Products</span>
                        </div>

                        <div class="progress">
                            <div class="progress-bar bg-red"
                                style="width:<?= $out_of_stock_percent ?>%">
                            </div>
                        </div>
                    </div>

                    <hr style="border-color:var(--border); margin:25px 0 18px;">

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted" style="font-size:11px;">Total Inventory Products</small>
                            <h4 class="fw-bold mb-0 mt-1">
                                <?= number_format($total_products) ?>
                            </h4>
                        </div>

                        <a href="inventory.php" class="view-all">
                            View Inventory <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 section-gap">
            <div class="col-lg-8">

                <div class="content-card">

                    <div class="card-heading">

                        <div>
                            <h6>Recent Sales</h6>
                            <small>Latest bakery transactions</small>
                        </div>

                        <a href="sales.php" class="view-all">
                            View All <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>

                    <div class="table-responsive">
                        <table class="table">

                            <thead>
                                <tr>
                                    <th>Invoice No.</th>
                                    <th>Date & Time</th>
                                    <th>Cashier</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>

                                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>

                                        <?php
                                        $status_class = $sale['status'] === 'Completed'
                                            ? 'status-completed'
                                            : 'status-pending';

                                        $cashier = !empty($sale['cashier_name'])
                                            ? $sale['cashier_name']
                                            : 'Admin';
                                        ?>

                                        <tr>
                                            <td class="invoice">
                                                <?= htmlspecialchars($sale['receipt_number']) ?>
                                            </td>

                                            <td>
                                                <?= date('M d, Y', strtotime($sale['created_at'])) ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?= date('h:i A', strtotime($sale['created_at'])) ?>
                                                </small>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($cashier) ?>
                                            </td>

                                            <td class="fw-bold">
                                                ₱<?= number_format((float) $sale['total_amount'], 2) ?>
                                            </td>

                                            <td>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= htmlspecialchars($sale['status']) ?>
                                                </span>
                                            </td>
                                        </tr>

                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No sales transactions today.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-card">
                    <div class="card-heading">
                        <div>
                            <h6>Top Selling Products</h6>
                            <small>Best sellers today</small>
                        </div>

                        <i class="bi bi-trophy text-warning"></i>

                    </div>
                    <?php if ($top_products && $top_products->num_rows > 0): ?>

                        <?php while ($product = $top_products->fetch_assoc()): ?>

                            <div class="product-row">

                                <div class="product-info">
                                    <div class="product-image">
                                        <?php if (!empty($product['product_image'])): ?>
                                            <img src="uploads/products/<?= htmlspecialchars(basename($product['product_image'])) ?>"
                                                alt="<?= htmlspecialchars($product['product_name']) ?>">
                                        <?php else: ?>
                                            <i class="bi bi-image text-muted"></i>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <strong>
                                            <?= htmlspecialchars($product['product_name']) ?>
                                        </strong>
                                        <small>Today's sales</small>
                                    </div>
                                </div>

                                <div class="product-sold">
                                    <strong><?= number_format($product['total_sold']) ?></strong>
                                    <small>Sold</small>
                                </div>

                            </div>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <div class="text-center text-muted py-4" style="font-size:12px;">
                            No products sold today.
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const dateElement = document.getElementById('currentDate');

        dateElement.textContent = new Date().toLocaleDateString('en-PH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const menuToggle = document.getElementById('menuToggle');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        const ctx = document.getElementById('salesChart');

        const salesLabels = <?= json_encode($chart_labels) ?>;
        const todaySalesValues = <?= json_encode($today_chart_values) ?>;
        const yesterdaySalesValues = <?= json_encode($yesterday_chart_values) ?>;

        const salesChart = new Chart(ctx, {
            type: 'line',

            data: {
                labels: salesLabels,

                datasets: [{
                        label: 'Today',
                        data: todaySalesValues,

                        borderColor: '#d8894c',
                        backgroundColor: 'rgba(216,137,76,0.12)',

                        fill: false,
                        tension: 0.4,
                        borderWidth: 2.5,

                        pointRadius: 4,
                        pointHoverRadius: 6,

                        pointBackgroundColor: '#d8894c',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Yesterday',
                        data: yesterdaySalesValues,

                        borderColor: '#6685ac',
                        backgroundColor: 'rgba(102,133,172,0.12)',

                        fill: false,
                        tension: 0.4,
                        borderWidth: 2.5,

                        pointRadius: 4,
                        pointHoverRadius: 6,

                        pointBackgroundColor: '#6685ac',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }
                ]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: true,
                        position: 'top',

                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: {
                                size: 11
                            }
                        }
                    },

                    tooltip: {
                        backgroundColor: '#4c2c20',
                        padding: 12,

                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label + ': ₱' +
                                    context.parsed.y.toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                            }
                        }
                    }
                },

                scales: {
                    y: {
                        beginAtZero: true,

                        grid: {
                            color: '#f2eee9',
                            drawBorder: false
                        },

                        ticks: {
                            font: {
                                size: 10
                            },

                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },

                    x: {
                        grid: {
                            display: false
                        },

                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        document.getElementById('salesFilter').addEventListener('change', function() {

            const selected = this.value;

            salesChart.data.datasets[0].hidden =
                selected === 'yesterday';

            salesChart.data.datasets[1].hidden =
                selected === 'today';

            salesChart.update();

        });
    </script>
</body>

</html>