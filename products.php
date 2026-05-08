<?php
require_once 'config/config.php';
require_once 'config/functions.php';

$pdo = getPDO();

// Kiểm tra trạng thái đăng nhập và quyền admin
$isLoggedIn = is_logged_in();
$isAdmin = is_admin();

// Lấy các tham số filter
$category_id = (int)($_GET['category'] ?? 0);
// Parse price inputs - remove dots and convert to float
$min_price_raw = $_GET['min_price'] ?? '';
$max_price_raw = $_GET['max_price'] ?? '';
$min_price = (float)str_replace('.', '', $min_price_raw);
$max_price = (float)str_replace('.', '', $max_price_raw);
$supplier_id = (int)($_GET['supplier'] ?? 0);
$sort = $_GET['sort'] ?? 'new';
$view = $_GET['view'] ?? 'grid';
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = USER_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Lấy danh sách danh mục
$categories = get_categories();

// Lấy danh sách nhà cung cấp
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name")->fetchAll();

// Xây dựng query với filter
$where_clauses = ["p.status = 'active'"];
$params = [];

if ($category_id) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($supplier_id) {
    $where_clauses[] = "p.supplier_id = ?";
    $params[] = $supplier_id;
}

if ($min_price > 0) {
    $where_clauses[] = "p.price >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $where_clauses[] = "p.price <= ?";
    $params[] = $max_price;
}

