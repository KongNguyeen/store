<?php
// Include necessary files if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'navbar.php') {
    // Try to include the real configuration
    @include_once '../config/config.php';
    @include_once '../config/functions.php';
    
    // If not successful, define necessary constants
    if (!defined('BASE_URL')) define('BASE_URL', '../');
    if (!defined('SITE_NAME')) define('SITE_NAME', 'Store My KongNguyeen');
    
    // Start a session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set up basic session data for preview
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 999;
        $_SESSION['full_name'] = 'Preview User';
        $_SESSION['role_id'] = 2; // Regular user
    }
    
    // Initialize database connection for the navbar
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        // If database connection fails, we'll just use default avatar
        $pdo = null;
    }
    
    // Start HTML document for direct access
    echo '<!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>'. SITE_NAME .' - Navbar Preview</title>
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
        <style>
            body { 
                padding: 0;
                margin: 0;
                font-family: "Inter", sans-serif;
                background-color: #f8f9fa;
            }
            .preview-note {
                text-align: center;
                padding: 15px;
                margin: 20px auto;
                background-color: #e9f5ff;
                border-radius: 5px;
                border-left: 5px solid #007bff;
                max-width: 1200px;
            }
            
            /* CSS Variables for theming from index.php */
            :root {
                --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                --tertiary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                --border-radius: 8px;
                --card-border-radius: 12px;
                --button-border-radius: 8px;
                --box-shadow: 0 10px 20px rgba(0,0,0,0.05);
                --hover-transform: translateY(-5px);
            }
        </style>
    </head>
    <body>
    <div class="preview-note container">
        <h4>Navbar Preview Mode</h4>
        <p>This is a direct preview of the navbar component. For the full site experience, visit the <a href="../index.php">homepage</a>.</p>
    </div>';
}
?>
<style>
/* Navbar Styles */
.navbar {
    backdrop-filter: blur(15px);
    background: linear-gradient(135deg, 
        rgba(0, 123, 255, 0.1) 0%, 
        rgba(0, 86, 179, 0.05) 25%,
        rgba(255, 255, 255, 0.95) 50%,
        rgba(248, 249, 250, 0.98) 75%,
        rgba(0, 123, 255, 0.08) 100%) !important;
    border-bottom: 2px solid;
    border-image: linear-gradient(90deg, 
        transparent 0%, 
        rgba(0, 123, 255, 0.3) 25%, 
        rgba(0, 123, 255, 0.6) 50%, 
        rgba(0, 123, 255, 0.3) 75%, 
        transparent 100%) 1;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0, 123, 255, 0.1);
}

<?php 
// Initialize database connection for the navbar if not already initialized
if (!isset($pdo) || $pdo === null) {
    try {
        require_once(dirname(__FILE__) . '/../config/functions.php');
        $pdo = getPDO();
    } catch (Exception $e) {
        // If database connection fails, we'll just use default avatar
        $pdo = null;
    }
}
?>

.navbar.scrolled {
    background: linear-gradient(135deg, 
        rgba(0, 123, 255, 0.15) 0%, 
        rgba(255, 255, 255, 0.98) 30%,
        rgba(248, 249, 250, 0.99) 70%,
        rgba(0, 123, 255, 0.12) 100%) !important;
    box-shadow: 0 4px 25px rgba(0, 123, 255, 0.15);
    border-image: linear-gradient(90deg, 
        transparent 0%, 
        rgba(0, 123, 255, 0.4) 20%, 
        rgba(0, 123, 255, 0.8) 50%, 
        rgba(0, 123, 255, 0.4) 80%, 
        transparent 100%) 1;
}

