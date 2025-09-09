<?php
/**
 * MIDDLEWARE XÁC THỰC NGƯỜI DÙNG
 * File này kiểm tra và xác thực trạng thái đăng nhập của user
 * Được include vào các trang cần yêu cầu đăng nhập
 */

// Khởi tạo session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include file functions nếu chưa được load
if (!function_exists('is_logged_in')) {
    require_once __DIR__ . '/../config/functions.php';
}

/**
 * KIỂM TRA TRẠNG THÁI ĐĂNG NHẬP
 * Nếu user chưa đăng nhập thì chuyển hướng về trang login
 */
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Lưu URL hiện tại để redirect sau khi đăng nhập thành công
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Xác định base URL nếu chưa được định nghĩa
    $base_url = defined('BASE_URL') ? BASE_URL : '/store/';
    
    // Chuyển hướng đến trang đăng nhập
    header('Location: ' . $base_url . 'login.php');
    exit;
}

/**
 * KIỂM TRA THỜI HAN SESSION
 * Session có thời hạn 30 ngày, sau đó sẽ tự động hết hạn
 */
// Validate session lifetime
if (isset($_SESSION['login_time'])) {
    $lifetime = 30 * 24 * 60 * 60; // 30 ngày (tính bằng giây)
    $current_time = time();
    
    // Kiểm tra xem session đã hết hạn chưa
    if ($current_time - $_SESSION['login_time'] > $lifetime) {
        // Session hết hạn, hủy session và chuyển về login
        session_destroy();
        $base_url = defined('BASE_URL') ? BASE_URL : '/store/';
        header('Location: ' . $base_url . 'login.php?expired=1');
        exit;
    }
    
    // Cập nhật thời gian login nếu đã qua 1 giờ (để gia hạn session)
    if ($current_time - $_SESSION['login_time'] > 3600) {
        $_SESSION['login_time'] = $current_time;
    }
}

/**
 * KIỂM TRA TRẠNG THÁI TÀI KHOẢN NGƯỜI DÙNG
 * Xác minh tài khoản vẫn active và lấy thông tin role
 */
// Check user account status
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT is_active, role_id 
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Nếu user không tồn tại hoặc bị vô hiệu hóa
    if (!$user || !$user['is_active']) {
        // Hủy session và chuyển về login với thông báo tài khoản bị khóa
        session_destroy();
        $base_url = defined('BASE_URL') ? BASE_URL : '/store/';
        header('Location: ' . $base_url . 'login.php?inactive=1');
        exit;
    }

    // Lưu role_id vào session nếu chưa có (để phân quyền)
    if (!isset($_SESSION['role_id'])) {
        $_SESSION['role_id'] = $user['role_id'];
    }

} catch (PDOException $e) {
    // Log lỗi database nhưng không hủy session (tránh gián đoạn user)
    error_log('Database error in auth middleware: ' . $e->getMessage());
    // Không destroy session khi có lỗi DB, chỉ log để debug
}

/**
 * LÀM MỚI CSRF TOKEN ĐỊNH KỲ
 * CSRF token được tạo mới mỗi 1 giờ để bảo mật
 */
// Refresh CSRF token periodically
if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_time']) || 
    time() - $_SESSION['csrf_time'] > 3600) {
    // Tạo CSRF token mới (64 ký tự hex ngẫu nhiên)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_time'] = time();
}