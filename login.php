<?php
require_once 'config/config.php';
require_once 'config/functions.php';
require_once 'config/security_config.php';

function sanitize_redirect_target($target, $fallback = 'index.php') {
    $target = trim((string)$target);

    if ($target === '') {
        return $fallback;
    }

    $target = str_replace(["\r", "\n", "\0"], '', $target);

    // Reject absolute/protocol-relative URLs.
    if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $target) || strpos($target, '//') === 0) {
        return $fallback;
    }

    // Normalize root-based app URLs.
    if (strpos($target, '/store/') === 0) {
        $target = substr($target, strlen('/store/'));
    } elseif (strpos($target, '/') === 0) {
        $target = ltrim($target, '/');
    }

    $path = parse_url($target, PHP_URL_PATH);
    $query = parse_url($target, PHP_URL_QUERY);

    if ($path === false || $path === null || $path === '') {
        return $fallback;
    }

    $normalized_path = ltrim($path, '/');
    if (
        strpos($normalized_path, '..') !== false ||
        !preg_match('/^(?:admin\/)?[A-Za-z0-9_\-\/]+\.php$/', $normalized_path)
    ) {
        return $fallback;
    }

    return $normalized_path . ($query ? ('?' . $query) : '');
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = sanitize_redirect_target($_SESSION['redirect_url'] ?? 'index.php');
    unset($_SESSION['redirect_url']);
    redirect($redirect);
}

// Store redirect URL from GET parameter
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_url'] = sanitize_redirect_target($_GET['redirect']);
}

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } else {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("
                SELECT user_id, full_name, email, password, role_id, is_active, avatar
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Email không tồn tại';
            } elseif (!$user['is_active']) {
                $error = 'Tài khoản đã bị khóa';
            } else {
                // Kiểm tra mật khẩu - hỗ trợ cả text và hash
                $password_valid = false;
                
                if (defined('USE_PLAIN_PASSWORD') && USE_PLAIN_PASSWORD) {
                    // So sánh text thường
                    $password_valid = ($password === $user['password']);
                } else {
                    // Kiểm tra hash hoặc text (tương thích)
                    if (password_verify($password, $user['password'])) {
                        $password_valid = true;
                    } elseif ($password === $user['password']) {
                        // Fallback cho mật khẩu text cũ
                        $password_valid = true;
                    }
                }
                
                if (!$password_valid) {
                    $error = 'Mật khẩu không chính xác';
                } else {
                    // Clear all existing session data
                    session_unset();
                    
                    // Regenerate session ID
                    session_regenerate_id(true);
                    
                    // Set session data
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role'] = ($user['role_id'] == 1) ? 'admin' : 'user';
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['avatar'] = $user['avatar'] ?: 'assets/images/avatar-default.jpg';
                    $_SESSION['login_time'] = time();
                    
                    // Generate new CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['csrf_time'] = time();

                    // Update last login time
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET updated_at = NOW() 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$user['user_id']]);

                    // Set success flag for JavaScript animation
                    $success = 'Đăng nhập thành công! Chào mừng ' . htmlspecialchars($user['full_name']) . ' trở lại.';
                    
                    // Store redirect URL in session for later use
                    $_SESSION['post_login_redirect'] = sanitize_redirect_target(
                        $_SESSION['redirect_url'] ?? ($user['role_id'] == 1 ? 'admin/index.php' : 'index.php')
                    );
                    unset($_SESSION['redirect_url']);
                    
                    // Don't redirect immediately - let JavaScript handle the animation first
                }
            } // Đóng khối else chính
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Có lỗi xảy ra, vui lòng thử lại sau';
        }
    }
}

// Set page title
$page_title = 'Đăng nhập';

// Add custom CSS and JS
$additional_css = ['auth.css'];
$additional_js = ['auth.js'];

include 'includes/navbar.php';
?>

<link rel="stylesheet" href="css/login.css">

