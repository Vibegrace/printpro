<?php
header('Content-Type: application/json');

require_once '../config.php';

try {
    if (!isset($_GET['id'])) {
        throw new Exception("Product ID is required");
    }
    
    $product_id = (int)$_GET['id'];
    
    $query = "SELECT p.*, c.name as category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Product not found");
    }
    
    $product = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
