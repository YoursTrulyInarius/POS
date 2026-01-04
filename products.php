<?php
include 'db.php';

$message = "";

/**
 * Ensure products table exists (create when missing)
 */
function ensureProductsTable($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'products'");
    if ($check === false) return false;
    if ($check->num_rows === 0) {
        $create = "
        CREATE TABLE products (
          product_id INT AUTO_INCREMENT PRIMARY KEY,
          product_name VARCHAR(100) NOT NULL,
          description TEXT,
          price DECIMAL(10,2) NOT NULL,
          stock INT NOT NULL DEFAULT 0,
          image VARCHAR(255),
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($create)) {
            return $conn->error;
        }
    }
    return true;
}

/**
 * Ensure image column exists (if table exists but column missing)
 */
function ensureImageColumn($conn) {
    $col = $conn->query("SHOW COLUMNS FROM products LIKE 'image'");
    if ($col === false) return $conn->error;
    if ($col->num_rows === 0) {
        $alter = "ALTER TABLE products ADD COLUMN image VARCHAR(255) NULL AFTER stock";
        if (!$conn->query($alter)) {
            return $conn->error;
        }
    }
    return true;
}

// Attempt to ensure table + image column exist (helps avoid prepare() failing)
$tbl_ok = ensureProductsTable($conn);
if ($tbl_ok !== true) {
    $message = "Database error creating products table: " . htmlspecialchars($tbl_ok);
}
$img_ok = ensureImageColumn($conn);
if ($img_ok !== true) {
    $message = "Database error altering products table: " . htmlspecialchars($img_ok);
}

