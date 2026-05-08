<?php
require_once 'config/config.php';
require_once 'config/functions.php';

$pdo = getPDO();

// Kiểm tra trạng thái đăng nhập
$isLoggedIn = is_logged_in();
$isAdmin = is_admin();

// Lấy tất cả danh mục có sản phẩm
$featured_categories = $pdo->query("
    SELECT c.*, COUNT(p.product_id) as product_count,
           (SELECT image_url FROM product_images pi 
            JOIN products p2 ON pi.product_id = p2.product_id
            WHERE p2.category_id = c.category_id AND pi.is_primary = 1 
            ORDER BY p2.created_at DESC LIMIT 1) as category_image
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id
    HAVING product_count > 0
    ORDER BY product_count DESC, c.name ASC
")->fetchAll();

// Lấy sản phẩm mới nhất
$new_products = $pdo->query("
    SELECT p.*, c.name as category_name,
           (SELECT image_url FROM product_images pi 
            WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

// Lấy sản phẩm bán chạy
$best_sellers = $pdo->query("
    SELECT p.*, c.name as category_name,
           COUNT(oi.order_item_id) as order_count,
           (SELECT image_url FROM product_images pi 
            WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE p.status = 'active' AND (o.status = 'delivered' OR o.status IS NULL)
    GROUP BY p.product_id
    ORDER BY order_count DESC
    LIMIT 8
")->fetchAll();

// Lấy khuyến mãi đang chạy
$promotions = $pdo->query("
    SELECT * FROM promotions 
    WHERE active = 1
    AND start_date <= NOW() 
    AND end_date >= NOW()
    ORDER BY discount_percent DESC
    LIMIT 3
")->fetchAll();

include 'includes/navbar.php';
?>

<!-- Enhanced CSS Styles -->
<link rel="stylesheet" href="css/index.css">


<!-- Floating Background Elements -->
<div class="floating-shapes">
    <div class="floating-shape"></div>
    <div class="floating-shape"></div>
    <div class="floating-shape"></div>
    <div class="floating-shape"></div>
</div>

<!-- Enhanced Banner Carousel -->
<div id="mainBanner" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#mainBanner" data-bs-slide-to="0" class="active"></button>
        <button type="button" data-bs-target="#mainBanner" data-bs-slide-to="1"></button>
        <button type="button" data-bs-target="#mainBanner" data-bs-slide-to="2"></button>
    </div>
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" class="d-block w-100" alt="Summer Sale" loading="eager" decoding="async">
            <div class="carousel-caption d-none d-md-block">
                <h2>🌟 Khuyến mãi mùa hè</h2>
                <p>Giảm giá lên đến 50% cho các sản phẩm mới nhất</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2340&q=80" class="d-block w-100" alt="Free Shipping" loading="lazy" decoding="async">
            <div class="carousel-caption d-none d-md-block">
                <h2>🚚 Miễn phí vận chuyển</h2>
                <p>Cho đơn hàng từ 500,000đ trên toàn quốc</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="https://images.unsplash.com/photo-1560472355-536de3962603?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2126&q=80" class="d-block w-100" alt="New Member" loading="lazy" decoding="async">
            <div class="carousel-caption d-none d-md-block">
                <h2>🎁 Ưu đãi thành viên mới</h2>
                <p>Giảm ngay 100,000đ cho đơn hàng đầu tiên</p>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#mainBanner" data-bs-slide="prev">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#mainBanner" data-bs-slide="next">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar for Categories - Right Column -->
        <div class="col-lg-3 order-lg-2 order-1 mb-4">
            <div class="card sidebar-card">
                <div class="card-header position-relative">
                    <div class="d-flex align-items-center">
                        <div class="category-icon me-2">
                            <i class="fas fa-folder-tree fa-lg"></i>
                        </div>
                        <h4 class="mb-0">Danh mục sản phẩm</h4>
                    </div>
                    <div class="header-decoration"></div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush category-list">
                        <?php foreach ($featured_categories as $category): ?>
                            <li class="list-group-item">
                                <a href="category.php?id=<?= $category['category_id'] ?>" class="text-decoration-none d-flex align-items-center">
                                    <div class="category-icon me-3">
                                        <?php if ($category['category_image']): ?>
                                            <img src="<?= $category['category_image'] ?>" alt="<?= htmlspecialchars($category['name']) ?>" class="img-fluid rounded" style="width: 40px; height: 40px; object-fit: cover;" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <div class="category-icon-placeholder">
                                                <i class="fas fa-folder"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($category['name']) ?></h6>
                                        <small class="text-muted"><?= $category['product_count'] ?> sản phẩm</small>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="products.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-th-large me-2"></i>
                        Xem tất cả sản phẩm
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="col-lg-9 order-lg-1 order-2">

<!-- New Products Section -->
<div class="container py-5">
    <div class="section-header animate-on-scroll">
        <h2 style="color: white !important; -webkit-text-fill-color: white !important;">✨ Sản phẩm mới</h2>
        <p style="color: white !important;">Những sản phẩm mới nhất và hot nhất trên thị trường</p>
    </div>
    <div class="row g-4">
        <?php foreach ($new_products as $index => $product): ?>
            <div class="col-lg-3 col-md-6 animate-on-scroll" style="animation-delay: <?= $index * 0.1 ?>s">
                <div class="card product-card">
                    <div class="product-image-container">
                        <?php if ($product['product_image']): ?>
                            <img src="<?= $product['product_image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="card-img-top" loading="lazy" decoding="async">
                        <?php else: ?>
                            <img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="<?= htmlspecialchars($product['name']) ?>" class="card-img-top" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div class="product-badge">New</div>
                    </div>
                    <div class="card-body">
                        <p class="product-category"><?= htmlspecialchars($product['category_name']) ?></p>
                        <h5 class="card-title">
                            <a href="product.php?id=<?= $product['product_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($product['name']) ?>
                            </a>
                        </h5>
                        <div class="product-rating">
                            <div class="stars">
                                <?php 
                                $rating = round($product['avg_rating'] ?? 0);
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <i class="<?= ($i <= $rating) ? 'fas' : 'far' ?> fa-star star"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-text">(<?= number_format($product['avg_rating'] ?? 0, 1) ?>)</span>
                        </div>
                        <p class="product-price"><?= format_currency($product['price']) ?></p>
                        <?php if (!$isAdmin): ?>
                        <button onclick="addToCart(<?= $product['product_id'] ?>, this)" 
                                class="btn btn-add-cart">
                            <i class="fas fa-shopping-cart me-2"></i>Thêm vào giỏ
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Best Sellers Section -->
<div class="section-container">
    <div class="container">
        <div class="section-header animate-on-scroll">
            <h2 style="color: white !important; -webkit-text-fill-color: white !important;">💎 Sản phẩm bán chạy</h2>
            <p style="color: white !important;">Những sản phẩm được khách hàng tin tưởng và lựa chọn nhiều nhất</p>
        </div>
        <div class="row g-4">
            <?php foreach ($best_sellers as $index => $product): ?>
                <div class="col-lg-3 col-md-6 animate-on-scroll" style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="card product-card">
                        <div class="product-image-container">
                            <?php if ($product['product_image']): ?>
                                <img src="<?= $product['product_image'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="card-img-top" loading="lazy" decoding="async">
                            <?php else: ?>
                                <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="<?= htmlspecialchars($product['name']) ?>" class="card-img-top" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <div class="product-badge">Best Seller</div>
                        </div>
                        <div class="card-body">
                            <p class="product-category"><?= htmlspecialchars($product['category_name']) ?></p>
                            <h5 class="card-title">
                                <a href="product.php?id=<?= $product['product_id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($product['name']) ?>
                                </a>
                            </h5>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php 
                                    $rating = round($product['avg_rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="<?= ($i <= $rating) ? 'fas' : 'far' ?> fa-star star"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text">(<?= number_format($product['avg_rating'] ?? 0, 1) ?>)</span>
                            </div>
                            <p class="product-price"><?= format_currency($product['price']) ?></p>
                            <?php if (!$isAdmin): ?>
                            <button onclick="addToCart(<?= $product['product_id'] ?>, this)" 
                                    class="btn btn-add-cart">
                                <i class="fas fa-shopping-cart me-2"></i>Thêm vào giỏ
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <small class="text-muted">
                                <i class="fas fa-fire text-danger me-1"></i>
                                Đã bán: <?= $product['order_count'] ?: 0 ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Promotions Section -->
<?php if ($promotions): ?>
<div class="section-container">
    <div class="container">
        <div class="section-header animate-on-scroll">
            <h2>🔥 Khuyến mãi hot</h2>
            <p>Đừng bỏ lỡ những ưu đãi hấp dẫn nhất</p>
        </div>
        <div class="row g-4">
            <?php foreach ($promotions as $index => $promo): ?>
                <div class="col-lg-4 col-md-6 animate-on-scroll" style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="card promotion-card">
                        <div class="card-body">
                            <div class="discount-badge">
                                <?= $promo['discount_percent'] ?>% OFF
                            </div>
                            <h5 class="card-title">
                                <?= htmlspecialchars($promo['description']) ?: 'Giảm giá đặc biệt' ?>
                            </h5>
                            <p class="text-muted mb-3">
                                <i class="fas fa-clock me-2"></i>
                                Có hiệu lực đến: <?= date('d/m/Y', strtotime($promo['end_date'])) ?>
                            </p>
                            <?php if ($promo['min_order_amount']): ?>
                                <p class="text-muted mb-3">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    Đơn tối thiểu: <?= format_currency($promo['min_order_amount']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($promo['code']): ?>
                                <div class="promotion-code" onclick="copyToClipboard('<?= $promo['code'] ?>')">
                                    <?= $promo['code'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>



<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Enhanced JavaScript -->
<script>
// Global variables
const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
const loginUrl = '<?= BASE_URL ?>login.php';

// Enhanced DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAnimations();
    initializeCarousel();
    initializeScrollEffects();
    initializeProductInteractions();
    initializeLazyLoading();
    createParticleEffect();
});

// Initialize Scroll Animations
function initializeAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                
                // Add staggered animation to children
                const children = entry.target.querySelectorAll('.col-lg-3, .col-lg-4, .col-md-6');
                children.forEach((child, index) => {
                    setTimeout(() => {
                        child.style.opacity = '1';
                        child.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            }
        });
    }, observerOptions);

    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
    
    // Initialize sidebar animations
    initializeSidebarAnimations();
}

// Initialize Sidebar Animations
function initializeSidebarAnimations() {
    const categoryItems = document.querySelectorAll('.category-list .list-group-item');
    
    categoryItems.forEach((item, index) => {
        // Add staggered entrance animation
        item.style.opacity = '0';
        item.style.transform = 'translateX(20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.4s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 100);
        
        // Add hover sound effect (optional)
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Add category count animation
    const countElements = document.querySelectorAll('.category-list small');
    countElements.forEach(count => {
        const finalCount = parseInt(count.textContent);
        if (finalCount > 0) {
            animateCount(count, finalCount);
        }
    });
}

// Animate category product count
function animateCount(element, target) {
    let current = 0;
    const increment = target / 20;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current) + ' sản phẩm';
    }, 50);
}

// Add click feedback for category links
function initializeCategoryClickFeedback() {
    const categoryLinks = document.querySelectorAll('.category-list a');
    
    categoryLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const item = this.closest('.list-group-item');
            
            // Add click animation
            item.style.transform = 'scale(0.98)';
            item.style.background = 'rgba(118, 75, 162, 0.15)';
            
            // Show loading indicator
            const loadingSpinner = document.createElement('div');
            loadingSpinner.innerHTML = '<i class="fas fa-spinner fa-spin ms-2"></i>';
            loadingSpinner.style.display = 'inline-block';
            this.appendChild(loadingSpinner);
            
            // Reset after a short delay (navigation will happen anyway)
            setTimeout(() => {
                item.style.transform = 'scale(1)';
                item.style.background = '';
                loadingSpinner.remove();
            }, 200);
        });
    });
}

// Initialize category click feedback when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initializeCategoryClickFeedback, 500);
});

