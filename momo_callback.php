<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    flash('error', 'Vui lòng đăng nhập để tiếp tục');
    redirect('login.php');
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Nhận thông tin từ MoMo return URL
$partnerCode = $_GET['partnerCode'] ?? '';
$orderId = $_GET['orderId'] ?? '';
$requestId = $_GET['requestId'] ?? '';
$amount = $_GET['amount'] ?? 0;
$orderInfo = $_GET['orderInfo'] ?? '';
$orderType = $_GET['orderType'] ?? '';
$transId = $_GET['transId'] ?? '';
$resultCode = $_GET['resultCode'] ?? -1;
$message = $_GET['message'] ?? '';
$payType = $_GET['payType'] ?? '';
$responseTime = $_GET['responseTime'] ?? '';
$extraData = $_GET['extraData'] ?? '';
$signature = $_GET['signature'] ?? '';

// Xác thực chữ ký
$secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
$rawHash = "accessKey=klm05TvNBzhg7h7j&amount=$amount&extraData=$extraData&message=$message&orderId=$orderId&orderInfo=$orderInfo&orderType=$orderType&partnerCode=$partnerCode&payType=$payType&requestId=$requestId&responseTime=$responseTime&resultCode=$resultCode&transId=$transId";
$expectedSignature = hash_hmac("sha256", $rawHash, $secretKey);

if ($signature !== $expectedSignature) {
    flash('error', 'Thông tin thanh toán không hợp lệ');
    redirect('checkout.php');
}

try {
    $pdo->beginTransaction();
    
    if ($resultCode == 0) {
        // Thanh toán thành công
        
        // Kiểm tra xem đơn hàng đã được tạo chưa
        $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE momo_order_id = ? AND payment_method = 'momo'");
        $stmt->execute([$orderId]);
        $existing_order_id = $stmt->fetchColumn();
        
        if ($existing_order_id) {
            // Đơn hàng đã tồn tại, chuyển hướng đến trang chi tiết
            $pdo->commit();
            flash('success', 'Thanh toán thành công!');
            redirect("order_detail.php?id=$existing_order_id");
        }
        
        // Kiểm tra thông tin đơn hàng tạm thời
        if (!isset($_SESSION['temp_order'])) {
            throw new Exception('Không tìm thấy thông tin đơn hàng. Vui lòng thử lại.');
        }
        
        $temp_order = $_SESSION['temp_order'];
        
        // Xác thực số tiền
        $expected_amount = $temp_order['total'] - $temp_order['discount'];
        if (abs($amount - $expected_amount) > 1) { // Cho phép sai lệch 1 VND
            throw new Exception('Số tiền thanh toán không khớp');
        }
        
        // Lưu địa chỉ giao hàng mới nếu có
        $address_id = $temp_order['address_id'];
        if (!$address_id && $temp_order['save_address']) {
            $stmt = $pdo->prepare("
                INSERT INTO addresses (
                    user_id, recipient_name, phone, address_line,
                    ward, district, city, is_default
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([
                $user_id,
                $temp_order['recipient_name'],
                $temp_order['phone'],
                $temp_order['address_line'],
                $temp_order['ward'],
                $temp_order['district'],
                $temp_order['city']
            ]);
            $address_id = $pdo->lastInsertId();
        }
        
        if (!$address_id) {
            throw new Exception('Không có thông tin địa chỉ giao hàng');
        }
        
        // Tạo đơn hàng mới
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, address_id, 
                total_amount, discount_amount,
                payment_method, shipping_method,
                status, momo_order_id, momo_trans_id,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $user_id,
            $address_id,
            $temp_order['total'],
            $temp_order['discount'],
            'momo',
            $temp_order['shipping_method'],
            $orderId,
            $transId
        ]);
        $order_id = $pdo->lastInsertId();
        
        // Thêm chi tiết đơn hàng
        $stmt = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, quantity, price
            ) VALUES (?, ?, ?, ?)
        ");
        
        foreach ($temp_order['cart_items'] as $item) {
            // Kiểm tra tồn kho trước khi đặt hàng
            $stock_check = $pdo->prepare("SELECT stock FROM products WHERE product_id = ?");
            $stock_check->execute([$item['product']['product_id']]);
            $current_stock = $stock_check->fetchColumn();
            
            if ($current_stock < $item['quantity']) {
                throw new Exception("Sản phẩm '{$item['product']['name']}' không đủ số lượng trong kho");
            }
            
            $stmt->execute([
                $order_id,
                $item['product']['product_id'],
                $item['quantity'],
                $item['product']['price']
            ]);
            
            // Cập nhật tồn kho
            $update_stock = $pdo->prepare("
                UPDATE products 
                SET stock = stock - ?, updated_at = NOW()
                WHERE product_id = ?
            ");
            $update_stock->execute([
                $item['quantity'],
                $item['product']['product_id']
            ]);
        }
        
        // Lưu thông tin giao dịch
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                order_id, momo_order_id, momo_trans_id, amount, 
                result_code, message, payment_method, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'momo', NOW())
        ");
        $stmt->execute([
            $order_id, $orderId, $transId, $amount, 
            $resultCode, $message
        ]);
        
        $pdo->commit();
        
        // Xóa giỏ hàng và thông tin tạm thời
        $_SESSION['cart'] = [];
        unset($_SESSION['promo']);
        unset($_SESSION['total_amount']);
        unset($_SESSION['temp_order']);
        
        // Xóa cart_items trong database nếu có
        $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart_id = $stmt->fetchColumn();
        if ($cart_id) {
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cart_id]);
        }
        
        flash('success', 'Thanh toán MoMo thành công! Đơn hàng của bạn đã được đặt.');
        redirect("order_detail.php?id=$order_id");
        
    } else {
        // Thanh toán thất bại
        $pdo->rollBack();
        
        // Lưu thông tin giao dịch thất bại
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                momo_order_id, momo_trans_id, amount, result_code, 
                message, payment_method, created_at
            ) VALUES (?, ?, ?, ?, ?, 'momo', NOW())
        ");
        $stmt->execute([
            $orderId, $transId, $amount, $resultCode, $message
        ]);
        
        flash('error', "Thanh toán thất bại: $message");
        redirect('checkout.php');
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Log lỗi
    error_log("MoMo payment callback error: " . $e->getMessage());
    
    flash('error', 'Có lỗi xảy ra khi xử lý thanh toán: ' . $e->getMessage());
    redirect('checkout.php');
}
?>
