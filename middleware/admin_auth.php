<?php
// middleware/admin_auth.php

// Include functions và config
require_once __DIR__ . '/../config/functions.php';

// Kiểm tra session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra CSRF token cho các request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect('../login.php');
    }
}

// Tạo CSRF token mới nếu chưa có
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// DEBUG: Kiểm tra đăng nhập
if (!is_logged_in()) {
    echo '<pre style="background:#fff;color:#333;border:2px solid red;padding:10px;">DEBUG: Không đăng nhập<br>'; print_r($_SESSION); echo '</pre>';
    flash('error', 'Vui lòng đăng nhập để tiếp tục.');
    redirect('../login.php');
}

// DEBUG: Kiểm tra quyền admin
if (!is_admin()) {
    echo '<pre style="background:#fff;color:#333;border:2px solid orange;padding:10px;">DEBUG: Không phải admin<br>'; print_r($_SESSION); echo '</pre>';
    flash('error', 'Bạn không có quyền truy cập trang này.');
    redirect('../index.php');
}

// Lấy thông tin admin hiện tại
$current_admin = get_user_by_id($_SESSION['user_id']);
if (!$current_admin || $current_admin['is_active'] != 1) {
    echo '<pre style="background:#fff;color:#333;border:2px solid blue;padding:10px;">DEBUG: Tài khoản admin bị vô hiệu hóa<br>'; print_r($current_admin); echo '</pre>';
    session_destroy();
    flash('error', 'Tài khoản của bạn đã bị vô hiệu hóa.');
    redirect('../login.php');
}

// Cập nhật thời gian hoạt động
refresh_session();

// Thiết lập timezone cho trang admin
date_default_timezone_set('Asia/Ho_Chi_Minh');
?>