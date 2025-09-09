<?php
#include 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chính Sách Mua Hàng - Nguyễn Văn Công</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/policy.css">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-store"></i> Nguyễn Văn Công
                </a>
                <nav>
                    <ul class="header-nav">
                        <li><a href="index.php"><i class="fas fa-home"></i> Trang chủ</a></li>
                        <li><a href="products.php"><i class="fas fa-box"></i> Sản phẩm</a></li>
                        <li><a href="#"><i class="fas fa-info-circle"></i> Giới thiệu</a></li>
                        <li><a href="#"><i class="fas fa-phone"></i> Liên hệ</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="policy-hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="hero-title">Chính Sách Mua Hàng</h1>
                <p class="hero-subtitle">Cam kết bảo vệ quyền lợi khách hàng với các chính sách rõ ràng và minh bạch</p>
            </div>
        </div>
    </div>

    <!-- Policy Navigation -->
    <div class="policy-nav">
        <div class="container">
            <div class="nav-items">
                <a href="#return-policy" class="nav-item active">
                    <i class="fas fa-undo-alt"></i>
                    <span>Đổi Trả</span>
                </a>
                <a href="#shipping-policy" class="nav-item">
                    <i class="fas fa-shipping-fast"></i>
                    <span>Vận Chuyển</span>
                </a>
                <a href="#payment-policy" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    <span>Thanh Toán</span>
                </a>
                <a href="#privacy-policy" class="nav-item">
                    <i class="fas fa-user-shield"></i>
                    <span>Bảo Mật</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Policy Content -->
    <div class="policy-content">
        <div class="container">
            
            <!-- Return Policy -->
            <section id="return-policy" class="policy-section">
                <div class="section-header">
                    <div class="section-icon return-icon">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                    <div class="section-title">
                        <h2>Chính Sách Đổi Trả</h2>
                        <p>Đảm bảo quyền lợi tối đa cho khách hàng</p>
                    </div>
                </div>
                
                <div class="policy-description">
                    <div class="description-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <p>Quý khách có thể đổi/trả sản phẩm trong vòng <span class="highlight-text">7 ngày</span> kể từ khi nhận hàng nếu sản phẩm bị lỗi từ nhà sản xuất hoặc giao sai mẫu.</p>
                    </div>
                </div>
                
                <div class="highlight-box">
                    <h4>
                        <i class="fas fa-clipboard-check box-icon"></i>
                        Điều Kiện Đổi Trả
                    </h4>
                    <ul>
                        <li>
                            <i class="fas fa-tag item-icon"></i>
                            <span>Sản phẩm còn nguyên tem, hộp</span>
                        </li>
                        <li>
                            <i class="fas fa-shield-check item-icon"></i>
                            <span>Chưa qua sử dụng</span>
                        </li>
                        <li>
                            <i class="fas fa-receipt item-icon"></i>
                            <span>Có hóa đơn mua hàng</span>
                        </li>
                    </ul>
                </div>

                <div class="highlight-box">
                    <h4>
                        <i class="fas fa-clock box-icon"></i>
                        Quy Trình Xử Lý
                    </h4>
                    <ul>
                        <li>
                            <i class="fas fa-phone-alt item-icon"></i>
                            <span><strong>Bước 1:</strong> Liên hệ hotline trong 24h đầu</span>
                        </li>
                        <li>
                            <i class="fas fa-truck item-icon"></i>
                            <span><strong>Bước 2:</strong> Gửi sản phẩm về cửa hàng (2-3 ngày)</span>
                        </li>
                        <li>
                            <i class="fas fa-search item-icon"></i>
                            <span><strong>Bước 3:</strong> Kiểm tra chất lượng sản phẩm</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle item-icon"></i>
                            <span><strong>Bước 4:</strong> Hoàn tất đổi trả trong 3-5 ngày</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Shipping Policy -->
            <section id="shipping-policy" class="policy-section">
                <div class="section-header">
                    <div class="section-icon shipping-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <div class="section-title">
                        <h2>Chính Sách Vận Chuyển</h2>
                        <p>Giao hàng nhanh chóng và an toàn</p>
                    </div>
                </div>
                
                <div class="policy-description">
                    <div class="description-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div>
                        <p>Chúng tôi giao hàng toàn quốc với thời gian dự kiến từ <span class="highlight-text">2-5 ngày làm việc</span> tùy khu vực.</p>
                    </div>
                </div>
                
                <div class="shipping-card">
                    <div class="card-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div>
                        <h4>Miễn Phí Vận Chuyển</h4>
                        <p>Cho đơn hàng từ <strong>500.000đ</strong></p>
                    </div>
                </div>

                <div class="delivery-zones">
                    <h4><i class="fas fa-map-marked-alt"></i> Khu Vực Giao Hàng</h4>
                    <div class="zone-grid">
                        <div class="zone-item">
                            <span class="zone-name">
                                <i class="fas fa-building"></i>
                                Nội thành TP.HCM
                            </span>
                            <span class="zone-time">
                                <i class="fas fa-clock"></i>
                                1-2 ngày
                            </span>
                        </div>
                        <div class="zone-item">
                            <span class="zone-name">
                                <i class="fas fa-mountain"></i>
                                Miền Nam
                            </span>
                            <span class="zone-time">
                                <i class="fas fa-clock"></i>
                                2-3 ngày
                            </span>
                        </div>
                        <div class="zone-item">
                            <span class="zone-name">
                                <i class="fas fa-water"></i>
                                Miền Trung
                            </span>
                            <span class="zone-time">
                                <i class="fas fa-clock"></i>
                                3-4 ngày
                            </span>
                        </div>
                        <div class="zone-item">
                            <span class="zone-name">
                                <i class="fas fa-tree"></i>
                                Miền Bắc
                            </span>
                            <span class="zone-time">
                                <i class="fas fa-clock"></i>
                                4-5 ngày
                            </span>
                        </div>
                    </div>
                </div>

                <div class="highlight-box">
                    <h4>
                        <i class="fas fa-truck-loading box-icon"></i>
                        Dịch Vụ Vận Chuyển
                    </h4>
                    <ul>
                        <li>
                            <i class="fas fa-box item-icon"></i>
                            <span>Đóng gói cẩn thận, chống sốc</span>
                        </li>
                        <li>
                            <i class="fas fa-shield-alt item-icon"></i>
                            <span>Bảo hiểm hàng hóa 100%</span>
                        </li>
                        <li>
                            <i class="fas fa-mobile-alt item-icon"></i>
                            <span>Theo dõi đơn hàng trực tuyến</span>
                        </li>
                        <li>
                            <i class="fas fa-clock item-icon"></i>
                            <span>Giao hàng đúng giờ cam kết</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Payment Policy -->
            <section id="payment-policy" class="policy-section">
                <div class="section-header">
                    <div class="section-icon payment-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="section-title">
                        <h2>Chính Sách Thanh Toán</h2>
                        <p>Đa dạng phương thức thanh toán tiện lợi</p>
                    </div>
                </div>
                
                <div class="policy-description">
                    <div class="description-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div>
                        <p>Chúng tôi hỗ trợ các hình thức thanh toán sau để tạo sự thuận tiện tối đa cho khách hàng:</p>
                    </div>
                </div>
                
                <div class="payment-methods">
                    <div class="method-card">
                        <div class="method-icon cod">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h4><i class="fas fa-money-bill-wave"></i> Thanh Toán COD</h4>
                        <p>Thanh toán trực tiếp cho shipper khi nhận hàng</p>
                        <div class="method-badge">Phổ biến nhất</div>
                    </div>
                    
                    <div class="method-card">
                        <div class="method-icon bank">
                            <i class="fas fa-university"></i>
                        </div>
                        <h4><i class="fas fa-piggy-bank"></i> Chuyển Khoản</h4>
                        <p>Chuyển khoản qua các ngân hàng trong nước</p>
                        <div class="method-badge">Giảm 2% phí ship</div>
                    </div>
                    
                    <div class="method-card">
                        <div class="method-icon ewallet">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4><i class="fas fa-qrcode"></i> Ví Điện Tử</h4>
                        <p>Thanh toán qua MoMo, ZaloPay, VNPay</p>
                        <div class="method-badge">Nhanh chóng</div>
                    </div>
                </div>

                <div class="highlight-box">
                    <h4>
                        <i class="fas fa-credit-card box-icon"></i>
                        Thông Tin Chuyển Khoản
                    </h4>
                    <ul>
                        <li>
                            <i class="fas fa-university item-icon"></i>
                            <span><strong>Ngân hàng:</strong> Vietcombank - Chi nhánh Thủ Đức</span>
                        </li>
                        <li>
                            <i class="fas fa-hashtag item-icon"></i>
                            <span><strong>Số tài khoản:</strong> 0123456789</span>
                        </li>
                        <li>
                            <i class="fas fa-user item-icon"></i>
                            <span><strong>Chủ tài khoản:</strong> Nguyễn Văn Công</span>
                        </li>
                        <li>
                            <i class="fas fa-comment item-icon"></i>
                            <span><strong>Nội dung:</strong> Họ tên + Số điện thoại</span>
                        </li>
                    </ul>
                </div>
                
                <div class="payment-note">
                    <div class="note-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="note-content">
                        <h5><i class="fas fa-exclamation-triangle"></i> Lưu Ý Quan Trọng</h5>
                        <p>Với đơn hàng trên <strong>5.000.000đ</strong>, quý khách cần đặt cọc <strong>50%</strong> giá trị đơn hàng để xác nhận.</p>
                    </div>
                </div>
            </section>

            <!-- Privacy Policy -->
            <section id="privacy-policy" class="policy-section">
                <div class="section-header">
                    <div class="section-icon privacy-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="section-title">
                        <h2>Chính Sách Bảo Mật Thông Tin</h2>
                        <p>Cam kết bảo vệ thông tin cá nhân khách hàng</p>
                    </div>
                </div>
                
                <div class="policy-description">
                    <div class="description-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <p>Thông tin cá nhân của khách hàng sẽ được <span class="highlight-text">bảo mật tuyệt đối</span> và chỉ sử dụng cho các mục đích sau:</p>
                    </div>
                </div>
                
                <div class="privacy-grid">
                    <div class="privacy-item">
                        <div class="privacy-icon">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <h4><i class="fas fa-clipboard-check"></i> Xác Nhận Đơn Hàng</h4>
                        <p>Xác nhận và xử lý đơn hàng của khách hàng một cách chính xác</p>
                    </div>
                    
                    <div class="privacy-item">
                        <div class="privacy-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h4><i class="fas fa-shipping-fast"></i> Giao Hàng</h4>
                        <p>Giao hàng đến địa chỉ yêu cầu của khách hàng đúng thời gian</p>
                    </div>
                    
                    <div class="privacy-item">
                        <div class="privacy-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4><i class="fas fa-phone-alt"></i> Hỗ Trợ Khách Hàng</h4>
                        <p>Hỗ trợ khách hàng khi có thắc mắc hoặc vấn đề phát sinh</p>
                    </div>
                </div>

                <div class="highlight-box">
                    <h4>
                        <i class="fas fa-database box-icon"></i>
                        Thông Tin Thu Thập
                    </h4>
                    <ul>
                        <li>
                            <i class="fas fa-id-card item-icon"></i>
                            <span>Họ tên, số điện thoại liên lạc</span>
                        </li>
                        <li>
                            <i class="fas fa-map-marker-alt item-icon"></i>
                            <span>Địa chỉ giao hàng chi tiết</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope item-icon"></i>
                            <span>Email để gửi thông báo đơn hàng</span>
                        </li>
                        <li>
                            <i class="fas fa-shopping-cart item-icon"></i>
                            <span>Lịch sử mua sắm để tư vấn tốt hơn</span>
                        </li>
                    </ul>
                </div>
                
                <div class="security-commitment">
                    <div class="commitment-icon">
                        <i class="fas fa-shield-check"></i>
                    </div>
                    <div class="commitment-content">
                        <h4><i class="fas fa-award"></i> Cam Kết Bảo Mật</h4>
                        <p>Chúng tôi <strong>không chia sẻ thông tin</strong> với bất kỳ bên thứ ba nào khi chưa có sự đồng ý của khách hàng. Tất cả dữ liệu được mã hóa và lưu trữ an toàn theo tiêu chuẩn quốc tế ISO 27001.</p>
                    </div>
                </div>

                <div class="highlight-box">
                    <h4>
                        <i class="fas fa-user-cog box-icon"></i>
                        Quyền Của Khách Hàng
                    </h4>
                    <ul>
                        <li>
                            <i class="fas fa-eye item-icon"></i>
                            <span>Xem lại thông tin cá nhân đã cung cấp</span>
                        </li>
                        <li>
                            <i class="fas fa-edit item-icon"></i>
                            <span>Yêu cầu chỉnh sửa thông tin không chính xác</span>
                        </li>
                        <li>
                            <i class="fas fa-trash item-icon"></i>
                            <span>Yêu cầu xóa thông tin cá nhân khỏi hệ thống</span>
                        </li>
                        <li>
                            <i class="fas fa-ban item-icon"></i>
                            <span>Từ chối nhận thông tin quảng cáo</span>
                        </li>
                    </ul>
                </div>
            </section>
            
            <!-- Contact Support -->
            <section class="support-section">
                <div class="support-content">
                    <div class="support-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h3><i class="fas fa-life-ring"></i> Cần Hỗ Trợ Thêm?</h3>
                    <p>Đội ngũ chăm sóc khách hàng luôn sẵn sàng hỗ trợ bạn 24/7</p>
                    <div class="support-buttons">
                        <a href="tel:+84123456789" class="support-btn">
                            <i class="fas fa-phone"></i>
                            Gọi Hotline
                        </a>
                        <a href="mailto:support@nguyenvancong.com" class="support-btn">
                            <i class="fas fa-envelope"></i>
                            Gửi Email
                        </a>
                        <a href="#" class="support-btn">
                            <i class="fas fa-comments"></i>
                            Chat Trực Tuyến
                        </a>
                        <a href="#" class="support-btn">
                            <i class="fab fa-facebook-messenger"></i>
                            Messenger
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>

    

    <!-- Progress Bar -->
    <div class="reading-progress">
        <div class="progress-fill"></div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation
            document.body.classList.add('loaded');
            
            // Smooth scrolling for navigation
            document.querySelectorAll('.nav-item').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all nav items
                    document.querySelectorAll('.nav-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // Smooth scroll to section
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    if (targetSection) {
                        const offsetTop = targetSection.offsetTop - 120;
                        window.scrollTo({
                            top: offsetTop,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Update active nav item on scroll
            window.addEventListener('scroll', function() {
                const sections = document.querySelectorAll('.policy-section[id]');
                const navItems = document.querySelectorAll('.nav-item');
                
                let currentSection = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop - 150;
                    const sectionHeight = section.offsetHeight;
                    
                    if (window.pageYOffset >= sectionTop && 
                        window.pageYOffset < sectionTop + sectionHeight) {
                        currentSection = section.getAttribute('id');
                    }
                });
                
                navItems.forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === `#${currentSection}`) {
                        item.classList.add('active');
                    }
                });

                // Update progress bar
                const windowHeight = window.innerHeight;
                const documentHeight = document.documentElement.scrollHeight - windowHeight;
                const scrollTop = window.pageYOffset;
                const progress = (scrollTop / documentHeight) * 100;
                
                document.querySelector('.progress-fill').style.width = progress + '%';
            });

            // Add intersection observer for animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '-50px'
            });

            // Observe all policy sections
            document.querySelectorAll('.policy-section').forEach(section => {
                observer.observe(section);
            });

            // Add hover effects to cards
            document.querySelectorAll('.method-card, .privacy-item, .zone-item').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add click effects to support buttons
            document.querySelectorAll('.support-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.3);
                        transform: scale(0);
                        animation: ripple-animation 0.6s linear;
                        pointer-events: none;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add typing effect to hero subtitle
            const heroSubtitle = document.querySelector('.hero-subtitle');
            if (heroSubtitle) {
                const text = heroSubtitle.textContent;
                heroSubtitle.textContent = '';
                
                let i = 0;
                const typeWriter = () => {
                    if (i < text.length) {
                        heroSubtitle.textContent += text.charAt(i);
                        i++;
                        setTimeout(typeWriter, 50);
                    }
                };
                
                setTimeout(typeWriter, 1000);
            }

            // Add final CSS animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
                
                .highlight-box li:hover .item-icon {
                    transform: scale(1.2);
                    transition: transform 0.3s ease;
                }
                
                .method-card:hover .method-icon {
                    transform: scale(1.1) rotate(5deg);
                    transition: all 0.3s ease;
                }
                
                .privacy-item:hover .privacy-icon {
                    transform: scale(1.1);
                    transition: transform 0.3s ease;
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>
<?php include 'includes/footer.php'; ?>