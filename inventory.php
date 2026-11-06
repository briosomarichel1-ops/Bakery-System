<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// GET PRODUCTS
$products = [];

$productQuery = $conn->query("
    SELECT 
        products.*,
        category.category_name
    FROM products
    LEFT JOIN category
        ON products.category_id = category.category_id
    ORDER BY products.product_name ASC
");

while ($product = $productQuery->fetch_assoc()) {
    $products[] = $product;
}

// ESCAPE OUTPUT
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// INVENTORY SUMMARY
$totalProducts = count($products);
$totalInStock = 0;
$totalLowStock = 0;
$totalOutOfStock = 0;

foreach ($products as $product) {
    $stock = (int)$product['stock_quantity'];
    $stockLimit = (int)$product['stock_limit'];

    if ($stock <= 0) {
        $totalOutOfStock++;
    } elseif ($stock <= $stockLimit) {
        $totalLowStock++;
    } else {
        $totalInStock++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | Bakery Sales & Inventory</title>

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

        /* INVENTORY CARDS */

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 19px;
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
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .icon-orange {
            background: #fbefe3;
            color: #c47a3e;
        }

        .icon-green {
            background: #e8f4ed;
            color: #478765;
        }

        .icon-yellow {
            background: #fff4df;
            color: #b98229;
        }

        .icon-red {
            background: #faece9;
            color: #cf6656;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
        }

        .stat-note {
            font-size: 11px;
            color: var(--muted);
            margin-top: 5px;
        }

        /* CONTENT CARD */

        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 21px;
        }

        .card-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .card-heading h6 {
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .card-heading small {
            font-size: 11px;
            color: var(--muted);
        }

        .search-box {
            position: relative;
            max-width: 280px;
            width: 100%;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .search-box input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 12px 9px 35px;
            font-size: 12px;
            outline: none;
        }

        .filter-select {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 12px;
            color: var(--text);
            background: white;
            outline: none;
        }

        .search-box input:focus,
        .filter-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(216, 137, 76, .12);
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

        .product-info {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .product-image {
            width: 40px;
            height: 40px;
            background: #f7eee5;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .product-name {
            display: block;
            font-weight: 700;
            color: var(--text);
        }

        .product-code {
            display: block;
            color: var(--muted);
            font-size: 10px;
            margin-top: 3px;
        }

        .stock-badge {
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .stock-in {
            background: #e8f4ed;
            color: #478765;
        }

        .stock-low {
            background: #fff4df;
            color: #b98229;
        }

        .stock-out {
            background: #faece9;
            color: #cf6656;
        }

        .action-btn {
            width: 31px;
            height: 31px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 13px;
        }

        .action-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px !important;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 30px;
            display: block;
            margin-bottom: 8px;
        }

        .section-gap {
            margin-top: 22px;
        }

        /* INVENTORY PRODUCT CARDS */

        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 18px;
        }

        .inventory-product-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
            transition: .2s;
        }

        .inventory-product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(65, 43, 28, .06);
        }

        .inventory-product-image {
            height: 155px;
            background: #f7eee5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .inventory-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .inventory-product-image i {
            font-size: 45px;
            color: var(--accent);
        }

        .inventory-product-body {
            padding: 16px;
        }

        .inventory-product-category {
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 7px;
        }

        .inventory-product-name {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text);
        }

        .inventory-product-price {
            font-size: 17px;
            font-weight: 700;
            color: var(--primary);
        }

        .inventory-stock-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border);
            margin-top: 14px;
            padding-top: 13px;
            gap: 8px;
        }

        .inventory-stock-info small {
            color: var(--muted);
            font-size: 11px;
        }

        .inventory-stock-quantity {
            font-size: 13px;
            font-weight: 700;
        }

        .inventory-product-card .stock-badge {
            display: inline-block;
        }

        .inventory-empty {
            text-align: center;
            padding: 45px 15px;
            color: var(--muted);
        }

        .inventory-empty i {
            display: block;
            font-size: 35px;
            margin-bottom: 10px;
        }

        @media(max-width: 576px) {
            .inventory-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .inventory-product-image {
                height: 120px;
            }

            .inventory-product-body {
                padding: 12px;
            }

            .inventory-product-name {
                font-size: 12px;
            }

            .inventory-product-price {
                font-size: 15px;
            }
        }

        /* MODAL */

        .modal-content {
            border: none;
            border-radius: 13px;
        }

        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 20px;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 15px 20px;
        }

        .detail-label {
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 13px;
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

            .stat-card {
                padding: 15px;
            }

            .stat-value {
                font-size: 21px;
            }

            .content-card {
                padding: 15px;
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
            <a href="dashboard.php" class="nav-link">
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

            <a href="inventory.php" class="nav-link active">
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
                    <h4>Inventory</h4>
                    <p>Monitor product stocks and availability</p>
                </div>

            </div>
        </div>
        <div class="row g-3">

            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-orange">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">Total Products</div>
                    <div class="stat-value"><?= $totalProducts ?></div>
                    <div class="stat-note">Registered products</div>

                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-green">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">In Stock</div>
                    <div class="stat-value"><?= $totalInStock ?></div>
                    <div class="stat-note">Products with sufficient stock</div>

                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-yellow">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">Low Stock</div>
                    <div class="stat-value"><?= $totalLowStock ?></div>
                    <div class="stat-note">Needs restocking</div>

                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">

                    <div class="stat-top">
                        <div class="stat-icon icon-red">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>

                    <div class="stat-label">Out of Stock</div>
                    <div class="stat-value"><?= $totalOutOfStock ?></div>
                    <div class="stat-note">Unavailable products</div>

                </div>
            </div>

        </div>
        <div class="content-card section-gap">

            <div class="card-heading">

                <div>
                    <h6>Product Inventory</h6>
                    <small>Current stock levels of all bakery products</small>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">

                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInventory" placeholder="Search products...">
                    </div>

                    <select class="filter-select" id="stockFilter">
                        <option value="all">All Stock</option>
                        <option value="in">In Stock</option>
                        <option value="low">Low Stock</option>
                        <option value="out">Out of Stock</option>
                    </select>

                </div>

            </div>

            <div class="inventory-grid" id="inventoryGrid">

                <?php foreach ($products as $product): ?>

                    <?php
                    $stock = (int)$product['stock_quantity'];
                    $stockLimit = (int)$product['stock_limit'];

                    if ($stock <= 0) {
                        $stockStatus = 'out';
                        $statusLabel = 'Out of Stock';
                        $statusClass = 'stock-out';
                    } elseif ($stock <= $stockLimit) {
                        $stockStatus = 'low';
                        $statusLabel = 'Low Stock';
                        $statusClass = 'stock-low';
                    } else {
                        $stockStatus = 'in';
                        $statusLabel = 'In Stock';
                        $statusClass = 'stock-in';
                    }
                    ?>

                    <div class="inventory-product-card"
                        data-stock="<?= $stock ?>"
                        data-status="<?= $stockStatus ?>">

                        <div class="inventory-product-image">

                            <?php if (!empty($product['product_image'])): ?>

                                <img src="<?= e($product['product_image']) ?>"
                                    alt="<?= e($product['product_name']) ?>">

                            <?php else: ?>

                                <i class="bi bi-image"></i>

                            <?php endif; ?>

                        </div>

                        <div class="inventory-product-body">

                            <div class="inventory-product-category">
                                <?= e($product['category_name'] ?? 'Uncategorized') ?>
                            </div>

                            <div class="inventory-product-name">
                                <?= e($product['product_name']) ?>
                            </div>

                            <div class="inventory-product-price">
                                ₱<?= number_format((float)$product['price'], 2) ?>
                            </div>

                            <div class="inventory-stock-info">

                                <div>
                                    <small>Available Stock</small>
                                    <div class="inventory-stock-quantity">
                                        <?= $stock ?> pcs
                                    </div>
                                </div>

                                <span class="stock-badge <?= $statusClass ?>">
                                    <?= $statusLabel ?>
                                </span>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div id="noResults" class="inventory-empty d-none">
                <i class="bi bi-search"></i>
                No inventory records found.
            </div>

            <?php if (count($products) === 0): ?>

                <div class="inventory-empty">
                    <i class="bi bi-box-seam"></i>
                    No products have been registered yet.
                </div>

            <?php endif; ?>

            <div class="mt-3">
                <small class="text-muted" id="inventoryResultCount">
                    Showing <?= count($products) ?> products
                </small>
            </div>

        </div>
    </main>

    <!-- VIEW INVENTORY MODAL -->
    <div class="modal fade" id="inventoryDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Inventory Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="product-image" id="detailImage" style="width:55px;height:55px;font-size:27px;">🥖</div>
                        <div>
                            <div class="fw-bold" id="detailName">Product Name</div>
                            <small class="text-muted" id="detailCode">PRD-001</small>
                        </div>
                    </div>

                    <div class="row g-3">

                        <div class="col-6">
                            <div class="detail-label">Category</div>
                            <div class="detail-value" id="detailCategory">Bread</div>
                        </div>

                        <div class="col-6">
                            <div class="detail-label">Selling Price</div>
                            <div class="detail-value" id="detailPrice">₱5.00</div>
                        </div>

                        <div class="col-6">
                            <div class="detail-label">Available Stock</div>
                            <div class="detail-value" id="detailStock">45 pcs</div>
                        </div>

                        <div class="col-6">
                            <div class="detail-label">Stock Status</div>
                            <div id="detailStatus">In Stock</div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                </div>

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

        function filterInventory() {

            const searchValue = document.getElementById('searchInventory').value.toLowerCase().trim();
            const stockFilter = document.getElementById('stockFilter').value;

            const cards = document.querySelectorAll('.inventory-product-card');

            let visibleCount = 0;

            cards.forEach(card => {

                const productText = card.textContent.toLowerCase();
                const stockStatus = card.dataset.status;

                const matchesSearch = productText.includes(searchValue);

                const matchesStock =
                    stockFilter === 'all' || stockStatus === stockFilter;

                const visible = matchesSearch && matchesStock;

                card.style.display = visible ? '' : 'none';

                if (visible) {
                    visibleCount++;
                }

            });

            document.getElementById('noResults').classList.toggle(
                'd-none',
                visibleCount !== 0 || cards.length === 0
            );

            document.getElementById('inventoryResultCount').textContent =
                `Showing ${visibleCount} product${visibleCount !== 1 ? 's' : ''}`;
        }

        document.getElementById('searchInventory').addEventListener('input', filterInventory);
        document.getElementById('stockFilter').addEventListener('change', filterInventory);

        document.getElementById('searchInventory').addEventListener('input', filterInventory);
        document.getElementById('stockFilter').addEventListener('change', filterInventory);
    </script>

</body>

</html>