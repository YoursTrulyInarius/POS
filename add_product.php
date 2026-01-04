<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['product_name'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("INSERT INTO products (product_name, stock) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $stock);
    $stmt->execute();
    header("Location: inventory.php");
    exit;
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
<form method="post">
    <input type="text" name="product_name" placeholder="Product Name" required>
    <input type="number" name="stock" placeholder="Initial Stock" required>
    <button type="submit">Save</button>
</form>
<a href="inventory.php">⬅ Back to Inventory</a>
</div>
</body>
</html>