/* Brand Styles */
.navbar-brand {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(45deg, #007bff, #0056b3, #004085);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    transition: all 0.3s ease;
    text-decoration: none !important;
    text-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
    position: relative;
}

.navbar-brand::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(45deg, #007bff, #0056b3);
    transition: width 0.3s ease;
}

.navbar-brand:hover::after {
    width: 100%;
}

.navbar-brand:hover {
    transform: scale(1.05);
    filter: brightness(1.2);
}

/* Navigation Links */
.navbar-nav .nav-link {
    font-weight: 500;
    color: #333 !important;
    padding: 0.5rem 0.75rem !important;
    position: relative;
    transition: all 0.3s ease;
    border-radius: 8px;
    margin: 0 0.15rem;
    font-size: 0.95rem;
}

.navbar-nav .nav-link::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: linear-gradient(45deg, #007bff, #0056b3);
    transition: all 0.3s ease;
    transform: translateX(-50%);
}

.navbar-nav .nav-link:hover::before,
.navbar-nav .nav-link.active::before {
    width: 80%;
}

.navbar-nav .nav-link:hover {
    color: #007bff !important;
    background: linear-gradient(135deg, 
        rgba(0, 123, 255, 0.1) 0%, 
        rgba(0, 123, 255, 0.05) 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2);
}

/* Dropdown Styles */
.dropdown-menu {
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    padding: 0.5rem 0;
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.98) 0%, 
        rgba(248, 249, 250, 0.95) 50%,
        rgba(255, 255, 255, 0.98) 100%);
    backdrop-filter: blur(15px);
    margin-top: 0.5rem;
    animation: dropdownFadeIn 0.3s ease-out;
    font-size: 0.9rem;
    border: 1px solid rgba(0, 123, 255, 0.1);
}

@keyframes dropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.dropdown-item {
    padding: 0.5rem 1rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border-radius: 6px;
    margin: 0 0.5rem;
    font-size: 0.9rem;
}

.dropdown-item:hover {
    background: linear-gradient(135deg, 
        rgba(0, 123, 255, 0.12) 0%, 
        rgba(0, 123, 255, 0.06) 50%,
        rgba(0, 123, 255, 0.08) 100%);
    color: #007bff;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
}

.dropdown-item.text-danger:hover {
    background: linear-gradient(135deg, 
        rgba(220, 53, 69, 0.12) 0%, 
        rgba(220, 53, 69, 0.06) 50%,
        rgba(220, 53, 69, 0.08) 100%);
    color: #dc3545 !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.15);
}

.dropdown-divider {
    margin: 0.5rem 1rem;
    border-color: rgba(0,0,0,0.1);
}

/* Cart Badge */
.cart-count {
    font-size: 0.7rem;
    min-width: 16px;
    height: 16px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
}

.cart-count.updated {
    animation: bounce 0.6s ease;
}

/* Mobile Toggle */
.navbar-toggler {
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.navbar-toggler:hover {
    background: rgba(0,123,255,0.1);
}

.navbar-toggler:focus {
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}

/* Search Bar Enhancement */
.search-container {
    position: relative;
    margin: 0 0.75rem;
}

.search-input {
    border: 2px solid rgba(0, 123, 255, 0.2);
    border-radius: 20px;
    padding: 0.4rem 0.75rem 0.4rem 2rem;
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.9) 0%, 
        rgba(248, 249, 250, 0.8) 100%);
    transition: all 0.3s ease;
    width: 200px;
    font-size: 0.9rem;
    backdrop-filter: blur(5px);
}

.search-input:focus {
    border-color: #007bff;
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.98) 0%, 
        rgba(248, 249, 250, 0.95) 100%);
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
    width: 250px;
}

.search-icon {
    position: absolute;
    left: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    transition: color 0.3s ease;
    font-size: 0.9rem;
}

.search-input:focus + .search-icon {
    color: #007bff;
}

/* Notifications */
.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: linear-gradient(45deg, #dc3545, #c82333);
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    font-size: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
}

/* Flash Messages */
.alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    animation: slideDown 0.5s ease-out;
    backdrop-filter: blur(10px);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: linear-gradient(135deg, 
        rgba(212, 237, 218, 0.95) 0%, 
        rgba(195, 230, 203, 0.9) 50%,
        rgba(212, 237, 218, 0.95) 100%);
    border-left: 4px solid #28a745;
    color: #155724;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.alert-danger {
    background: linear-gradient(135deg, 
        rgba(248, 215, 218, 0.95) 0%, 
        rgba(245, 198, 203, 0.9) 50%,
        rgba(248, 215, 218, 0.95) 100%);
    border-left: 4px solid #dc3545;
    color: #721c24;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

.alert-warning {
    background: linear-gradient(135deg, 
        rgba(255, 243, 205, 0.95) 0%, 
        rgba(255, 234, 167, 0.9) 50%,
        rgba(255, 243, 205, 0.95) 100%);
    border-left: 4px solid #ffc107;
    color: #856404;
    border: 1px solid rgba(255, 193, 7, 0.2);
}

.alert-info {
    background: linear-gradient(135deg, 
        rgba(209, 236, 241, 0.95) 0%, 
        rgba(190, 229, 235, 0.9) 50%,
        rgba(209, 236, 241, 0.95) 100%);
    border-left: 4px solid #17a2b8;
    color: #0c5460;
    border: 1px solid rgba(23, 162, 184, 0.2);
}

