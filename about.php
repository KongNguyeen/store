<?php
include('config/database.php');
include('includes/navbar.php');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giới thiệu - KongNguyeen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/about.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

<div class="about-hero">
    <div class="container">
        <div class="row align-items-center" data-aos="fade-up">
            <div class="col-lg-6">
                <h1>Về Chúng Tôi</h1>
                <p class="lead">Chào mừng bạn đến với KongNguyeen - Nơi Chất Lượng Gặp Gỡ Sự Tin Cậy</p>
                <div class="hero-features mt-4">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Sản phẩm chất lượng cao</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Giao hàng nhanh chóng</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-headset"></i>
                        <span>Hỗ trợ 24/7</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-icon-container">
                    <div class="hero-icon">
                        <i class="fas fa-store fa-4x"></i>
                    </div>
                    <div class="hero-circles">
                        <div class="circle circle-1"></div>
                        <div class="circle circle-2"></div>
                        <div class="circle circle-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="our-story py-5">
    <div class="container">
        <div class="row" data-aos="fade-up" data-aos-delay="100">
            <div class="col-lg-8 mx-auto text-center">
                <h2>Câu Chuyện Của Chúng Tôi</h2>
                <p>Được thành lập vào năm 2023, KongNguyeen đã không ngừng phát triển và cam kết mang đến những sản phẩm chất lượng cao nhất cho khách hàng. Chúng tôi tự hào về dịch vụ khách hàng xuất sắc và sự đa dạng trong danh mục sản phẩm.</p>
            </div>
        </div>
    </div>
</section>

<section class="values py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5" data-aos="fade-up">Giá Trị Cốt Lõi</h2>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="value-card">
                    <i class="fas fa-heart"></i>
                    <h3>Chất Lượng</h3>
                    <p>Cam kết cung cấp sản phẩm chất lượng cao nhất đến tay khách hàng.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="value-card">
                    <i class="fas fa-handshake"></i>
                    <h3>Tin Cậy</h3>
                    <p>Xây dựng mối quan hệ dựa trên sự tin tưởng và minh bạch.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="value-card">
                    <i class="fas fa-star"></i>
                    <h3>Sáng Tạo</h3>
                    <p>Không ngừng đổi mới để mang đến trải nghiệm tốt nhất.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="team py-5">
    <div class="container">
        <h2 class="text-center mb-5" data-aos="fade-up">Đội Ngũ Của Chúng Tôi</h2>
        <div class="row g-4">
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="team-member">
                    <img src="assets/images/avatar-default.jpg" alt="Team Member" class="img-fluid rounded-circle">
                    <h3>Nguyễn Văn Công</h3>
                    <p class="position">Người Sáng Lập & CEO</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
            <!-- Thêm các thành viên khác nếu cần -->
        </div>
    </div>
</section>

<section class="stats py-5 bg-dark text-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3 text-center" data-aos="fade-up">
                <div class="stat-item">
                    <i class="fas fa-users fa-2x mb-3"></i>
                    <h2 class="counter">5000+</h2>
                    <p>Khách Hàng Hài Lòng</p>
                </div>
            </div>
            <div class="col-md-3 text-center" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-item">
                    <i class="fas fa-shopping-bag fa-2x mb-3"></i>
                    <h2 class="counter">10000+</h2>
                    <p>Sản Phẩm Đã Bán</p>
                </div>
            </div>
            <div class="col-md-3 text-center" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-item">
                    <i class="fas fa-store fa-2x mb-3"></i>
                    <h2 class="counter">2+</h2>
                    <p>Năm Kinh Nghiệm</p>
                </div>
            </div>
            <div class="col-md-3 text-center" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-item">
                    <i class="fas fa-certificate fa-2x mb-3"></i>
                    <h2 class="counter">100%</h2>
                    <p>Khách Hàng Tin Tưởng</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="contact-cta py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
                <h2>Bạn Muốn Tìm Hiểu Thêm?</h2>
                <p class="lead">Hãy liên hệ với chúng tôi ngay hôm nay để được tư vấn!</p>
                <a href="#" class="btn btn-primary btn-lg">Liên Hệ Ngay</a>
            </div>
        </div>
    </div>
</section>

<?php include('includes/footer.php'); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 1000,
        once: true
    });

    // Counter animation
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = parseInt(counter.innerText);
        let count = 0;
        const speed = 2000 / target;
        
        function updateCount() {
            const text = counter.innerText;
            const value = parseInt(text);
            
            if (count < target) {
                count += 1;
                counter.innerText = count + (text.includes('+') ? '+' : '');
                setTimeout(updateCount, speed);
            }
        }
        
        updateCount();
    });
</script>
</body>
</html>
