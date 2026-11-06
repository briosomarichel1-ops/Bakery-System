<?php
session_start();
include "../conn.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories | Bakery Sales & Inventory</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        /* PAGE CONTENT */

        .content-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 21px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .content-header h5 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .content-header p {
            color: var(--muted);
            font-size: 12px;
            margin: 0;
        }

        .btn-add {
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-add:hover {
            background: #c8793e;
            color: white;
        }

        .search-box {
            position: relative;
            max-width: 300px;
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

        .search-box input:focus,
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(216, 137, 76, .12);
        }

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
            padding: 15px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f3eee9;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .category-icon {
            width: 38px;
            height: 38px;
            background: #f7eee5;
            color: var(--primary);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .category-name {
            font-weight: 700;
            color: var(--text);
        }

        .category-description {
            font-size: 11px;
            color: var(--muted);
        }

        .count-badge {
            background: #f4e9df;
            color: var(--primary);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-badge {
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-active {
            background: #e8f4ed;
            color: #478765;
        }

        .status-inactive {
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
            margin-right: 4px;
        }

        .action-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .action-btn.delete:hover {
            background: #faece9;
            color: var(--danger);
        }

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

        .form-label {
            font-size: 12px;
            font-weight: 600;
        }

        .form-control,
        .form-select {
            font-size: 12px;
            padding: 10px 12px;
            border-color: var(--border);
        }

        .btn-save {
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 7px;
            padding: 9px 17px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-save:hover {
            background: #c8793e;
            color: white;
        }

        .btn-cancel {
            background: #f3eee9;
            color: var(--text);
            border: none;
            border-radius: 7px;
            padding: 9px 17px;
            font-size: 12px;
            font-weight: 600;
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

            <a href="categories.php" class="nav-link active">
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
                    <h4>Categories</h4>
                    <p>Manage your bakery product categories</p>
                </div>
            </div>

        </div>

        <div class="content-card">
            <div class="content-header">
                <div>
                    <h5>Product Categories</h5>
                    <p>Organize your bakery products by category.</p>
                </div>

                <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-lg me-1"></i>
                    Add Category
                </button>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchCategory" placeholder="Search categories...">
                </div>
                <small class="text-muted" style="font-size:11px;">
                    Total Categories: <strong id="categoryCount">5</strong>
                </small>
            </div>

            <div class="table-responsive">
                <table class="table" id="categoryTable">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Products</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTableBody">

                        <?php
                        $display = $conn->prepare("
                                    SELECT 
                                        category.*,
                                        COUNT(products.product_id) AS total_products
                                    FROM category
                                    LEFT JOIN products ON category.category_id = products.category_id
                                    GROUP BY category.category_id
                                    ORDER BY category.category_name ASC
                                ");
                        $display->execute();
                        $result = $display->get_result();
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {

                        ?>

                                <tr data-id="<?= $row['category_id'] ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="category-name">
                                                <?= htmlspecialchars($row['category_name']) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="count-badge">
                                            <?= $row['total_products'] ?>
                                            <?= $row['total_products'] == 1 ? 'Product' : 'Products' ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <button
                                            class="action-btn"
                                            onclick="editCategory(<?= $row['category_id'] ?>, <?= htmlspecialchars(json_encode($row['category_name']), ENT_QUOTES, 'UTF-8') ?>)"
                                            title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <button
                                            class="action-btn delete"
                                            onclick="deleteCategory(<?= $row['category_id'] ?>, <?= htmlspecialchars(json_encode($row['category_name']), ENT_QUOTES, 'UTF-8') ?>)"
                                            title="<?= $row['total_products'] > 0 ? 'Cannot delete: category has products' : 'Delete' ?>"
                                            <?= $row['total_products'] > 0 ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo "<td colspan='3' class='text-center'>No Category yet.</td>";
                        }
                        ?>
                    </tbody>

                </table>

                <div id="noResults" class="empty-state d-none">
                    <i class="bi bi-search"></i>
                    No categories found.
                </div>
            </div>
        </div>
        <div class="text-center mt-4 mb-2">
            <small class="text-muted" style="font-size:11px;">
                © 2026 Crumb & Co. Bakery Sales & Inventory System.
                All rights reserved.
            </small>
        </div>
    </main>

    <!-- ADD CATEGORY MODAL -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <form action="actions/crud_operations.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-control" name="category_name" id="addCategoryName" placeholder="Enter category name" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_category" class="btn btn-save">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT CATEGORY MODAL -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <form action="actions/crud_operations.php" method="POST">

                    <div class="modal-header">
                        <h5 class="modal-title">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <input type="hidden" name="category_id" id="editCategoryId">

                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input
                                type="text"
                                class="form-control"
                                name="category_name"
                                id="editCategoryName"
                                placeholder="Enter category name"
                                required>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" name="update_category" class="btn btn-save">
                            Update Category
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

        document.getElementById('searchCategory').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#categoryTableBody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const matches = row.textContent.toLowerCase().includes(searchValue);
                row.style.display = matches ? '' : 'none';

                if (matches) visibleCount++;
            });

            document.getElementById('noResults').classList.toggle('d-none', visibleCount !== 0);
        });

        function updateCategoryCount() {
            const count = document.querySelectorAll('#categoryTableBody tr').length;
            document.getElementById('categoryCount').textContent = count;
        }

        function editCategory(id, name) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;

            new bootstrap.Modal(
                document.getElementById('editCategoryModal')
            ).show();
        }

        function deleteCategory(id, name) {

            Swal.fire({
                title: 'Delete Category?',
                text: `Are you sure you want to delete "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#cf6656',
                cancelButtonColor: '#95877d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {

                if (result.isConfirmed) {

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'actions/crud_operations.php';

                    const categoryId = document.createElement('input');
                    categoryId.type = 'hidden';
                    categoryId.name = 'category_id';
                    categoryId.value = id;

                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_category';
                    deleteInput.value = '1';

                    form.appendChild(categoryId);
                    form.appendChild(deleteInput);

                    document.body.appendChild(form);
                    form.submit();
                }

            });
        }
    </script>

    <?php if (isset($_SESSION['swal'])): ?>
        <script>
            Swal.fire({
                icon: <?= json_encode($_SESSION['swal']['icon']); ?>,
                title: <?= json_encode($_SESSION['swal']['title']); ?>,
                text: <?= json_encode($_SESSION['swal']['text']); ?>
            });
        </script>
    <?php
        unset($_SESSION['swal']);
    endif;
    ?>
</body>

</html>