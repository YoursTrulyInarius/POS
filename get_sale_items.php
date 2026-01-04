<?php
header('Content-Type: application/json');
include "db.php";

// Check if sale_id is provided
if (!isset($_GET['sale_id']) || empty($_GET['sale_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sale ID is required']);
    exit;
}

$sale_id = intval($_GET['sale_id']);

try {
    // Query to get sale items with product details
    $query = "
        SELECT 
            si.sale_item_id,
            si.quantity,
            si.price,
            p.product_name
        FROM sale_items si
        JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = ?
        ORDER BY si.sale_item_id ASC
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'product_name' => $row['product_name'],
            'price' => $row['price'],
            'quantity' => $row['quantity'],
            'subtotal' => floatval($row['price']) * intval($row['quantity'])
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'total_items' => count($items)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>