// Enhanced Carousel
function initializeCarousel() {
    const carousel = document.getElementById('mainBanner');
    if (!carousel) return;
    
    const carouselInstance = new bootstrap.Carousel(carousel, {
        interval: 6000,
        ride: 'carousel',
        pause: 'hover'
    });

    // Add custom indicators animation
    const indicators = carousel.querySelectorAll('.carousel-indicators button');
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            indicators.forEach(ind => ind.classList.remove('active'));
            indicator.classList.add('active');
        });
    });

    // Enhanced slide change animation
    carousel.addEventListener('slide.bs.carousel', function(e) {
        const activeCaption = e.relatedTarget.querySelector('.carousel-caption');
        if (activeCaption) {
            activeCaption.style.animation = 'none';
            setTimeout(() => {
                activeCaption.style.animation = 'slideInLeft 0.8s ease-out';
            }, 100);
        }
    });
}

// Scroll Effects
function initializeScrollEffects() {
    // Parallax effect for floating shapes
    window.addEventListener('scroll', () => {
        const shapes = document.querySelectorAll('.floating-shape');
        const scrolled = window.pageYOffset;
        shapes.forEach((shape, index) => {
            const speed = 0.5 + (index * 0.1);
            shape.style.transform = `translateY(${scrolled * speed}px)`;
        });
    });
}

