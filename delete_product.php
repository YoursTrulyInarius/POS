<?php
include "db.php";

$id = $_GET['id'];

// delete inventory first (foreign key safety)
$conn->query("DELETE FROM inventory WHERE product_id=$id");
// delete product
$conn->query("DELETE FROM products WHERE product_id=$id");

header("Location: inventory.php");
exit;
?>
