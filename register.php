<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';
$success = '';
$data = [
    'full_name' => '',
    'email' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'email' => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];

    if (!$data['full_name'] || !$data['email'] || !$data['phone'] || !$data['password']) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } elseif (!preg_match('/^[0-9]{10}$/', $data['phone'])) {
        $error = 'Số điện thoại không hợp lệ (10 chữ số)';
    } elseif (strlen($data['password']) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif ($data['password'] !== $data['confirm_password']) {
        $error = 'Mật khẩu không khớp';
    } else {
        try {
            $pdo = getPDO();
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $error = 'Email đã được sử dụng';
            } else {
                // Insert new user with role_id = 2 (normal user)
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        role_id, full_name, email, phone, 
                        password, created_at, updated_at, is_active
                    ) VALUES (
                        2, ?, ?, ?, ?,
                        NOW(), NOW(), 1
                    )
                ");
                $stmt->execute([
                    $data['full_name'],
                    $data['email'],
                    $data['phone'],
                    $data['password']
                ]);

                $success = 'Đăng ký thành công! Chào mừng ' . htmlspecialchars($data['full_name']) . ' đến với chúng tôi!';
                $data = ['full_name' => '', 'email' => '', 'phone' => ''];
            }
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            $error = 'Có lỗi xảy ra, vui lòng thử lại sau';
        }
    }
}

// Set page title
$page_title = 'Đăng ký';

// Add custom CSS and JS
$additional_css = ['auth.css'];
$additional_js = ['auth.js'];

include 'includes/navbar.php';
?>

<link rel="stylesheet" href="css/register.css">

