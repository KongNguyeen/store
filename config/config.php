<?php
// Define root path first
define('ROOT_PATH', str_replace('config', '', __DIR__));

// Start session if not started
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Set session save path
    $session_path = ROOT_PATH . 'storage/sessions';
    if (!is_dir($session_path)) {
        mkdir($session_path, 0777, true);
    }
    
    // Set session cookie parameters
    $lifetime = 30 * 24 * 60 * 60; // 30 days
    session_set_cookie_params($lifetime, '/', '', false, true);
    
    // Set session save path and start session
    session_save_path($session_path);
    session_start();
}


// Site configuration
define('SITE_NAME', 'KongNguyeen');
define('BASE_URL', '/store/');

// File upload limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png']);

// Pagination
define('ADMIN_ITEMS_PER_PAGE', 10);
define('USER_ITEMS_PER_PAGE', 12);

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Order status
define('ORDER_STATUS', [
    'pending' => 'Chờ xử lý',
    'processing' => 'Đang xử lý',
    'shipped' => 'Đang giao hàng',
    'delivered' => 'Đã giao hàng',
    'cancelled' => 'Đã hủy',
    'returned' => 'Đã hoàn trả',
    'paid' => 'Đã thanh toán'
]);

// Payment methods
define('PAYMENT_METHODS', [
    'credit_card' => 'Thẻ tín dụng',
    'COD' => 'Thanh toán khi nhận hàng',
    'e_wallet' => 'Ví điện tử',
    'bank_transfer' => 'Chuyển khoản',
    'momo' => 'Ví MoMo',
    'vnpay' => 'VNPay'
]);

// Đường dẫn lưu ảnh sản phẩm
if (!defined('PRODUCT_IMG_PATH')) {
    define('PRODUCT_IMG_PATH', __DIR__ . '/../assets/img/products/');
}

// Include database config
require_once __DIR__ . '/database.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Initialize session data if needed
if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
}

// Extend session lifetime on each request
if (isset($_SESSION['login_time']) && !headers_sent()) {
    setcookie(session_name(), session_id(), time() + $lifetime, '/');
}