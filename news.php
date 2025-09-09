<?php
include('config/database.php');
include('includes/navbar.php');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tin Tức - KongNguyeen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/news.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

<!-- Hero Section -->
<section class="news-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-12 text-center" data-aos="fade-up">
                <h1>Tin Tức & Sự Kiện</h1>
                <p class="lead">Cập nhật những thông tin mới nhất về sản phẩm và ưu đãi</p>
            </div>
        </div>
    </div>
</section>

<!-- Featured News -->
<section class="featured-news py-5">
    <div class="container">
        <div class="featured-news-card" data-aos="fade-up">
            <div class="row g-0">
                <div class="col-lg-6">
                    <div class="news-image-wrapper">
                        <div class="news-image" style="background-image: url('https://images.unsplash.com/photo-1661956602116-aa6865609028?ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=764&q=80');">
                            <div class="news-date">
                                <span class="day">26</span>
                                <span class="month">Tháng 8</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="news-content">
                        <div class="news-tag">Nổi bật</div>
                        <h2>Sự Kiện Ra Mắt Sản Phẩm Mới 2025</h2>
                        <p>Chúng tôi tự hào giới thiệu bộ sưu tập sản phẩm mới nhất, mang đến trải nghiệm tuyệt vời cho khách hàng với nhiều tính năng độc đáo và thiết kế hiện đại.</p>
                        <a href="#" class="btn-read-more">Xem thêm <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- News Grid -->
<section class="news-grid py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            <!-- News Item 1 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="news-card">
                    <div class="news-image" style="background-image: url('https://images.unsplash.com/photo-1661956602116-aa6865609028?ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=764&q=80');">
                        <div class="news-overlay">
                            <a href="#" class="btn-view">Xem chi tiết</a>
                        </div>
                    </div>
                    <div class="news-body">
                        <div class="news-meta">
                            <span><i class="far fa-calendar"></i> 26/08/2025</span>
                            <span><i class="far fa-user"></i> Admin</span>
                        </div>
                        <h3>Xu Hướng Công Nghệ 2025</h3>
                        <p>Khám phá những xu hướng công nghệ mới nhất đang định hình tương lai.</p>
                    </div>
                </div>
            </div>

            <!-- News Item 2 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="news-card">
                    <div class="news-image" style="background-image: url('https://images.unsplash.com/photo-1661956602116-aa6865609028?ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=764&q=80');">
                        <div class="news-overlay">
                            <a href="#" class="btn-view">Xem chi tiết</a>
                        </div>
                    </div>
                    <div class="news-body">
                        <div class="news-meta">
                            <span><i class="far fa-calendar"></i> 25/08/2025</span>
                            <span><i class="far fa-user"></i> Admin</span>
                        </div>
                        <h3>Khuyến Mãi Đặc Biệt</h3>
                        <p>Chương trình giảm giá lớn nhân dịp kỷ niệm 2 năm thành lập.</p>
                    </div>
                </div>
            </div>

            <!-- News Item 3 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="news-card">
                    <div class="news-image" style="background-image: url('https://images.unsplash.com/photo-1661956602116-aa6865609028?ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=764&q=80');">
                        <div class="news-overlay">
                            <a href="#" class="btn-view">Xem chi tiết</a>
                        </div>
                    </div>
                    <div class="news-body">
                        <div class="news-meta">
                            <span><i class="far fa-calendar"></i> 24/08/2025</span>
                            <span><i class="far fa-user"></i> Admin</span>
                        </div>
                        <h3>Hướng Dẫn Sử Dụng Sản Phẩm</h3>
                        <p>Những tips hữu ích giúp bạn tối ưu hóa trải nghiệm sử dụng sản phẩm.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="newsletter-section py-5">
    <div class="container">
        <div class="newsletter-container" data-aos="fade-up">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2>Đăng Ký Nhận Tin</h2>
                    <p>Nhận những thông tin mới nhất về sản phẩm và ưu đãi đặc biệt</p>
                </div>
                <div class="col-lg-6">
                    <form class="newsletter-form">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Nhập email của bạn">
                            <button class="btn btn-primary" type="submit">Đăng Ký</button>
                        </div>
                    </form>
                </div>
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
</script>
</body>
</html>
