<?php
session_start();
include "../../conn.php";

if (isset($_POST['save_category'])) {
    $category_name = $_POST['category_name'];

    $insert = $conn->prepare('INSERT INTO `category`(`category_name`) VALUES (?)');
    $insert->bind_param("s", $category_name);

    if ($insert->execute()) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Inserted!',
            'text' => 'New Category inserted successfully!'
        ];
        header("location: ../categories.php");
        exit();
    }
}

// UPDATE CATEGORY
if (isset($_POST['update_category'])) {

    $category_id = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);

    if ($category_id > 0 && !empty($category_name)) {

        $update = $conn->prepare("
            UPDATE category 
            SET category_name = ?
            WHERE category_id = ?
        ");

        $update->bind_param("si", $category_name, $category_id);

        if ($update->execute()) {
            $_SESSION['swal'] = [
                'icon' => 'success',
                'title' => 'Category Updated!',
                'text' => 'Category has been successfully updated.'
            ];
        } else {
            $_SESSION['swal'] = [
                'icon' => 'error',
                'title' => 'Update Failed!',
                'text' => 'Unable to update category.'
            ];
        }
    }

    header("Location: ../categories.php");
    exit();
}

if (isset($_POST['delete_category'])) {

    $category_id = intval($_POST['category_id']);

    // CHECK IF CATEGORY HAS PRODUCTS
    $check = $conn->prepare("
        SELECT COUNT(*) AS total_products
        FROM products
        WHERE category_id = ?
    ");

    $check->bind_param("i", $category_id);
    $check->execute();

    $result = $check->get_result()->fetch_assoc();

    if ($result['total_products'] > 0) {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Cannot Delete Category!',
            'text' => 'This category already has products. Remove or reassign its products first.'
        ];
    } else {

        $delete = $conn->prepare("
            DELETE FROM category
            WHERE category_id = ?
        ");

        $delete->bind_param("i", $category_id);

        if ($delete->execute()) {

            $_SESSION['swal'] = [
                'icon' => 'success',
                'title' => 'Category Deleted!',
                'text' => 'Category has been successfully deleted.'
            ];
        } else {

            $_SESSION['swal'] = [
                'icon' => 'error',
                'title' => 'Delete Failed!',
                'text' => 'Unable to delete category.'
            ];
        }
    }

    header("Location: ../categories.php");
    exit();
}

if (isset($_POST['save_product'])) {

    $product_name = $_POST['product_name'];
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $stock_quantity = 0;
    $stock_limit = $_POST['stock_limit'];
    $status = 'Active';
    $product_image = $_FILES['product_image'];

    // Product Image Upload
    $image_name = NULL;

    if (!empty($product_image['name'])) {

        $upload_directory = "../uploads/products/";

        if (!is_dir($upload_directory)) {
            mkdir($upload_directory, 0777, true);
        }

        $image_extension = strtolower(pathinfo($product_image['name'], PATHINFO_EXTENSION));
        $image_name = uniqid('product_') . '.' . $image_extension;

        move_uploaded_file(
            $product_image['tmp_name'],
            $upload_directory . $image_name
        );

        $image_name = "uploads/products/" . $image_name;
    }

    // Insert Product
    $stmt = $conn->prepare("
        INSERT INTO products
        (category_id, product_name, price, stock_quantity, stock_limit, product_image, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isdiiss",
        $category_id,
        $product_name,
        $price,
        $stock_quantity,
        $stock_limit,
        $image_name,
        $status
    );

    if ($stmt->execute()) {
        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Product Added!',
            'text' => 'Product has been added successfully.'
        ];
    } else {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error!',
            'text' => 'Failed to add product.'
        ];
    }

    header("Location: ../products.php");
    exit();
}

if (isset($_POST['update_product'])) {

    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $category_id = $_POST['category_id'];
    $price = $_POST['price'];
    $stock_limit = $_POST['stock_limit'];
    $status = $_POST['status'];
    $product_image = $_FILES['product_image'];

    // Get Current Product Image
    $query = $conn->prepare("SELECT product_image FROM products WHERE product_id = ?");
    $query->bind_param("i", $product_id);
    $query->execute();
    $result = $query->get_result();
    $current_product = $result->fetch_assoc();

    $image_name = $current_product['product_image'];

    // Upload New Image
    if (!empty($product_image['name'])) {

        $upload_directory = "../uploads/products/";

        if (!is_dir($upload_directory)) {
            mkdir($upload_directory, 0777, true);
        }

        $image_extension = strtolower(pathinfo($product_image['name'], PATHINFO_EXTENSION));
        $new_image_name = uniqid('product_') . '.' . $image_extension;

        if (move_uploaded_file($product_image['tmp_name'], $upload_directory . $new_image_name)) {

            // Delete Previous Image
            if (!empty($image_name) && file_exists("../" . $image_name)) {
                unlink("../" . $image_name);
            }

            $image_name = "uploads/products/" . $new_image_name;
        }
    }

    // Update Product
    $stmt = $conn->prepare("
        UPDATE products
        SET category_id = ?,
            product_name = ?,
            price = ?,
            stock_limit = ?,
            product_image = ?,
            status = ?
        WHERE product_id = ?
    ");

    $stmt->bind_param(
        "isdissi",
        $category_id,
        $product_name,
        $price,
        $stock_limit,
        $image_name,
        $status,
        $product_id
    );

    if ($stmt->execute()) {

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Product Updated!',
            'text' => 'Product information has been updated successfully.'
        ];
    } else {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Update Failed!',
            'text' => 'Unable to update product information.'
        ];
    }

    header("Location: ../products.php");
    exit();
}