// Handle form submit safely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name'])) {
    // basic validation & sanitization
    $product_name = trim($_POST['product_name']);
    $description  = trim($_POST['description'] ?? '');
    $price_raw    = $_POST['price'] ?? '0';
    $stock_raw    = $_POST['stock'] ?? '0';

    $price = is_numeric($price_raw) ? (float)$price_raw : 0.00;
    $stock = is_numeric($stock_raw) ? (int)$stock_raw : 0;

    // handle image upload (optional)
    $image_path = "";
    if (!empty($_FILES['image']['name'])) {
        $targetDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // sanitize filename and make it unique
        $originalName = basename($_FILES['image']['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $finalName = time() . "_" . bin2hex(random_bytes(4)) . "_" . $safeBase . ($ext ? "." . $ext : "");
        $finalPath = $targetDir . DIRECTORY_SEPARATOR . $finalName;

        if (is_uploaded_file($_FILES['image']['tmp_name'])) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $finalPath)) {
                // store path relative to web root for display (uploads/...)
                $image_path = 'uploads/' . $finalName;
            } else {
                $message = "Warning: failed to move uploaded file.";
            }
        } else {
            $message = "Warning: upload failed or no file uploaded.";
        }
    }

    // Use prepared statement, but first check prepare result to avoid fatal
    $sql = "INSERT INTO products (product_name, description, price, stock, image) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        // prepare failed — report error but do not cause fatal
        $message = "Database prepare failed: " . htmlspecialchars($conn->error);
    } else {
        // Bind params: product_name (s), description (s), price (d), stock (i), image (s)
        // ensure image_path is string (empty string if no image)
        $image_to_store = $image_path ?: "";
        $stmt->bind_param("ssdis", $product_name, $description, $price, $stock, $image_to_store);

        if ($stmt->execute()) {
            // success — redirect to avoid duplicate submissions
            $stmt->close();
            header("Location: products.php?added=1");
            exit();
        } else {
            $message = "Insert failed: " . htmlspecialchars($stmt->error);
            $stmt->close();
        }
    }
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && isset($_POST['selected_products'])) {
    $selected = $_POST['selected_products'];
    if (!empty($selected)) {
        $placeholders = str_repeat('?,', count($selected) - 1) . '?';
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param(str_repeat('i', count($selected)), ...$selected);
            if ($stmt->execute()) {
                $message = "Successfully deleted " . count($selected) . " product(s)!";
            } else {
                $message = "Error deleting products: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// If redirected after add
$justAdded = isset($_GET['added']) && $_GET['added'] == '1';
if ($justAdded) {
    $message = "Product added successfully!";
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(product_name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

if ($stock_filter === 'low') {
    $where_conditions[] = "stock <= 10";
} elseif ($stock_filter === 'out') {
    $where_conditions[] = "stock = 0";
} elseif ($stock_filter === 'high') {
    $where_conditions[] = "stock > 50";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Validate sort parameters
$valid_sort_columns = ['product_id', 'product_name', 'price', 'stock', 'created_at'];
$sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'created_at';
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// Count total products for pagination
$count_sql = "SELECT COUNT(*) as total FROM products $where_sql";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_products = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_products = $conn->query($count_sql)->fetch_assoc()['total'];
}

// Pagination
$per_page = 10;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;
$total_pages = ceil($total_products / $per_page);

// Fetch products with filters and pagination
$sql = "SELECT * FROM products $where_sql ORDER BY $sort_by $sort_order LIMIT $per_page OFFSET $offset";
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $products = $stmt->get_result();
    $stmt->close();
} else {
    $products = $conn->query($sql);
}

// Get inventory statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(stock) as total_stock,
        AVG(price) as avg_price,
        COUNT(CASE WHEN stock <= 10 THEN 1 END) as low_stock_count
    FROM products
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Products Management - Advanced</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --primary-light: #e8f0fe;
            --secondary-color: #fff;
            --text-color: #333;
            --bg-color: #f4f6f9;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }

        .theme-red {
            --primary-color: #dc3545;
            --primary-dark: #c82333;
            --primary-light: #f8d7da;
            --bg-color: #fff5f5;
        }

        .theme-green {
            --primary-color: #28a745;
            --primary-dark: #218838;
            --primary-light: #d4edda;
            --bg-color: #f8fff9;
        }

        .theme-purple {
            --primary-color: #6f42c1;
            --primary-dark: #59359a;
            --primary-light: #e2d9f3;
            --bg-color: #faf9fc;
        }

        .theme-orange {
            --primary-color: #fd7e14;
            --primary-dark: #e8690b;
            --primary-light: #ffeeba;
            --bg-color: #fffaf3;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-content h1 {
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header-content p {
            color: #666;
            font-size: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        .btn-success {
            background: var(--success);
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.products::before {
            background: linear-gradient(90deg, var(--primary-color), var(--info));
        }

        .stat-card.stock::before {
            background: linear-gradient(90deg, var(--success), #20c997);
        }

        .stat-card.price::before {
            background: linear-gradient(90deg, var(--warning), #fd7e14);
        }

        .stat-card.alerts::before {
            background: linear-gradient(90deg, var(--danger), #e74c3c);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            font-size: 24px;
            opacity: 0.7;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-subtitle {
            font-size: 14px;
            color: #666;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-title {
            color: var(--dark);
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 25px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left-color: var(--success);
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: var(--danger);
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .form-control {
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        /* Filter & Search */
        .filters-section {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 15px;
            align-items: end;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 45px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .search-box::before {
            content: "🔍";
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            z-index: 1;
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            transition: border-color 0.3s ease;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 18px 15px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            user-select: none;
            position: relative;
            transition: background 0.3s ease;
        }

        thead th:hover {
            background: var(--primary-dark);
        }

        thead th.sortable::after {
            content: "↕";
            position: absolute;
            right: 8px;
            opacity: 0.5;
            font-size: 12px;
        }

        thead th.sorted-asc::after {
            content: "↑";
            opacity: 1;
        }

        thead th.sorted-desc::after {
            content: "↓";
            opacity: 1;
        }

        tbody td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }

        .product-name {
            font-weight: 600;
            color: var(--dark);
        }

        .product-price {
            font-weight: 600;
            color: var(--success);
            font-size: 16px;
        }

        .stock-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            min-width: 70px;
            text-align: center;
        }

        .stock-high {
            background: #d4edda;
            color: #155724;
        }

        .stock-medium {
            background: #fff3cd;
            color: #856404;
        }

        .stock-low {
            background: #f8d7da;
            color: #721c24;
        }

        .product-description {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Bulk Actions */
        .bulk-actions {
            display: none;
            padding: 15px 20px;
            background: var(--primary-light);
            border-bottom: 1px solid #e9ecef;
            align-items: center;
            gap: 15px;
        }

        .bulk-actions.active {
            display: flex;
        }

        .bulk-counter {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Pagination */
        .pagination {
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        .pagination-info {
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }

        .pagination-buttons {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pagination-buttons a,
        .pagination-buttons span {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            background: white;
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 14px;
            min-width: 40px;
            text-align: center;
        }

        .pagination-buttons a:hover {
            background: var(--primary-color);
            color: white;
        }

        .pagination-buttons .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 25px;
        }

        .close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 5px 10px;
            border-radius: 50%;
            transition: 0.3s;
        }

        .close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .table-responsive {
                font-size: 12px;
            }
            
            thead th, tbody td {
                padding: 10px 8px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .pagination-buttons {
                gap: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <h1>Products Management</h1>
                <p>Advanced inventory management with enhanced features</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn">← Return to Dashboard</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-header">
                    <div class="stat-title">Total Products</div>
                    <div class="stat-icon">📦</div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_products'] ?? 0) ?></div>
                <div class="stat-subtitle">Items in inventory</div>
            </div>

            <div class="stat-card stock">
                <div class="stat-header">
                    <div class="stat-title">Total Stock</div>
                    <div class="stat-icon">📊</div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_stock'] ?? 0) ?></div>
                <div class="stat-subtitle">Units available</div>
            </div>

            <div class="stat-card price">
                <div class="stat-header">
                    <div class="stat-title">Average Price</div>
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-value">₱<?= number_format($stats['avg_price'] ?? 0, 2) ?></div>
                <div class="stat-subtitle">Per product</div>
            </div>

            <div class="stat-card alerts">
                <div class="stat-header">
                    <div class="stat-title">Low Stock Alert</div>
                    <div class="stat-icon">⚠️</div>
                </div>
                <div class="stat-value"><?= number_format($stats['low_stock_count'] ?? 0) ?></div>
                <div class="stat-subtitle">Items need restocking</div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="message <?php echo $justAdded ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Add New Product</h2>
                <button type="button" class="btn" onclick="toggleAddForm()">
                    <span id="toggleText">Hide Form</span>
                </button>
            </div>
            <div class="card-body" id="addProductForm">
                <form method="post" enctype="multipart/form-data" novalidate>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="product_name">Product Name *</label>
                            <input type="text" id="product_name" name="product_name" class="form-control" placeholder="Enter product name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price *</label>
                            <input type="number" id="price" name="price" step="0.01" class="form-control" placeholder="0.00" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock">Stock Quantity *</label>
                            <input type="number" id="stock" name="stock" class="form-control" placeholder="0" value="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="image">Product Image</label>
                            <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" placeholder="Enter product description (optional)"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="products.php" class="btn btn-secondary">Reset Form</a>
                        <button type="submit" class="btn btn-success">Add Product</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Product List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Product Inventory</h2>
            </div>
            
            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" id="filterForm">
                    <div class="filters-grid">
                        <div class="search-box">
                            <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <select name="stock" class="filter-select">
                            <option value="">All Stock Levels</option>
                            <option value="high" <?= $stock_filter === 'high' ? 'selected' : '' ?>>High Stock (>50)</option>
                            <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Low Stock (≤10)</option>
                            <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                        
                        <select name="sort" class="filter-select">
                            <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Sort by Date</option>
                            <option value="product_name" <?= $sort_by === 'product_name' ? 'selected' : '' ?>>Sort by Name</option>
                            <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Sort by Price</option>
                            <option value="stock" <?= $sort_by === 'stock' ? 'selected' : '' ?>>Sort by Stock</option>
                        </select>
                        
                        <select name="order" class="filter-select">
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions Bar -->
            <!-- Removed bulk actions section -->
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="product_id">ID</th>
                            <th>Image</th>
                            <th class="sortable" data-sort="product_name">Product Name</th>
                            <th>Description</th>
                            <th class="sortable" data-sort="price">Price</th>
                            <th class="sortable" data-sort="stock">Stock</th>
                            <th class="sortable" data-sort="created_at">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products && $products->num_rows > 0): ?>
                            <?php while ($r = $products->fetch_assoc()):
                                $stock = (int)$r['stock'];
                                $stockClass = $stock == 0 ? 'stock-low' : ($stock <= 10 ? 'stock-low' : ($stock > 50 ? 'stock-high' : 'stock-medium'));
                            ?>
                            <tr>
                                <td><strong><?= (int)$r['product_id'] ?></strong></td>
                                <td>
                                    <?php
                                    if (!empty($r['image']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $r['image'])) {
                                        echo '<img src="'.htmlspecialchars($r['image']).'" alt="Product Image" class="product-image" onclick="showImageModal(\''.htmlspecialchars($r['image']).'\', \''.htmlspecialchars($r['product_name']).'\')">';
                                    } elseif (!empty($r['image'])) {
                                        echo '<div style="width:60px;height:60px;background:#e9ecef;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#6c757d;">No Image</div>';
                                    } else {
                                        echo '<div style="width:60px;height:60px;background:#e9ecef;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;color:#6c757d;">—</div>';
                                    }
                                    ?>
                                </td>
                                <td class="product-name"><?= htmlspecialchars($r['product_name']) ?></td>
                                <td>
                                    <div class="product-description" title="<?= htmlspecialchars($r['description']) ?>">
                                        <?= $r['description'] ? htmlspecialchars($r['description']) : '—' ?>
                                    </div>
                                </td>
                                <td class="product-price">₱<?= number_format((float)$r['price'], 2) ?></td>
                                <td>
                                    <span class="stock-badge <?= $stockClass ?>">
                                        <?= $stock ?> units
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <div class="icon">📦</div>
                                    <div>No products found</div>
                                    <div style="font-size: 14px; margin-top: 5px;">
                                        <?= !empty($search) ? 'Try adjusting your search or filters' : 'Add your first product using the form above' ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?= ($offset + 1) ?> to <?= min($offset + $per_page, $total_products) ?> of <?= $total_products ?> products
                </div>
                <div class="pagination-buttons">
                    <?php if ($current_page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">First</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $current_page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>">Next</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">Last</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="imageModalTitle">Product Image</h3>
                <button class="close" onclick="closeImageModal()">&times;</button>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Product Image" style="width: 100%; height: auto; border-radius: 8px;">
            </div>
        </div>
    </div>

    <script>
        // Auto-submit filters on change
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('selectedTheme') || 'blue';
            if (savedTheme !== 'blue') {
                document.body.classList.add('theme-' + savedTheme);
            }

            const filterInputs = document.querySelectorAll('#filterForm input, #filterForm select');
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            });

            // Auto-submit search with debounce
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 500);
            });
        });

        // Remove bulk operations and export functions
        
        let bulkMode = false;

        function toggleAddForm() {
            const form = document.getElementById('addProductForm');
            const toggleText = document.getElementById('toggleText');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                toggleText.textContent = 'Hide Form';
            } else {
                form.style.display = 'none';
                toggleText.textContent = 'Show Form';
            }
        }

        function showImageModal(imageSrc, productName) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModalTitle').textContent = productName;
            document.getElementById('imageModal').style.display = 'block';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }

        // Sort table functionality
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                const currentSort = new URLSearchParams(window.location.search).get('sort');
                const currentOrder = new URLSearchParams(window.location.search).get('order');
                
                let newOrder = 'ASC';
                if (currentSort === sortBy && currentOrder === 'ASC') {
                    newOrder = 'DESC';
                }
                
                const params = new URLSearchParams(window.location.search);
                params.set('sort', sortBy);
                params.set('order', newOrder);
                params.set('page', '1'); 
                
                window.location.search = params.toString();
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const currentSort = new URLSearchParams(window.location.search).get('sort') || 'created_at';
            const currentOrder = new URLSearchParams(window.location.search).get('order') || 'DESC';
            
            document.querySelectorAll('.sortable').forEach(header => {
                if (header.dataset.sort === currentSort) {
                    header.classList.add(currentOrder === 'ASC' ? 'sorted-asc' : 'sorted-desc');
                }
            });
        });
    </script>
</body>
</html>