/* User Avatar */
.user-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #007bff;
    margin-right: 0.4rem;
    transition: all 0.3s ease;
}

.user-avatar:hover {
    transform: scale(1.1);
    box-shadow: 0 0 0 3px rgba(0,123,255,0.3);
}

/* Mobile Responsive */
@media (max-width: 991.98px) {
    .navbar-nav {
        padding: 1rem 0;
    }
    
    .navbar-nav .nav-link {
        margin: 0.25rem 0;
        border-radius: 8px;
    }
    
    .dropdown-menu {
        background: white;
        border: 1px solid rgba(0,0,0,0.1);
        margin-top: 0;
    }
    
    .search-input {
        width: 100%;
        margin: 0.5rem 0;
    }
    
    .search-input:focus {
        width: 100%;
    }
}

/* Admin specific styles - Removed for consistency */

/* Loading States */
.nav-loading {
    opacity: 0.7;
    pointer-events: none;
}

.nav-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    transform: translate(-50%, -50%);
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Animations */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes bounce {
    0%, 20%, 53%, 80%, 100% { transform: scale(1); }
    40%, 43% { transform: scale(1.3); }
    70% { transform: scale(1.1); }
    90% { transform: scale(1.05); }
}

/* Search suggestions */
.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    z-index: 1000;
    display: none;
    max-height: 300px;
    overflow-y: auto;
}

.search-suggestion-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.2s ease;
}

.search-suggestion-item:hover {
    background: #f8f9fa;
}

.search-suggestion-item:last-child {
    border-bottom: none;
}

.search-item-image {
    width: 32px;
    height: 32px;
    object-fit: cover;
    border-radius: 4px;
}

.search-item-name {
    font-weight: 500;
    color: #333;
    font-size: 0.9rem;
}

