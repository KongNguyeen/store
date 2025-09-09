<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Check if user is actually logged in
$user_logged_in = isset($_SESSION['user_id']);
$user_name = '';

if ($user_logged_in) {
    try {
        $pdo = getPDO();
        
        // Get user name for goodbye message
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        $user_name = $user ? $user['full_name'] : '';
        
        // Log the logout activity
        $stmt = $pdo->prepare("
            INSERT INTO user_logs (
                user_id, action_type, ip_address, 
                user_agent, created_at
            ) VALUES (?, 'logout', ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $e) {
        error_log('Logout error: ' . $e->getMessage());
    }
}

// Clear session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'],
         $params['secure'], $params['httponly']);
}

// Destroy session
session_destroy();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng xuất - <?= SITE_NAME ?? 'Website' ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/logout.css">

</head>
<body>
    <!-- Animated Background Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Goodbye Animation -->
    <div class="goodbye-animation" id="goodbyeText">
        <?php if ($user_logged_in && $user_name): ?>
            Tạm biệt, <?= htmlspecialchars($user_name) ?>! 👋
        <?php else: ?>
            Tạm biệt! 👋
        <?php endif; ?>
    </div>

    <div class="logout-container" style="display: none;">
        <div class="logout-card">
            <!-- Logout Icon -->
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>

            <!-- Success Icon (hidden initially) -->
            <div class="success-icon" id="successIcon">
                <i class="fas fa-check" style="font-size: 2rem; color: white;"></i>
            </div>

            <!-- Content -->
            <h1 class="logout-title">Đăng xuất thành công!</h1>
            
            <?php if ($user_logged_in && $user_name): ?>
                <div class="logout-user">
                    <i class="fas fa-user me-2"></i>
                    Cảm ơn <?= htmlspecialchars($user_name) ?>
                </div>
            <?php endif; ?>

            <p class="logout-message">
                Bạn đã đăng xuất khỏi hệ thống một cách an toàn. <br>
                Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi!
            </p>

            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress">
                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                </div>
                <div class="loading-text" id="loadingText">
                    Đang đăng xuất...
                </div>
            </div>

            <!-- Login Button (hidden initially) -->
            <a href="login.php" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt me-2"></i>
                Đăng nhập lại
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.getElementById('progressBar');
            const loadingText = document.getElementById('loadingText');
            const loginBtn = document.getElementById('loginBtn');
            const logoutIcon = document.querySelector('.logout-icon');
            const successIcon = document.getElementById('successIcon');
            const goodbyeText = document.getElementById('goodbyeText');

            let progress = 0;
            const steps = [
                { text: 'Đang đăng xuất...', duration: 2000 },
                { text: 'Đăng xuất thành công!', duration: 1000 }
            ];

            let currentStep = 0;
            const stepProgress = 100 / steps.length;

            function updateProgress() {
                if (currentStep < steps.length) {
                    const step = steps[currentStep];
                    loadingText.textContent = step.text;
                    
                    // Animate progress bar
                    setTimeout(() => {
                        progress += stepProgress;
                        progressBar.style.width = progress + '%';
                        
                        if (currentStep === steps.length - 1) {
                            // Last step - show completion
                            setTimeout(() => {
                                showCompletion();
                            }, step.duration);
                        } else {
                            // Continue to next step
                            setTimeout(() => {
                                currentStep++;
                                updateProgress();
                            }, step.duration);
                        }
                    }, 100);
                } else {
                    showCompletion();
                }
            }

            function showCompletion() {
                // Hide loading elements
                loadingText.style.display = 'none';
                document.querySelector('.progress-container').style.display = 'none';
                
                // Switch icons
                logoutIcon.style.display = 'none';
                successIcon.classList.add('show');
                
                // Show login button
                setTimeout(() => {
                    loginBtn.classList.add('show');
                }, 300);

                // Show goodbye animation
                setTimeout(() => {
                    goodbyeText.style.display = 'block';
                }, 500);

                // Auto redirect after 5 seconds
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 5000);
            }

            // Show goodbye text first, then start logout process
            goodbyeText.style.display = 'block';
            
            // Start the logout animation after goodbye text
            setTimeout(() => {
                document.querySelector('.logout-container').style.display = 'block';
                updateProgress();
            }, 2000);

            // Add some random particle effects
            function createFloatingElements() {
                const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c'];
                
                setInterval(() => {
                    const element = document.createElement('div');
                    element.style.cssText = `
                        position: absolute;
                        width: ${Math.random() * 10 + 5}px;
                        height: ${Math.random() * 10 + 5}px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        border-radius: 50%;
                        left: ${Math.random() * 100}%;
                        top: 100%;
                        opacity: 0.7;
                        pointer-events: none;
                        z-index: 2;
                        animation: floatUp 4s linear forwards;
                    `;
                    
                    document.body.appendChild(element);
                    
                    setTimeout(() => {
                        element.remove();
                    }, 4000);
                }, 300);
            }

            // Add floating elements style
            const style = document.createElement('style');
            style.textContent = `
                @keyframes floatUp {
                    0% {
                        transform: translateY(0) rotate(0deg);
                        opacity: 0.7;
                    }
                    100% {
                        transform: translateY(-100vh) rotate(360deg);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            createFloatingElements();

            // Add click effect to login button
            loginBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Add loading effect
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang chuyển hướng...';
                this.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1000);
            });
        });
    </script>
</body>
</html>