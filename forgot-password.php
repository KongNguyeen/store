<?php
require_once 'config/config.php';
require_once 'config/functions.php';
require_once 'config/security_config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';
$success = '';
$email = '';
$step = 'forgot'; // forgot hoặc reset
$token = '';

// Kiểm tra xem có token không để xác định step
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $step = 'reset';
    $token = $_GET['token'];
    
    // Verify token
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch();
        
        if (!$reset_data) {
            $error = 'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
            $step = 'forgot'; // Quay về trang forgot nếu token không hợp lệ
        } else {
            $email = $reset_data['email'];
        }
    } catch (PDOException $e) {
        error_log('Token verification error: ' . $e->getMessage());
        $error = 'Có lỗi xảy ra, vui lòng thử lại sau.';
        $step = 'forgot';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'forgot') {
        // Xử lý forgot password
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if (!$email) {
            $error = 'Vui lòng nhập email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ';
        } else {
            try {
                $pdo = getPDO();
                
                // Check if email exists
                $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token in database
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (email, token, expires_at, created_at, used) 
                        VALUES (?, ?, ?, NOW(), 0)
                        ON DUPLICATE KEY UPDATE 
                        token = VALUES(token), 
                        expires_at = VALUES(expires_at), 
                        created_at = NOW(),
                        used = 0
                    ");
                    $stmt->execute([$email, $token, $expires]);
                    
                    // Prepare reset link
                    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "?token=" . $token;
                    
                    // Send email using custom function
                    if (send_reset_password_email($email, $user['full_name'], $resetLink)) {
                        $success = 'Chúng tôi đã gửi link đặt lại mật khẩu đến email ' . htmlspecialchars($email) . '. Vui lòng kiểm tra hộp thư của bạn.';
                        $email = ''; // Clear email field on success
                    } else {
                        $error = 'Có lỗi xảy ra khi gửi email. Vui lòng thử lại sau.';
                    }
                } else {
                    // For security, show same success message even if email doesn't exist
                    $success = 'Nếu email này tồn tại trong hệ thống, chúng tôi đã gửi link đặt lại mật khẩu đến email của bạn.';
                    $email = '';
                }
            } catch (PDOException $e) {
                error_log('Forgot password error: ' . $e->getMessage());
                $error = 'Có lỗi xảy ra, vui lòng thử lại sau';
            }
        }
    } elseif ($step === 'reset') {
        // Xử lý reset password
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $reset_token = $_POST['token'] ?? '';
        
        if (!$new_password) {
            $error = 'Vui lòng nhập mật khẩu mới';
        } elseif (strlen($new_password) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Xác nhận mật khẩu không khớp';
        } else {
            try {
                $pdo = getPDO();
                
                // Verify token again
                $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
                $stmt->execute([$reset_token]);
                $reset_data = $stmt->fetch();
                
                if (!$reset_data) {
                    $error = 'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
                } else {
                    // Update password - Kiểm tra cài đặt bảo mật
                    if (defined('USE_PLAIN_PASSWORD') && USE_PLAIN_PASSWORD) {
                        // CẢNH BÁO: Lưu mật khẩu dạng text (chỉ cho testing)
                        $password_to_save = $new_password;
                        error_log("WARNING: Saving plain text password for email: {$reset_data['email']}");
                    } else {
                        // An toàn: Hash mật khẩu
                        $password_to_save = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
                    $stmt->execute([$password_to_save, $reset_data['email']]);
                    
                    // Mark token as used
                    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                    $stmt->execute([$reset_token]);
                    
                    $success = 'Mật khẩu đã được đặt lại thành công. Bạn có thể đăng nhập với mật khẩu mới.';
                    $step = 'success'; // Chuyển sang step success
                }
            } catch (PDOException $e) {
                error_log('Reset password error: ' . $e->getMessage());
                $error = 'Có lỗi xảy ra, vui lòng thử lại sau';
            }
        }
    }
}

// Set page title
if ($step === 'reset') {
    $page_title = 'Đặt lại mật khẩu';
} elseif ($step === 'success') {
    $page_title = 'Thành công';
} else {
    $page_title = 'Quên mật khẩu';
}

// Add custom CSS and JS
$additional_css = ['auth.css'];
$additional_js = ['auth.js'];

?>

    <link rel="stylesheet" href="css/forgot-password.css">

