<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Nhận dữ liệu từ POST hoặc GET
$category_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

// Kiểm tra CSRF token
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

// Validate category ID
if (!$category_id) {
    $response['message'] = 'Invalid category ID';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getPDO();
    
    // Kiểm tra danh mục có tồn tại không
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        $response['message'] = 'Danh mục không tồn tại';
        echo json_encode($response);
        exit;
    }

    // Kiểm tra có danh mục con không
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
    $stmt->execute([$category_id]);
    $subcategories_count = $stmt->fetchColumn();

    if ($subcategories_count > 0) {
        $response['message'] = 'Không thể xóa danh mục này vì có ' . $subcategories_count . ' danh mục con';
        echo json_encode($response);
        exit;
    }

    // Kiểm tra có sản phẩm không
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $products_count = $stmt->fetchColumn();

    if ($products_count > 0) {
        // Chuyển sản phẩm sang danh mục cha (nếu có) hoặc category_id = 1 (mặc định)
        $new_category_id = $category['parent_id'] ?: 1;
        
        $stmt = $pdo->prepare("
            UPDATE products 
            SET category_id = ?, updated_at = NOW() 
            WHERE category_id = ?
        ");
        $stmt->execute([$new_category_id, $category_id]);
    }

    // Xóa danh mục
    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->execute([$category_id]);

    $response['success'] = true;
    $response['message'] = 'Xóa danh mục thành công';
    
    if ($products_count > 0) {
        $response['message'] .= '. ' . $products_count . ' sản phẩm đã được chuyển sang danh mục khác';
    }

} catch (PDOException $e) {
    $response['message'] = 'Có lỗi xảy ra khi xóa danh mục: ' . $e->getMessage();
}

echo json_encode($response);
?>