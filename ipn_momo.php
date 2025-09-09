<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Log file để debug
$log_file = 'storage/payment_logs/momo_ipn_' . date('Y-m-d') . '.log';
if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0777, true);
}

function writeLog($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// Nhận dữ liệu từ MoMo
$input = file_get_contents('php://input');
writeLog("Received IPN data: " . $input);

if (empty($input)) {
    writeLog("Empty input data");
    http_response_code(400);
    exit('No data received');
}

$data = json_decode($input, true);
if (!$data) {
    writeLog("Invalid JSON data");
    http_response_code(400);
    exit('Invalid JSON');
}

// Thông tin cấu hình MoMo
$secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';

// Xác thực chữ ký
$rawHash = "accessKey=" . ($data['accessKey'] ?? '') . 
           "&amount=" . ($data['amount'] ?? '') . 
           "&extraData=" . ($data['extraData'] ?? '') . 
           "&message=" . ($data['message'] ?? '') . 
           "&orderId=" . ($data['orderId'] ?? '') . 
           "&orderInfo=" . ($data['orderInfo'] ?? '') . 
           "&orderType=" . ($data['orderType'] ?? '') . 
           "&partnerCode=" . ($data['partnerCode'] ?? '') . 
           "&payType=" . ($data['payType'] ?? '') . 
           "&requestId=" . ($data['requestId'] ?? '') . 
           "&responseTime=" . ($data['responseTime'] ?? '') . 
           "&resultCode=" . ($data['resultCode'] ?? '') . 
           "&transId=" . ($data['transId'] ?? '');

$signature = hash_hmac("sha256", $rawHash, $secretKey);

if ($signature !== ($data['signature'] ?? '')) {
    writeLog("Invalid signature. Expected: $signature, Received: " . ($data['signature'] ?? ''));
    http_response_code(400);
    exit('Invalid signature');
}

writeLog("Signature verified successfully");

// Xử lý theo kết quả thanh toán
$resultCode = $data['resultCode'] ?? -1;
$orderId = $data['orderId'] ?? '';
$transId = $data['transId'] ?? '';
$amount = $data['amount'] ?? 0;

try {
    $pdo = getPDO();
    
    if ($resultCode == 0) {
        // Thanh toán thành công
        writeLog("Payment successful for orderId: $orderId, transId: $transId, amount: $amount");
        
        // Kiểm tra xem đơn hàng đã được tạo chưa
        $stmt = $pdo->prepare("SELECT order_id FROM orders WHERE momo_order_id = ? AND payment_method = 'momo'");
        $stmt->execute([$orderId]);
        $existing_order = $stmt->fetchColumn();
        
        if ($existing_order) {
            writeLog("Order already exists with ID: $existing_order");
            echo json_encode(['status' => 'success', 'message' => 'Order already processed']);
            exit;
        }
        
        // Lưu thông tin giao dịch
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                momo_order_id, momo_trans_id, amount, result_code, 
                message, payment_method, created_at
            ) VALUES (?, ?, ?, ?, ?, 'momo', NOW())
        ");
        $stmt->execute([
            $orderId, 
            $transId, 
            $amount, 
            $resultCode, 
            $data['message'] ?? ''
        ]);
        
        writeLog("Transaction saved successfully");
        
    } else {
        // Thanh toán thất bại
        writeLog("Payment failed for orderId: $orderId, resultCode: $resultCode");
        
        // Lưu thông tin giao dịch thất bại
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                momo_order_id, momo_trans_id, amount, result_code, 
                message, payment_method, created_at
            ) VALUES (?, ?, ?, ?, ?, 'momo', NOW())
        ");
        $stmt->execute([
            $orderId, 
            $transId ?? '', 
            $amount, 
            $resultCode, 
            $data['message'] ?? ''
        ]);
        
        writeLog("Failed transaction saved");
    }
    
} catch (Exception $e) {
    writeLog("Error processing IPN: " . $e->getMessage());
    http_response_code(500);
    exit('Internal server error');
}

// Phản hồi cho MoMo
echo json_encode(['status' => 'success']);
writeLog("IPN processed successfully");
?>