if ($search) {
    $where_clauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Order by
$order_sql = match($sort) {
    'price_asc' => 'ORDER BY p.price ASC',
    'price_desc' => 'ORDER BY p.price DESC',
    'bestseller' => 'ORDER BY order_count DESC',
    default => 'ORDER BY p.created_at DESC'
};

// Đếm tổng số sản phẩm
$count_sql = "
    SELECT COUNT(*) 
    FROM products p
    $where_sql
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Query chính
$sql = "
    SELECT p.*, c.name as category_name, s.name as supplier_name,
           (SELECT image_url FROM product_images pi 
            WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image,
           (SELECT COUNT(*) FROM order_items oi 
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.product_id = p.product_id AND o.status = 'delivered') as order_count
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN suppliers s ON p.supplier_id = s.supplier_id
    $where_sql
    $order_sql
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Lấy min/max price cho filter
$price_range = $pdo->query("
    SELECT MIN(price) as min_price, MAX(price) as max_price 
    FROM products WHERE status = 'active'
")->fetch();

include 'includes/navbar.php';
?>

<link rel="stylesheet" href="css/products.css">

<div class="products-container">
    <div class="container py-5">
        <div class="row">
            <!-- Sidebar filter -->
            <div class="col-lg-3">
                <div class="filter-sidebar">
                    <h5 class="filter-title">
                        <i class="fas fa-filter me-2"></i>
                        Lọc sản phẩm
                    </h5>
                    
                    <form method="get" id="filterForm">
                        <input type="hidden" name="view" value="<?= $view ?>">
                        <input type="hidden" name="sort" value="<?= $sort ?>">

                        <!-- Search -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-search me-2"></i>Tìm kiếm
                            </label>
                            <input type="text" class="form-control" name="search" 
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Nhập tên sản phẩm...">
                        </div>

                        <!-- Categories -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-tags me-2"></i>Danh mục
                            </label>
                            <select class="form-select" name="category">
                                <option value="">Tất cả danh mục</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"
                                        <?= $category_id == $cat['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price range -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-dollar-sign me-2"></i>Khoảng giá
                            </label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control price-input" name="min_price" 
                                        placeholder="Từ (VD: 100.000)" value="<?= $min_price ? number_format($min_price, 0, ',', '.') : '' ?>"
                                        data-min="<?= floor($price_range['min_price']) ?>" 
                                        data-max="<?= ceil($price_range['max_price']) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control price-input" name="max_price" 
                                        placeholder="Đến (VD: 500.000)" value="<?= $max_price ? number_format($max_price, 0, ',', '.') : '' ?>"
                                        data-min="<?= floor($price_range['min_price']) ?>" 
                                        data-max="<?= ceil($price_range['max_price']) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Suppliers -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-truck me-2"></i>Nhà cung cấp
                            </label>
                            <select class="form-select" name="supplier">
                                <option value="">Tất cả nhà cung cấp</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['supplier_id'] ?>"
                                        <?= $supplier_id == $sup['supplier_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sup['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn filter-btn w-100">
                            <i class="fas fa-filter me-2"></i>Áp dụng lọc
                        </button>
                    </form>
                </div>
            </div>

            <!-- Product list -->
            <div class="col-lg-9 products-main">
                <!-- Toolbar -->
                <div class="toolbar d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted fw-medium">
                            <i class="fas fa-info-circle me-2"></i>
                            Hiển thị <strong><?= count($products) ?></strong> / <strong><?= $total_products ?></strong> sản phẩm
                        </span>
                    </div>
                    <div class="d-flex gap-3 align-items-center">
                        <!-- Sort -->
                        <select class="form-select sort-select" style="width: auto;" 
                                onchange="updateQueryParam('sort', this.value)">
                            <option value="new" <?= $sort == 'new' ? 'selected' : '' ?>>
                                <i class="fas fa-clock"></i> Mới nhất
                            </option>
                            <option value="price_asc" <?= $sort == 'price_asc' ? 'selected' : '' ?>>
                                <i class="fas fa-sort-amount-up"></i> Giá tăng dần
                            </option>
                            <option value="price_desc" <?= $sort == 'price_desc' ? 'selected' : '' ?>>
                                <i class="fas fa-sort-amount-down"></i> Giá giảm dần
                            </option>
                            <option value="bestseller" <?= $sort == 'bestseller' ? 'selected' : '' ?>>
                                <i class="fas fa-fire"></i> Bán chạy nhất
                            </option>
                        </select>

                        <!-- View mode -->
                        <div class="btn-group">
                            <button type="button" class="btn view-toggle-btn <?= $view == 'grid' ? 'active' : '' ?>"
                                    onclick="updateQueryParam('view', 'grid')">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button type="button" class="btn view-toggle-btn <?= $view == 'list' ? 'active' : '' ?>"
                                    onclick="updateQueryParam('view', 'list')">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading animation -->
                <div class="loading" id="loading">
                    <div class="loading-spinner"></div>
                    <p class="text-muted">Đang tải sản phẩm...</p>
                </div>

                <!-- Products content -->
                <div id="productsContent">
                    <?php if (empty($products)): ?>
                        <div class="no-products alert alert-info">
                            <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                            <h5>Không tìm thấy sản phẩm</h5>
                            <p class="mb-0">Không có sản phẩm nào phù hợp với điều kiện lọc của bạn.</p>
                        </div>
                    <?php else: ?>
                        <?php if ($view == 'grid'): ?>
                            <!-- Grid view -->
                            <div class="row g-4">
                                <?php foreach ($products as $index => $product): ?>
                                    <div class="col-md-4" style="animation-delay: <?= $index * 0.1 ?>s">
                                        <div class="card product-card h-100">
                                            <?php if ($product['product_image']): ?>
                                                   <img src="<?= $product['product_image'] ?>" 
                                                       class="card-img-top product-image" 
                                                       alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                   <img src="assets/images/no-image.jpg" 
                                                       class="card-img-top product-image" 
                                                       alt="No image" loading="lazy" decoding="async">
                                            <?php endif; ?>
                                            <div class="card-body product-body">
                                                <p class="product-category mb-2">
                                                    <?= htmlspecialchars($product['category_name']) ?>
                                                </p>
                                                <h5 class="card-title product-title">
                                                    <a href="product.php?id=<?= $product['product_id'] ?>" 
                                                       class="text-decoration-none">
                                                        <?= htmlspecialchars($product['name']) ?>
                                                    </a>
                                                </h5>
                                                <p class="card-text product-price mb-3">
                                                    <?= format_currency($product['price']) ?>
                                                </p>
                                                <?php if (isset($_SESSION['user_id']) && !$isAdmin): ?>
                                                    <button type="button" class="btn add-to-cart-btn w-100" 
                                                            data-product-id="<?= $product['product_id'] ?>"
                                                            onclick="addToCartFromProducts.call(this, <?= $product['product_id'] ?>)">
                                                        <i class="fas fa-shopping-cart me-2"></i>
                                                        <span class="btn-text">Thêm vào giỏ</span>
                                                        <span class="loading d-none">
                                                            <i class="fas fa-spinner fa-spin"></i>
                                                        </span>
                                                    </button>
                                                <?php elseif (!isset($_SESSION['user_id'])): ?>
                                                    <a href="login.php" 
                                                       class="btn add-to-cart-btn w-100">
                                                        <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập để mua hàng
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($product['order_count'] > 0): ?>
                                                <div class="card-footer bg-transparent border-0 pt-0">
                                                    <div class="bestseller-badge">
                                                        <i class="fas fa-fire"></i>
                                                        Đã bán: <?= $product['order_count'] ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- List view -->
                            <div class="list-group">
                                <?php foreach ($products as $index => $product): ?>
                                    <div class="list-group-item list-item" style="animation-delay: <?= $index * 0.1 ?>s">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <?php if ($product['product_image']): ?>
                                                      <img src="<?= $product['product_image'] ?>" 
                                                          class="img-fluid rounded" 
                                                          alt="<?= htmlspecialchars($product['name']) ?>"
                                                          style="height: 120px; object-fit: cover;" loading="lazy" decoding="async">
                                                <?php else: ?>
                                                      <img src="assets/images/no-image.jpg" 
                                                          class="img-fluid rounded" 
                                                          alt="No image"
                                                          style="height: 120px; object-fit: cover;" loading="lazy" decoding="async">
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-7">
                                                <p class="product-category mb-2">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <?= htmlspecialchars($product['category_name']) ?> |
                                                    <i class="fas fa-truck ms-2 me-1"></i>
                                                    <?= htmlspecialchars($product['supplier_name']) ?>
                                                </p>
                                                <h5 class="product-title mb-2">
                                                    <a href="product.php?id=<?= $product['product_id'] ?>" 
                                                       class="text-decoration-none">
                                                        <?= htmlspecialchars($product['name']) ?>
                                                    </a>
                                                </h5>
                                                <p class="mb-2 text-muted">
                                                    <?= nl2br(htmlspecialchars(substr($product['description'], 0, 200))) ?>...
                                                </p>
                                                <?php if ($product['order_count'] > 0): ?>
                                                    <div class="bestseller-badge">
                                                        <i class="fas fa-fire"></i>
                                                        Đã bán: <?= $product['order_count'] ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <p class="product-price h4 mb-3">
                                                    <?= format_currency($product['price']) ?>
                                                </p>
                                                <?php if (isset($_SESSION['user_id']) && !$isAdmin): ?>
                                                    <button type="button" class="btn add-to-cart-btn" 
                                                            data-product-id="<?= $product['product_id'] ?>"
                                                            onclick="addToCartFromProducts.call(this, <?= $product['product_id'] ?>)">
                                                        <i class="fas fa-shopping-cart me-2"></i>
                                                        <span class="btn-text">Thêm vào giỏ</span>
                                                        <span class="loading d-none">
                                                            <i class="fas fa-spinner fa-spin"></i>
                                                        </span>
                                                    </button>
                                                <?php elseif (!isset($_SESSION['user_id'])): ?>
                                                    <a href="login.php" class="btn add-to-cart-btn">
                                                        <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center mt-5">
                                <nav aria-label="Page navigation">
                                    <?= generate_pagination($page, $total_pages) ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

// Debug function to check if script is loading
console.log('Products.php script loaded');
window.testCartFunction = function() {
    if (typeof addToCartFromProducts === 'function') {
        console.log('addToCartFromProducts function is available');
        return true;
    } else {
        console.log('addToCartFromProducts function is NOT available');
        return false;
    }
};

// Add to cart function for products page - GLOBAL SCOPE
function addToCartFromProducts(productId) {
    // Check if user is admin
    if (isAdmin) {
        showToast('Admin không thể thêm sản phẩm vào giỏ hàng!', 'warning');
        return;
    }
    
    // Get the button that was clicked (this is passed via .call(this, ...))
    const button = this;
    if (!button) {
        console.error('Button not found');
        return;
    }
    
    const btnText = button.querySelector('.btn-text');
    const loading = button.querySelector('.loading');
    
    if (!btnText || !loading) {
        console.error('Button elements not found:', { btnText, loading });
        return;
    }
    
    // Show loading state
    btnText.style.display = 'none';
    loading.classList.remove('d-none');
    button.disabled = true;
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('id', productId);
    formData.append('quantity', 1);
    
    // Send AJAX request
    fetch('cart.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Show success notification using the new toast function
            showBootstrapToast(data.message || 'Đã thêm sản phẩm vào giỏ hàng thành công!', 'success');
            
            // Update cart count if available
            const cartCount = document.querySelector('.cart-count');
            if (cartCount && data.cart_count) {
                cartCount.textContent = data.cart_count;
                cartCount.style.display = data.cart_count > 0 ? 'inline' : 'none';
            }
        } else {
            showBootstrapToast(data.message || 'Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng!', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Fallback to direct link if AJAX fails
        const productId = button.dataset.productId;
        if (productId) {
            console.log('Falling back to direct link for product:', productId);
            window.location.href = `cart.php?action=add&id=${productId}`;
            return; // Don't reset button state since we're redirecting
        }
        
        showBootstrapToast('Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng!', 'error');
    })
    .finally(() => {
        // Reset button state
        if (btnText && loading && button) {
            btnText.style.display = 'inline';
            loading.classList.add('d-none');
            button.disabled = false;
        }
    });
}

// Bootstrap Toast notification function - GLOBAL SCOPE
function showBootstrapToast(message, type = 'success') {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 1055;';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Initialize and show toast
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        bsToast.show();
        
        // Remove from DOM after hiding
        toast.addEventListener('hidden.bs.toast', function() {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        });
    } else {
        // Fallback if Bootstrap is not available
        console.warn('Bootstrap not found, using simple notification');
        toast.style.display = 'block';
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 3000);
    }
}

// Smooth transitions and animations
document.addEventListener('DOMContentLoaded', function() {
    // Add stagger animation to products
    const products = document.querySelectorAll('.product-card, .list-item');
    products.forEach((product, index) => {
        product.style.animationDelay = `${index * 0.1}s`;
    });

    // Enhanced form interactions
    const formInputs = document.querySelectorAll('.form-control, .form-select');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });

    // Smooth scrolling for pagination
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.href.includes('#')) {
                showLoading();
            }
        });
    });

    // Search input enhancements
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            this.style.borderColor = '#fbbf24';
            
            searchTimeout = setTimeout(() => {
                this.style.borderColor = '';
            }, 1000);
        });
    }

    // Price range validation
    const minPriceInput = document.querySelector('input[name="min_price"]');
    const maxPriceInput = document.querySelector('input[name="max_price"]');
    
    // Price formatting function
    function formatPrice(value) {
        // Remove all non-digits
        const digits = value.replace(/\D/g, '');
        // Add dots every 3 digits from right
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    
    // Parse price function (remove dots)
    function parsePrice(value) {
        return parseInt(value.replace(/\./g, '')) || 0;
    }
    
    if (minPriceInput && maxPriceInput) {
        // Add price formatting on input
        [minPriceInput, maxPriceInput].forEach(input => {
            input.addEventListener('input', function(e) {
                const cursorPosition = e.target.selectionStart;
                const formatted = formatPrice(e.target.value);
                e.target.value = formatted;
                
                // Restore cursor position (roughly)
                const newCursorPos = Math.min(cursorPosition, formatted.length);
                e.target.setSelectionRange(newCursorPos, newCursorPos);
            });
        });
        
        function validatePriceRange() {
            const minPrice = parsePrice(minPriceInput.value);
            const maxPrice = parsePrice(maxPriceInput.value);
            
            if (minPrice > 0 && maxPrice > 0 && minPrice > maxPrice) {
                maxPriceInput.setCustomValidity('Giá tối đa phải lớn hơn giá tối thiểu');
                maxPriceInput.style.borderColor = '#ef4444';
            } else {
                maxPriceInput.setCustomValidity('');
                maxPriceInput.style.borderColor = '';
            }
        }
        
        minPriceInput.addEventListener('input', validatePriceRange);
        maxPriceInput.addEventListener('input', validatePriceRange);
        
        // Convert to raw number before form submit
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                // Create hidden inputs with raw values
                const minPriceRaw = parsePrice(minPriceInput.value);
                const maxPriceRaw = parsePrice(maxPriceInput.value);
                
                if (minPriceRaw > 0) {
                    minPriceInput.value = minPriceRaw;
                }
                if (maxPriceRaw > 0) {
                    maxPriceInput.value = maxPriceRaw;
                }
            });
        }
    }

    // Product image lazy loading with placeholder
    const productImages = document.querySelectorAll('.product-image');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                // Add placeholder background while loading
                img.style.backgroundColor = '#f3f4f6';
                
                // Store original src in data attribute
                const originalSrc = img.src;
                img.style.opacity = '0';
                
                // Create new image object to preload
                const tempImage = new Image();
                tempImage.src = originalSrc;
                
                tempImage.onload = function() {
                    img.style.transition = 'opacity 0.3s ease';
                    img.style.opacity = '1';
                    img.style.backgroundColor = 'transparent';
                };
                
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: '50px 0px', // Start loading images before they enter viewport
        threshold: 0.1
    });
    
    productImages.forEach(img => imageObserver.observe(img));

    // Parallax effect for background
    window.addEventListener('scroll', function() {
        const scrolled = window.pageYOffset;
        const parallax = document.querySelector('.products-container');
        const speed = scrolled * 0.5;
        parallax.style.backgroundPosition = `center ${speed}px`;
    });

    // Filter form auto-submit with debounce
    const filterForm = document.getElementById('filterForm');
    const filterInputs = filterForm.querySelectorAll('input, select');
    let filterTimeout;
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            clearTimeout(filterTimeout);
            showLoading();
            
            filterTimeout = setTimeout(() => {
                filterForm.submit();
            }, 500);
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + F to focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Arrow keys for pagination
        if (e.key === 'ArrowLeft') {
            const prevLink = document.querySelector('.pagination .page-item:not(.disabled) .page-link[aria-label="Previous"]');
            if (prevLink) prevLink.click();
        }
        
        if (e.key === 'ArrowRight') {
            const nextLink = document.querySelector('.pagination .page-item:not(.disabled) .page-link[aria-label="Next"]');
            if (nextLink) nextLink.click();
        }
    });

    // Product hover effects
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '';
        });
    });
});

