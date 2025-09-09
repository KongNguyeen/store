<?php
// Include config để có BASE_URL và các constants
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Include email config
require_once __DIR__ . '/email_config.php';

// Chuyển hướng trang
function redirect($url) {
    header("Location: $url");
    exit();
}

// Kiểm tra đăng nhập
function is_logged_in() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

// Kiểm tra quyền admin - SỬA LẠI
function is_admin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

// Lấy thông tin user theo ID
function get_user_by_id($user_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Xử lý dữ liệu đầu vào an toàn
function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Thông báo flash - Cập nhật để đồng bộ
function flash($type, $message = null) {
    if ($message !== null) {
        // Set flash message
        $_SESSION['flash'][$type] = $message;
        return null;
    } else {
        // Get flash message
        if (isset($_SESSION['flash'][$type])) {
            $msg = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $msg;
        } elseif (isset($_SESSION['flash']) && isset($_SESSION['flash']['type']) && $_SESSION['flash']['type'] == $type) {
            // Hỗ trợ cấu trúc cũ
            $msg = $_SESSION['flash']['message'];
            unset($_SESSION['flash']);
            return $msg;
        }
    }
    return null;
}

// Lấy danh sách category cho dropdown
function get_categories() {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Kiểm tra email đã tồn tại chưa
function email_exists($email) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

// Tạo user mới (dùng cho đăng ký)
function create_user($role_id, $full_name, $email, $phone, $password) {
    $pdo = getPDO();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, email, phone, password, created_at, updated_at, is_active) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)");
    return $stmt->execute([$role_id, $full_name, $email, $phone, $hash]);
}

// Kiểm tra password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Format tiền tệ VND
function format_currency($amount) {
    return number_format($amount, 0, ',', '.') . ' đ';
}

// Lấy cart hiện tại của user
function get_user_cart($user_id) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT c.cart_id
        FROM carts c
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Lấy tổng số sản phẩm trong giỏ hàng
function get_cart_count($cart_id) {
    if (!$cart_id) return 0;
    
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as total
        FROM cart_items
        WHERE cart_id = ?
    ");
    $stmt->execute([$cart_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

// Upload hình ảnh
function upload_image($file, $destination_path) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    // Kiểm tra và tạo thư mục nếu chưa tồn tại
    if (!is_dir(dirname($destination_path))) {
        mkdir(dirname($destination_path), 0777, true);
    }
    
    // Kiểm tra định dạng file
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return false;
    }
    
    // Di chuyển file
    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        return true;
    }
    
    return false;
}

// Lấy danh sách sản phẩm phân trang
function get_products($page = 1, $limit = 12, $category_id = null) {
    $pdo = getPDO();
    
    $offset = ($page - 1) * $limit;
    $params = [];
    
    $sql = "
        SELECT p.*, c.name as category_name,
        (SELECT image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as primary_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE p.status = 'active'
    ";
    
    if ($category_id) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Đếm tổng số sản phẩm (dùng cho phân trang)
function count_products($category_id = null) {
    $pdo = getPDO();
    
    $sql = "SELECT COUNT(*) FROM products WHERE status = 'active'";
    $params = [];
    
    if ($category_id) {
        $sql .= " AND category_id = ?";
        $params[] = $category_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn();
}

// Tạo chuỗi phân trang
function generate_pagination($current_page, $total_pages) {
    $pagination = '';
    
    if ($total_pages > 1) {
        $pagination .= '<ul class="pagination pagination-sm">';
        
        // Previous page link
        if ($current_page > 1) {
            $prev_params = $_GET;
            $prev_params['page'] = $current_page - 1;
            $prev_url = '?' . http_build_query($prev_params);
            $pagination .= '<li class="page-item">
                <a class="page-link" href="' . $prev_url . '">
                    <i class="fas fa-chevron-left me-1"></i>Trước
                </a>
            </li>';
        } else {
            $pagination .= '<li class="page-item disabled">
                <span class="page-link">
                    <i class="fas fa-chevron-left me-1"></i>Trước
                </span>
            </li>';
        }
        
        // Page numbers
        $range = 2; // Number of pages to show on each side of current page
        $start = max(1, $current_page - $range);
        $end = min($total_pages, $current_page + $range);
        
        // Show first page if not in range
        if ($start > 1) {
            $page_params = $_GET;
            $page_params['page'] = 1;
            $page_url = '?' . http_build_query($page_params);
            $pagination .= '<li class="page-item">
                <a class="page-link" href="' . $page_url . '">1</a>
            </li>';
            
            if ($start > 2) {
                $pagination .= '<li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>';
            }
        }
        
        // Page range
        for ($i = $start; $i <= $end; $i++) {
            $page_params = $_GET;
            $page_params['page'] = $i;
            $page_url = '?' . http_build_query($page_params);
            
            if ($i == $current_page) {
                $pagination .= '<li class="page-item active">
                    <span class="page-link">' . $i . '</span>
                </li>';
            } else {
                $pagination .= '<li class="page-item">
                    <a class="page-link" href="' . $page_url . '">' . $i . '</a>
                </li>';
            }
        }
        
        // Show last page if not in range
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) {
                $pagination .= '<li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>';
            }
            
            $page_params = $_GET;
            $page_params['page'] = $total_pages;
            $page_url = '?' . http_build_query($page_params);
            $pagination .= '<li class="page-item">
                <a class="page-link" href="' . $page_url . '">' . $total_pages . '</a>
            </li>';
        }
        
        // Next page link
        if ($current_page < $total_pages) {
            $next_params = $_GET;
            $next_params['page'] = $current_page + 1;
            $next_url = '?' . http_build_query($next_params);
            $pagination .= '<li class="page-item">
                <a class="page-link" href="' . $next_url . '">
                    Tiếp<i class="fas fa-chevron-right ms-1"></i>
                </a>
            </li>';
        } else {
            $pagination .= '<li class="page-item disabled">
                <span class="page-link">
                    Tiếp<i class="fas fa-chevron-right ms-1"></i>
                </span>
            </li>';
        }
        
        $pagination .= '</ul>';
    }
    
    return $pagination;
}

// THÊM: Các functions bổ sung để tương thích với admin middleware

// Kiểm tra session có hợp lệ không
function is_session_valid() {
    return isset($_SESSION['last_activity']) && 
           (time() - $_SESSION['last_activity'] < SESSION_TIMEOUT);
}

// Refresh session activity
function refresh_session() {
    $_SESSION['last_activity'] = time();
}

// Kiểm tra CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

// Tạo CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Debug function (chỉ dùng khi development)
function debug_session() {
    if (defined('DEBUG') && DEBUG === true) {
        echo '<pre>';
        print_r($_SESSION);
        echo '</pre>';
    }
}

// Include email config
require_once __DIR__ . '/email_config.php';

// Hàm gửi email cho việc đặt lại mật khẩu - Cập nhật để hỗ trợ Gmail
function send_reset_password_email($email, $full_name, $reset_link) {
    // Luôn luôn log email để backup
    $log_success = log_email_to_file($email, $full_name, $reset_link);
    
    // Kiểm tra xem có gửi email thật không
    if (defined('USE_REAL_EMAIL') && USE_REAL_EMAIL && !empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD)) {
        return send_gmail_email($email, $full_name, $reset_link);
    }
    
    // Nếu không gửi email thật, chỉ trả về kết quả log
    return $log_success;
}

