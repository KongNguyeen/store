<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed';
    echo json_encode($response);
    exit;
}

// Validate CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

// Validate input
$order_id = (int)($_POST['order_id'] ?? 0);
$new_status = $_POST['status'] ?? '';

if (!$order_id || !$new_status) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// Validate status value
$allowed_statuses = array_keys(ORDER_STATUS);
if (!in_array($new_status, $allowed_statuses)) {
    $response['message'] = 'Invalid status value';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // Lấy thông tin đơn hàng hiện tại
    $stmt = $pdo->prepare("
        SELECT o.*, u.email, u.full_name
        FROM orders o 
        JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    $old_status = $order['status'];
    
    // Kiểm tra logic chuyển trạng thái (tạm thời cho phép tất cả chuyển đổi để debug)
    $valid_transitions = [
        'pending' => ['processing', 'cancelled', 'shipped', 'delivered'],
        'processing' => ['shipped', 'cancelled', 'delivered'],
        'shipped' => ['delivered', 'returned', 'cancelled'],
        'delivered' => ['returned'],
        'cancelled' => ['pending', 'processing'], // Cho phép revert để test
        'returned' => ['processing']
    ];

    // Tạm thời comment out kiểm tra transition để debug
    /*
    if (!in_array($new_status, $valid_transitions[$old_status])) {
        throw new Exception('Invalid status transition from ' . $old_status . ' to ' . $new_status);
    }
    */

    // Cập nhật trạng thái đơn hàng
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = ?, updated_at = NOW()
        WHERE order_id = ?
    ");
    $result = $stmt->execute([$new_status, $order_id]);
    
    if (!$result) {
        throw new Exception('Failed to update order status');
    }

    // Ghi log thay đổi vào order_status_history (theo đúng cấu trúc DB)
    $stmt = $pdo->prepare("
        INSERT INTO order_status_history (
            order_id, 
            status, 
            changed_by,
            note
        ) VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $order_id,
        $new_status,
        $_SESSION['user_id'] ?? 1,
        "Cập nhật trạng thái từ " . (ORDER_STATUS[$old_status] ?? $old_status) . " sang " . (ORDER_STATUS[$new_status] ?? $new_status)
    ]);

    // Cập nhật kho nếu trạng thái là cancelled hoặc returned
    if (in_array($new_status, ['cancelled', 'returned'])) {
        // Lấy danh sách sản phẩm trong đơn hàng
        $stmt = $pdo->prepare("
            SELECT product_id, quantity 
            FROM order_items 
            WHERE order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();

        // Cộng lại số lượng vào kho
        $update_stock = $pdo->prepare("
            UPDATE products 
            SET stock = stock + ?, updated_at = NOW()
            WHERE product_id = ?
        ");

        foreach ($items as $item) {
            $update_stock->execute([$item['quantity'], $item['product_id']]);
            
            // Thêm transaction vào inventory (nếu bảng tồn tại)
            try {
                $add_inventory = $pdo->prepare("
                    INSERT INTO inventory_transactions (
                        product_id, 
                        transaction_type,
                        quantity,
                        note
                    ) VALUES (?, 'import', ?, ?)
                ");
                $add_inventory->execute([
                    $item['product_id'],
                    $item['quantity'],
                    "Hoàn kho từ đơn hàng #{$order_id}"
                ]);
            } catch (Exception $e) {
                // Bỏ qua lỗi inventory_transactions nếu bảng không có dữ liệu
                error_log("Inventory transaction error (ignored): " . $e->getMessage());
            }
        }
    }

    $pdo->commit();

    // TODO: Gửi email thông báo cho khách hàng
    // $to = $order['email'];
    // $subject = "Cập nhật trạng thái đơn hàng #{$order_id}";
    // $message = "Xin chào {$order['full_name']},\n\n";
    // $message .= "Đơn hàng #{$order_id} của bạn đã được cập nhật sang trạng thái: " . ORDER_STATUS[$new_status];
    // mail($to, $subject, $message);

    $response['success'] = true;
    $response['message'] = 'Cập nhật trạng thái thành công';

} catch (Exception $e) {
    if (isset($pdo)) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    
    // Log chi tiết lỗi để debug
    error_log("Order status update error: " . $e->getMessage());
    error_log("Order ID: " . $order_id);
    error_log("New Status: " . $new_status);
    error_log("User ID: " . ($_SESSION['user_id'] ?? 'not set'));
    
    $response['message'] = 'Có lỗi xảy ra khi cập nhật trạng thái';
}

echo json_encode($response);
?>