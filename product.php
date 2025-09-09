<?php
require_once 'config/config.php';
require_once 'config/functions.php';

$pdo = getPDO();

// Kiểm tra trạng thái đăng nhập và quyền admin
$isLoggedIn = is_logged_in();
$isAdmin = is_admin();

// Validate product ID
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) {
    redirect('products.php');
}

// Lấy thông tin sản phẩm
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, s.name as supplier_name,
           (SELECT COUNT(*) FROM order_items oi 
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.product_id = p.product_id AND o.status = 'delivered') as total_sold,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN suppliers s ON p.supplier_id = s.supplier_id
    WHERE p.product_id = ? AND p.status = 'active'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('products.php');
}

// Lấy hình ảnh sản phẩm
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll();

// Lấy thuộc tính sản phẩm
$stmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ?");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll();

// Lấy đánh giá sản phẩm
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name 
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Lấy sản phẩm liên quan (cùng danh mục)
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT image_url FROM product_images pi 
            WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image
    FROM products p
    WHERE p.category_id = ? 
    AND p.product_id != ? 
    AND p.status = 'active'
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll();

include 'includes/navbar.php';
?>
<link rel="stylesheet" href="css/product.css">


<div class="container py-5">
   

    <div class="row product-main-section">
        <!-- Product images -->
        <div class="col-md-6 mb-4">
            <div id="productImages" class="carousel slide product-carousel" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <?php foreach ($images as $i => $img): ?>
                        <button type="button" data-bs-target="#productImages" 
                                data-bs-slide-to="<?= $i ?>" 
                                class="<?= $i === 0 ? 'active' : '' ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner">
                    <?php if (empty($images)): ?>
                        <div class="carousel-item active">
                            <img src="assets/images/no-image.jpg" class="d-block w-100 image-zoom" alt="No image">
                        </div>
                    <?php else: ?>
                        <?php foreach ($images as $i => $img): ?>
                            <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                                <img src="<?= $img['image_url'] ?>" class="d-block w-100 image-zoom" 
                                     alt="<?= htmlspecialchars($product['name']) ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#productImages" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#productImages" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>
        </div>

        <!-- Product info -->
        <div class="col-md-6 product-info">
            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
            
            <div class="mb-3">
                <span class="price-tag"><?= format_currency($product['price']) ?></span>
            </div>

            <?php if ($product['review_count'] > 0): ?>
                <div class="mb-3 rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?= $i <= round($product['avg_rating']) ? ' text-warning' : ' text-muted' ?>"></i>
                    <?php endfor; ?>
                    <span class="text-muted ms-2">
                        <?= number_format($product['avg_rating'], 1) ?>/5 
                        (<?= $product['review_count'] ?> đánh giá)
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($product['total_sold'] > 0): ?>
                <div class="mb-3">
                    <span class="text-muted">
                        <i class="fas fa-fire text-danger"></i>
                        Đã bán: <?= $product['total_sold'] ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
            </div>

            <?php if (!empty($attributes)): ?>
                <div class="mb-4">
                    <h6>Thông số kỹ thuật:</h6>
                    <table class="table specs-table">
                        <tbody>
                            <?php foreach ($attributes as $attr): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($attr['attribute_name']) ?></td>
                                    <td><?= htmlspecialchars($attr['attribute_value']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Add to cart form -->
            <?php if (is_logged_in() && !$isAdmin): ?>
                <form action="cart.php" method="get" class="mb-4" id="addToCartForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" value="<?= $product_id ?>">
                    
                    <div class="row align-items-center mb-3">
                        <div class="col-auto">
                            <label class="form-label fw-bold">Số lượng:</label>
                            <input type="number" class="form-control quantity-input" name="quantity" 
                                   value="1" min="1" max="<?= $product['stock'] ?>"
                                   style="width: 100px;">
                        </div>
                        <div class="col">
                            <?php
                            $stock_class = 'stock-high';
                            if ($product['stock'] <= 10) $stock_class = 'stock-low';
                            elseif ($product['stock'] <= 50) $stock_class = 'stock-medium';
                            ?>
                            <small class="stock-indicator <?= $stock_class ?>">
                                Còn <?= $product['stock'] ?> sản phẩm
                            </small>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-enhanced btn-lg" id="addToCartBtn">
                            <i class="fas fa-shopping-cart"></i> 
                            <span class="btn-text">Thêm vào giỏ</span>
                            <span class="loading d-none"></span>
                        </button>
                    </div>
                </form>
            <?php elseif (!is_logged_in()): ?>
                <div class="login-required-section mb-4">
                    <div class="alert alert-info border-0 shadow-sm">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <span>Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng</span>
                        </div>
                    </div>
                    <div class="row align-items-center mb-3">
                        <div class="col-auto">
                            <label class="form-label fw-bold">Số lượng:</label>
                            <input type="number" class="form-control quantity-input" 
                                   value="1" min="1" max="<?= $product['stock'] ?>"
                                   style="width: 100px;" disabled>
                        </div>
                        <div class="col">
                            <?php
                            $stock_class = 'stock-high';
                            if ($product['stock'] <= 10) $stock_class = 'stock-low';
                            elseif ($product['stock'] <= 50) $stock_class = 'stock-medium';
                            ?>
                            <small class="stock-indicator <?= $stock_class ?>">
                                Còn <?= $product['stock'] ?> sản phẩm
                            </small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                           class="btn btn-enhanced btn-lg">
                            <i class="fas fa-sign-in-alt"></i> 
                            <span class="btn-text">Đăng nhập để mua hàng</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h6>Thông tin thêm:</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-box text-muted me-2"></i>
                        Danh mục: 
                        <a href="products.php?category=<?= $product['category_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </a>
                    </li>
                    <li>
                        <i class="fas fa-truck text-muted me-2"></i>
                        Nhà cung cấp: <?= htmlspecialchars($product['supplier_name']) ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Reviews -->
    <div class="row mt-5 product-reviews-section">
        <div class="col-12">
            <h3 class="mb-4">Đánh giá sản phẩm</h3>

            <?php if (empty($reviews)): ?>
                <div class="alert alert-info">
                    Chưa có đánh giá nào cho sản phẩm này.
                </div>
            <?php else: ?>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h1 class="display-4 fw-bold text-warning mb-0">
                                <?= number_format($product['avg_rating'], 1) ?>
                            </h1>
                            <div class="mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($product['avg_rating']) ? ' text-warning' : ' text-muted' ?> fa-lg"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="text-muted">
                                <?= $product['review_count'] ?> đánh giá
                            </p>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card position-relative">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($review['full_name']) ?></h6>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($review['created_at'])) ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= $review['rating'] ? ' text-warning' : ' text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        <?= nl2br(htmlspecialchars($review['comment'])) ?>
                                    </p>
                                    <?php if (isset($review['reply']) && $review['reply']): ?>
                                        <div class="bg-light p-3 rounded">
                                            <small class="text-muted">Phản hồi từ shop:</small><br>
                                            <?= nl2br(htmlspecialchars($review['reply'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="review-card">
                    <div class="card-body">
                        <h5 class="card-title">Viết đánh giá</h5>
                        <form action="review_add.php" method="post" id="reviewForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Đánh giá của bạn</label>
                                <div class="rating-container">
                                    <div class="rating">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" required>
                                            <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nhận xét của bạn</label>
                                <textarea class="form-control" name="comment" rows="3" maxlength="500" required placeholder="Chia sẻ trải nghiệm của bạn với sản phẩm này..."></textarea>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-enhanced">
                                    <i class="fas fa-paper-plane me-2"></i> Gửi đánh giá
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Vui lòng <a href="login.php">đăng nhập</a> để viết đánh giá.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Related products -->
    <?php if (!empty($related_products)): ?>
        <div class="row mt-5 product-related-section">
            <div class="col-12">
                <h3 class="mb-4">Sản phẩm liên quan</h3>
                <div class="row g-4" id="relatedProducts">
                    <?php foreach ($related_products as $index => $related): ?>
                        <div class="col-md-3" style="animation-delay: <?= $index * 0.1 ?>s">
                            <div class="related-product-card h-100">
                                <?php if ($related['product_image']): ?>
                                    <img src="<?= $related['product_image'] ?>" 
                                         class="card-img-top" 
                                         alt="<?= htmlspecialchars($related['name']) ?>">
                                <?php else: ?>
                                    <img src="assets/images/no-image.jpg" 
                                         class="card-img-top" 
                                         alt="No image">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="product.php?id=<?= $related['product_id'] ?>" 
                                           class="text-decoration-none">
                                            <?= htmlspecialchars($related['name']) ?>
                                        </a>
                                    </h5>
                                    <p class="card-text text-danger fw-bold">
                                        <?= format_currency($related['price']) ?>
                                    </p>
                                    <?php if (is_logged_in() && !$isAdmin): ?>
                                        <a href="cart.php?action=add&id=<?= $related['product_id'] ?>" 
                                           class="btn btn-enhanced w-100 add-to-cart-related">
                                            <i class="fas fa-shopping-cart"></i> Thêm vào giỏ
                                        </a>
                                    <?php elseif (!is_logged_in()): ?>
                                        <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                           class="btn btn-outline-primary w-100">
                                            <i class="fas fa-sign-in-alt"></i> Đăng nhập để mua
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Global variables
const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Image zoom functionality
    const images = document.querySelectorAll('.image-zoom');
    images.forEach(img => {
        img.addEventListener('click', function() {
            const zoomContainer = document.createElement('div');
            zoomContainer.className = 'zoomed';
            
            const zoomedImg = this.cloneNode();
            zoomContainer.appendChild(zoomedImg);
            
            document.body.appendChild(zoomContainer);
            document.body.style.overflow = 'hidden';
            
            zoomContainer.addEventListener('click', function() {
                document.body.removeChild(zoomContainer);
                document.body.style.overflow = 'auto';
            });
        });
    });

    // Character counter for review textarea
    const reviewTextarea = document.querySelector('textarea[name="comment"]');
    if (reviewTextarea) {
        // Create and add character counter element
        const counterDiv = document.createElement('div');
        counterDiv.className = 'text-end text-muted mt-1';
        counterDiv.style.maxWidth = '100%';
        counterDiv.style.overflow = 'hidden';
        counterDiv.innerHTML = '<small><span id="charCount">0</span> / 500 ký tự</small>';
        reviewTextarea.insertAdjacentElement('afterend', counterDiv);
        
        // Update counter on input
        reviewTextarea.addEventListener('input', function() {
            const count = this.value.length;
            const charCount = document.getElementById('charCount');
            charCount.textContent = count;
            
            // Change color based on length
            if (count > 400) {
                charCount.style.color = '#e74c3c';
            } else if (count > 300) {
                charCount.style.color = '#f39c12';
            } else {
                charCount.style.color = '';
            }
        });
        
        // Add focus effects
        reviewTextarea.addEventListener('focus', function() {
            this.style.boxShadow = '0 0 15px rgba(102, 126, 234, 0.3)';
            this.style.borderColor = '#667eea';
        });
        
        reviewTextarea.addEventListener('blur', function() {
            this.style.boxShadow = '';
        });
    }

    // Enhanced add to cart functionality (only if user is logged in)
    const addToCartForm = document.getElementById('addToCartForm');
    if (addToCartForm) {
        addToCartForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Check if user is admin
            if (isAdmin) {
                showToast('Admin không thể thêm sản phẩm vào giỏ hàng!', 'warning');
                return;
            }
            
            const button = this.querySelector('button[type="submit"]');
            const btnText = button.querySelector('.btn-text');
            const loading = button.querySelector('.loading');
            
            // Show loading state
            btnText.classList.add('d-none');
            loading.classList.remove('d-none');
            button.disabled = true;
            
            // Send AJAX request to add to cart
            const formData = new FormData(this);
            
            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showSuccessMessage(data.message || 'Đã thêm sản phẩm vào giỏ hàng thành công!');
                    
                    // Update cart count if available
                    if (typeof window.updateCartCount === 'function' && data.cart_count !== undefined) {
                        window.updateCartCount(data.cart_count);
                    }
                } else {
                    showErrorMessage(data.message || 'Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng!');
            })
            .finally(() => {
                // Reset button state
                btnText.classList.remove('d-none');
                loading.classList.add('d-none');
                button.disabled = false;
            });
        });
    }

    // Enhanced rating system
    const ratingInputs = document.querySelectorAll('.rating input');
    const ratingLabels = document.querySelectorAll('.rating label');
    
    // Add initial animation to show users it's interactive
    ratingLabels.forEach((label, index) => {
        setTimeout(() => {
            label.style.animation = 'bounce 0.6s ease-out';
            setTimeout(() => {
                label.style.animation = '';
            }, 600);
        }, index * 100);
    });
    
    ratingInputs.forEach(input => {
        input.addEventListener('change', function() {
            const rating = this.value;
            const stars = this.parentElement.querySelectorAll('label');
            
            // Add animation to selected stars
            stars.forEach((star, index) => {
                if (5 - index <= rating) {
                    star.style.animation = 'bounce 0.6s ease-out';
                    star.style.textShadow = '0 0 15px rgba(255, 193, 7, 0.7)';
                    setTimeout(() => {
                        star.style.animation = '';
                    }, 600);
                } else {
                    star.style.textShadow = 'none';
                }
            });
            
            // Show a feedback message based on rating
            let feedbackMessage = '';
            switch(parseInt(rating)) {
                case 1: feedbackMessage = 'Rất không hài lòng'; break;
                case 2: feedbackMessage = 'Không hài lòng'; break;
                case 3: feedbackMessage = 'Bình thường'; break;
                case 4: feedbackMessage = 'Hài lòng'; break;
                case 5: feedbackMessage = 'Rất hài lòng'; break;
            }
            
            // Check if we already have a feedback element
            let feedbackEl = document.querySelector('.rating-feedback');
            if (!feedbackEl) {
                feedbackEl = document.createElement('div');
                feedbackEl.className = 'rating-feedback text-center mt-2 mb-3';
                feedbackEl.style.maxWidth = '100%';
                feedbackEl.style.wordWrap = 'break-word';
                this.parentElement.insertAdjacentElement('afterend', feedbackEl);
            }
            
            feedbackEl.textContent = feedbackMessage;
            feedbackEl.style.color = '#667eea';
            feedbackEl.style.fontWeight = 'bold';
            feedbackEl.style.animation = 'fadeIn 0.5s';
        });
    });

    // Smooth scroll for anchors
    const anchors = document.querySelectorAll('a[href^="#"]');
    anchors.forEach(anchor => {
        anchor.addEventListener('click', function(e) {
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

    // Quantity input enhancement
    const quantityInput = document.querySelector('.quantity-input');
    if (quantityInput) {
        quantityInput.addEventListener('focus', function() {
            this.style.borderColor = '#764ba2';
            this.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.3)';
        });
        
        quantityInput.addEventListener('blur', function() {
            this.style.borderColor = '#667eea';
            this.style.boxShadow = 'none';
        });
    }

    // Related products add to cart (only for logged in users)
    const relatedCartButtons = document.querySelectorAll('.add-to-cart-related');
    relatedCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="loading"></span> Đang thêm...';
            this.disabled = true;
            
            // Extract product ID from href
            const href = this.getAttribute('href');
            const url = new URL(href, window.location.origin);
            
            // Send AJAX request
            fetch(href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (response.ok) {
                    showSuccessMessage('Đã thêm sản phẩm vào giỏ hàng!');
                    this.innerHTML = '<i class="fas fa-check"></i> Đã thêm';
                    
                    // Update cart count if available
                    if (typeof window.updateCartCount === 'function') {
                        // Re-fetch cart count
                        fetch('api/get-cart-count.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.cart_count !== undefined) {
                                    window.updateCartCount(data.cart_count);
                                }
                            })
                            .catch(console.error);
                    }
                } else {
                    showErrorMessage('Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng!');
            })
            .finally(() => {
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 2000);
            });
        });
    });

    // Animate elements on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe elements for scroll animations
    const animateElements = document.querySelectorAll('.review-card, .related-product-card, .specs-table');
    animateElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s ease-out';
        observer.observe(el);
    });

    // Carousel enhancement
    const carousel = document.querySelector('#productImages');
    if (carousel) {
        carousel.addEventListener('slide.bs.carousel', function(e) {
            const activeItem = e.relatedTarget;
            const img = activeItem.querySelector('img');
            
            // Add slide transition effect
            img.style.transform = 'scale(1.1)';
            setTimeout(() => {
                img.style.transform = 'scale(1)';
            }, 300);
        });
    }

    // Price animation on page load
    const priceTag = document.querySelector('.price-tag');
    if (priceTag) {
        setTimeout(() => {
            priceTag.style.animation = 'pulse 2s infinite';
        }, 1000);
    }

    // Review form enhancement
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        const textarea = reviewForm.querySelector('textarea');
        
        textarea.addEventListener('input', function() {
            const charCount = this.value.length;
            let countDisplay = this.nextElementSibling;
            
            if (!countDisplay || !countDisplay.classList.contains('char-count')) {
                countDisplay = document.createElement('small');
                countDisplay.className = 'char-count text-muted';
                this.parentNode.appendChild(countDisplay);
            }
            
            countDisplay.textContent = `${charCount}/500 ký tự`;
            
            if (charCount > 450) {
                countDisplay.style.color = '#e74c3c';
            } else {
                countDisplay.style.color = '#6c757d';
            }
        });
    }

    // Breadcrumb animation
    const breadcrumbItems = document.querySelectorAll('.breadcrumb-item');
    breadcrumbItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.3s ease-out';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 100);
    });
});