// Product Interactions
function initializeProductInteractions() {
    // Add hover effects to product cards
    document.querySelectorAll('.product-card').forEach(card => {
        const image = card.querySelector('img');
        
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-15px) scale(1.02)';
            if (image) {
                image.style.transform = 'scale(1.1)';
            }
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
            if (image) {
                image.style.transform = 'scale(1)';
            }
        });
    });
    
    // Add click animation to buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

// Lazy Loading
function initializeLazyLoading() {
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                const src = img.dataset.src || img.src;
                
                img.style.filter = 'blur(5px)';
                img.style.transition = 'filter 0.3s ease';
                
                const tempImg = new Image();
                tempImg.onload = () => {
                    img.src = src;
                    img.style.filter = 'blur(0)';
                    img.classList.add('loaded');
                };
                tempImg.src = src;
                
                imageObserver.unobserve(img);
            }
        });
    });
    
    document.querySelectorAll('img').forEach(img => {
        imageObserver.observe(img);
    });
}

// Particle Effect
function createParticleEffect() {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.zIndex = '-1';
    canvas.style.pointerEvents = 'none';
    document.body.appendChild(canvas);
    
    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    const particles = [];
    const particleCount = 50;
    
    class Particle {
        constructor() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.vx = (Math.random() - 0.5) * 0.5;
            this.vy = (Math.random() - 0.5) * 0.5;
            this.radius = Math.random() * 2 + 1;
            this.opacity = Math.random() * 0.5 + 0.1;
        }
        
        update() {
            this.x += this.vx;
            this.y += this.vy;
            
            if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
            if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
        }
        
        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
            ctx.fill();
        }
    }
    
    for (let i = 0; i < particleCount; i++) {
        particles.push(new Particle());
    }
    
    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        particles.forEach(particle => {
            particle.update();
            particle.draw();
        });
        
        // Connect nearby particles
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < 100) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.strokeStyle = `rgba(255, 255, 255, ${0.1 * (1 - distance / 100)})`;
                    ctx.stroke();
                }
            }
        }
        
        requestAnimationFrame(animate);
    }
    
    animate();
}

