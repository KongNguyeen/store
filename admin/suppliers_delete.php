<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check if it's a POST or GET request
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$requestData = $isPost ? $_POST : $_GET;

// Validate CSRF token
if (!isset($requestData['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $requestData['csrf_token'] !== $_SESSION['csrf_token']) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

// Validate supplier ID
$supplier_id = (int)($requestData['id'] ?? 0);
if (!$supplier_id) {
    $response['message'] = 'Invalid supplier ID';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // Kiểm tra nhà cung cấp tồn tại
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch();

    if (!$supplier) {
        throw new Exception('Nhà cung cấp không tồn tại');
    }

    // Kiểm tra xem có sản phẩm nào không
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);
    $product_count = $stmt->fetchColumn();

    if ($product_count > 0) {
        // Chuyển sản phẩm sang nhà cung cấp mặc định (ID = 1)
        $stmt = $pdo->prepare("
            UPDATE products 
            SET supplier_id = 1, updated_at = NOW()
            WHERE supplier_id = ?
        ");
        $stmt->execute([$supplier_id]);

        // Ghi log (chỉ nếu bảng admin_logs tồn tại)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO admin_logs (
                    admin_id,
                    action_type,
                    target_type,
                    target_id,
                    note,
                    created_at
                ) VALUES (?, 'update_products', 'suppliers', ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $supplier_id,
                "Chuyển {$product_count} sản phẩm sang nhà cung cấp mặc định"
            ]);
        } catch (Exception $logError) {
            // Bỏ qua lỗi log nếu bảng không tồn tại hoặc có vấn đề khác
        }
    }

    // Xóa nhà cung cấp
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplier_id]);

    // Ghi log (chỉ nếu bảng admin_logs tồn tại)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (
                admin_id,
                action_type,
                target_type,
                target_id,
                note,
                created_at
            ) VALUES (?, 'delete', 'suppliers', ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $supplier_id,
            "Xóa nhà cung cấp: {$supplier['name']}"
        ]);
    } catch (Exception $logError) {
        // Bỏ qua lỗi log nếu bảng không tồn tại hoặc có vấn đề khác
        // Không ảnh hưởng đến việc xóa nhà cung cấp
    }

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Xóa nhà cung cấp thành công';
    if ($product_count > 0) {
        $response['message'] .= ". {$product_count} sản phẩm đã được chuyển sang nhà cung cấp mặc định";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Có lỗi xảy ra: ' . $e->getMessage();
}

echo json_encode($response);
?>