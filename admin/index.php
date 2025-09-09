<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ Admin</title>
    <?php
    // Kết nối CSDL
    $conn = null;
    $db_path = realpath(__DIR__ . '/../config/database.php');
    if ($db_path && file_exists($db_path)) {
        require_once $db_path;
    }
    if (!isset($conn) || !$conn) {
        // Nếu chưa có $conn, tạo kết nối thủ công
        $conn = mysqli_connect('localhost', 'root', '', 'store');
        if (!$conn) {
            die('Không thể kết nối CSDL');
        }
    }

    // Sản phẩm
    $productCount = 0;
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products");
    if ($row = mysqli_fetch_assoc($result)) {
        $productCount = (int)$row['total'];
    }

    // Đơn hàng đã giao hoàn thành
    $orderCount = 0;
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'delivered'");
    if ($row = mysqli_fetch_assoc($result)) {
        $orderCount = (int)$row['total'];
    }

    // Người dùng
    $userCount = 0;
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
    if ($row = mysqli_fetch_assoc($result)) {
        $userCount = (int)$row['total'];
    }

    // Danh mục
    $categoryCount = 0;
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM categories");
    if ($row = mysqli_fetch_assoc($result)) {
        $categoryCount = (int)$row['total'];
    }

    // Đơn mới 7 ngày
    $newOrdersWeek = 0;
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($row = mysqli_fetch_assoc($result)) {
        $newOrdersWeek = (int)$row['total'];
    }

    // Doanh thu tháng từ đơn hàng đã giao
    $monthlyRevenue = '0 ₫';
    
    // Debug: Xem tháng và năm hiện tại
    $currentMonth = date('n'); // Tháng không có số 0 đằng trước
    $currentYear = date('Y');
    
    $sql = "SELECT 
                SUM(total_amount) AS revenue,
                COUNT(*) AS order_count,
                '$currentMonth' as current_month,
                '$currentYear' as current_year
            FROM orders 
            WHERE status = 'delivered' 
            AND MONTH(created_at) = $currentMonth 
            AND YEAR(created_at) = $currentYear";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $revenue = $row['revenue'];
        $orderCount = $row['order_count'];
        
        if ($revenue !== null && $revenue > 0) {
            $monthlyRevenue = number_format($revenue, 0, ',', '.') . ' ₫';
        } else {
            $monthlyRevenue = '0 ₫';
        }
        
        // Debug: Thêm thông tin debug (có thể xóa sau khi fix)
        // $monthlyRevenue .= " (Debug: $orderCount đơn, tháng $currentMonth/$currentYear)";
        
    } else {
        // Nếu có lỗi SQL, hiển thị lỗi để debug
        $monthlyRevenue = 'Lỗi SQL: ' . mysqli_error($conn);
    }
    ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_index.css">
</head>
<body>
    <!-- Loading screen -->
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <!-- Animated background particles -->
    <div class="particles" id="particles"></div>

    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-crown"></i> ADMIN DASHBOARD</h1>
        <p>Trung tâm quản lý hệ thống</p>
    </div>

    <!-- Main container -->
    <div class="container">
        <!-- Stats bar -->
        <div class="stats-bar">
            <div class="stats-content">
                <div class="stat-item">
                    <span class="stat-number" data-target="<?php echo $productCount; ?>"><?php echo $productCount; ?></span>
                    <span class="stat-label">Sản phẩm</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-target="<?php echo $orderCount; ?>"><?php echo $orderCount; ?></span>
                    <span class="stat-label">Đơn hàng đã giao</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-target="<?php echo $userCount; ?>"><?php echo $userCount; ?></span>
                    <span class="stat-label">Người dùng</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-target="<?php echo $categoryCount; ?>"><?php echo $categoryCount; ?></span>
                    <span class="stat-label">Danh mục</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-target="<?php echo $newOrdersWeek; ?>"><?php echo $newOrdersWeek; ?></span>
                    <span class="stat-label">Đơn mới (7 ngày)</span>
                </div>
                <div class="stat-item">
                    <span class="stat-revenue"><?php echo $monthlyRevenue; ?></span>
                    <span class="stat-label">Doanh thu tháng (đã giao)</span>
                </div>
            </div>
        </div>

        <!-- Dashboard grid -->
        <div class="dashboard-grid">
            <!-- Products -->
            <div class="admin-card" data-delay="0">
                <a href="products.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3 class="card-title">Quản lý sản phẩm</h3>
                    <p class="card-description">Thêm, sửa, xóa và quản lý toàn bộ sản phẩm trong hệ thống</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Categories -->
            <div class="admin-card" data-delay="100">
                <a href="categories.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h3 class="card-title">Quản lý danh mục</h3>
                    <p class="card-description">Tổ chức và phân loại sản phẩm theo các danh mục khác nhau</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Orders -->
            <div class="admin-card" data-delay="200">
                <a href="orders.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="card-title">Quản lý đơn hàng</h3>
                    <p class="card-description">Theo dõi và xử lý các đơn hàng từ khách hàng</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Reports -->
            <div class="admin-card" data-delay="300">
                <a href="reports.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="card-title">Báo cáo</h3>
                    <p class="card-description">Xem các báo cáo chi tiết về doanh thu và hiệu suất</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Promotions -->
            <div class="admin-card" data-delay="400">
                <a href="promotions.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <h3 class="card-title">Khuyến mãi</h3>
                    <p class="card-description">Tạo và quản lý các chương trình khuyến mãi</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Roles -->
            <div class="admin-card" data-delay="500">
                <a href="roles.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="card-title">Phân quyền</h3>
                    <p class="card-description">Quản lý quyền truy cập và vai trò người dùng</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Inventory -->
            <div class="admin-card" data-delay="600">
                <a href="inventory.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <h3 class="card-title">Kho hàng</h3>
                    <p class="card-description">Theo dõi tồn kho và quản lý hàng hóa</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Shipments -->
            <div class="admin-card" data-delay="700">
                <a href="shipments.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3 class="card-title">Vận chuyển</h3>
                    <p class="card-description">Quản lý các đơn vận chuyển và giao hàng</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- Users -->
            <div class="admin-card" data-delay="800">
                <a href="users.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="card-title">Người dùng</h3>
                    <p class="card-description">Quản lý tài khoản và thông tin người dùng</p>
                    <div class="card-action">
                        Truy cập <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>
            <div class="admin-card" data-delay="800">
    <a href="suppliers.php" class="card-link">
        <div class="card-icon">
            <i class="fas fa-truck"></i>
        </div>
        <h3 class="card-title">Nhà cung cấp</h3>
        <p class="card-description">Quản lý thông tin và hợp đồng với các nhà cung cấp</p>
        <div class="card-action">
            Quản lý <i class="fas fa-arrow-right"></i>
        </div>
    </a>