// Enhanced Add to Cart Function
function addToCart(productId, button) {
    // Check if user is admin
    if (isAdmin) {
        showToast('Admin không thể thêm sản phẩm vào giỏ hàng!', 'warning');
        return;
    }
    
    // Check if user is logged in
    if (!isLoggedIn) {
        // Show login required message
        showLoginRequiredModal();
        return;
    }

    const originalContent = button.innerHTML;
    
    // Add loading state with enhanced animation
    button.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Đang thêm...';
    button.disabled = true;
    button.style.transform = 'scale(0.95)';
    
    // Simulate API call with enhanced feedback
    setTimeout(() => {
        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&id=${productId}`
        })
        .then(response => {
            // Kiểm tra xem response có phải JSON hợp lệ không
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server không trả về JSON hợp lệ');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Success animation
                button.innerHTML = '<i class="fas fa-check me-2"></i>Đã thêm!';
                button.style.background = 'var(--success-gradient)';
                button.style.transform = 'scale(1.05)';
                
                // Update cart count with animation
                updateCartCount(data.cart_count);
                
                // Show success toast with green background
                showToast('Đã thêm sản phẩm vào giỏ hàng!', 'success', 5000, button);
                
                // Add floating cart animation
                createFloatingCartAnimation(button);
                
            } else {
                // Error state
                button.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Lỗi!';
                button.style.background = 'var(--warning-gradient)';
                showToast(data.message || 'Có lỗi xảy ra!', 'error', 5000, button);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Lỗi!';
            button.style.background = 'var(--warning-gradient)';
            showToast('Có lỗi xảy ra khi thêm sản phẩm!', 'error', 5000, button);
        })
        .finally(() => {
            // Reset button after animation
            setTimeout(() => {
                button.innerHTML = originalContent;
                button.disabled = false;
                button.style.background = 'var(--primary-gradient)';
                button.style.transform = 'scale(1)';
            }, 2500);
        });
    }, 800);
}

// Floating Cart Animation
function createFloatingCartAnimation(button) {
    const rect = button.getBoundingClientRect();
    const cartIcon = document.querySelector('.cart-icon') || document.querySelector('[href*="cart"]');
    
    if (!cartIcon) return;
    
    const cartRect = cartIcon.getBoundingClientRect();
    
    // Create floating element
    const floatingItem = document.createElement('div');
    floatingItem.innerHTML = '<i class="fas fa-shopping-cart"></i>';
    floatingItem.style.cssText = `
        position: fixed;
        left: ${rect.left + rect.width / 2}px;
        top: ${rect.top + rect.height / 2}px;
        width: 30px;
        height: 30px;
        background: var(--primary-gradient);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
        z-index: 9999;
        pointer-events: none;
        transform: scale(0);
        animation: floatToCart 1.5s ease-out forwards;
    `;
    
    document.body.appendChild(floatingItem);
    
    // Animate to cart
    floatingItem.style.setProperty('--end-x', `${cartRect.left + cartRect.width / 2 - rect.left - rect.width / 2}px`);
    floatingItem.style.setProperty('--end-y', `${cartRect.top + cartRect.height / 2 - rect.top - rect.height / 2}px`);
    
    setTimeout(() => {
        floatingItem.remove();
    }, 1500);
}

// Update Cart Count Animation
function updateCartCount(newCount) {
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        // Animate the change
        cartCount.style.transform = 'scale(1.5)';
        cartCount.style.background = 'var(--warning-gradient)';
        
        setTimeout(() => {
            cartCount.textContent = newCount;
            cartCount.style.transform = 'scale(1)';
            cartCount.style.background = 'var(--secondary-gradient)';
        }, 200);
    }
}

// Enhanced Toast System
function showToast(message, type = 'info', duration = 5000) {
    const iconMap = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${iconMap[type]} me-3" style="font-size: 1.2rem;"></i>
            <div class="flex-grow-1">${message}</div>
            <button class="btn-close btn-close-white ms-3" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
        <div class="progress mt-2" style="height: 3px;">
            <div class="progress-bar" style="width: 100%; transition: width ${duration}ms linear;"></div>
        </div>
    `;

    let removeTimeout;
    // Nếu có anchorEl thì hiển thị toast gần anchorEl (button)
    if (arguments.length > 3 && arguments[3]) {
        // Hiển thị ở giữa màn hình
        toast.style.position = 'fixed';
        toast.style.zIndex = 20000;
        toast.style.minWidth = '260px';
        toast.style.left = '50%';
        toast.style.top = '50%';
        toast.style.transform = 'translate(-50%, -50%)';
        document.body.appendChild(toast);
    } else {
        // Mặc định: hiển thị ở góc phải trên
        const container = document.getElementById('toastContainer');
        container.appendChild(toast);
    }

    // Animate progress bar
    setTimeout(() => {
        const progressBar = toast.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = '0%';
        }
    }, 100);

    // Auto remove
    removeTimeout = setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);

    // Nếu click close thì clear timeout
    toast.querySelector('.btn-close').addEventListener('click', () => {
        clearTimeout(removeTimeout);
    });
}