// Hàm log email vào file (backup)
function log_email_to_file($email, $full_name, $reset_link) {
    $log_dir = ROOT_PATH . 'storage/email_logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $email_content = "
========================================
Email Reset Password
========================================
To: {$email}
Subject: Đặt lại mật khẩu - " . SITE_NAME . "
Date: " . date('Y-m-d H:i:s') . "

Xin chào {$full_name},

Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.

Nhấp vào link bên dưới để đặt lại mật khẩu:
{$reset_link}

Lưu ý: Link này chỉ có hiệu lực trong 1 giờ.

Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.

Trân trọng,
" . SITE_NAME . "
========================================

";
    
    $log_file = $log_dir . '/reset_password_' . date('Y-m-d') . '.log';
    return file_put_contents($log_file, $email_content, FILE_APPEND | LOCK_EX) !== false;
}

// Hàm gửi email thật qua Gmail SMTP
function send_gmail_email($email, $full_name, $reset_link) {
    // Import PHPMailer classes
    require_once ROOT_PATH . 'vendor/autoload.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email, $full_name);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Đặt lại mật khẩu - ' . SITE_NAME;
        
        $mail->Body = get_reset_email_html_template($full_name, $reset_link);
        $mail->AltBody = get_reset_email_text_template($full_name, $reset_link);

        $mail->send();
        
        // Log thành công
        error_log("Email sent successfully to: {$email}");
        return true;
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // Log lỗi
        error_log("Email sending failed to {$email}: {$mail->ErrorInfo}");
        return false;
    }
}

// Template HTML cho email
function get_reset_email_html_template($full_name, $reset_link) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Đặt lại mật khẩu - " . SITE_NAME . "</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 30px; }
            .logo { font-size: 24px; font-weight: bold; color: #007bff; margin-bottom: 10px; }
            .button { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px; color: #666; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>" . SITE_NAME . "</div>
                <h2>Đặt lại mật khẩu</h2>
            </div>
            
            <p>Xin chào <strong>{$full_name}</strong>,</p>
            
            <p>Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.</p>
            
            <p>Nhấp vào nút bên dưới để đặt lại mật khẩu:</p>
            
            <div style='text-align: center;'>
                <a href='{$reset_link}' class='button'>Đặt lại mật khẩu</a>
            </div>
            
            <div class='warning'>
                <strong>Lưu ý quan trọng:</strong>
                <ul>
                    <li>Link này chỉ có hiệu lực trong <strong>1 giờ</strong></li>
                    <li>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này</li>
                    <li>Không chia sẻ link này với bất kỳ ai</li>
                </ul>
            </div>
            
            <p>Nếu nút không hoạt động, bạn có thể copy và paste link sau vào trình duyệt:</p>
            <p style='word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace;'>{$reset_link}</p>
            
            <div class='footer'>
                <p>Trân trọng,<br><strong>" . SITE_NAME . " Team</strong></p>
                <p><small>Email này được gửi tự động, vui lòng không reply.</small></p>
            </div>
        </div>
    </body>
    </html>";
}

// Template text cho email (fallback)
function get_reset_email_text_template($full_name, $reset_link) {
    return "
Xin chào {$full_name},

Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn tại " . SITE_NAME . ".

Nhấp vào link bên dưới để đặt lại mật khẩu:
{$reset_link}

LƯU Ý QUAN TRỌNG:
- Link này chỉ có hiệu lực trong 1 giờ
- Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này
- Không chia sẻ link này với bất kỳ ai

Trân trọng,
" . SITE_NAME . " Team

---
Email này được gửi tự động, vui lòng không reply.
";
}

// Hàm gửi email thực tế (legacy - giữ lại để tương thích)
function send_email_production($to, $subject, $message, $headers = '') {
    if (defined('USE_REAL_EMAIL') && USE_REAL_EMAIL && !empty(MAIL_USERNAME)) {
        // Sử dụng PHPMailer để gửi email
        require_once ROOT_PATH . 'vendor/autoload.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("Email error: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    return false;
}
?>