</div>
 <div class="admin-card" data-delay="800">
    <a href="feedbacks.php" class="card-link">
        <div class="card-icon">
            <i class="fas fa-comments"></i>
        </div>
        <h3 class="card-title">Phản hồi</h3>
        <p class="card-description">Quản lý phản hồi và đánh giá từ khách hàng</p>
        <div class="card-action">
            Xem chi tiết <i class="fas fa-arrow-right"></i>
        </div>
    </a>
</div>
 <div class="admin-card" data-delay="800">
    <a href="../index.php" class="card-link">
        <div class="card-icon">
            <i class="fas fa-home"></i>
        </div>
        <h3 class="card-title">Trang Chủ</h3>
        <p class="card-description">Trang bán hàng</p>
        <div class="card-action">
            Xem chi tiết <i class="fas fa-arrow-right"></i>
        </div>
    </a>
</div>
            <!-- Logout -->
            <div class="admin-card logout-card" data-delay="900">
                <a href="../logout.php" class="card-link">
                    <div class="card-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
                    <h3 class="card-title">Đăng xuất</h3>
                    <p class="card-description">Thoát khỏi hệ thống quản trị một cách an toàn</p>
                    <div class="card-action">
                        Đăng xuất <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>
            
        </div>
    </div>

    <script>
        // Loading screen
        window.addEventListener('load', function() {
            setTimeout(() => {
                document.getElementById('loading').classList.add('fade-out');
            }, 1000);
        });

        // Create animated particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 4 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 3 + 4) + 's';
                
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize particles
        createParticles();

        // Staggered animation for cards
        const cards = document.querySelectorAll('.admin-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.1) + 's';
        });

        // Add click effect to cards
        cards.forEach(card => {
            card.addEventListener('click', function(e) {
                const ripple = document.createElement('div');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.3)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.left = (e.clientX - card.offsetLeft) + 'px';
                ripple.style.top = (e.clientY - card.offsetTop) + 'px';
                ripple.style.width = '20px';
                ripple.style.height = '20px';
                ripple.style.pointerEvents = 'none';
                
                card.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Smooth scroll effect for any internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add hover sound effect (optional)
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                // You can add a subtle sound effect here if desired
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Parallax effect for particles
        window.addEventListener('mousemove', function(e) {
            const particles = document.querySelectorAll('.particle');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            particles.forEach((particle, index) => {
                const speed = (index % 5 + 1) * 0.5;
                particle.style.transform = `translate(${x * speed}px, ${y * speed}px)`;
            });
        });

        // Add stats counter animation
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            
            statNumbers.forEach(stat => {
                const finalNumber = parseInt(stat.getAttribute('data-target') || stat.textContent.replace(/,/g, ''));
                let currentNumber = 0;
                const increment = finalNumber / 100;
                
                stat.textContent = '0';
                
                const counter = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= finalNumber) {
                        stat.textContent = finalNumber.toLocaleString();
                        clearInterval(counter);
                    } else {
                        stat.textContent = Math.floor(currentNumber).toLocaleString();
                    }
                }, 20);
            });
        }

        // Start stats animation after page load
        setTimeout(animateStats, 1500);

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                // Enhanced tab navigation with visual feedback
                const focusableElements = document.querySelectorAll('.admin-card a, .card-link');
                focusableElements.forEach(element => {
                    element.addEventListener('focus', function() {
                        this.parentElement.style.outline = '2px solid #4ecdc4';
                        this.parentElement.style.outlineOffset = '2px';
                    });
                    element.addEventListener('blur', function() {
                        this.parentElement.style.outline = 'none';
                    });
                });
            }
        });
    </script>
</body>
</html>