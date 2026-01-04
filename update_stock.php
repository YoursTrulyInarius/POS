<?php
include "db.php";

$message = "";
$id = $_GET['id'];
$res = $conn->query("SELECT * FROM products WHERE product_id=$id");
$product = $res->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_product'])) {
        // Update product information
        $name = trim($_POST['product_name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        
        $stmt = $conn->prepare("UPDATE products SET product_name=?, description=?, price=? WHERE product_id=?");
        $stmt->bind_param("ssdi", $name, $description, $price, $id);
        
        if ($stmt->execute()) {
            $message = "✅ Product information updated successfully!";
            // Refresh product data
            $res = $conn->query("SELECT * FROM products WHERE product_id=$id");
            $product = $res->fetch_assoc();
        } else {
            $message = "❌ Error updating product information.";
        }
    } elseif (isset($_POST['update_stock'])) {
        // Update stock quantity
        $qty = intval($_POST['quantity']);

        // Update stock in products
        $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id=?");
        $stmt->bind_param("ii", $qty, $id);
        $stmt->execute();

        // Update inventory table
        $stmt = $conn->prepare("INSERT INTO inventory (product_id, quantity, last_updated) 
                                VALUES (?, ?, NOW())
                                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), last_updated = NOW()");
        $stmt->bind_param("ii", $id, $qty);
        $stmt->execute();

        $message = "✅ Stock updated successfully!";
        // Refresh product data
        $res = $conn->query("SELECT * FROM products WHERE product_id=$id");
        $product = $res->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Update Product - <?= htmlspecialchars($product['product_name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Theme System */
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
    --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
    color: #333;
    line-height: 1.6;
    padding: 20px;
    transition: all 0.3s ease;
}

.container {
    max-width: 800px;
    margin: 0 auto;
}

.header {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
    text-align: center;
}

.header h1 {
    color: var(--primary-color);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
}

.header p {
    color: #666;
    font-size: 16px;
}

.message {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
    text-align: center;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid var(--success);
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid var(--danger);
}

.card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: var(--card-shadow);
}

.card-header {
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.card-title {
    color: var(--primary-color);
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 5px;
}

.card-subtitle {
    color: #666;
    font-size: 14px;
}

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

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
    min-width: 140px;
}

.btn:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-success {
    background: var(--success);
}

.btn-success:hover {
    background: #218838;
}

.btn-info {
    background: var(--info);
}

.btn-info:hover {
    background: #138496;
}

.btn-secondary {
    background: #6c757d;
}

.btn-secondary:hover {
    background: #545b62;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.current-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: var(--dark);
}

.info-value {
    color: #666;
}

.stock-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
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

.back-link {
    text-align: center;
    margin-top: 20px;
}

.back-link a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.back-link a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .container {
        padding: 15px;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Update Product</h1>
        <p>Modify product information and manage stock levels</p>
    </div>
    <?php if ($message): ?>
    <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <!-- Current Product Information -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Current Product Information</div>
            <div class="card-subtitle">Product ID: <?= $product['product_id'] ?></div>
        </div>
        
        <div class="current-info">
            <div class="info-row">
                <span class="info-label">Product Name:</span>
                <span class="info-value"><?= htmlspecialchars($product['product_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Description:</span>
                <span class="info-value"><?= $product['description'] ? htmlspecialchars($product['description']) : 'No description' ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Price:</span>
                <span class="info-value">₱<?= number_format($product['price'], 2) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Current Stock:</span>
                <span class="info-value">
                    <?php 
                    $stock = intval($product['stock']);
                    $stockClass = $stock > 50 ? 'stock-high' : ($stock > 10 ? 'stock-medium' : 'stock-low');
                    ?>
                    <span class="stock-badge <?= $stockClass ?>"><?= $stock ?> units</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Update Product Information -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📝 Edit Product Information</div>
            <div class="card-subtitle">Update name, description, and price</div>
        </div>
        
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="form-control" 
                           value="<?= htmlspecialchars($product['product_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="price">Price (₱)</label>
                    <input type="number" id="price" name="price" step="0.01" class="form-control" 
                           value="<?= $product['price'] ?>" required>
                </div>
                
                <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" 
                              placeholder="Enter product description"><?= htmlspecialchars($product['description']) ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_product" class="btn btn-success">
                    💾 Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Update Stock -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📦 Update Stock Quantity</div>
            <div class="card-subtitle">Add or remove inventory (use negative numbers to reduce stock)</div>
        </div>
        
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="quantity">Quantity to Add/Remove</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" 
                           placeholder="Enter positive number to add, negative to remove" required>
                    <small style="color: #666; margin-top: 5px;">
                        Current stock: <strong><?= $product['stock'] ?> units</strong>
                    </small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="update_stock" class="btn btn-info">
                    📊 Update Stock
                </button>
            </div>
        </form>
    </div>
   <div class="back-link">
        <a href="inventory.php">← Back to Inventory Management</a>
    </div>
</div>
<script>
// Load saved theme
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('selectedTheme') || 'blue';
    if (savedTheme !== 'blue') {
        document.body.classList.add('theme-' + savedTheme);
    }
});
</script>
</body>
</html>