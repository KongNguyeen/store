<?php
require_once 'middleware/admin_auth.php';

echo "<h2>Debug Thanh Toán MoMo</h2>";

// Kiểm tra session
echo "<h3>Thông tin Session:</h3>";
echo "<pre>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Không có') . "\n";
echo "Total Amount: " . ($_SESSION['total_amount'] ?? 'Không có') . "\n";
echo "Temp Order: " . (isset($_SESSION['temp_order']) ? 'Có' : 'Không có') . "\n";

if (isset($_SESSION['temp_order'])) {
    echo "\nChi tiết Temp Order:\n";
    print_r($_SESSION['temp_order']);
}

echo "\nTất cả Session:\n";
print_r($_SESSION);
echo "</pre>";

// Kiểm tra database
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getPDO();
        
        echo "<h3>Đơn hàng gần đây:</h3>";
        $stmt = $pdo->prepare("
            SELECT order_id, total_amount, payment_method, status, 
                   momo_order_id, momo_trans_id, created_at
            FROM orders 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $orders = $stmt->fetchAll();
        
        if ($orders) {
            echo "<table border='1'>";
            echo "<tr><th>Order ID</th><th>Amount</th><th>Payment</th><th>Status</th><th>MoMo Order</th><th>MoMo Trans</th><th>Created</th></tr>";
            foreach ($orders as $order) {
                echo "<tr>";
                echo "<td>" . $order['order_id'] . "</td>";
                echo "<td>" . number_format($order['total_amount']) . "</td>";
                echo "<td>" . $order['payment_method'] . "</td>";
                echo "<td>" . $order['status'] . "</td>";
                echo "<td>" . ($order['momo_order_id'] ?? 'N/A') . "</td>";
                echo "<td>" . ($order['momo_trans_id'] ?? 'N/A') . "</td>";
                echo "<td>" . $order['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Không có đơn hàng nào.</p>";
        }
        
        echo "<h3>Giao dịch thanh toán gần đây:</h3>";
        $stmt = $pdo->prepare("
            SELECT * FROM payment_transactions 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $transactions = $stmt->fetchAll();
        
        if ($transactions) {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Order ID</th><th>MoMo Order</th><th>Amount</th><th>Result</th><th>Message</th><th>Created</th></tr>";
            foreach ($transactions as $trans) {
                echo "<tr>";
                echo "<td>" . $trans['transaction_id'] . "</td>";
                echo "<td>" . ($trans['order_id'] ?? 'N/A') . "</td>";
                echo "<td>" . ($trans['momo_order_id'] ?? 'N/A') . "</td>";
                echo "<td>" . number_format($trans['amount']) . "</td>";
                echo "<td>" . $trans['result_code'] . "</td>";
                echo "<td>" . $trans['message'] . "</td>";
                echo "<td>" . $trans['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Không có giao dịch nào.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>Lỗi database: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='checkout.php'>Checkout</a> | <a href='orders.php'>Orders</a> | <a href='update_database.php'>Update Database</a></p>";
?>