.search-item-price {
    font-size: 0.8rem;
    color: #007bff;
    font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
    .navbar * {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" id="mainNavbar">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL ?>"><?= SITE_NAME ?></a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Left navigation - Show for all logged in users and guests -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" 
                       href="<?= BASE_URL ?>">
                        <i class="fas fa-home me-1"></i>Trang chủ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>" 
                       href="<?= BASE_URL ?>products.php">
                        <i class="fas fa-box me-1"></i>Sản phẩm
                    </a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>" 
                           href="<?= BASE_URL ?>orders.php">
                            <i class="fas fa-shopping-bag me-1"></i>Đơn hàng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'feedbacks.php' ? 'active' : '' ?>" 
                           href="<?= BASE_URL ?>feedbacks.php">
                            <i class="fas fa-headset me-1"></i>Hỗ trợ
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

           

            <ul class="navbar-nav ms-auto align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role_id'] == 1): ?>
                        <!-- Admin Navigation - Simplified dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                                <?php
                                // Get admin's avatar from database to ensure it's the same as profile
                                $avatar_path = 'assets/images/avatar-default.jpg';
                                if (isset($pdo) && $pdo !== null) {
                                    try {
                                        $avatar_stmt = $pdo->prepare("SELECT avatar FROM users WHERE user_id = ?");
                                        $avatar_stmt->execute([$_SESSION['user_id']]);
                                        $avatar_result = $avatar_stmt->fetch();
                                        if ($avatar_result && !empty($avatar_result['avatar'])) {
                                            $avatar_path = $avatar_result['avatar'];
                                        }
                                    } catch (Exception $e) {
                                        // In case of error, use default avatar
                                    }
                                }
                                ?>
                                <img src="<?= BASE_URL . $avatar_path ?>" alt="Avatar" class="user-avatar">
                                <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="admin/index.php" id="adminDashboardLink">
                                        <i class="fas fa-tachometer-alt fa-fw me-2"></i>
                                        Quản trị
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php">
                                        <i class="fas fa-sign-out-alt fa-fw me-2"></i>
                                        Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Regular User Navigation - Full menu -->
                        <!-- Shopping cart -->
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="<?= BASE_URL ?>cart.php" title="Giỏ hàng" id="cartLink">
                                <i class="fas fa-shopping-cart"></i>
                            </a>
                        </li>

                        <!-- Notifications -->
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" title="Thông báo">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge" id="notificationCount" style="display: none;">0</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                                <li class="dropdown-header">
                                    <strong>Thông báo</strong>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li class="px-3 py-2">
                                    <div class="text-muted text-center">Không có thông báo mới</div>
                                </li>
                            </ul>
                        </li>

                        <!-- User dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                                <?php
                                // Get user's avatar from database to ensure it's the same as profile
                                $avatar_path = 'assets/images/avatar-default.jpg';
                                if (isset($pdo) && $pdo !== null) {
                                    try {
                                        $avatar_stmt = $pdo->prepare("SELECT avatar FROM users WHERE user_id = ?");
                                        $avatar_stmt->execute([$_SESSION['user_id']]);
                                        $avatar_result = $avatar_stmt->fetch();
                                        if ($avatar_result && !empty($avatar_result['avatar'])) {
                                            $avatar_path = $avatar_result['avatar'];
                                        }
                                    } catch (Exception $e) {
                                        // In case of error, use default avatar
                                    }
                                }
                                ?>
                                <img src="<?= BASE_URL . $avatar_path ?>" alt="Avatar" class="user-avatar">
                                <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>profile.php">
                                        <i class="fas fa-user fa-fw me-2"></i>
                                        Tài khoản
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>orders.php">
                                        <i class="fas fa-shopping-bag fa-fw me-2"></i>
                                        Đơn hàng của tôi
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>feedbacks.php">
                                        <i class="fas fa-headset fa-fw me-2"></i>
                                        Hỗ trợ khách hàng
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php">
                                        <i class="fas fa-sign-out-alt fa-fw me-2"></i>
                                        Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Guest Navigation -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-primary ms-2 px-3" href="<?= BASE_URL ?>register.php">
                            <i class="fas fa-user-plus me-1"></i>Đăng ký
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Chatbot AI Widget -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/chatbot.css">
<script src="<?= BASE_URL ?>js/chatbot.js"></script>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.getElementById('mainNavbar');
    const searchInput = document.getElementById('searchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');
    const cartCount = document.getElementById('cartCount');
    const notificationCount = document.getElementById('notificationCount');

    // Navbar scroll effect
    let lastScrollY = window.scrollY;
    window.addEventListener('scroll', function() {
        const currentScrollY = window.scrollY;
        
        if (currentScrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Hide/show navbar on scroll
        if (currentScrollY > lastScrollY && currentScrollY > 100) {
            navbar.style.transform = 'translateY(-100%)';
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScrollY = currentScrollY;
    });

    // Search functionality - Always available
    let searchTimeout;
    if (searchInput) {
        // Handle Enter key press
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchValue = this.value.trim();
                if (searchValue) {
                    window.location.href = `${BASE_URL}products.php?search=${encodeURIComponent(searchValue)}`;
                }
            }
        });

        // Handle search suggestions
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            } else {
                hideSearchSuggestions();
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                hideSearchSuggestions();
            }
        });
    }

    // Cart count animation - Only for regular users
    function updateCartCount(count) {
        if (cartCount) {
            if (count > 0) {
                cartCount.textContent = count;
                cartCount.style.display = 'flex';
                cartCount.classList.add('updated');
                setTimeout(() => cartCount.classList.remove('updated'), 600);
            } else {
                cartCount.style.display = 'none';
            }
        }
    }

    // Load initial counts - Always try to load for all users
    loadCartCount();
    // loadNotificationCount(); // TODO: Implement notification system

    // Dropdown enhancements
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('shown.bs.dropdown', function() {
            this.setAttribute('aria-expanded', 'true');
        });
        
        dropdown.addEventListener('hidden.bs.dropdown', function() {
            this.setAttribute('aria-expanded', 'false');
        });
    });

    // Mobile menu enhancements
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.getElementById('navbarMain');
    
    if (navbarToggler) {
        navbarToggler.addEventListener('click', function() {
            setTimeout(() => {
                if (navbarCollapse.classList.contains('show')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }, 300);
        });
    }

    // Close mobile menu when clicking on links
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                const collapse = new bootstrap.Collapse(navbarCollapse, {
                    hide: true
                });
            }
        });
    });
});

