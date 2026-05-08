<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KongNguyeen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/footer.css" rel="stylesheet">
</head>
<body>


    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-content">
            <div class="row">
                <!-- Thông tin công ty -->
                <div class="col-lg-4 col-md-6 footer-section">
                    <h5><i class="fas fa-building me-2"></i>Về Chúng Tôi</h5>
                    <p><strong>Nguyễn Văn Công</strong></p>
                    <p>Chúng tôi cam kết mang đến những sản phẩm và dịch vụ chất lượng cao nhất cho khách hàng tại khu vực Thủ Đức và các vùng lân cận.</p>
                    
                    <div class="contact-info">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Thủ Đức, Thành phố Hồ Chí Minh</span>
                    </div>
                    
                    <div class="contact-info">
                        <i class="fas fa-phone"></i>
                        <span>0395350889</span>
                    </div>
                    
                    <div class="contact-info">
                        <i class="fas fa-envelope"></i>
                        <span>congnbn45@gmail.com</span>
                    </div>
                </div>

        

                <!-- Liên kết hữu ích -->
                <div class="col-lg-2 col-md-6 footer-section">
                    <h5><i class="fas fa-link me-2"></i>Liên Kết</h5>
                    <ul>
                        <li><a href="index.php">Trang chủ</a></li>
                        <li><a href="about.php">Giới thiệu</a></li>
                        <li><a href="products.php">Sản phẩm</a></li>
                        <li><a href="news.php">Tin tức</a></li>
                        <li><a href="contact.php">Liên hệ</a></li>
                        <li><a href="policy.php">Chính sách </a></li>
                    </ul>           
                </div>

                <!-- Mạng xã hội và liên hệ -->
                <div class="col-lg-4 col-md-6 footer-section">
                    <h5><i class="fas fa-share-alt me-2"></i>Kết Nối Với Chúng Tôi</h5>
                    <p>Theo dõi chúng tôi trên các mạng xã hội để cập nhật thông tin mới nhất:</p>
                    
                    <div class="social-links">
                        <a href="https://www.facebook.com/kong.nguyeen/" class="social-link facebook" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link twitter" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.instagram.com/kong.nguyeen/" class="social-link instagram" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link linkedin" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-link youtube" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <h6><i class="fas fa-clock me-2"></i>Giờ Làm Việc</h6>
                        <p class="mb-1">Thứ 2 - Thứ 6: 8:00 - 17:30</p>
                        <p class="mb-1">Thứ 7: 8:00 - 12:00</p>
                        <p>Chủ nhật: Nghỉ</p>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="row">
                    <div class="col-md-6">
                        <p>&copy; 2025 Nguyễn Văn Công. Tất cả quyền được bảo lưu.</p>
                    </div>
                    <div class="col-md-6">
                        <p>Thiết kế bởi <i class="fas fa-heart text-danger"></i> Nguyễn Văn Công</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" type="button" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js" defer></script>
    <script>
        // Back to Top functionality
        const backToTopButton = document.getElementById('backToTop');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        // Show/hide back to top button
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        });
        
        // Scroll to top
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: prefersReducedMotion ? 'auto' : 'smooth'
            });
        });

        // Contact info click effects
        document.querySelectorAll('.contact-info').forEach(info => {
            info.addEventListener('click', function() {
                const text = this.textContent.trim();
                
                if (text.includes('+84')) {
                    window.location.href = 'tel:' + text.replace(/\s/g, '');
                } else if (text.includes('@')) {
                    window.location.href = 'mailto:' + text;
                } else if (text.includes('Thủ Đức')) {
                    window.open('https://maps.google.com/?q=Thủ Đức, Thành phố Hồ Chí Minh', '_blank');
                }
            });
        });

        // Add ripple effect to clickable elements
        function createRipple(event) {
            if (prefersReducedMotion) return;
            const button = event.currentTarget;
            const circle = document.createElement('span');
            const diameter = Math.max(button.clientWidth, button.clientHeight);
            const radius = diameter / 2;

            circle.style.width = circle.style.height = `${diameter}px`;
            circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
            circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
            circle.classList.add('ripple');

            const ripple = button.getElementsByClassName('ripple')[0];
            if (ripple) {
                ripple.remove();
            }

            button.appendChild(circle);
        }

        // Apply ripple effect to social links
        document.querySelectorAll('.social-link').forEach(link => {
            link.addEventListener('click', createRipple);
        });

    </script>
</body>
</html>