<div class="auth-page">
    <div class="container">
        <div class="card auth-card">
            <div class="card-body">
                <h1 class="card-title">Đăng ký tài khoản</h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-enhanced" id="error-alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-form" id="registerForm">
                    <div class="form-group name-group">
                        <input type="text" class="form-control" name="full_name" 
                               value="<?= htmlspecialchars($data['full_name']) ?>" required autofocus placeholder=" " autocomplete="name">
                        <label class="form-label">Họ và tên</label>
                    </div>

                    <div class="form-group email-group">
                        <input type="email" class="form-control" name="email" 
                               value="<?= htmlspecialchars($data['email']) ?>" required placeholder=" " autocomplete="email">
                        <label class="form-label">Email</label>
                    </div>

                    <div class="form-group phone-group">
                        <input type="tel" class="form-control" name="phone" 
                               value="<?= htmlspecialchars($data['phone']) ?>" required placeholder=" " autocomplete="tel">
                        <label class="form-label">Số điện thoại</label>
                    </div>

                    <div class="form-group password-group">
                        <input type="password" class="form-control" name="password" required placeholder=" " autocomplete="new-password">
                        <label class="form-label">Mật khẩu</label>
                        <div class="password-strength" id="passwordStrength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="password-strength-text" id="passwordStrengthText"></div>
                    </div>

                    <div class="form-group confirm-password-group">
                        <input type="password" class="form-control" name="confirm_password" required placeholder=" " autocomplete="new-password">
                        <label class="form-label">Xác nhận mật khẩu</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="registerBtn">
                        <span class="loading-spinner"></span>
                        <i class="fas fa-user-plus me-2" id="register-icon"></i>
                        <span id="register-text">Đăng ký</span>
                    </button>
                </form>

                <div class="auth-divider">
                    <span>hoặc</span>
                </div>

                <div class="auth-links">
                    <p class="mb-0">
                        Đã có tài khoản? 
                        <a href="login.php">Đăng nhập ngay</a>
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
        <div class="success-text" id="successTitle">Đăng ký thành công!</div>
        <div class="success-subtext" id="successMessage">Chào mừng bạn đến với chúng tôi!</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const registerBtn = document.getElementById('registerBtn');
    const loadingSpinner = document.querySelector('.loading-spinner');
    const registerIcon = document.getElementById('register-icon');
    const registerText = document.getElementById('register-text');
    const errorAlert = document.getElementById('error-alert');
    const passwordInput = document.querySelector('input[name="password"]');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');

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

    // Password strength checker
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordStrengthBar = document.getElementById('passwordStrengthBar');
    const passwordStrengthText = document.getElementById('passwordStrengthText');

    passwordInput.addEventListener('input', function() {
        const password = this.value;
        
        if (password.length > 0) {
            const strength = checkPasswordStrength(password);
            passwordStrength.classList.add('visible');
            passwordStrengthText.classList.add('visible');
            
            passwordStrengthBar.style.width = strength.width;
            passwordStrengthBar.style.backgroundColor = strength.color;
            passwordStrengthText.textContent = strength.text;
            passwordStrengthText.style.color = strength.color;
        } else {
            passwordStrength.classList.remove('visible');
            passwordStrengthText.classList.remove('visible');
        }
        
        // Check password match
        if (confirmPasswordInput.value) {
            checkPasswordMatch();
        }
    });

    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    function checkPasswordStrength(password) {
        let score = 0;
        
        if (password.length >= 6) score += 1;
        if (password.length >= 8) score += 1;
        if (/[a-z]/.test(password)) score += 1;
        if (/[A-Z]/.test(password)) score += 1;
        if (/[0-9]/.test(password)) score += 1;
        if (/[^a-zA-Z0-9]/.test(password)) score += 1;
        
        if (score < 2) {
            return { text: 'Mật khẩu yếu', level: 'weak', width: '25%', color: '#dc3545' };
        } else if (score < 4) {
            return { text: 'Mật khẩu trung bình', level: 'medium', width: '50%', color: '#ffc107' };
        } else if (score < 5) {
            return { text: 'Mật khẩu khá mạnh', level: 'good', width: '75%', color: '#17a2b8' };
        } else {
            return { text: 'Mật khẩu mạnh', level: 'strong', width: '100%', color: '#28a745' };
        }
    }

    function checkPasswordMatch() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const confirmLabel = confirmPasswordInput.nextElementSibling;
        
        if (confirmPassword && password !== confirmPassword) {
            confirmPasswordInput.style.borderColor = '#dc3545';
            confirmLabel.style.color = '#dc3545';
        } else {
            confirmPasswordInput.style.borderColor = '';
            confirmLabel.style.color = '';
        }
    }

    // Add form submission loading effect
    registerForm.addEventListener('submit', function(e) {
        // Check password match before submitting
        if (passwordInput.value !== confirmPasswordInput.value) {
            e.preventDefault();
            confirmPasswordInput.focus();
            registerForm.classList.add('form-shake');
            setTimeout(() => {
                registerForm.classList.remove('form-shake');
            }, 500);
            return;
        }

        if (!registerForm.checkValidity()) {
            return;
        }

        // Show loading state
        registerBtn.classList.add('btn-loading');
        loadingSpinner.style.display = 'inline-block';
        registerIcon.style.display = 'none';
        registerText.textContent = 'Đang đăng ký...';
        registerBtn.disabled = true;
    });

    // Shake effect for errors
    <?php if ($error): ?>
    if (errorAlert) {
        registerForm.classList.add('form-shake');
        setTimeout(() => {
            registerForm.classList.remove('form-shake');
        }, 500);
    }
    <?php endif; ?>

    // Success animation
    <?php if ($success): ?>
    setTimeout(() => {
        showRegisterSuccess();
    }, 500);

    function showRegisterSuccess() {
        const overlay = document.getElementById('successOverlay');
        const successTitle = document.getElementById('successTitle');
        const successMessage = document.getElementById('successMessage');
        
        // Update messages
        successTitle.textContent = 'Đăng ký thành công!';
        successMessage.textContent = '<?= addslashes($success) ?>';
        
        // Ensure overlay covers full viewport
        document.body.style.overflow = 'hidden';
        
        // Show overlay with flex display for perfect centering
        overlay.style.display = 'flex';
        
        // Force reflow to ensure proper positioning
        overlay.offsetHeight;
        
        // Add confetti effect
        createConfetti();
        
        // Show login redirect message after animation
        setTimeout(() => {
            successMessage.textContent = 'Bạn có thể đăng nhập ngay bây giờ!';
            
            // Add login button
            const loginButton = document.createElement('button');
            loginButton.className = 'btn btn-light mt-3';
            loginButton.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Đăng nhập ngay';
            loginButton.style.cssText = `
                background: rgba(255,255,255,0.2);
                border: 2px solid rgba(255,255,255,0.3);
                color: white;
                border-radius: 8px;
                padding: 12px 24px;
                font-weight: 600;
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
            `;
            
            loginButton.addEventListener('click', function() {
                document.body.style.overflow = '';
                window.location.href = 'login.php?registered=1';
            });
            
            document.querySelector('.success-content').appendChild(loginButton);
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
    const formInputs = registerForm.querySelectorAll('input[required]');
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

    // Phone number formatting
    const phoneInput = document.querySelector('input[name="phone"]');
    phoneInput.addEventListener('input', function(e) {
        // Remove non-digits
        let value = e.target.value.replace(/\D/g, '');
        
        // Limit to 10 digits
        if (value.length > 10) {
            value = value.slice(0, 10);
        }
        
        e.target.value = value;
        
        // Visual feedback for phone validation
        if (value.length === 10) {
            e.target.style.borderColor = '#28a745';
        } else if (value.length > 0) {
            e.target.style.borderColor = '#ffc107';
        } else {
            e.target.style.borderColor = '';
        }
    });

    // Real-time email validation
    const emailInput = document.querySelector('input[name="email"]');
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

    // Name validation (no numbers or special chars)
    const nameInput = document.querySelector('input[name="full_name"]');
    nameInput.addEventListener('input', function(e) {
        const name = e.target.value;
        const nameRegex = /^[a-zA-ZÀ-ỹ\s]+$/;
        
        if (name && nameRegex.test(name) && name.trim().length >= 2) {
            e.target.style.borderColor = '#28a745';
        } else if (name) {
            e.target.style.borderColor = '#ffc107';
        } else {
            e.target.style.borderColor = '';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>




