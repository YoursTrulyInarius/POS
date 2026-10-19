<?php
include "../includes/db.php";

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['product_name'] ?? '');
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 2147483647],
    ]);

    if ($name === '') {
        $message = "Product name is required.";
    } elseif ($stock === false) {
        $message = "Stock quantity must be a whole number zero or greater.";
    } else {
        $stmt = $conn->prepare("INSERT INTO products (product_name, stock) VALUES (?, ?)");
        if (!$stmt) {
            $message = "Unable to prepare product insert: " . htmlspecialchars($conn->error);
        } else {
            $stmt->bind_param("si", $name, $stock);
            if ($stmt->execute()) {
                header("Location: inventory.php");
                exit;
            }
            $message = "Unable to add product: " . htmlspecialchars($stmt->error);
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Add Product</title>
<style>
body {font-family: Arial; background:#f4f6f9; margin:0; padding:20px;}
.container {max-width:600px; margin:auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);}
input, button {width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:4px;}
button {background:#007bff; color:#fff; border:none; cursor:pointer;}
button:hover {background:#0056b3;}
a {text-decoration:none; display:inline-block; margin-top:10px; color:#007bff;}
</style>
</head>
<body>
<div class="container">
<h2>Add Product</h2>
<?php if ($message): ?>
    <p role="alert"><?= $message ?></p>
<?php endif; ?>
<form method="post">
    <input type="text" name="product_name" placeholder="Product Name" required>
    <input type="number" name="stock" placeholder="Initial Stock" min="0" step="1" required>
    <button type="submit">Save</button>
</form>
<a href="inventory.php">⬅ Back to Inventory</a>
</div>
</body>
</html>