// Copy to Clipboard Function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast(`Đã sao chép mã: ${text}`, 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast(`Đã sao chép mã: ${text}`, 'success');
    });
}

// Newsletter Subscription
function subscribeNewsletter(event) {
    event.preventDefault();
    const form = event.target;
    const email = form.querySelector('input[type="email"]').value;
    const button = form.querySelector('button');
    const originalText = button.innerHTML;
    
    // Show loading
    button.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Đang xử lý...';
    button.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Success
        button.innerHTML = '<i class="fas fa-check me-2"></i>Đã đăng ký!';
        button.style.background = 'var(--success-gradient)';
        showToast('Cảm ơn bạn đã đăng ký nhận tin!', 'success');
        form.reset();
        
        // Reset button
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            button.style.background = 'var(--primary-gradient)';
        }, 3000);
    }, 1500);
}

// Show Login Required Modal
function showLoginRequiredModal() {
    // Create modal elements
    const modal = document.createElement('div');
    modal.className = 'modal login-required-modal';
    modal.style.display = 'block';
    modal.style.opacity = '0';
    modal.style.transition = 'opacity 0.3s ease';
    
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.style.opacity = '0';
    backdrop.style.transition = 'opacity 0.3s ease';
    
    // Create modal content
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Đăng nhập yêu cầu</h5>
                    <button type="button" class="btn-close" onclick="closeLoginModal()"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-user-lock fa-4x text-primary mb-3"></i>
                        <h4>Bạn cần đăng nhập!</h4>
                        <p class="text-muted">Để thêm sản phẩm vào giỏ hàng, vui lòng đăng nhập trước.</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeLoginModal()">Đóng</button>
                    <a href="${loginUrl}" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập ngay
                    </a>
                </div>
            </div>
        </div>
    `;
    
    // Add modal to DOM
    document.body.appendChild(backdrop);
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Add fade-in animation
    setTimeout(() => {
        backdrop.style.opacity = '0.5';
        modal.style.opacity = '1';
    }, 10);
    
    // Store references for cleanup
    window.loginModal = modal;
    window.loginBackdrop = backdrop;
}

// Close Login Modal
function closeLoginModal() {
    if (window.loginModal) {
        window.loginModal.style.opacity = '0';
        window.loginBackdrop.style.opacity = '0';
        
        setTimeout(() => {
            window.loginModal.remove();
            window.loginBackdrop.remove();
            document.body.style.overflow = '';
        }, 300);
    }
}

// Add Custom CSS for Animations
const additionalStyles = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.4);
        pointer-events: none;
        animation: rippleEffect 0.6s ease-out;
    }
    
    @keyframes rippleEffect {
        0% { transform: scale(0); opacity: 1; }
        100% { transform: scale(4); opacity: 0; }
    }
    
    @keyframes floatToCart {
        0% { transform: scale(0); }
        20% { transform: scale(1); }
        100% { 
            transform: scale(0.5) translate(var(--end-x), var(--end-y)); 
            opacity: 0;
        }
    }
    

    
    .progress {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        overflow: hidden;
    }
    
    .progress-bar {
        background: var(--primary-gradient);
        border-radius: 10px;
    }
    
    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
    }
    
    /* Login Required Modal Styles */
    .login-required-modal .modal-content {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-light);
        transform: translateY(0);
        transition: transform 0.3s ease;
    }
    
    .login-required-modal .modal-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
    }
    
    .login-required-modal .modal-footer {
        border-top: 1px solid rgba(0,  0, 0, 0.05);
        padding: 1.5rem;
    }
    
    .login-required-modal .fa-user-lock {
        color: #764ba2;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .login-required-modal .btn-primary {
        background: var(--primary-gradient);
        border: none;
        box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
        transition: all 0.3s ease;
    }
    
    .login-required-modal .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(118, 75, 162, 0.4);
    }
    

    /* Standardized Card Sizes */
    .card {
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .card-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
    }
    
    .product-card .card-body {
        min-height: 280px;
        justify-content: space-between;
    }
    
    .category-card .card-body {
        min-height: 150px;
        justify-content: center;
    }
    
    .promotion-card .card-body {
        min-height: 220px;
        justify-content: center;
    }
    
    /* Equal height rows */
    .row.g-4 {
        display: flex;
        flex-wrap: wrap;
    }
    
    .row.g-4 > [class*="col-"] {
        display: flex;
        margin-bottom: 2rem;
    }
    
    /* Add bottom margin to elements inside card-body except the last one */
    .card-body > *:not(:last-child) {
        margin-bottom: 0.75rem;
    }
    
    /* Push buttons to bottom of card */
    .card-body .btn {
        margin-top: auto;
    }
    
    /* Fix product card specific alignments */
    .product-card .product-price {
        font-weight: bold;
        font-size: 1.25rem;
        margin-bottom: 1rem;
    }
    
    .product-card .btn-add-cart {
        width: 100%;
    }
    
    .product-card .product-rating {
        margin-bottom: 0.5rem;
    }
    
    /* Sidebar Styles */
    .sidebar-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-light);
        overflow: hidden;
        position: sticky;
        top: 20px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    /* Enhanced Category Header */
    .sidebar-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 1.2rem;
        border-bottom: none;
        position: relative;
        overflow: hidden;
    }
    
    .sidebar-card .card-header h4 {
        color: white;
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .category-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-right: 15px;
        transition: transform 0.3s ease;
    }
    
    .sidebar-card:hover .category-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    .header-decoration {
        position: absolute;
        right: -20px;
        top: -20px;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .header-decoration::before {
        content: '';
        position: absolute;
        right: -30px;
        bottom: -30px;
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }
    
    .category-list .list-group-item {
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
        padding: 1rem 1.25rem;
    }
    
    .category-list .list-group-item:hover {
        border-left-color: #764ba2;
        background: rgba(118, 75, 162, 0.05);
    }
    
    .sidebar-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-dark);
    }
    
    .sidebar-card .list-group-item {
        background: transparent;
        transition: background-color 0.3s ease;
        border-left: none;
        border-right: none;
    }
    
    .sidebar-card .list-group-item:hover {
        background-color: rgba(255, 255, 255, 0.7);
    }
    
    .sidebar-card .list-group-item a {
        color: var(--text-primary);
        transition: color 0.3s ease;
    }
    
    .sidebar-card .list-group-item:hover a {
        color: #764ba2;
    }
    
    .category-icon-placeholder {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    
    /* Fix category icon in header */
    .sidebar-card .card-header .category-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-right: 15px;
        transition: transform 0.3s ease;
    }
    
    /* Fix category list item icons */
    .category-list .category-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .category-list .category-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* Improve text styling in category list */
    .category-list h6 {
        color: var(--text-primary);
        font-weight: 600;
        margin-bottom: 2px;
    }
    
    .category-list small {
        color: var(--text-secondary);
        font-size: 0.75rem;
    }
    
    /* Add hover animation */
    .category-list .list-group-item {
        position: relative;
        overflow: hidden;
    }
    
    .category-list .list-group-item::before {
        content: '';
        position: absolute;
        left: -100%;
        top: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(118, 75, 162, 0.1), transparent);
        transition: left 0.5s;
    }
    
    .category-list .list-group-item:hover::before {
        left: 100%;
    }
    
    .sidebar-card .card-footer {
        background: transparent;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .sidebar-card .btn-outline-primary {
        border-color: #764ba2;
        color: #764ba2;
    }
    
    .sidebar-card .btn-outline-primary:hover {
        background: var(--primary-gradient);
        color: white;
        border-color: transparent;
    }
    
    /* Media query for responsive sidebar */
    @media (max-width: 991.98px) {
        .sidebar-card {
            position: static;
            margin-bottom: 30px;
        }
        
        .sidebar-card .card-header {
            padding: 1rem;
        }
        
        .sidebar-card .card-header h4 {
            font-size: 1.1rem;
        }
        
        .category-list .list-group-item {
            padding: 0.75rem 1rem;
        }
        
        .category-icon, .category-icon-placeholder {
            width: 35px;
            height: 35px;
        }
        
        .sidebar-card .card-header .category-icon {
            width: 35px;
            height: 35px;
            margin-right: 10px;
        }
    }
    
    @media (max-width: 767.98px) {
        .sidebar-card {
            margin-bottom: 20px;
        }
        
        .category-list h6 {
            font-size: 0.9rem;
        }
        
        .category-list small {
            font-size: 0.7rem;
        }
    }
    
    /* Add subtle animation to the entire sidebar */
    .sidebar-card {
        animation: slideInRight 0.6s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Ensure sidebar is always visible */
    .sidebar-card {
        visibility: visible;
        opacity: 1;
    }
`;

// Add the styles to head
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);
</script>

        </div> <!-- Close Main Content Column -->
    </div> <!-- Close Row -->
