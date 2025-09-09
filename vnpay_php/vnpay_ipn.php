<?php
/* Payment Notify
 * IPN URL: Ghi nhận kết quả thanh toán từ VNPAY
 * Các bước thực hiện:
 * Kiểm tra checksum 
 * Tìm giao dịch trong database
 * Kiểm tra số tiền giữa hai hệ thống
 * Kiểm tra tình trạng của giao dịch trước khi cập nhật
 * Cập nhật kết quả vào Database
 * Trả kết quả ghi nhận lại cho VNPAY
 */

require_once("./config.php");
require_once("../config/config.php");
$inputData = array();
$returnData = array();
foreach ($_GET as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }

$vnp_SecureHash = $inputData['vnp_SecureHash'];
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
$vnpTranId = $inputData['vnp_TransactionNo']; //Mã giao dịch tại VNPAY
$vnp_BankCode = $inputData['vnp_BankCode']; //Ngân hàng thanh toán
$vnp_Amount = $inputData['vnp_Amount']/100; // Số tiền thanh toán VNPAY phản hồi

$Status = 0; // Là trạng thái thanh toán của giao dịch chưa có IPN lưu tại hệ thống của merchant chiều khởi tạo URL thanh toán.
$orderId = $inputData['vnp_TxnRef'];

try {
    //Check Orderid    
    //Kiểm tra checksum của dữ liệu
    if ($secureHash == $vnp_SecureHash) {
        // Lấy thông tin đơn hàng từ database
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order != NULL) {
            // Kiểm tra số tiền thanh toán
            if($order["total_amount"] == $vnp_Amount)
            {
                if ($order["status"] == 'pending') {
                    if ($inputData['vnp_ResponseCode'] == '00' && $inputData['vnp_TransactionStatus'] == '00') {
                        try {
                            $pdo->beginTransaction();

                            // Cập nhật trạng thái đơn hàng thành công
                            $stmt = $pdo->prepare("
                                UPDATE orders 
                                SET status = 'paid',
                                    payment_status = 'paid',
                                    vnpay_transaction_no = ?,
                                    vnpay_txn_ref = ?
                                WHERE order_id = ?
                            ");
                            $stmt->execute([
                                $vnpTranId,
                                $orderId,
                                $orderId
                            ]);

                            // Thêm vào lịch sử đơn hàng
                            $stmt = $pdo->prepare("
                                INSERT INTO order_status_history (
                                    order_id, status, changed_at, note
                                ) VALUES (?, 'paid', NOW(), ?)
                            ");
                            $stmt->execute([
                                $orderId,
                                'Đơn hàng đã được thanh toán qua VNPAY'
                            ]);

                            // Xóa giỏ hàng
                            $user_id = $order['user_id'];
                            $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $cart_id = $stmt->fetchColumn();

                            if ($cart_id) {
                                // Xóa cart_items trước
                                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                                $stmt->execute([$cart_id]);
                                // Xóa cart
                                $stmt = $pdo->prepare("DELETE FROM carts WHERE cart_id = ?");
                                $stmt->execute([$cart_id]);
                            }

                            $pdo->commit();
                            $Status = 1; // Trạng thái thanh toán thành công
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    } else {
                        // Thanh toán thất bại
                        try {
                            $pdo->beginTransaction();

                            // Cập nhật trạng thái đơn hàng thất bại
                            $stmt = $pdo->prepare("
                                UPDATE orders 
                                SET status = 'cancelled',
                                    payment_status = 'failed'
                                WHERE order_id = ?
                            ");
                            $stmt->execute([$orderId]);

                            // Thêm vào lịch sử đơn hàng
                            $stmt = $pdo->prepare("
                                INSERT INTO order_status_history (
                                    order_id, status, changed_at, note
                                ) VALUES (?, 'cancelled', NOW(), ?)
                            ");
                            $stmt->execute([
                                $orderId,
                                'Đơn hàng bị hủy - Thanh toán VNPAY thất bại'
                            ]);

                            // Hoàn lại số lượng sản phẩm vào kho
                            $stmt = $pdo->prepare("
                                SELECT product_id, quantity 
                                FROM order_items 
                                WHERE order_id = ?
                            ");
                            $stmt->execute([$orderId]);
                            $items = $stmt->fetchAll();

                            foreach ($items as $item) {
                                $stmt = $pdo->prepare("
                                    UPDATE products 
                                    SET stock = stock + ? 
                                    WHERE product_id = ?
                                ");
                                $stmt->execute([$item['quantity'], $item['product_id']]);
                            }

                            $pdo->commit();
                            $Status = 2; // Trạng thái thanh toán thất bại
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                    }
                    //Trả kết quả về cho VNPAY: Website/APP TMĐT ghi nhận yêu cầu thành công                
                    $returnData['RspCode'] = '00';
                    $returnData['Message'] = 'Confirm Success';
                } else {
                    $returnData['RspCode'] = '02';
                    $returnData['Message'] = 'Order already confirmed';
                }
            }
            else {
                $returnData['RspCode'] = '04';
                $returnData['Message'] = 'invalid amount';
            }
        } else {
            $returnData['RspCode'] = '01';
            $returnData['Message'] = 'Order not found';
        }
    } else {
        $returnData['RspCode'] = '97';
        $returnData['Message'] = 'Invalid signature';
    }
} catch (Exception $e) {
    $returnData['RspCode'] = '99';
    $returnData['Message'] = 'Unknow error';
}
//Trả lại VNPAY theo định dạng JSON
echo json_encode($returnData);
