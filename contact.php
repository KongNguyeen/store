<?php
include('config/database.php');
include('includes/navbar.php');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liên Hệ - KongNguyeen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/contact.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

<!-- Contact Hero -->
<section class="contact-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-12 text-center" data-aos="fade-up">
                <h1>Liên Hệ Với Chúng Tôi</h1>
                <p class="lead">Chúng tôi luôn sẵn sàng lắng nghe và hỗ trợ bạn</p>
            </div>
        </div>
    </div>
</section>

<!-- Contact Info Cards -->
<section class="contact-info py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="info-card">
                    <div class="icon-box">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Địa Chỉ</h3>
                    <p>Thủ Đức, Thành phố Hồ Chí Minh</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="info-card">
                    <div class="icon-box">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3>Điện Thoại</h3>
                    <p>0395350889</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="info-card">
                    <div class="icon-box">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Email</h3>
                    <p>congnbn45@gmail.com</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section class="contact-form-section py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="map-container">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3918.214525515917!2d106.78957931474!3d10.871276392257255!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x317527587e9ad5bf%3A0xafa66f9c8be3c91!2zVGjhu6cgxJDhu6ljLCBUaMOgbmggcGjhu5EgSOG7kyBDaMOtIE1pbmgsIFZp4buHdCBOYW0!5e0!3m2!1svi!2s!4v1629785058866!5m2!1svi!2s"
                        width="100%" 
                        height="450" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy">
                    </iframe>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="contact-form-container">
                    <h2>Gửi Tin Nhắn</h2>
                    <form id="contactForm" class="contact-form">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="name" placeholder="Họ và tên">
                            <label for="name">Họ và tên</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" placeholder="Email">
                            <label for="email">Email</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="phone" placeholder="Số điện thoại">
                            <label for="phone">Số điện thoại</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="message" placeholder="Nội dung" style="height: 150px"></textarea>
                            <label for="message">Nội dung</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            Gửi Tin Nhắn
                            <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Business Hours -->
<section class="business-hours py-5">
    <div class="container">
        <div class="hours-container" data-aos="fade-up">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2>Giờ Làm Việc</h2>
                    <div class="hours-list">
                        <div class="hours-item">
                            <span class="day">Thứ 2 - Thứ 6:</span>
                            <span class="time">8:00 - 17:30</span>
                        </div>
                        <div class="hours-item">
                            <span class="day">Thứ 7:</span>
                            <span class="time">8:00 - 12:00</span>
                        </div>
                        <div class="hours-item">
                            <span class="day">Chủ nhật:</span>
                            <span class="time">Nghỉ</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="social-links">
                        <h3>Kết Nối Với Chúng Tôi</h3>
                        <div class="social-icons">
                            <a href="https://www.facebook.com/kong.nguyeen/" class="social-icon facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-icon twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://www.instagram.com/kong.nguyeen/" class="social-icon instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-icon linkedin">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>
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

    // Form submission animation
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const button = this.querySelector('button[type="submit"]');
        button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Đang gửi...';
        
        // Simulate form submission
        setTimeout(() => {
            button.innerHTML = '<i class="fas fa-check"></i> Đã gửi thành công';
            button.classList.add('success');
            
            // Reset form after 2 seconds
            setTimeout(() => {
                this.reset();
                button.innerHTML = 'Gửi Tin Nhắn <i class="fas fa-paper-plane ms-2"></i>';
                button.classList.remove('success');
            }, 2000);
        }, 1500);
    });
</script>
</body>
</html>