</div> <!-- Close Container -->

<!-- Chat Widget (chỉ hiện khi đã đăng nhập) -->
<?php if ($isLoggedIn): ?>
<!-- Chat Widget -->
<div id="chatWidget" class="chat-widget">
    <div class="chat-toggle" id="chatToggle">
        <i class="fas fa-comments"></i>
        <div class="chat-badge" id="chatBadge" style="display: none;">
            <span id="unreadCount">0</span>
        </div>
    </div>
    
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <h6 class="mb-0">
                <i class="fas fa-headset me-2"></i>
                Hỗ trợ trực tuyến
            </h6>
            <button class="btn-close-chat" id="closeChatBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="chat-body" id="chatBody">
            <div class="welcome-message">
                <div class="avatar-admin">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="message-content">
                    <strong>Đội ngũ hỗ trợ</strong>
                    <p>Xin chào! Chúng tôi có thể giúp gì cho bạn?</p>
                    <small class="text-muted">Vừa xong</small>
                </div>
            </div>
        </div>
        
        <div class="chat-input">
            <div class="input-group">
                <input type="text" class="form-control" id="chatMessageInput" placeholder="Nhập tin nhắn...">
                <button class="btn btn-primary" id="sendChatBtn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
        
        <div class="chat-footer">
            <a href="feedbacks.php" class="btn btn-sm btn-outline-primary w-100">
                <i class="fas fa-expand me-2"></i>
                Mở chat đầy đủ
            </a>
        </div>
    </div>