<div class="auth-page">
    <div class="container">
        <div class="card auth-card">
            <div class="card-body">
                <h1 class="card-title">Đăng nhập</h1>

                <?php if (isset($_GET['expired'])): ?>
                    <div class="alert alert-warning alert-enhanced">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Phiên làm việc đã hết hạn, vui lòng đăng nhập lại
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['inactive'])): ?>
                    <div class="alert alert-warning alert-enhanced">
                        <i class="fas fa-user-lock me-2"></i>
                        Tài khoản đã bị khóa, vui lòng liên hệ quản trị viên
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success alert-enhanced">
                        <i class="fas fa-check-circle me-2"></i>
                        Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-enhanced" id="error-alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-form" id="loginForm">
                    <div class="form-group email-group">
                        <input type="email" class="form-control" name="email" 
                               value="<?= htmlspecialchars($email) ?>" required autofocus placeholder=" " autocomplete="email">
                        <label class="form-label">Email</label>
                    </div>

                    <div class="form-group password-group">
                        <input type="password" class="form-control" name="password" required placeholder=" " autocomplete="current-password">
                        <label class="form-label">Mật khẩu</label>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember-me" name="remember">
                            <label class="form-check-label" for="remember-me">
                                Ghi nhớ đăng nhập
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                        <span class="loading-spinner"></span>
                        <i class="fas fa-sign-in-alt me-2" id="login-icon"></i>
                        <span id="login-text">Đăng nhập</span>
                    </button>
                </form>

                <div class="auth-divider">
                    <span>hoặc</span>
                </div>

                <div class="auth-links">
                    <p class="mb-2">
                        Chưa có tài khoản? 
                        <a href="register.php">Đăng ký ngay</a>
                    </p>
                    <p class="mb-0">
                        <a href="forgot-password.php" class="text-muted">
                            <i class="fas fa-key me-1"></i>Quên mật khẩu?
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Overlay -->
<div class="success-overlay" id="successOverlay">
    <div class="success-content">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="success-text" id="successTitle">Đăng nhập thành công!</div>
        <div class="success-subtext" id="successMessage">Đang chuyển hướng...</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const loadingSpinner = document.querySelector('.loading-spinner');
    const loginIcon = document.getElementById('login-icon');
    const loginText = document.getElementById('login-text');
    const errorAlert = document.getElementById('error-alert');

    // Enhanced form interactions
    const formGroups = document.querySelectorAll('.form-group');
    const inputs = document.querySelectorAll('.form-control');

    // Handle floating labels only for email and password inputs
    inputs.forEach(input => {
        const label = input.nextElementSibling;
        
        // Only apply floating label logic to inputs with labels that should float
        if (!label || !label.classList.contains('form-label') || 
            (!input.closest('.email-group') && !input.closest('.password-group'))) {
            return;
        }
        
        // Check if input has value on page load
        if (input.value.trim()) {
            label.classList.add('active');
        }

        input.addEventListener('focus', function() {
            label.classList.add('active', 'gradient-text');
            
            // Typing effect for label text
            if (!label.classList.contains('typed')) {
                label.classList.add('typing-effect', 'typed');
                setTimeout(() => {
                    label.classList.remove('typing-effect');
                }, 1500);
            }
        });

        input.addEventListener('blur', function() {
            if (!input.value.trim()) {
                label.classList.remove('active', 'gradient-text');
            } else {
                label.classList.remove('gradient-text');
            }
        });

        input.addEventListener('input', function() {
            if (input.value.trim()) {
                label.classList.add('active');
            }
        });
    });

    // Add form submission loading effect
    loginForm.addEventListener('submit', function(e) {
        if (!loginForm.checkValidity()) {
            return;
        }

        // Show loading state
        loginBtn.classList.add('btn-loading');
        loadingSpinner.style.display = 'inline-block';
        loginIcon.style.display = 'none';
        loginText.textContent = 'Đang đăng nhập...';
        loginBtn.disabled = true;
    });

    // Shake effect for errors
    <?php if ($error): ?>
    if (errorAlert) {
        loginForm.classList.add('form-shake');
        setTimeout(() => {
            loginForm.classList.remove('form-shake');
        }, 500);
    }
    <?php endif; ?>

    // Success animation
    <?php if ($success): ?>
    setTimeout(() => {
        showLoginSuccess();
    }, 500);

    function showLoginSuccess() {
        const overlay = document.getElementById('successOverlay');
        const successTitle = document.getElementById('successTitle');
        const successMessage = document.getElementById('successMessage');
        
        // Update messages
        successTitle.textContent = 'Đăng nhập thành công!';
        successMessage.textContent = '<?= addslashes($success) ?>';
        
        // Ensure overlay covers full viewport
        document.body.style.overflow = 'hidden';
        
        // Show overlay with flex display for perfect centering
        overlay.style.display = 'flex';
        
        // Force reflow to ensure proper positioning
        overlay.offsetHeight;
        
        // Add confetti effect (optional)
        createConfetti();
        
        // Redirect after animation
        setTimeout(() => {
            successMessage.textContent = 'Đang chuyển hướng...';
            setTimeout(() => {
                document.body.style.overflow = '';
                window.location.href = <?= json_encode(sanitize_redirect_target($_SESSION['post_login_redirect'] ?? 'index.php')) ?>;
            }, 1000);
        }, 2500);
    }

    // Simple confetti effect
    function createConfetti() {
        const colors = ['#28a745', '#20c997', '#17a2b8', '#6f42c1', '#e83e8c'];
        const confettiCount = 50;
        
        for (let i = 0; i < confettiCount; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-10px';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.borderRadius = '50%';
                confetti.style.pointerEvents = 'none';
                confetti.style.zIndex = '10000';
                confetti.style.animation = `confetti-fall ${Math.random() * 2 + 3}s linear forwards`;
                
                document.body.appendChild(confetti);
                
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.parentNode.removeChild(confetti);
                    }
                }, 5000);
            }, i * 50);
        }
    }

    // Add confetti animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    <?php endif; ?>

    // Enhanced form validation
    const formInputs = loginForm.querySelectorAll('input[required]');
    formInputs.forEach(input => {
        input.addEventListener('invalid', function(e) {
            e.preventDefault();
            this.classList.add('is-invalid');
            const label = this.nextElementSibling;
            if (label) {
                label.style.color = '#dc3545';
                label.style.animation = 'shake 0.5s ease-in-out';
            }
            
            // Remove invalid class after user starts typing
            this.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const label = this.nextElementSibling;
                if (label) {
                    label.style.color = '';
                    label.style.animation = '';
                }
            }, { once: true });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<style>
/* Hide footer on auth pages */

</style>