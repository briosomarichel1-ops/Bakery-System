<?php
session_start();
date_default_timezone_set('Asia/Manila');

include '../conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'process_sale') {

    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['cart']) || !is_array($input['cart'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Your cart is empty.'
        ]);
        exit;
    }

    $payment_method = $input['payment_method'] ?? '';
    $transaction_no = trim($input['transaction_no'] ?? '');
    $amount_paid = (float) ($input['amount_paid'] ?? 0);

    if (!in_array($payment_method, ['cash', 'gcash'], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid payment method.'
        ]);
        exit;
    }

    if ($payment_method === 'gcash' && $transaction_no === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter the GCash reference number.'
        ]);
        exit;
    }

    if ($payment_method === 'cash') {
        $transaction_no = 'CASH';
    }

    try {

        $conn->begin_transaction();

        $items = [];
        $total_amount = 0;

        $product_stmt = $conn->prepare("
            SELECT product_id, product_name, price, stock_quantity, status
            FROM products
            WHERE product_id = ?
            FOR UPDATE
        ");

        foreach ($input['cart'] as $cart_item) {

            $product_id = (int) ($cart_item['product_id'] ?? 0);
            $quantity = (int) ($cart_item['quantity'] ?? 0);

            if ($product_id <= 0 || $quantity <= 0) {
                throw new Exception("Invalid product or quantity.");
            }

            $product_stmt->bind_param("i", $product_id);
            $product_stmt->execute();

            $result = $product_stmt->get_result();
            $product = $result->fetch_assoc();

            if (!$product) {
                throw new Exception("A product in your cart no longer exists.");
            }

            if ($product['status'] !== 'Active') {
                throw new Exception($product['product_name'] . " is not available.");
            }

            if ($quantity > (int) $product['stock_quantity']) {
                throw new Exception(
                    "Insufficient stock for " . $product['product_name'] .
                        ". Available stock: " . $product['stock_quantity']
                );
            }

            $price = (float) $product['price'];
            $subtotal = $price * $quantity;

            $total_amount += $subtotal;

            $items[] = [
                'product_id' => $product_id,
                'product_name' => $product['product_name'],
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal,
                'previous_stock' => (int) $product['stock_quantity'],
                'new_stock' => (int) $product['stock_quantity'] - $quantity
            ];
        }

        $product_stmt->close();

        if ($amount_paid < $total_amount) {
            throw new Exception("Amount received is insufficient.");
        }

        $receipt_number = 'CC-' . date('YmdHis') . '-' . random_int(100, 999);

        $payment_method_db = ucfirst($payment_method);
        $status = 'Completed';
        $created_at = date('Y-m-d H:i:s');

        $sale_stmt = $conn->prepare("
            INSERT INTO sales
            (
                receipt_number,
                user_id,
                total_amount,
                payment_method,
                transaction_no,
                status,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $sale_stmt->bind_param(
            "sidssss",
            $receipt_number,
            $user_id,
            $total_amount,
            $payment_method_db,
            $transaction_no,
            $status,
            $created_at
        );

        if (!$sale_stmt->execute()) {
            throw new Exception("Unable to save the sale.");
        }

        $sale_id = $conn->insert_id;
        $sale_stmt->close();

        $item_stmt = $conn->prepare("
            INSERT INTO sale_items
            (
                sale_id,
                product_id,
                quantity,
                unit_price,
                subtotal
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stock_stmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = ?
            WHERE product_id = ?
        ");

        foreach ($items as $item) {

            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            $subtotal = $item['subtotal'];
            $new_stock = $item['new_stock'];

            $item_stmt->bind_param(
                "iiidd",
                $sale_id,
                $product_id,
                $quantity,
                $price,
                $subtotal
            );

            if (!$item_stmt->execute()) {
                throw new Exception("Unable to save sale items.");
            }

            $stock_stmt->bind_param(
                "ii",
                $new_stock,
                $product_id
            );

            if (!$stock_stmt->execute()) {
                throw new Exception("Unable to update product stock.");
            }
        }

        $item_stmt->close();
        $stock_stmt->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Sale completed successfully.',
            'receipt_number' => $receipt_number,
            'total_amount' => number_format($total_amount, 2, '.', '')
        ]);
    } catch (Throwable $e) {

        $conn->rollback();

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

$product_query = "
    SELECT
        p.product_id,
        p.category_id,
        p.product_name,
        p.price,
        p.stock_quantity,
        p.stock_limit,
        p.product_image,
        p.status,
        c.category_name
    FROM products p
    LEFT JOIN category c
        ON p.category_id = c.category_id
    WHERE p.status = 'Active'
    ORDER BY p.product_name ASC
";

$products = $conn->query($product_query);

$categories = $conn->query("
    SELECT category_id, category_name
    FROM category
    ORDER BY category_name ASC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS | Bakery Sales & Inventory</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* MAIN CONTENT */

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 25px 28px;
            min-height: 100vh;
        }

        /* TOPBAR */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
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

        /* POS LAYOUT */

        .pos-container {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 370px;
            gap: 20px;
            align-items: start;
        }

        .products-panel,
        .cart-panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 20px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .section-subtitle {
            font-size: 11px;
            color: var(--muted);
        }

        /* SEARCH */

        .search-wrapper {
            position: relative;
            margin-top: 20px;
        }

        .search-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .search-input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 12px 15px 12px 40px;
            font-size: 12px;
            outline: none;
            transition: .2s;
        }

        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(216, 137, 76, .1);
        }

        /* CATEGORY FILTER */

        .category-list {
            display: flex;
            gap: 9px;
            overflow-x: auto;
            padding: 18px 0;
        }

        .category-btn {
            border: 1px solid var(--border);
            background: white;
            color: var(--muted);
            border-radius: 8px;
            padding: 9px 15px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            transition: .2s;
        }

        .category-btn:hover,
        .category-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* PRODUCT CARDS */

        .products-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 13px;
        }

        .product-card {
            border: 1px solid var(--border);
            border-radius: 11px;
            overflow: hidden;
            cursor: pointer;
            transition: .2s;
            background: white;
        }

        .product-card:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(65, 43, 28, .07);
        }

        .product-card.disabled {
            opacity: .5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .product-image {
            height: 115px;
            background: #f8f0e8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-details {
            padding: 12px;
        }

        .product-name {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .product-category {
            color: var(--muted);
            font-size: 10px;
            margin-bottom: 10px;
        }

        .product-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .product-price {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        .product-stock {
            font-size: 10px;
            color: var(--muted);
        }

        .add-icon {
            width: 26px;
            height: 26px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* CART */

        .cart-panel {
            position: sticky;
            top: 20px;
        }

        .cart-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 17px;
            border-bottom: 1px solid var(--border);
        }

        .cart-count {
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            padding: 5px 9px;
            border-radius: 20px;
        }

        .cart-items {
            min-height: 220px;
            max-height: 360px;
            overflow-y: auto;
        }

        .empty-cart {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 220px;
            color: var(--muted);
            text-align: center;
        }

        .empty-cart i {
            font-size: 38px;
            margin-bottom: 10px;
            color: #d9c9bd;
        }

        .empty-cart strong {
            font-size: 13px;
            color: var(--text);
        }

        .empty-cart small {
            font-size: 11px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 0;
            border-bottom: 1px solid #f2eee9;
        }

        .cart-item-name {
            font-size: 12px;
            font-weight: 700;
        }

        .cart-item-price {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .quantity-control button {
            width: 24px;
            height: 24px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 6px;
            color: var(--primary);
            font-size: 12px;
        }

        .quantity-control span {
            font-size: 11px;
            font-weight: 700;
        }

        .cart-item-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-end;
        }

        .cart-item-total {
            font-size: 12px;
            font-weight: 700;
        }

        .remove-item {
            border: 0;
            background: none;
            color: var(--danger);
            font-size: 14px;
        }

        /* TOTALS */

        .cart-summary {
            border-top: 1px solid var(--border);
            padding-top: 17px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 13px;
        }

        .summary-row span:first-child {
            color: var(--muted);
        }

        .summary-row strong {
            font-weight: 700;
        }

        .total-row {
            border-top: 1px dashed var(--border);
            padding-top: 15px;
            margin-top: 5px;
        }

        .total-row span:first-child {
            color: var(--text);
            font-weight: 700;
        }

        .total-row strong {
            color: var(--primary);
            font-size: 22px;
        }

        /* PAYMENT */

        .payment-title {
            font-size: 12px;
            font-weight: 700;
            margin: 18px 0 10px;
        }

        .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px;
        }

        .payment-option {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
        }

        .payment-option input {
            display: none;
        }

        .payment-option i {
            display: block;
            font-size: 19px;
            margin-bottom: 4px;
        }

        .payment-option.active {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .payment-input {
            margin-top: 13px;
        }

        .payment-input label {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .payment-input input {
            width: 100%;
            padding: 11px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            font-size: 13px;
        }

        .payment-input input:focus {
            border-color: var(--accent);
        }

        .change-box {
            background: #edf6f0;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 13px;
        }

        .change-box span {
            font-size: 11px;
            color: var(--success);
        }

        .change-box strong {
            font-size: 16px;
            color: var(--success);
        }

        .checkout-btn {
            width: 100%;
            border: none;
            background: var(--primary);
            color: white;
            border-radius: 9px;
            padding: 13px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 17px;
            transition: .2s;
        }

        .checkout-btn:hover {
            background: var(--primary-dark);
        }

        .checkout-btn:disabled {
            background: #c6b8ae;
            cursor: not-allowed;
        }

        .clear-cart-btn {
            border: none;
            background: none;
            color: var(--danger);
            font-size: 11px;
            font-weight: 600;
        }

        /* MODAL */

        .modal-content {
            border: none;
            border-radius: 13px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--primary-dark);
            color: white;
            border: none;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .confirm-icon {
            width: 65px;
            height: 65px;
            margin: 10px auto 15px;
            background: #e8f4ed;
            color: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        .confirm-total {
            font-size: 27px;
            color: var(--primary);
            font-weight: 700;
        }

        /* RESPONSIVE */

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            z-index: 1040;
        }

        @media(max-width: 1200px) {
            .pos-container {
                grid-template-columns: minmax(0, 1fr) 340px;
            }

            .products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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

            .pos-container {
                grid-template-columns: 1fr;
            }

            .cart-panel {
                position: static;
            }
        }

        @media(max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }

            .page-heading h4 {
                font-size: 18px;
            }

            .profile-info {
                display: none;
            }

            .products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 9px;
            }

            .product-image {
                height: 95px;
                font-size: 38px;
            }

            .products-panel,
            .cart-panel {
                padding: 14px;
            }

            .product-details {
                padding: 9px;
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

            <a href="pos.php" class="nav-link active">
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
                    <h4>Point of Sale</h4>
                    <p>Process bakery orders and payments</p>
                </div>
            </div>
        </div>

        <div class="pos-container">

            <section class="products-panel">

                <div>
                    <h6 class="section-title">Select Products</h6>
                    <p class="section-subtitle">Choose bakery products to add to your cart.</p>
                </div>

                <div class="search-wrapper">
                    <i class="bi bi-search"></i>

                    <input
                        type="text"
                        id="productSearch"
                        class="search-input"
                        placeholder="Search product name...">
                </div>

                <div class="category-list">

                    <button class="category-btn active" data-category="all">
                        All Products
                    </button>

                    <?php if ($categories && $categories->num_rows > 0): ?>
                        <?php while ($category = $categories->fetch_assoc()): ?>

                            <button
                                class="category-btn"
                                data-category="<?= htmlspecialchars($category['category_name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($category['category_name']) ?>
                            </button>

                        <?php endwhile; ?>
                    <?php endif; ?>

                </div>
                <div class="products-grid" id="productsGrid">

                    <?php if ($products && $products->num_rows > 0): ?>

                        <?php while ($product = $products->fetch_assoc()): ?>

                            <?php
                            $stock = (int) $product['stock_quantity'];
                            $image = trim($product['product_image'] ?? '');
                            ?>

                            <div
                                class="product-card <?= $stock <= 0 ? 'disabled' : '' ?>"
                                data-id="<?= (int) $product['product_id'] ?>"
                                data-name="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                                data-category="<?= htmlspecialchars($product['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-price="<?= htmlspecialchars($product['price']) ?>"
                                data-stock="<?= $stock ?>">

                                <div class="product-image">

                                    <?php if ($image !== ''): ?>
                                        <img
                                            src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <span style="display:none;">🥐</span>
                                    <?php else: ?>
                                        🥐
                                    <?php endif; ?>

                                </div>

                                <div class="product-details">
                                    <div class="product-name">
                                        <?= htmlspecialchars($product['product_name']) ?>
                                    </div>

                                    <div class="product-category">
                                        <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
                                    </div>

                                    <div class="product-bottom">
                                        <div>
                                            <div class="product-price">
                                                ₱<?= number_format((float) $product['price'], 2) ?>
                                            </div>

                                            <div class="product-stock">
                                                <?php if ($stock <= 0): ?>
                                                    Out of stock
                                                <?php else: ?>
                                                    <?= $stock ?> pcs available
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="add-icon">
                                            <i class="bi bi-plus"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <div class="text-center py-4 <?= ($products && $products->num_rows > 0) ? 'd-none' : '' ?>" id="noProducts">
                    <i class="bi bi-search text-muted fs-3"></i>
                    <p class="text-muted small mt-2 mb-0">No products found.</p>
                </div>

            </section>

            <section class="cart-panel">

                <div class="cart-heading">

                    <div>
                        <h6 class="section-title mb-1">Current Order</h6>
                        <p class="section-subtitle mb-0">Add products to your cart</p>
                    </div>

                    <span class="cart-count" id="cartCount">0 Items</span>

                </div>

                <div class="cart-items" id="cartItems">

                    <div class="empty-cart" id="emptyCart">
                        <i class="bi bi-cart3"></i>
                        <strong>Your cart is empty</strong>
                        <small>Select products to start a new sale.</small>
                    </div>

                </div>

                <div class="cart-summary">

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong id="subtotal">₱0.00</strong>
                    </div>

                    <div class="summary-row total-row">
                        <span>Total Amount</span>
                        <strong id="total">₱0.00</strong>
                    </div>

                    <div class="payment-title">Payment Method</div>

                    <div class="payment-options">

                        <label class="payment-option active">
                            <input type="radio" name="paymentMethod" value="cash" checked>
                            <i class="bi bi-cash-stack"></i>
                            Cash
                        </label>

                        <label class="payment-option">
                            <input type="radio" name="paymentMethod" value="gcash">
                            <i class="bi bi-phone"></i>
                            GCash
                        </label>

                    </div>

                    <div class="payment-input d-none" id="gcashReferenceGroup">

                        <label for="transactionNo">GCash Reference Number</label>

                        <input
                            type="text"
                            id="transactionNo"
                            placeholder="Enter GCash reference number"
                            maxlength="100">

                    </div>

                    <div class="payment-input">

                        <label for="amountPaid">Amount Received</label>

                        <input
                            type="number"
                            id="amountPaid"
                            placeholder="Enter amount received"
                            min="0"
                            step="0.01">

                    </div>
                    <div class="change-box">
                        <span>
                            <i class="bi bi-wallet2 me-1"></i>
                            Change
                        </span>

                        <strong id="change">₱0.00</strong>
                    </div>
                    <button class="checkout-btn" id="checkoutBtn" disabled>
                        <i class="bi bi-check-circle me-2"></i>
                        Complete Sale
                    </button>

                    <button class="clear-cart-btn w-100 mt-3" id="clearCart">
                        <i class="bi bi-trash me-1"></i>
                        Clear Cart
                    </button>
                </div>
            </section>
        </div>
    </main>

    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">
                        Confirm Transaction
                    </h6>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body text-center p-4">
                    <div class="confirm-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>

                    <h5 class="fw-bold">Complete this sale?</h5>

                    <p class="text-muted small">
                        Please review the payment details before proceeding.
                    </p>

                    <div class="bg-light rounded-3 p-3 my-3 text-start">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">Total Amount</small>
                            <strong id="confirmTotal">₱0.00</strong>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">Amount Received</small>
                            <strong id="confirmPaid">₱0.00</strong>
                        </div>

                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Change</small>
                            <strong class="text-success" id="confirmChange">₱0.00</strong>
                        </div>
                    </div>
                    <button class="checkout-btn" id="confirmSale">
                        Confirm Sale
                    </button>
                    <button class="btn btn-light w-100 mt-2 btn-sm" data-bs-dismiss="modal">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        document.getElementById('menuToggle').addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        let cart = [];

        const money = amount => '₱' + amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[character]);
        }

        document.querySelectorAll('.product-card:not(.disabled)').forEach(card => {

            card.addEventListener('click', () => {

                const productId = Number(card.dataset.id);
                const name = card.dataset.name;
                const price = Number(card.dataset.price);
                const stock = Number(card.dataset.stock);

                if (stock <= 0) return;

                const existing = cart.find(item => item.product_id === productId);

                if (existing) {

                    if (existing.quantity >= stock) {
                        alert('Not enough available stock.');
                        return;
                    }

                    existing.quantity++;

                } else {

                    cart.push({
                        product_id: productId,
                        name: name,
                        price: price,
                        quantity: 1,
                        stock: stock
                    });

                }

                renderCart();

            });

        });

        function renderCart() {

            const cartItems = document.getElementById('cartItems');

            cartItems.innerHTML = '';

            if (cart.length === 0) {

                cartItems.innerHTML = `
            <div class="empty-cart" id="emptyCart">
                <i class="bi bi-cart3"></i>
                <strong>Your cart is empty</strong>
                <small>Select products to start a new sale.</small>
            </div>
        `;

            } else {

                cart.forEach((item, index) => {

                    const itemElement = document.createElement('div');

                    itemElement.className = 'cart-item';

                    itemElement.innerHTML = `
                <div>
                    <div class="cart-item-name">${escapeHtml(item.name)}</div>

                    <div class="cart-item-price">
                        ${money(item.price)} each
                    </div>

                    <div class="quantity-control">

                        <button type="button" onclick="changeQuantity(${index}, -1)">
                            <i class="bi bi-dash"></i>
                        </button>

                        <span>${item.quantity}</span>

                        <button type="button" onclick="changeQuantity(${index}, 1)">
                            <i class="bi bi-plus"></i>
                        </button>

                    </div>
                </div>

                <div class="cart-item-right">

                    <div class="cart-item-total">
                        ${money(item.price * item.quantity)}
                    </div>

                    <button type="button" class="remove-item" onclick="removeItem(${index})">
                        <i class="bi bi-trash"></i>
                    </button>

                </div>
            `;

                    cartItems.appendChild(itemElement);

                });

            }

            document.getElementById('cartCount').textContent =
                cart.reduce((sum, item) => sum + item.quantity, 0) + ' Items';

            updateTotals();

        }

        function changeQuantity(index, amount) {

            const item = cart[index];

            const newQuantity = item.quantity + amount;

            if (newQuantity <= 0) {
                removeItem(index);
                return;
            }

            if (newQuantity > item.stock) {
                alert('Not enough available stock.');
                return;
            }

            item.quantity = newQuantity;

            renderCart();

        }

        function removeItem(index) {

            cart.splice(index, 1);

            renderCart();

        }

        function getPaymentMethod() {
            return document.querySelector('input[name="paymentMethod"]:checked').value;
        }

        function updateTotals() {

            const subtotal = cart.reduce((sum, item) => {
                return sum + (item.price * item.quantity);
            }, 0);

            const total = subtotal;

            const paid = Number(document.getElementById('amountPaid').value) || 0;

            const change = Math.max(0, paid - total);

            document.getElementById('subtotal').textContent = money(subtotal);
            document.getElementById('total').textContent = money(total);
            document.getElementById('change').textContent = money(change);

            const checkoutBtn = document.getElementById('checkoutBtn');

            const transactionNo = document.getElementById('transactionNo').value.trim();
            const paymentMethod = getPaymentMethod();

            const validReference = paymentMethod !== 'gcash' || transactionNo !== '';

            checkoutBtn.disabled =
                cart.length === 0 ||
                total <= 0 ||
                paid < total ||
                !validReference;

            return {
                subtotal,
                total,
                paid,
                change
            };

        }

        document.getElementById('amountPaid').addEventListener('input', updateTotals);

        document.getElementById('transactionNo').addEventListener('input', updateTotals);
        document.querySelectorAll('.payment-option').forEach(option => {

            option.addEventListener('click', () => {

                document.querySelectorAll('.payment-option').forEach(item => {
                    item.classList.remove('active');
                });

                option.classList.add('active');

                option.querySelector('input').checked = true;

                const paymentMethod = getPaymentMethod();

                document.getElementById('gcashReferenceGroup')
                    .classList.toggle('d-none', paymentMethod !== 'gcash');

                updateTotals();

            });

        });
        document.getElementById('productSearch').addEventListener('input', filterProducts);

        let selectedCategory = 'all';

        document.querySelectorAll('.category-btn').forEach(button => {

            button.addEventListener('click', () => {

                document.querySelectorAll('.category-btn').forEach(btn => {
                    btn.classList.remove('active');
                });

                button.classList.add('active');

                selectedCategory = button.dataset.category;

                filterProducts();

            });

        });


        function filterProducts() {

            const search = document.getElementById('productSearch').value.toLowerCase();

            let visible = 0;

            document.querySelectorAll('.product-card').forEach(card => {

                const name = card.dataset.name.toLowerCase();
                const category = card.dataset.category;

                const matchesSearch = name.includes(search);

                const matchesCategory =
                    selectedCategory === 'all' || category === selectedCategory;

                const show = matchesSearch && matchesCategory;

                card.style.display = show ? '' : 'none';

                if (show) visible++;

            });

            document.getElementById('noProducts').classList.toggle('d-none', visible !== 0);

        }


        // CLEAR CART

        document.getElementById('clearCart').addEventListener('click', () => {

            if (cart.length === 0) return;

            if (confirm('Are you sure you want to clear the cart?')) {

                cart = [];

                document.getElementById('amountPaid').value = '';
                document.getElementById('transactionNo').value = '';

                renderCart();

            }

        });

        const checkoutModal = new bootstrap.Modal(
            document.getElementById('checkoutModal')
        );


        document.getElementById('checkoutBtn').addEventListener('click', () => {

            const totals = updateTotals();

            if (cart.length === 0 || totals.paid < totals.total || totals.total <= 0) {
                return;
            }

            document.getElementById('confirmTotal').textContent = money(totals.total);
            document.getElementById('confirmPaid').textContent = money(totals.paid);
            document.getElementById('confirmChange').textContent = money(totals.change);

            checkoutModal.show();

        });

        document.getElementById('confirmSale').addEventListener('click', async () => {

            const confirmButton = document.getElementById('confirmSale');

            if (cart.length === 0) return;

            const totals = updateTotals();

            if (totals.paid < totals.total || totals.total <= 0) {
                alert('Please check the amount received.');
                return;
            }

            const paymentMethod = getPaymentMethod();
            const transactionNo = document.getElementById('transactionNo').value.trim();

            if (paymentMethod === 'gcash' && transactionNo === '') {
                alert('Please enter the GCash reference number.');
                return;
            }

            confirmButton.disabled = true;
            confirmButton.textContent = 'Processing...';

            try {

                const response = await fetch('pos.php?action=process_sale', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        cart: cart.map(item => ({
                            product_id: item.product_id,
                            quantity: item.quantity
                        })),
                        payment_method: paymentMethod,
                        transaction_no: transactionNo,
                        amount_paid: totals.paid
                    })
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to complete sale.');
                }
                checkoutModal.hide();

                await Swal.fire({
                    icon: 'success',
                    title: 'Sale Completed!',
                    html: `
                            <p class="mb-2">The transaction was completed successfully.</p>
                            <div class="bg-light rounded-3 p-3 text-start">
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Receipt Number</small>
                                    <strong>${escapeHtml(result.receipt_number)}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Total Amount</small>
                                    <strong>${money(Number(result.total_amount))}</strong>
                                </div>
                            </div>
                        `,
                    confirmButtonText: 'Done',
                    confirmButtonColor: '#75452e'
                });

                cart = [];

                document.getElementById('amountPaid').value = '';
                document.getElementById('transactionNo').value = '';

                checkoutModal.hide();

                renderCart();
                window.location.reload();

            } catch (error) {

                Swal.fire({
                    icon: 'error',
                    title: 'Transaction Failed!',
                    text: error.message,
                    confirmButtonText: 'Okay',
                    confirmButtonColor: '#75452e'
                });

            } finally {

                confirmButton.disabled = false;
                confirmButton.textContent = 'Confirm Sale';

            }

        });
        renderCart();
    </script>

</body>

</html>