</div>



<script>
class ChatWidget {
    constructor() {
        this.chatToggle = document.getElementById('chatToggle');
        this.chatWindow = document.getElementById('chatWindow');
        this.closeChatBtn = document.getElementById('closeChatBtn');
        this.chatBody = document.getElementById('chatBody');
        this.messageInput = document.getElementById('chatMessageInput');
        this.sendBtn = document.getElementById('sendChatBtn');
        this.chatBadge = document.getElementById('chatBadge');
        this.unreadCount = document.getElementById('unreadCount');
        
        this.isOpen = false;
        this.currentRoomId = null;
        this.lastMessageId = 0;
        
        this.init();
    }
    
    init() {
        // Event listeners
        this.chatToggle.addEventListener('click', () => this.toggleChat());
        this.closeChatBtn.addEventListener('click', () => this.closeChat());
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Get or create room when widget loads
        this.getOrCreateRoom();
        
        // Check for new messages every 5 seconds when closed
        this.checkInterval = setInterval(() => {
            if (!this.isOpen) {
                this.checkNewMessages();
            }
        }, 5000);
        
        // Auto-refresh messages when chat is open
        this.refreshInterval = null;
    }
    
    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }
    
    openChat() {
        this.isOpen = true;
        this.chatWindow.classList.add('show');
        this.messageInput.focus();
        this.loadMessages();
        this.hideUnreadBadge();
        
        // Start auto-refresh when chat is open
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        this.refreshInterval = setInterval(() => {
            this.loadMessages();
        }, 3000);
    }
    
    closeChat() {
        this.isOpen = false;
        this.chatWindow.classList.remove('show');
        
        // Stop auto-refresh when chat is closed
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    async getOrCreateRoom() {
        try {
            const response = await fetch('api/chat_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_or_create_room'
            });
            
            const data = await response.json();
            if (data.success) {
                this.currentRoomId = data.room_id;
                console.log('Chat room created/found:', this.currentRoomId);
            }
        } catch (error) {
            console.error('Error creating chat room:', error);
        }
    }
    
    async loadMessages() {
        if (!this.currentRoomId) return;
        
        try {
            const response = await fetch('api/chat_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_messages&room_id=${this.currentRoomId}`
            });
            
            const data = await response.json();
            if (data.success && data.messages.length > 0) {
                this.displayMessages(data.messages);
                console.log('Loaded', data.messages.length, 'messages');
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }
    
    async checkNewMessages() {
        if (!this.currentRoomId) return;
        
        try {
            const response = await fetch('api/chat_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_messages&room_id=${this.currentRoomId}`
            });
            
            const data = await response.json();
            if (data.success && data.messages.length > 0) {
                const newMessages = data.messages.filter(msg => msg.message_id > this.lastMessageId);
                if (newMessages.length > 0 && !this.isOpen) {
                    this.showUnreadBadge(newMessages.length);
                    console.log('Found', newMessages.length, 'new messages');
                }
            }
        } catch (error) {
            console.error('Error checking new messages:', error);
        }
    }
    
    displayMessages(messages) {
        // Clear welcome message if there are real messages
        const welcomeMsg = this.chatBody.querySelector('.welcome-message');
        if (welcomeMsg && messages.length > 0) {
            welcomeMsg.remove();
        }
        
        // Clear existing messages except welcome
        const existingMessages = this.chatBody.querySelectorAll('.message-user, .message-admin');
        existingMessages.forEach(msg => msg.remove());
        
        // Display messages (show last 10 messages in widget)
        const recentMessages = messages.slice(-10);
        let maxMessageId = 0;
        
        recentMessages.forEach(message => {
            const messageEl = this.createMessageElement(message);
            this.chatBody.appendChild(messageEl);
            maxMessageId = Math.max(maxMessageId, message.message_id);
        });
        
        // Update last message ID
        if (maxMessageId > 0) {
            this.lastMessageId = maxMessageId;
        }
        
        this.scrollToBottom();
    }
    
    createMessageElement(message) {
        const messageDiv = document.createElement('div');
        const isUser = message.sender_type === 'user';
        
        if (isUser) {
            messageDiv.className = 'message-user';
            messageDiv.innerHTML = `
                <div class="message-content">
                    <p>${this.escapeHtml(message.message)}</p>
                    <small class="text-muted">${this.formatTime(message.created_at)}</small>
                </div>
            `;
        } else {
            messageDiv.className = 'message-admin';
            messageDiv.innerHTML = `
                <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 15px;">
                    <div class="avatar-admin">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="message-content">
                        <strong>Hỗ trợ viên</strong>
                        <p>${this.escapeHtml(message.message)}</p>
                        <small class="text-muted">${this.formatTime(message.created_at)}</small>
                    </div>
                </div>
            `;
        }
        
        return messageDiv;
    }
    
    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message || !this.currentRoomId) return;
        
        try {
            const response = await fetch('api/chat_simple.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=send_message&room_id=${this.currentRoomId}&message=${encodeURIComponent(message)}`
            });
            
            const data = await response.json();
            if (data.success) {
                this.messageInput.value = '';
                this.loadMessages();
                console.log('Message sent successfully');
            } else {
                console.error('Failed to send message:', data.message);
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }
    
    showUnreadBadge(count) {
        this.unreadCount.textContent = count;
        this.chatBadge.style.display = 'flex';
        
        // Add notification sound or visual effect
        this.chatToggle.style.animation = 'pulse 1.5s ease-in-out 3';
        setTimeout(() => {
            this.chatToggle.style.animation = '';
        }, 4500);
    }
    
    hideUnreadBadge() {
        this.chatBadge.style.display = 'none';
        this.unreadCount.textContent = '0';
    }
    
    scrollToBottom() {
        this.chatBody.scrollTop = this.chatBody.scrollHeight;
    }
    
    formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'Vừa xong';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'p';
        if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
        return date.toLocaleDateString('vi-VN');
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Cleanup intervals when widget is destroyed
    destroy() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Initialize chat widget when page loads
document.addEventListener('DOMContentLoaded', () => {
    window.chatWidget = new ChatWidget();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.chatWidget) {
        window.chatWidget.destroy();
    }
});
</script>
<?php endif; ?>

<!-- Chatbot AI Widget (hiện cho tất cả người dùng) -->
<link rel="stylesheet" href="css/chatbot.css">
<script src="js/chatbot.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Chatbot AI...');
    
    // Kiểm tra xem đã có instance chưa
    if (window.chatbotAIInstance) {
        console.log('Chatbot AI already initialized');
        return;
    }
    
    // Khởi tạo chatbot AI
    const chatbotAI = new ChatbotAI();
    
    // Debug: Kiểm tra xem có admin chat widget không
    const adminChatExists = document.getElementById('chatWidget');
    console.log('Admin chat widget exists:', adminChatExists !== null);
    
    // Đảm bảo chatbot widget được hiển thị
    setTimeout(() => {
        const chatbotWidget = document.querySelector('.chatbot-widget');
        if (chatbotWidget) {
            console.log('Chatbot widget found and made visible');
            chatbotWidget.style.display = 'block';
        } else {
            console.error('Chatbot widget not found!');
        }
    }, 200);
});
</script>

<?php include 'includes/footer.php'; ?>