// Search function - Always available
function performSearch(query) {
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (!searchSuggestions) return;
    
    // Show loading
    searchSuggestions.innerHTML = `
        <div class="search-suggestion-item">
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <span>Đang tìm kiếm...</span>
            </div>
        </div>
    `;
    searchSuggestions.style.display = 'block';

    // Perform AJAX search
    fetch(`${BASE_URL}api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchSuggestions(data);
        })
        .catch(error => {
            console.error('Search error:', error);
            hideSearchSuggestions();
        });
}

// Display search suggestions
function displaySearchSuggestions(results) {
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (!searchSuggestions) return;
    
    if (results.length === 0) {
        searchSuggestions.innerHTML = `
            <div class="search-suggestion-item">
                <div class="text-muted">Không tìm thấy kết quả</div>
            </div>
        `;
    } else {
        let html = '';
        results.slice(0, 5).forEach(item => {
            html += `
                <div class="search-suggestion-item" onclick="selectSearchItem('${item.id}', '${item.type}')">
                    <div class="d-flex align-items-center">
                        <img src="${item.image}" alt="${item.name}" class="search-item-image me-2">
                        <div>
                            <div class="search-item-name">${item.name}</div>
                            <div class="search-item-price text-muted">${item.price}</div>
                        </div>
                    </div>
                </div>
            `;
        });
        searchSuggestions.innerHTML = html;
    }
    
    searchSuggestions.style.display = 'block';
}

// Hide search suggestions
function hideSearchSuggestions() {
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (searchSuggestions) {
        searchSuggestions.style.display = 'none';
    }
}

// Select search item
function selectSearchItem(id, type) {
    if (type === 'product') {
        window.location.href = `${BASE_URL}product.php?id=${id}`;
    } else if (type === 'category') {
        window.location.href = `${BASE_URL}category.php?id=${id}`;
    }
}

// Load cart count - Available for all users
function loadCartCount() {
    const cartCount = document.getElementById('cartCount');
    if (!cartCount) return;
    
    fetch(`${BASE_URL}api/cart-count.php`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartCount(data.count);
            }
        })
        .catch(error => console.error('Error loading cart count:', error));
}


// Global functions for updating counts - Available for all users
window.updateCartCount = function(count) {
    const cartCount = document.getElementById('cartCount');
    if (cartCount) {
        if (count > 0) {
            cartCount.textContent = count;
            cartCount.style.display = 'flex';
            cartCount.classList.add('updated');
            setTimeout(() => cartCount.classList.remove('updated'), 600);
        } else {
            cartCount.style.display = 'none';
        }
    }
};


</script>

<?php
// Add closing tags if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'navbar.php') {
    echo '
    <div class="preview-note container mt-4">
        <h5>Why does this look different from the homepage?</h5>
        <p>The navbar in the homepage loads with additional CSS and JavaScript from the main layout. This preview loads only the essential styles.</p>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize with mock data when directly accessed
        document.addEventListener("DOMContentLoaded", function() {
            // Show a sample cart count
            updateCartCount(3);
            
            // Basic search functionality for preview
            const searchInput = document.getElementById("searchInput");
            if (searchInput) {
                searchInput.addEventListener("focus", function() {
                    const suggestions = document.getElementById("searchSuggestions");
                    if (suggestions) {
                        suggestions.innerHTML = `
                            <div class="search-item" onclick="selectSearchItem(1, \'product\')">
                                <div class="d-flex align-items-center">
                                    <div style="width: 40px; height: 40px; background: #f8f9fa; border-radius: 4px;" class="me-2"></div>
                                    <div>
                                        <div class="search-item-name">Sample Product 1</div>
                                        <div class="search-item-price text-muted">99.000₫</div>
                                    </div>
                                </div>
                            </div>
                            <div class="search-item" onclick="selectSearchItem(2, \'product\')">
                                <div class="d-flex align-items-center">
                                    <div style="width: 40px; height: 40px; background: #f8f9fa; border-radius: 4px;" class="me-2"></div>
                                    <div>
                                        <div class="search-item-name">Sample Product 2</div>
                                        <div class="search-item-price text-muted">149.000₫</div>
                                    </div>
                                </div>
                            </div>
                        `;
                        suggestions.style.display = "block";
                    }
                });
                
                // Close suggestions when clicking outside
                document.addEventListener("click", function(e) {
                    if (!e.target.closest(".search-container")) {
                        const suggestions = document.getElementById("searchSuggestions");
                        if (suggestions) {
                            suggestions.style.display = "none";
                        }
                    }
                });
            }
            
            // Add smooth scrolling effect
            window.addEventListener("scroll", function() {
                const navbar = document.getElementById("mainNavbar");
                if (navbar) {
                    if (window.scrollY > 50) {
                        navbar.classList.add("scrolled");
                    } else {
                        navbar.classList.remove("scrolled");
                    }
                }
            });
        });
    </script>
    </body>
    </html>';
}
?>