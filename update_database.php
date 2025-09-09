<?php
require_once 'config/config.php';

echo "<h2>Cập nhật Database cho Thanh toán MoMo</h2>";

try {
    $pdo = getPDO();
    
    // Đọc file SQL
    $sql_file = __DIR__ . '/database_updates.sql';
    if (!file_exists($sql_file)) {
        throw new Exception('File database_updates.sql không tồn tại');
    }
    
    $sql_content = file_get_contents($sql_file);
    $statements = explode(';', $sql_content);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "<p>✓ Thực hiện thành công: " . substr($statement, 0, 50) . "...</p>";
        } catch (PDOException $e) {
            // Bỏ qua lỗi nếu bảng/cột đã tồn tại
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p>~ Đã tồn tại: " . substr($statement, 0, 50) . "...</p>";
            } else {
                echo "<p>✗ Lỗi: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Tạo thư mục logs
    $log_dirs = [
        'storage/payment_logs',
        'storage/email_logs'
    ];
    
    foreach ($log_dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            echo "<p>✓ Tạo thư mục: $dir</p>";
        } else {
            echo "<p>~ Thư mục đã tồn tại: $dir</p>";
        }
    }
    
    echo "<h3>Cập nhật hoàn tất!</h3>";
    echo "<p><a href='checkout.php'>Quay lại trang thanh toán</a></p>";
    
} catch (Exception $e) {
    echo "<p><strong>Lỗi:</strong> " . $e->getMessage() . "</p>";
}
?>
