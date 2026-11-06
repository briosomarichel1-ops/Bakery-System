<?php
session_start();
include "../conn.php";

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// GET STOCK MOVEMENTS
$movements = [];

$movementQuery = $conn->query("
    SELECT 
        stock_movements.*,
        products.product_name,
        users.fullname
    FROM stock_movements
    LEFT JOIN products
        ON stock_movements.product_id = products.product_id
    LEFT JOIN users
        ON stock_movements.user_id = users.user_id
    ORDER BY stock_movements.stock_id DESC
");

while ($movement = $movementQuery->fetch_assoc()) {
    $movements[] = $movement;
}

// GET PRODUCTS
$products = [];

$productQuery = $conn->query("
    SELECT product_id, product_name, stock_quantity
    FROM products
    WHERE status = 'Active'
    ORDER BY product_name ASC
");

while ($product = $productQuery->fetch_assoc()) {
    $products[] = $product;
}
// MOVEMENT STATISTICS (TODAY ONLY)
$today = date('Y-m-d');

$totalMovements = 0;
$totalStockIn = 0;
$totalStockOut = 0;
$totalAdjustments = 0;

foreach ($movements as $movement) {

    $movementDate = date('Y-m-d', strtotime($movement['created_at']));

    if ($movementDate === $today) {

        $totalMovements++;

        if ($movement['movement_type'] === 'Stock In') {
            $totalStockIn += (int)$movement['quantity'];
        }

        if ($movement['movement_type'] === 'Stock Out') {
            $totalStockOut += (int)$movement['quantity'];
        }

        if ($movement['movement_type'] === 'Adjustment') {
            $totalAdjustments++;
        }
    }
}

// ESCAPE OUTPUT
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// MOVEMENT REFERENCE
function movementReference($id)
{
    return "SM-" . str_pad($id, 4, "0", STR_PAD_LEFT);
}

// MOVEMENT BADGE
function movementBadge($type)
{
    if ($type === 'Stock In') return 'badge-in';
    if ($type === 'Stock Out') return 'badge-out';
    return 'badge-adjustment';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements | Bakery Sales & Inventory</title>

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
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            background: var(--background);
            color: var(--text);
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
        }

        .sidebar .nav-link i {
            font-size: 17px
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

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            z-index: 1040;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 15px;
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
            margin-top: 4px;
        }

        .mobile-toggle {
            display: none;
            border: 0;
            background: var(--white);
            padding: 8px 11px;
            border-radius: 8px;
            color: var(--text);
            font-size: 20px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .icon-button {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 10px;
            color: var(--text);
            position: relative;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .profile small {
            display: block;
            color: var(--muted);
            font-size: 11px;
        }

        .btn-primary-custom {
            background: var(--primary);
            color: white;
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-primary-custom:hover {
            background: var(--primary-dark);
            color: white;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 19px;
            height: 100%;
        }

        .stat-icon {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 19px;
            margin-bottom: 13px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
        }

        .stat-value {
            font-size: 25px;
            font-weight: 700;
            margin-top: 3px;
        }

        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
        }

        .card-header-custom {
            padding: 19px 21px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-header-custom h6 {
            font-weight: 700;
            margin: 0;
        }

        .search-box {
            position: relative;
            min-width: 220px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 10px;
            color: var(--muted);
        }

        .search-box input {
            padding-left: 35px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
        }

        .form-control,
        .form-select {
            border-color: var(--border);
            font-size: 13px;
            padding: 10px 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(216, 137, 76, .12);
        }

        .table {
            margin: 0;
            white-space: nowrap;
        }

        .table thead th {
            background: #fcfaf7;
            color: var(--muted);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
        }

        .table tbody td {
            padding: 15px 18px;
            font-size: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f2ede7;
        }

        .table tbody tr:last-child td {
            border-bottom: 0
        }

        .badge-stock {
            padding: 6px 9px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
        }

        .badge-in {
            background: #e7f3eb;
            color: var(--success);
        }

        .badge-out {
            background: #fbeae7;
            color: var(--danger);
        }

        .badge-adjustment {
            background: #fff3df;
            color: #b47b22;
        }

        .product-name {
            font-weight: 700
        }

        .text-muted-custom {
            color: var(--muted)
        }

        .movement-quantity {
            font-weight: 700;
        }

        .quantity-in {
            color: var(--success)
        }

        .quantity-out {
            color: var(--danger)
        }

        .action-btn {
            border: 1px solid var(--border);
            background: white;
            color: var(--muted);
            width: 30px;
            height: 30px;
            border-radius: 7px;
        }

        .action-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .modal-content {
            border: 0;
            border-radius: 13px;
        }

        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 20px 23px;
        }

        .modal-title {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
        }

        .modal-body {
            padding: 23px
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 15px 23px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        @media(max-width:991px) {
            .sidebar {
                transform: translateX(-100%)
            }

            .sidebar.show {
                transform: translateX(0)
            }

            .sidebar-overlay.show {
                display: block
            }

            .main-content {
                margin-left: 0;
                padding: 20px
            }

            .mobile-toggle {
                display: inline-block
            }
        }

        @media(max-width:576px) {
            .main-content {
                padding: 17px 13px
            }

            .page-title {
                font-size: 22px
            }

            .topbar-right {
                gap: 8px
            }

            .profile-info {
                display: none
            }

            .card-header-custom {
                align-items: stretch
            }

            .search-box {
                width: 100%
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
            <a href="stock-movements.php" class="nav-link active"><i class="bi bi-arrow-left-right"></i> Stock Movements</a>
        </nav>

        <div class="menu-label">SALES & REPORTS</div>
        <nav class="nav flex-column">
            <a href="sales.php" class="nav-link"><i class="bi bi-receipt"></i> Sales History</a>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
        </nav>

        <div class="menu-label">SYSTEM</div>
        <nav class="nav flex-column">
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">

        <div class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" id="menuToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h1 class="page-title">Stock Movements</h1>
                    <div class="page-subtitle">Monitor stock-in, stock-out, and inventory adjustments.</div>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#eaf2fb;color:#5484b5;">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <div class="stat-label">Total Movements</div>
                    <div class="stat-value" id="totalMovements">
                        <?= $totalMovements ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#e7f3eb;color:var(--success);">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                    <div class="stat-label">Stock In</div>
                    <div class="stat-value" id="totalStockIn">
                        <?= number_format($totalStockIn) ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fbeae7;color:var(--danger);">
                        <i class="bi bi-box-arrow-up"></i>
                    </div>
                    <div class="stat-label">Stock Out</div>
                    <div class="stat-value" id="totalStockOut">
                        <?= number_format($totalStockOut) ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff3df;color:var(--warning);">
                        <i class="bi bi-sliders"></i>
                    </div>
                    <div class="stat-label">Adjustments</div>
                    <div class="stat-value" id="totalAdjustments">
                        <?= $totalAdjustments ?>
                    </div>
                </div>
            </div>

        </div>

        <div class="content-card">

            <div class="card-header-custom">
                <div>
                    <h6>Movement History</h6>
                    <small class="text-muted-custom">View all recorded inventory movements.</small>
                </div>
            </div>

            <div class="p-3 d-flex flex-wrap gap-2 justify-content-between">

                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search product or reference...">
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <select class="form-select" id="typeFilter" style="width:150px;">
                        <option value="all">All Types</option>
                        <option value="Stock In">Stock In</option>
                        <option value="Stock Out">Stock Out</option>
                        <option value="Adjustment">Adjustment</option>
                    </select>

                    <select class="form-select" id="dateFilter" style="width:150px;">
                        <option value="all">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">Last 7 Days</option>
                    </select>
                </div>

            </div>

            <div class="table-responsive">
                <table class="table" id="movementTable">
                    <thead>
                        <tr>
                            <th>Reference No.</th>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Movement Type</th>
                            <th>Quantity</th>
                            <th>Previous Stock</th>
                            <th>New Stock</th>
                            <th>Recorded By</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="movementBody">

                        <?php foreach ($movements as $movement): ?>

                            <?php
                            $reference = movementReference($movement['stock_id']);
                            $type = $movement['movement_type'];
                            $quantity = (int)$movement['quantity'];

                            $quantityDisplay = $quantity;

                            if ($type === 'Stock In') {
                                $quantityDisplay = '+' . $quantity;
                                $quantityClass = 'quantity-in';
                            } elseif ($type === 'Stock Out' || $type === 'Sale') {
                                $quantityDisplay = '-' . $quantity;
                                $quantityClass = 'quantity-out';
                            } else {
                                $quantityDisplay = (int)$movement['new_stock'] - (int)$movement['previous_stock'];

                                if ($quantityDisplay > 0) {
                                    $quantityDisplay = '+' . $quantityDisplay;
                                    $quantityClass = 'quantity-in';
                                } else {
                                    $quantityClass = 'quantity-out';
                                }
                            }

                            $date = date('Y-m-d', strtotime($movement['created_at']));
                            ?>

                            <tr
                                data-type="<?= e($type) ?>"
                                data-date="<?= e($date) ?>"
                                data-notes="<?= e($movement['remarks']) ?>">

                                <td>
                                    <strong><?= e($reference) ?></strong>
                                </td>

                                <td>
                                    <?= date('M d, Y', strtotime($movement['created_at'])) ?>
                                    <small class="d-block text-muted-custom">
                                        <?= date('h:i A', strtotime($movement['created_at'])) ?>
                                    </small>
                                </td>

                                <td>
                                    <span class="product-name">
                                        <?= e($movement['product_name'] ?? 'Unknown Product') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge-stock <?= movementBadge($type) ?>">
                                        <?= e($type) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="movement-quantity <?= $quantityClass ?>">
                                        <?= e($quantityDisplay) ?>
                                    </span>
                                </td>

                                <td><?= (int)$movement['previous_stock'] ?></td>

                                <td>
                                    <strong><?= (int)$movement['new_stock'] ?></strong>
                                </td>

                                <td>
                                    <?= e($movement['fullname'] ?? 'Unknown User') ?>
                                </td>

                                <td>
                                    <button class="action-btn" onclick="viewMovement(this)" title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top" style="border-color:var(--border)!important;">
                <small class="text-muted-custom" id="resultCount">
                    Showing <?= count($movements) ?> movements
                </small>
            </div>

        </div>

    </main>

    <!-- VIEW MOVEMENT MODAL -->
    <div class="modal fade" id="viewMovementModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Movement Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <small class="text-muted-custom">Reference No.</small>
                            <div class="fw-bold" id="viewReference">-</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted-custom">Movement Type</small>
                            <div class="fw-bold" id="viewType">-</div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted-custom">Product</small>
                            <div class="fw-bold" id="viewProduct">-</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted-custom">Quantity</small>
                            <div class="fw-bold" id="viewQuantity">-</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted-custom">Recorded By</small>
                            <div class="fw-bold" id="viewUser">-</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted-custom">Previous Stock</small>
                            <div class="fw-bold" id="viewPrevious">-</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted-custom">New Stock</small>
                            <div class="fw-bold" id="viewNew">-</div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted-custom">Date & Time</small>
                            <div class="fw-bold" id="viewDate">-</div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted-custom">Reason / Notes</small>
                            <div class="p-3 rounded mt-1" style="background:var(--background);" id="viewNotes">-</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
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

        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');
        const dateFilter = document.getElementById('dateFilter');
        const movementBody = document.getElementById('movementBody');

        function filterMovements() {
            const search = searchInput.value.toLowerCase().trim();
            const type = typeFilter.value;
            const date = dateFilter.value;
            const rows = movementBody.querySelectorAll('tr');

            let visible = 0;

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const weekAgo = new Date(today);
            weekAgo.setDate(today.getDate() - 6);

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();

                const matchesSearch = text.includes(search);

                const matchesType =
                    type === 'all' || row.dataset.type === type;

                const rowDate = new Date(row.dataset.date + 'T00:00:00');

                const matchesDate =
                    date === 'all' ||
                    (date === 'today' && rowDate.getTime() === today.getTime()) ||
                    (date === 'week' && rowDate >= weekAgo && rowDate <= today);

                const show = matchesSearch && matchesType && matchesDate;

                row.style.display = show ? '' : 'none';

                if (show) {
                    visible++;
                }
            });

            document.getElementById('resultCount').textContent =
                `Showing ${visible} movement${visible !== 1 ? 's' : ''}`;
        }

        searchInput.addEventListener('input', filterMovements);
        typeFilter.addEventListener('change', filterMovements);
        dateFilter.addEventListener('change', filterMovements);

        // PRODUCT STOCK DISPLAY
        const productSelect = document.getElementById('productSelect');
        const currentStock = document.getElementById('currentStock');

        productSelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];

            currentStock.value = selected.dataset.stock !== undefined ?
                selected.dataset.stock + ' units' :
                'Select a product first';
        });

        function viewMovement(button) {
            const row = button.closest('tr');
            const cells = row.querySelectorAll('td');

            document.getElementById('viewReference').textContent = cells[0].innerText;
            document.getElementById('viewDate').textContent = cells[1].innerText;
            document.getElementById('viewProduct').textContent = cells[2].innerText;
            document.getElementById('viewType').textContent = cells[3].innerText;
            document.getElementById('viewQuantity').textContent = cells[4].innerText;
            document.getElementById('viewPrevious').textContent = cells[5].innerText;
            document.getElementById('viewNew').textContent = cells[6].innerText;
            document.getElementById('viewUser').textContent = cells[7].innerText;
            document.getElementById('viewNotes').textContent = row.dataset.notes || 'No notes available.';

            new bootstrap.Modal(document.getElementById('viewMovementModal')).show();
        }

        function updateSummary() {
            const rows = movementBody.querySelectorAll('tr');

            let stockIn = 0;
            let stockOut = 0;
            let adjustments = 0;

            rows.forEach(row => {
                const type = row.dataset.type;
                const quantityText = row.cells[4].innerText.replace('+', '');
                const quantity = Math.abs(parseInt(quantityText)) || 0;

                if (type === 'Stock In') stockIn += quantity;
                if (type === 'Stock Out') stockOut += quantity;
                if (type === 'Adjustment') adjustments++;
            });

            document.getElementById('totalMovements').textContent = rows.length;
            document.getElementById('totalStockIn').textContent = stockIn;
            document.getElementById('totalStockOut').textContent = stockOut;
            document.getElementById('totalAdjustments').textContent = adjustments;
        }
    </script>

</body>

</html>