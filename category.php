<?php
require_once 'config/config.php';
require_once 'config/functions.php';

$pdo = getPDO();

// Validate category ID
$category_id = (int)($_GET['id'] ?? 0);
if (!$category_id) {
    redirect('index.php');
}

// Lấy thông tin danh mục
$stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    redirect('index.php');
}

// Lấy danh sách sản phẩm theo danh mục
$stmt = $pdo->prepare("SELECT p.*, 
           (SELECT image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
    FROM products p
    WHERE p.category_id = ? AND p.status = 'active'
    ORDER BY p.created_at DESC");
$stmt->execute([$category_id]);
$products = $stmt->fetchAll();

include 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trang sản phẩm theo danh mục</title>
    <link rel="stylesheet" href="css/category.css">
</head>
<body>




<div class="container py-5">
    <!-- Enhanced Category Header -->
    <div class="category-header">
        <h1>Danh mục: <?= htmlspecialchars($category['name']) ?></h1>
        <div class="category-stats">
            <div class="stat-item">
                <i class="fas fa-box"></i>
                <span><?= count($products) ?> sản phẩm</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-star"></i>
                <span>Danh mục phổ biến</span>
            </div>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <h3>Không có sản phẩm nào</h3>
            <p>Không có sản phẩm nào trong danh mục này.</p>
        </div>
    <?php else: ?>
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-options">
                <button class="filter-btn active" data-filter="all">Tất cả</button>
                <button class="filter-btn" data-filter="new">Mới nhất</button>
                <button class="filter-btn" data-filter="popular">Phổ biến</button>
            </div>
            <select class="sort-dropdown" id="sortProducts">
                <option value="newest">Mới nhất</option>
                <option value="price-low">Giá thấp đến cao</option>
                <option value="price-high">Giá cao đến thấp</option>
                <option value="rating">Đánh giá cao nhất</option>
            </select>
        </div>

        <!-- Enhanced Products Grid -->
        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $index => $product): ?>
                <div class="product-card" style="animation-delay: <?= ($index * 0.1) + 0.1 ?>s" data-price="<?= $product['price'] ?>" data-rating="<?= $product['avg_rating'] ?? 0 ?>">
                    <div class="product-image-container">
                        <img src="<?= $product['product_image'] ?: 'assets/images/no-image.jpg' ?>" 
                             class="card-img-top" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                                loading="lazy" decoding="async">
                        <div class="product-overlay">
                            <a href="product.php?id=<?= $product['product_id'] ?>" class="quick-view-btn">
                                <i class="fas fa-info-circle"></i> Xem chi tiết
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="product.php?id=<?= $product['product_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($product['name']) ?>
                            </a>
                        </h5>
                        <p class="product-price text-danger fw-bold">
                            <?= format_currency($product['price']) ?>
                        </p>
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
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Scroll to Top Button -->
<button class="scroll-to-top" id="scrollToTop">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Enhanced JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation delays for product cards
    const productCards = document.querySelectorAll('.product-card');
    
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, observerOptions);

    productCards.forEach(card => {
        observer.observe(card);
    });

    // Filter functionality
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            filterBtns.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const filter = this.getAttribute('data-filter');
            
            // Animate products
            productCards.forEach((product, index) => {
                product.style.animation = 'none';
                product.style.opacity = '0';
                product.style.transform = 'translateY(30px) scale(0.9)';
                
                setTimeout(() => {
                    product.style.display = 'block';
                    setTimeout(() => {
                        product.style.animation = `fadeInUp 0.6s ease ${index * 0.1}s forwards`;
                    }, 50);
                }, 200);
            });
        });
    });

    // Sort functionality
    const sortDropdown = document.getElementById('sortProducts');
    if (sortDropdown) {
        sortDropdown.addEventListener('change', function() {
            const sortValue = this.value;
            const productsContainer = document.getElementById('productsGrid');
            const productsArray = Array.from(productCards);

            // Sort products based on selected option
            productsArray.sort((a, b) => {
                switch(sortValue) {
                    case 'price-low':
                        return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    case 'price-high':
                        return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    case 'rating':
                        return parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating);
                    default:
                        return 0;
                }
            });

            // Animate out
            productsArray.forEach(product => {
                product.style.opacity = '0';
                product.style.transform = 'translateY(-20px)';
            });

            setTimeout(() => {
                // Clear and re-add sorted products
                productsContainer.innerHTML = '';
                productsArray.forEach((product, index) => {
                    product.style.animationDelay = `${index * 0.1}s`;
                    product.style.animation = 'fadeInUp 0.6s ease forwards';
                    productsContainer.appendChild(product);
                });
            }, 300);
        });
    }

    // Scroll to top functionality
    const scrollToTopBtn = document.getElementById('scrollToTop');
    if (scrollToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.classList.add('visible');
            } else {
                scrollToTopBtn.classList.remove('visible');
            }
        });

        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Parallax effect for background
    window.addEventListener('scroll', function() {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        document.body.style.backgroundPosition = `center ${rate}px`;
    });

    // Hover effects for product cards
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});

// Loading animation utility
function showLoading() {
    const grid = document.getElementById('productsGrid');
    if (grid) {
        grid.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-primary"></i><p class="mt-3">Đang tải sản phẩm...</p></div>';
    }
}

// Add smooth scrolling for internal links
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
</script>

<?php include 'includes/footer.php'; ?>