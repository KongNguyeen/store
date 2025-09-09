<?php
// Email Configuration
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls'); // tls hoặc ssl
define('MAIL_USERNAME', 'congnbn45@gmail.com'); // Thay bằng email Gmail của bạn
define('MAIL_PASSWORD', 'mqex orvc rnfk bxeo'); // Thay bằng App Password 16 ký tự
define('MAIL_FROM_EMAIL', 'congnbn45@gmail.com'); // Thay bằng email Gmail của bạn
define('MAIL_FROM_NAME', 'KongNguyeen Store'); // Tên hiển thị khi gửi email

// Cấu hình để sử dụng email thật hay simulation
define('USE_REAL_EMAIL', true); // Đặt true để gửi email thật, false để chỉ log

/* 
HƯỚNG DẪN THIẾT LẬP GMAIL:

1. Bật 2-Step Verification cho tài khoản Gmail:
   - Vào Google Account Settings
   - Security > 2-Step Verification > Turn on

2. Tạo App Password:
   - Vào Google Account Settings  
   - Security > 2-Step Verification > App passwords
   - Chọn app: Mail
   - Chọn device: Other (Custom name) -> nhập "XAMPP Store"
   - Copy password được tạo

3. Cấu hình:
   - MAIL_USERNAME: your-email@gmail.com
   - MAIL_PASSWORD: app-password-vừa-tạo (16 ký tự)
   - MAIL_FROM_EMAIL: your-email@gmail.com

4. Đặt USE_REAL_EMAIL = true để kích hoạt gửi email thật

QUAN TRỌNG: 
- Không bao giờ commit file này với thông tin thật lên Git
- Sử dụng App Password, không phải mật khẩu Gmail thường
- Kiểm tra "Less secure app access" nếu cần (không khuyến khích)
*/