// Success message function
function showSuccessMessage(message) {
    const successMsg = document.createElement('div');
    successMsg.className = 'success-message';
    successMsg.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        ${message}
    `;
    
    document.body.appendChild(successMsg);

// Toast message function for different types
function showToast(message, type = 'info', duration = 5000) {
    const iconMap = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle', 
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast show position-fixed`;
    toast.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 9999;
        background: ${type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 300px;
    `;
    
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas ${iconMap[type]} me-3" style="font-size: 1.2rem;"></i>
            <div class="flex-grow-1">${message}</div>
            <button class="btn-close btn-close-white ms-3" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}
    
    setTimeout(() => {
        if (document.body.contains(successMsg)) {
            document.body.removeChild(successMsg);
        }
    }, 3000);
}

// Lazy loading for images
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });

    const lazyImages = document.querySelectorAll('img[data-src]');
    lazyImages.forEach(img => {
        imageObserver.observe(img);
    });
}

// Keyboard navigation for carousel
document.addEventListener('keydown', function(e) {
    const carousel = document.querySelector('#productImages');
    if (!carousel) return;
    
    if (e.key === 'ArrowLeft') {
        const prevButton = carousel.querySelector('.carousel-control-prev');
        prevButton.click();
    } else if (e.key === 'ArrowRight') {
        const nextButton = carousel.querySelector('.carousel-control-next');
        nextButton.click();
    }
});

// Touch gestures for mobile
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    const carousel = document.querySelector('#productImages');
    if (!carousel) return;
    
    const swipeThreshold = 50;
    const diff = touchStartX - touchEndX;
    
    if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
            // Swipe left - next image
            const nextButton = carousel.querySelector('.carousel-control-next');
            nextButton.click();
        } else {
            // Swipe right - previous image
            const prevButton = carousel.querySelector('.carousel-control-prev');
            prevButton.click();
        }
    }
}

// Performance optimization - debounce scroll events
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Optimized scroll handler
const handleScroll = debounce(() => {
    const scrolled = window.pageYOffset;
    const navbar = document.querySelector('.navbar');
    
    if (navbar) {
        if (scrolled > 100) {
            navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            navbar.style.backdropFilter = 'blur(10px)';
        } else {
            navbar.style.boxShadow = 'none';
            navbar.style.backdropFilter = 'none';
        }
    }
}, 10);

window.addEventListener('scroll', handleScroll);

// Function to show success message
function showSuccessMessage(message) {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 1055;';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 3000
    });
    bsToast.show();
    
    // Remove from DOM after hiding
    toast.addEventListener('hidden.bs.toast', function() {
        document.body.removeChild(toast);
    });
}

// Function to show error message
function showErrorMessage(message) {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-danger border-0 position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 1055;';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-exclamation-circle me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 3000
    });
    bsToast.show();
    
    // Remove from DOM after hiding
    toast.addEventListener('hidden.bs.toast', function() {
        document.body.removeChild(toast);
    });
}
</script>

<?php include 'includes/footer.php'; ?>