if (isset($_POST['add_stock'])) {

    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $remarks = trim($_POST['remarks'] ?? '');
    $user_id = (int)$_SESSION['user_id'];

    if ($product_id <= 0 || $quantity <= 0) {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Invalid Quantity',
            'text' => 'Please enter a valid quantity.'
        ];

        header("Location: ../products.php");
        exit;
    }

    $conn->begin_transaction();

    try {

        // GET CURRENT STOCK
        $stmt = $conn->prepare("
            SELECT stock_quantity
            FROM products
            WHERE product_id = ?
            FOR UPDATE
        ");

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new Exception("Product not found.");
        }

        $previous_stock = (int)$product['stock_quantity'];
        $new_stock = $previous_stock + $quantity;

        // UPDATE PRODUCT STOCK
        $stmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = ?
            WHERE product_id = ?
        ");

        $stmt->bind_param("ii", $new_stock, $product_id);
        $stmt->execute();

        // INSERT STOCK MOVEMENT
        $movement_type = "Stock In";

        $stmt = $conn->prepare("
            INSERT INTO stock_movements
            (
                product_id,
                user_id,
                movement_type,
                quantity,
                previous_stock,
                new_stock,
                remarks,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "iisiiis",
            $product_id,
            $user_id,
            $movement_type,
            $quantity,
            $previous_stock,
            $new_stock,
            $remarks
        );

        $stmt->execute();

        $conn->commit();

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Stock Added!',
            'text' => 'Product quantity has been updated successfully.'
        ];
    } catch (Exception $e) {

        $conn->rollback();

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Failed!',
            'text' => $e->getMessage()
        ];
    }

    header("Location: ../products.php");
    exit;
}
// STOCK OUT
if (isset($_POST['stock_out'])) {

    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $remarks = trim($_POST['remarks'] ?? '');
    $user_id = (int)$_SESSION['user_id'];

    $allowedReasons = [
        'Damaged Product',
        'Expired Product',
        'Spoiled Product',
        'Missing or Lost',
        'Staff Consumption',
        'Free Samples / Giveaways',
        'Other'
    ];

    if (
        $product_id <= 0 ||
        $quantity <= 0 ||
        !in_array($remarks, $allowedReasons, true)
    ) {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Invalid Details',
            'text' => 'Please enter valid stock out details.'
        ];

        header("Location: ../products.php");
        exit;
    }

    $conn->begin_transaction();

    try {

        // GET CURRENT STOCK
        $stmt = $conn->prepare("
            SELECT stock_quantity
            FROM products
            WHERE product_id = ?
            FOR UPDATE
        ");

        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new Exception("Product not found.");
        }

        $previous_stock = (int)$product['stock_quantity'];

        if ($quantity > $previous_stock) {
            throw new Exception("Insufficient stock. Available stock: " . $previous_stock);
        }

        $new_stock = $previous_stock - $quantity;

        // UPDATE PRODUCT STOCK
        $stmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = ?
            WHERE product_id = ?
        ");

        $stmt->bind_param("ii", $new_stock, $product_id);
        $stmt->execute();

        // INSERT STOCK MOVEMENT
        $movement_type = "Stock Out";

        $stmt = $conn->prepare("
            INSERT INTO stock_movements
            (
                product_id,
                user_id,
                movement_type,
                quantity,
                previous_stock,
                new_stock,
                remarks,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "iisiiis",
            $product_id,
            $user_id,
            $movement_type,
            $quantity,
            $previous_stock,
            $new_stock,
            $remarks
        );

        $stmt->execute();

        $conn->commit();

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Stock Removed!',
            'text' => 'Product quantity has been deducted successfully.'
        ];
    } catch (Exception $e) {

        $conn->rollback();

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Stock Out Failed!',
            'text' => $e->getMessage()
        ];
    }

    header("Location: ../products.php");
    exit;
}

if (isset($_POST['delete_product'])) {

    $product_id = intval($_POST['product_id']);

    if ($product_id <= 0) {
        header("Location: ../products.php");
        exit();
    }

    // CHECK IF PRODUCT HAS STOCK MOVEMENT HISTORY
    $check = $conn->prepare("
        SELECT COUNT(*) AS total_movements
        FROM stock_movements
        WHERE product_id = ?
    ");

    $check->bind_param("i", $product_id);
    $check->execute();

    $movementResult = $check->get_result()->fetch_assoc();

    if ((int)$movementResult['total_movements'] > 0) {

        $_SESSION['swal'] = [
            'icon' => 'warning',
            'title' => 'Cannot Delete Product!',
            'text' => 'This product has stock movement history. Keep it in the system so its inventory records are preserved. You can set its status to Inactive instead.'
        ];

        header("Location: ../products.php");
        exit();
    }

    // GET IMAGE
    $getImage = $conn->prepare("
        SELECT product_image
        FROM products
        WHERE product_id = ?
    ");

    $getImage->bind_param("i", $product_id);
    $getImage->execute();

    $product = $getImage->get_result()->fetch_assoc();

    // DELETE PRODUCT
    $delete = $conn->prepare("
        DELETE FROM products
        WHERE product_id = ?
    ");

    $delete->bind_param("i", $product_id);

    if ($delete->execute() && $delete->affected_rows > 0) {

        if (!empty($product['product_image'])) {
            $imagePath = "../" . $product['product_image'];

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Product Deleted!',
            'text' => 'Product has been successfully deleted.'
        ];
    } else {

        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Delete Failed!',
            'text' => 'Unable to delete this product.'
        ];
    }

    header("Location: ../products.php");
    exit();
}
