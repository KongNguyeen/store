<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="">
        <title>Kết quả thanh toán VNPAY</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { 
                background-color: #f8f9fa;
                padding-top: 40px;
            }
            .payment-result {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 15px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .header img {
                max-width: 200px;
                margin-bottom: 20px;
            }
            .result-success {
                color: #28a745;
                font-size: 1.2em;
                font-weight: bold;
            }
            .result-failed {
                color: #dc3545;
                font-size: 1.2em;
                font-weight: bold;
            }
            .info-row {
                margin-bottom: 15px;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .info-label {
                font-weight: 600;
                color: #666;
            }
            .back-button {
                text-align: center;
                margin-top: 30px;
            }
        </style>
    </head>
    <body>
        <?php
        require_once("./config.php");
        require_once("../config/config.php");
        require_once("../config/functions.php");
        
        $vnp_SecureHash = $_GET['vnp_SecureHash'];
        $inputData = array();
        foreach ($_GET as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        // Xử lý kết quả trả về và cập nhật trạng thái đơn hàng
        try {
            if ($secureHash == $vnp_SecureHash) {
            $order_id = $_GET['vnp_TxnRef'];
            $pdo = getPDO();

            // Lấy thông tin response code
            $response_code = $_GET['vnp_ResponseCode'];
            
            if ($response_code == '00') {
                // Bắt đầu transaction
                $pdo->beginTransaction();
                
                try {
                    // Thanh toán thành công
                    // Cập nhật trạng thái đơn hàng thành paid
                    $stmt = $pdo->prepare("
                        UPDATE orders 
                        SET status = 'paid',
                            payment_status = 'paid',
                            vnpay_transaction_no = ?,
                            vnpay_txn_ref = ?
                        WHERE order_id = ?
                    ");
                    $stmt->execute([
                        $_GET['vnp_TransactionNo'],
                        $_GET['vnp_TxnRef'],
                        $order_id
                    ]);

                    // Verify the update
                    $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                    $updatedStatus = $stmt->fetchColumn();
                    
                    if ($updatedStatus !== 'paid') {
                        throw new Exception('Failed to update order status to paid');
                    }

                    // Thêm vào lịch sử trạng thái đơn hàng
                    $stmt = $pdo->prepare("
                        INSERT INTO order_status_history (
                            order_id, status, changed_at, note
                        ) VALUES (?, 'paid', NOW(), ?)
                    ");
                    $stmt->execute([
                        $order_id,
                        'Đơn hàng đã thanh toán thành công qua VNPAY'
                    ]);

                    // Ghi log giao dịch thành công
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_transactions (
                            order_id, vnpay_txn_ref, vnpay_transaction_no,
                            amount, result_code, message, payment_method
                        ) VALUES (?, ?, ?, ?, ?, ?, 'vnpay')
                    ");
                    $stmt->execute([
                        $order_id,
                        $_GET['vnp_TxnRef'],
                        $_GET['vnp_TransactionNo'],
                        $_GET['vnp_Amount']/100,
                        0,
                        'Giao dịch thành công'
                    ]);

                    // Commit transaction
                    $pdo->commit();
                    
                    // Thiết lập thông báo thành công và chuyển hướng đến chi tiết đơn hàng
                    flash('success', 'Đơn hàng #' . $order_id . ' đã được thanh toán thành công qua VNPAY!');
                    redirect('../order_detail.php?id=' . $order_id);
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    error_log("VNPAY Update Error: " . $e->getMessage());
                    throw $e;
                }
            } else {
                // Xác định lý do thất bại/hủy
                $cancel_reason = '';
                $status_note = '';
                switch ($response_code) {
                    case '24':
                        $cancel_reason = 'Khách hàng hủy giao dịch';
                        $status_note = 'Đơn hàng bị hủy - Khách hàng hủy thanh toán VNPAY';
                        break;
                    case '15':
                        $cancel_reason = 'Giao dịch đã hết hạn';
                        $status_note = 'Đơn hàng bị hủy - Giao dịch VNPAY hết hạn';
                        break;
                    default:
                        $cancel_reason = 'Giao dịch thất bại';
                        $status_note = 'Đơn hàng bị hủy - Thanh toán VNPAY thất bại';
                }

                // Cập nhật trạng thái đơn hàng thành cancelled
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET status = 'cancelled',
                        payment_status = 'failed'
                    WHERE order_id = ?
                ");
                $stmt->execute([$order_id]);

                // Thêm vào lịch sử trạng thái đơn hàng
                $stmt = $pdo->prepare("
                    INSERT INTO order_status_history (
                        order_id, status, changed_at, note
                    ) VALUES (?, 'cancelled', NOW(), ?)
                ");
                $stmt->execute([
                    $order_id,
                    $status_note
                ]);

                // Ghi log giao dịch thất bại
                $stmt = $pdo->prepare("
                    INSERT INTO payment_transactions (
                        order_id, vnpay_txn_ref, vnpay_transaction_no,
                        amount, result_code, message, payment_method
                    ) VALUES (?, ?, ?, ?, ?, ?, 'vnpay')
                ");
                $stmt->execute([
                    $order_id,
                    $_GET['vnp_TxnRef'],
                    $_GET['vnp_TransactionNo'],
                    $_GET['vnp_Amount']/100,
                    $_GET['vnp_ResponseCode'],
                    'Giao dịch thất bại hoặc bị hủy'
                ]);

                // Hoàn lại số lượng sản phẩm vào kho
                $stmt = $pdo->prepare("
                    SELECT product_id, quantity 
                    FROM order_items 
                    WHERE order_id = ?
                ");
                $stmt->execute([$order_id]);
                $items = $stmt->fetchAll();

                foreach ($items as $item) {
                    // Cập nhật lại stock
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET stock = stock + ? 
                        WHERE product_id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['product_id']]);

                    // Ghi log inventory
                    $stmt = $pdo->prepare("
                        INSERT INTO inventory_transactions (
                            product_id, transaction_type, 
                            quantity, note
                        ) VALUES (?, 'import', ?, ?)
                    ");
                    $stmt->execute([
                        $item['product_id'],
                        $item['quantity'],
                        'Hoàn lại hàng từ đơn hàng #' . $order_id . ' bị hủy'
                    ]);
                }
                
                // Thiết lập thông báo lỗi và chuyển hướng
                flash('error', 'Thanh toán đơn hàng #' . $order_id . ' không thành công. ' . $cancel_reason);
                redirect('../orders.php');
            }
        } else {
            // Chữ ký không hợp lệ
            redirect('../orders.php');
        }
        } catch (Exception $e) {
            // Log lỗi nếu cần
            error_log("VNPAY Error: " . $e->getMessage());
            redirect('../orders.php');
        }
        ?>
        <!--Begin display -->
        <div class="container">
            <div class="payment-result">
                <div class="header">
                    <img src="https://sandbox.vnpayment.vn/paymentv2/Images/logos/logo-en.svg" alt="VNPAY Logo">
                    <h3>Kết quả thanh toán</h3>
                </div>
                <div class="result-info">
                    <?php if ($secureHash == $vnp_SecureHash): ?>
                        <?php if ($_GET['vnp_ResponseCode'] == '00'): ?>
                            <div class="text-center mb-4">
                                <span class="result-success">✓ Thanh toán thành công</span>
                            </div>
                        <?php else: ?>
                            <div class="text-center mb-4">
                                <span class="result-failed">✗ Thanh toán không thành công</span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center mb-4">
                            <span class="result-failed">✗ Chữ ký không hợp lệ</span>
                        </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <span class="info-label">Mã đơn hàng:</span>
                        <span class="info-value"><?php echo $_GET['vnp_TxnRef'] ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Số tiền:</span>
                        <span class="info-value"><?php echo number_format($_GET['vnp_Amount']/100, 0, ',', '.') ?> VNĐ</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Nội dung thanh toán:</span>
                        <span class="info-value"><?php echo $_GET['vnp_OrderInfo'] ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Mã giao dịch VNPAY:</span>
                        <span class="info-value"><?php echo $_GET['vnp_TransactionNo'] ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Ngân hàng:</span>
                        <span class="info-value"><?php echo $_GET['vnp_BankCode'] ?></span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Thời gian thanh toán:</span>
                        <span class="info-value">
                            <?php 
                            $date = DateTime::createFromFormat('YmdHis', $_GET['vnp_PayDate']);
                            echo $date ? $date->format('d/m/Y H:i:s') : $_GET['vnp_PayDate'];
                            ?>
                        </span>
                    </div>

                    <?php if ($_GET['vnp_ResponseCode'] == '00'): ?>
                        <div class="back-button">
                            <a href="/store/order_detail.php?order_id=<?php echo $_GET['vnp_TxnRef'] ?>" 
                               class="btn btn-primary">Xem chi tiết đơn hàng</a>
                        </div>
                    <?php else: ?>
                        <div class="back-button">
                            <a href="/store/checkout.php" class="btn btn-secondary">Quay lại thanh toán</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-center mt-3">
                <p class="text-muted">&copy; <?php echo date('Y')?> VNPAY. All rights reserved.</p>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