<div class="auth-page">
    <div class="container">
        <div class="card auth-card">
            <div class="card-body">
                <?php if ($step === 'forgot'): ?>
                    <h1 class="card-title">Quên mật khẩu</h1>
                    <p class="card-subtitle">Nhập email của bạn và chúng tôi sẽ gửi link đặt lại mật khẩu đến email đó</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-enhanced" id="error-alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-enhanced" id="success-alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="auth-form" id="forgotForm">
                        <div class="form-group email-group">
                            <input type="email" class="form-control" name="email" 
                                   value="<?= htmlspecialchars($email) ?>" required autofocus placeholder=" " autocomplete="email">
                            <label class="form-label">Email</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="forgotBtn">
                            <span class="loading-spinner"></span>
                            <i class="fas fa-paper-plane me-2" id="forgot-icon"></i>
                            <span id="forgot-text">Gửi link đặt lại mật khẩu</span>
                        </button>
                    </form>

                    <div class="auth-divider">
                        <span>hoặc</span>
                    </div>

                    <div class="auth-links">
                        <p class="mb-2">
                            <a href="login.php">← Quay lại đăng nhập</a>
                        </p>
                        <p class="mb-0">
                            Chưa có tài khoản? 
                            <a href="register.php">Đăng ký ngay</a>
                        </p>
                    </div>

                <?php elseif ($step === 'reset'): ?>
                    <h1 class="card-title">Đặt lại mật khẩu</h1>
                    <p class="card-subtitle">Nhập mật khẩu mới cho tài khoản <strong><?= htmlspecialchars($email) ?></strong></p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-enhanced" id="error-alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="auth-form" id="resetForm">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        
                        <div class="form-group password-group">
                            <input type="password" class="form-control" name="new_password" 
                                   required autofocus placeholder=" " autocomplete="new-password">
                            <label class="form-label">Mật khẩu mới</label>
                        </div>

                        <div class="form-group password-group">
                            <input type="password" class="form-control" name="confirm_password" 
                                   required placeholder=" " autocomplete="new-password">
                            <label class="form-label">Xác nhận mật khẩu</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="resetBtn">
                            <span class="loading-spinner"></span>
                            <i class="fas fa-key me-2" id="reset-icon"></i>
                            <span id="reset-text">Đặt lại mật khẩu</span>
                        </button>
                    </form>

                    <div class="auth-divider">
                        <span>hoặc</span>
                    </div>

                    <div class="auth-links">
                        <p class="mb-0">
                            <a href="forgot-password.php">← Quay lại gửi lại email</a>
                        </p>
                    </div>

                <?php else: // step === 'success' ?>
                    <div class="text-center">
                        <div class="success-icon-large mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h1 class="card-title text-success">Thành công!</h1>
                        <p class="card-subtitle"><?= $success ?></p>
                        
                        <a href="login.php" class="btn btn-primary mt-3">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Đăng nhập ngay
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Success Overlay -->
<div class="success-overlay" id="successOverlay">
    <div class="success-content">
        <div class="success-icon">
            <i class="fas fa-envelope-check"></i>
        </div>
        <div class="success-text" id="successTitle">Email đã được gửi!</div>
        <div class="success-subtext" id="successMessage">Vui lòng kiểm tra hộp thư của bạn</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forgotForm = document.getElementById('forgotForm');
    const resetForm = document.getElementById('resetForm');
    const forgotBtn = document.getElementById('forgotBtn');
    const resetBtn = document.getElementById('resetBtn');
    const loadingSpinner = document.querySelector('.loading-spinner');
    const errorAlert = document.getElementById('error-alert');
    const successAlert = document.getElementById('success-alert');

    // Enhanced form interactions
    const inputs = document.querySelectorAll('.form-control');

    // Handle floating labels
    inputs.forEach(input => {
        const label = input.nextElementSibling;
        
        // Only apply floating label logic to inputs with labels that should float
        if (!label || !label.classList.contains('form-label')) {
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

    // Add form submission loading effect for forgot form
    if (forgotForm) {
        forgotForm.addEventListener('submit', function(e) {
            if (!forgotForm.checkValidity()) {
                return;
            }

            // Show loading state
            forgotBtn.classList.add('btn-loading');
            const spinner = forgotBtn.querySelector('.loading-spinner');
            const icon = forgotBtn.querySelector('#forgot-icon');
            const text = forgotBtn.querySelector('#forgot-text');
            
            spinner.style.display = 'inline-block';
            icon.style.display = 'none';
            text.textContent = 'Đang gửi...';
            forgotBtn.disabled = true;
        });
    }

    // Add form submission loading effect for reset form
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            if (!resetForm.checkValidity()) {
                return;
            }

            // Show loading state
            resetBtn.classList.add('btn-loading');
            const spinner = resetBtn.querySelector('.loading-spinner');
            const icon = resetBtn.querySelector('#reset-icon');
            const text = resetBtn.querySelector('#reset-text');
            
            spinner.style.display = 'inline-block';
            icon.style.display = 'none';
            text.textContent = 'Đang xử lý...';
            resetBtn.disabled = true;
        });
    }

    // Shake effect for errors
    <?php if ($error): ?>
    if (errorAlert) {
        const currentForm = forgotForm || resetForm;
        if (currentForm) {
            currentForm.classList.add('form-shake');
            setTimeout(() => {
                currentForm.classList.remove('form-shake');
            }, 500);
        }
    }
    <?php endif; ?>

    // Success animation for forgot password
    <?php if ($success && $step === 'forgot'): ?>
    setTimeout(() => {
        showForgotSuccess();
    }, 500);

    function showForgotSuccess() {
        const overlay = document.getElementById('successOverlay');
        const successTitle = document.getElementById('successTitle');
        const successMessage = document.getElementById('successMessage');
        
        // Update messages
        successTitle.textContent = 'Email đã được gửi!';
        successMessage.textContent = 'Vui lòng kiểm tra hộp thư của bạn và làm theo hướng dẫn để đặt lại mật khẩu.';
        
        // Ensure overlay covers full viewport
        document.body.style.overflow = 'hidden';
        
        // Show overlay with flex display for perfect centering
        overlay.style.display = 'flex';
        
        // Force reflow to ensure proper positioning
        overlay.offsetHeight;
        
        // Add confetti effect
        createConfetti();
        
        // Show email check message after animation
        setTimeout(() => {
            successMessage.textContent = 'Bạn có thể đóng trang này và kiểm tra email.';
        }, 2500);
    }
    <?php endif; ?>

    // Success animation for reset password
    <?php if ($step === 'success'): ?>
    // Add success animation for the check icon
    const successIcon = document.querySelector('.success-icon-large i');
    if (successIcon) {
        successIcon.style.opacity = '0';
        successIcon.style.transform = 'scale(0)';
        setTimeout(() => {
            successIcon.style.transition = 'all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            successIcon.style.opacity = '1';
            successIcon.style.transform = 'scale(1)';
        }, 300);
        
        // Add bounce animation after scaling
        setTimeout(() => {
            successIcon.style.animation = 'bounce 0.6s ease-in-out';
        }, 900);
    }
    <?php endif; ?>

    // Enhanced form validation
    const formInputs = document.querySelectorAll('input[required]');
    formInputs.forEach(input => {
        input.addEventListener('invalid', function(e) {
            e.preventDefault();
            this.classList.add('is-invalid');
            const label = this.nextElementSibling;
            if (label && label.classList.contains('form-label')) {
                label.style.color = '#dc3545';
                label.style.animation = 'shake 0.5s ease-in-out';
            }
            
            // Remove invalid class after user starts typing
            this.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const label = this.nextElementSibling;
                if (label && label.classList.contains('form-label')) {
                    label.style.color = '';
                    label.style.animation = '';
                }
            }, { once: true });
        });
    });

    // Real-time email validation
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', function(e) {
            const email = e.target.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && emailRegex.test(email)) {
                e.target.style.borderColor = '#28a745';
            } else if (email) {
                e.target.style.borderColor = '#ffc107';
            } else {
                e.target.style.borderColor = '';
            }
        });
    }

    // Password strength validation
    const passwordInputs = document.querySelectorAll('input[name="new_password"], input[name="confirm_password"]');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            const password = e.target.value;
            
            if (e.target.name === 'new_password') {
                if (password.length >= 6) {
                    e.target.style.borderColor = '#28a745';
                } else if (password.length > 0) {
                    e.target.style.borderColor = '#ffc107';
                } else {
                    e.target.style.borderColor = '';
                }
            }
            
            if (e.target.name === 'confirm_password') {
                const newPassword = document.querySelector('input[name="new_password"]').value;
                if (password && password === newPassword) {
                    e.target.style.borderColor = '#28a745';
                } else if (password) {
                    e.target.style.borderColor = '#dc3545';
                } else {
                    e.target.style.borderColor = '';
                }
            }
        });
    });

    // Add subtle pulse animation to buttons on hover
    const buttons = document.querySelectorAll('.btn-primary');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            if (!this.disabled) {
                this.style.animation = 'pulse 1.5s infinite';
            }
        });

        btn.addEventListener('mouseleave', function() {
            this.style.animation = '';
        });
    });

    // Simple confetti effect function
    function createConfetti() {
        const colors = ['#28a745', '#20c997', '#17a2b8', '#6f42c1', '#e83e8c'];
        const confettiCount = 30;
        
        for (let i = 0; i < confettiCount; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-10px';
                confetti.style.width = '8px';
                confetti.style.height = '8px';
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

    // Add additional animations CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 4px 12px rgba(0,123,255,0.3);
            }
            50% {
                box-shadow: 0 4px 20px rgba(0,123,255,0.5);
            }
            100% {
                box-shadow: 0 4px 12px rgba(0,123,255,0.3);
            }
        }
        
        @keyframes bounce {
            0%, 20%, 60%, 100% {
                transform: scale(1);
            }
            40% {
                transform: scale(1.2);
            }
            80% {
                transform: scale(1.1);
            }
        }
        
        .is-invalid {
            border-color: #dc3545 !important;
            animation: inputShake 0.5s ease-in-out;
        }
        
        @keyframes inputShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include 'includes/footer.php'; ?>
