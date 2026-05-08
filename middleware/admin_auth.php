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
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        flash('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect('../login.php');
    }
}

// Tạo CSRF token mới nếu chưa có
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!is_logged_in()) {
    flash('error', 'Vui lòng đăng nhập để tiếp tục.');
    redirect('../login.php');
}

if (!is_admin()) {
    flash('error', 'Bạn không có quyền truy cập trang này.');
    redirect('../index.php');
}

// Lấy thông tin admin hiện tại
$current_admin = get_user_by_id($_SESSION['user_id']);
if (!$current_admin || $current_admin['is_active'] != 1) {
    session_destroy();
    flash('error', 'Tài khoản của bạn đã bị vô hiệu hóa.');
    redirect('../login.php');
}

// Cập nhật thời gian hoạt động
refresh_session();

// Thiết lập timezone cho trang admin
date_default_timezone_set('Asia/Ho_Chi_Minh');
?>