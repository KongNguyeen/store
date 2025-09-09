
<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Lấy dữ liệu từ POST hoặc GET
$product_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

// Kiểm tra CSRF token
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

// Validate product ID
if (!$product_id) {
    $response['message'] = 'Invalid product ID';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // Xóa hình ảnh sản phẩm
    $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($images as $image_url) {
        if ($image_url && file_exists($image_url)) {
            @unlink($image_url);
        }
    }

    // Xóa dữ liệu từ các bảng liên quan
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
    $stmt->execute([$product_id]);

    $stmt = $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // Xóa các bản ghi liên quan trong order_items
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE product_id = ?");
    $stmt->execute([$product_id]);
    
    // Xóa các bản ghi liên quan trong reviews nếu có
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE product_id = ?");
    $stmt->execute([$product_id]);

    // Xóa từ bảng products
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Xóa sản phẩm thành công';

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    
    // Log error for debugging
    error_log("Product Delete Error: " . $e->getMessage() . " - Product ID: " . $product_id);
    
    $response['message'] = 'Có lỗi xảy ra khi xóa sản phẩm: ' . $e->getMessage();
    $response['debug'] = [
        'product_id' => $product_id,
        'csrf_token' => substr($csrf_token, 0, 10) . '...',
        'session_csrf' => isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 10) . '...' : null,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
?>