// Enhanced query parameter update with smooth transition
function updateQueryParam(param, value) {
    showLoading();
    
    const url = new URL(window.location.href);
    url.searchParams.set(param, value);
    url.searchParams.set('page', '1'); // Reset to first page
    
    // Add transition effect
    const productsContent = document.getElementById('productsContent');
    productsContent.style.opacity = '0.5';
    productsContent.style.transform = 'scale(0.98)';
    
    setTimeout(() => {
        window.location.href = url.toString();
    }, 300);
}

// Show loading animation
function showLoading() {
    const loading = document.getElementById('loading');
    const productsContent = document.getElementById('productsContent');
    
    if (loading && productsContent) {
        loading.style.display = 'block';
        productsContent.style.opacity = '0.5';
        productsContent.style.pointerEvents = 'none';
    }
}

// Hide loading animation
function hideLoading() {
    const loading = document.getElementById('loading');
    const productsContent = document.getElementById('productsContent');
    
    if (loading && productsContent) {
        loading.style.display = 'none';
        productsContent.style.opacity = '1';
        productsContent.style.pointerEvents = 'auto';
    }
}

// Product quick view functionality
function quickView(productId) {
    // Create modal backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        z-index: 1050;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    `;
    
    // Create modal content
    const modal = document.createElement('div');
    modal.className = 'quick-view-modal';
    modal.style.cssText = `
        background: white;
        border-radius: 20px;
        max-width: 800px;
        width: 90%;
        max-height: 90%;
        overflow-y: auto;
        padding: 2rem;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        animation: slideInUp 0.3s ease;
        position: relative;
    `;
    
    modal.innerHTML = `
        <button onclick="closeQuickView()" style="
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            transition: color 0.3s ease;
        " onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">
            <i class="fas fa-times"></i>
        </button>
        <div class="text-center">
            <div class="loading-spinner mx-auto mb-3"></div>
            <p>Đang tải thông tin sản phẩm...</p>
        </div>
    `;
    
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);
    document.body.style.overflow = 'hidden';
    
    // Close on backdrop click
    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) {
            closeQuickView();
        }
    });
    
    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeQuickView();
        }
    }, { once: true });
}

function closeQuickView() {
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
        backdrop.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            backdrop.remove();
            document.body.style.overflow = '';
        }, 300);
    }
}

// Advanced filtering with AJAX (if needed for future enhancement)
function filterProducts(formData) {
    showLoading();
    
    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newContent = doc.getElementById('productsContent');
        
        if (newContent) {
            document.getElementById('productsContent').innerHTML = newContent.innerHTML;
            
            // Re-initialize animations
            const products = document.querySelectorAll('.product-card, .list-item');
            products.forEach((product, index) => {
                product.style.animationDelay = `${index * 0.1}s`;
                product.style.animation = 'none';
                product.offsetHeight; // Trigger reflow
                product.style.animation = 'fadeInUp 0.6s ease-out';
            });
        }
        
        hideLoading();
    })
    .catch(error => {
        console.error('Error:', error);
        hideLoading();
    });
}

// Initialize tooltips (if Bootstrap tooltips are available)
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});

// Smooth page transitions
window.addEventListener('beforeunload', function() {
    document.body.style.opacity = '0.8';
    document.body.style.transform = 'scale(0.98)';
});


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

</script>

<?php include 'includes/footer.php'; ?>