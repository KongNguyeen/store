<?php
// Get cart count API endpoint
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../config/functions.php';

try {
    $user_id = $_SESSION['user_id'] ?? 0;
    $cart_count = 0;
    
    if ($user_id) {
        $pdo = getPDO();
        
        // Get cart count from database
        $stmt = $pdo->prepare("
            SELECT SUM(ci.quantity) as cart_count
            FROM carts c 
            JOIN cart_items ci ON c.cart_id = ci.cart_id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $cart_count = (int)($result['cart_count'] ?? 0);
    } else {
        // Get cart count from session for guest users
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            $cart_count = array_sum($_SESSION['cart']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'cart_count' => $cart_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi lấy số lượng giỏ hàng',
        'error' => $e->getMessage()
    ]);
}
?>
