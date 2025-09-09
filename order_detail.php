<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    flash('error', 'Vui lòng đăng nhập để xem đơn hàng');
    redirect('login.php');
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*, a.*, u.full_name, u.email, u.phone
    FROM orders o
    JOIN addresses a ON o.address_id = a.address_id
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Đơn hàng không tồn tại hoặc không thuộc về bạn');
    redirect('orders.php');
}

// Lấy chi tiết đơn hàng
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.product_id,
           (SELECT image_url FROM product_images pi 
            WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image,
           (SELECT review_id FROM reviews r 
            WHERE r.product_id = p.product_id AND r.user_id = ?) as review_id
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt->execute([$user_id, $order_id]);
$items = $stmt->fetchAll();

// Lấy lịch sử trạng thái
$stmt = $pdo->prepare("
    SELECT h.*, u.full_name as changed_by_name
    FROM order_status_history h
    JOIN users u ON h.changed_by = u.user_id
    WHERE h.order_id = ?
    ORDER BY h.changed_at DESC
");
$stmt->execute([$order_id]);
$status_history = $stmt->fetchAll();

// Xử lý hủy đơn hàng
if (isset($_POST['cancel_order']) && $order['status'] == 'pending') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash('error', 'Invalid CSRF token');
    } else {
        try {
            $pdo->beginTransaction();

            // Cập nhật trạng thái đơn hàng
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'cancelled', updated_at = NOW()
                WHERE order_id = ? AND user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$order_id, $user_id]);

            // Ghi log trạng thái
                    $stmt = $pdo->prepare("
                        INSERT INTO order_status_history (
                            order_id, status, changed_by, changed_at, note
                        ) VALUES (?, 'cancelled', ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $order_id,
                        $user_id,
                        'Đơn hàng bị hủy bởi khách hàng'
                    ]);

            // Hoàn lại số lượng vào kho
            $stmt = $pdo->prepare("
                UPDATE products p
                JOIN order_items oi ON p.product_id = oi.product_id
                SET p.stock = p.stock + oi.quantity
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);

            $pdo->commit();
            flash('success', 'Đã hủy đơn hàng thành công');
            
            // Update the order data in memory to reflect the cancelled status
            $order['status'] = 'cancelled';
            
            // Get the updated status history
            $stmt = $pdo->prepare("
                SELECT h.*, u.full_name as changed_by_name
                FROM order_status_history h
                JOIN users u ON h.changed_by = u.user_id
                WHERE h.order_id = ?
                ORDER BY h.changed_at DESC
            ");
            $stmt->execute([$order_id]);
            $status_history = $stmt->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}

include 'includes/navbar.php';
?>

<!-- Enhanced CSS Styles -->
    <link rel="stylesheet" href="css/orders_detail.css">


<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-spinner"></div>
</div>

<!-- Enhanced Page Header -->
<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="page-title">Chi tiết đơn hàng #<?= $order_id ?></h1>
            <a href="orders.php" class="btn btn-light btn-enhanced">
                <i class="fas fa-arrow-left me-2"></i> Quay lại
            </a>
        </div>
    </div>
</div>

<div class="container py-5">
    <?php if (isset($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $type => $message): ?>
            <div class="alert alert-<?= $type ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    <div class="row">
        <div class="col-lg-8">
            <?php if ($order['status'] == 'cancelled'): ?>
                <div class="alert alert-danger mt-4 mb-4">
                    Đơn hàng này đã bị hủy và không còn hiệu lực.
                </div>
            <?php endif; ?>
            <!-- Thông tin đơn hàng -->
            <div class="enhanced-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-info-circle me-2"></i>Thông tin đơn hàng
                            </h5>
                            <div class="info-item mb-2">
                                <strong>Mã đơn hàng:</strong> 
                                <span class="text-primary">#<?= $order_id ?></span>
                            </div>
                            <div class="info-item mb-2">
                                <strong>Ngày đặt:</strong> 
                                <span class="order-date"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div class="info-item mb-2">
                                <strong>PTTT:</strong>
                                <span class="payment-method"><?= PAYMENT_METHODS[$order['payment_method']] ?></span>
                            </div>
                            <div class="info-item">
                                <strong>PTVC:</strong>
                                <span class="shipping-method">
                                    <?= $order['shipping_method'] == 'express' ? 'Giao hàng nhanh' : 'Giao hàng tiêu chuẩn' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-truck me-2"></i>Trạng thái đơn hàng
                            </h5>
                            <div class="d-flex align-items-center mb-3">
                                <span class="status-badge <?= 
                                    $order['status'] == 'pending' ? 'bg-warning' : 
                                    ($order['status'] == 'processing' ? 'bg-info' : 
                                    ($order['status'] == 'shipped' ? 'bg-primary' : 
                                    ($order['status'] == 'delivered' ? 'bg-success' : 
                                    ($order['status'] == 'paid' ? 'bg-gradient-paid' : 
                                    ($order['status'] == 'returned' ? 'bg-secondary' : 'bg-danger')))))
                                ?>">
                                    <i class="fas fa-<?= 
                                        $order['status'] == 'pending' ? 'clock' : 
                                        ($order['status'] == 'processing' ? 'cog fa-spin' : 
                                        ($order['status'] == 'shipped' ? 'shipping-fast' : 
                                        ($order['status'] == 'delivered' ? 'check-circle' : 
                                        ($order['status'] == 'paid' ? 'money-check-alt' : 
                                        ($order['status'] == 'returned' ? 'undo' : 'times-circle')))))
                                    ?> me-2"></i>
                                    <?= ORDER_STATUS[$order['status']] ?>
                                </span>
                            </div>

                            <?php if ($order['status'] == 'pending'): ?>
                                <form method="post" id="cancelForm">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="button" name="cancel_order" class="btn btn-enhanced btn-cancel" id="cancelBtn">
                                        <i class="fas fa-times me-2"></i> Hủy đơn hàng
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-user me-2"></i>Thông tin người đặt
                            </h5>
                            <div class="info-item mb-2">
                                <strong>Họ tên:</strong> 
                                <span class="customer-name"><?= htmlspecialchars($order['full_name']) ?></span>
                            </div>
                            <div class="info-item mb-2">
                                <strong>Email:</strong> 
                                <span class="customer-email"><?= htmlspecialchars($order['email']) ?></span>
                            </div>
                            <div class="info-item">
                                <strong>SĐT:</strong> 
                                <span class="customer-phone"><?= $order['phone'] ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-map-marker-alt me-2"></i>Địa chỉ giao hàng
                            </h5>
                            <div class="info-item mb-2">
                                <strong>Người nhận:</strong> 
                                <span class="recipient-name"><?= htmlspecialchars($order['recipient_name']) ?></span>
                            </div>
                            <div class="info-item mb-2">
                                <strong>SĐT:</strong> 
                                <span class="recipient-phone"><?= $order['phone'] ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Địa chỉ:</strong><br>
                                <div class="address-details">
                                    <?= htmlspecialchars($order['address_line']) ?>,<br>
                                    <?= htmlspecialchars($order['ward']) ?>,<br>
                                    <?= htmlspecialchars($order['district']) ?>,<br>
                                    <?= htmlspecialchars($order['city']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chi tiết sản phẩm -->
            <div class="enhanced-card mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">
                        <i class="fas fa-box me-2"></i>Chi tiết sản phẩm
                    </h5>
                    
                    <div class="table-responsive">
                        <table class="table enhanced-table">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th class="text-center">Số lượng</th>
                                    <th class="text-end">Giá</th>
                                    <th class="text-end">Tổng</th>
                                    <?php if ($order['status'] == 'delivered'): ?>
                                        <th></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $index => $item): ?>
                                    <tr class="product-item" data-aos="fade-right" data-aos-delay="<?= ($index + 1) * 100 ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="product-image me-3" style="width: 60px; height: 60px;">
                                                    <?php if ($item['product_image']): ?>
                                                        <img src="<?= $item['product_image'] ?>" 
                                                             class="rounded w-100 h-100 object-cover"
                                                             alt="<?= htmlspecialchars($item['name']) ?>">
                                                    <?php else: ?>
                                                        <img src="assets/images/no-image.jpg" 
                                                             class="rounded w-100 h-100 object-cover"
                                                             alt="No image">
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <a href="product.php?id=<?= $item['product_id'] ?>"
                                                       class="text-decoration-none product-link">
                                                        <?= htmlspecialchars($item['name']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark quantity-badge">
                                                <?= $item['quantity'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end price"><?= format_currency($item['price']) ?></td>
                                        <td class="text-end total-price">
                                            <?= format_currency($item['price'] * $item['quantity']) ?>
                                        </td>
                                        <?php if ($order['status'] == 'delivered'): ?>
                                            <td class="text-end">
                                                <?php if ($item['review_id']): ?>
                                                    <span class="text-success review-status">
                                                        <i class="fas fa-check-circle me-1"></i> Đã đánh giá
                                                    </span>
                                                <?php else: ?>
                                                    <a href="product.php?id=<?= $item['product_id'] ?>#review"
                                                       class="btn btn-outline-primary btn-sm btn-enhanced review-btn">
                                                        <i class="fas fa-star me-1"></i> Đánh giá
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-summary" data-aos="fade-up">
                                    <td colspan="3" class="text-end"><strong>Tạm tính:</strong></td>
                                    <td class="text-end subtotal"><?= format_currency($order['total_amount']) ?></td>
                                    <?php if ($order['status'] == 'delivered'): ?>
                                        <td></td>
                                    <?php endif; ?>
                                </tr>
                                <?php if (($order['discount_amount'] ?? 0) > 0): ?>
                                    <tr class="table-discount" data-aos="fade-up" data-aos-delay="100">
                                        <td colspan="3" class="text-end"><strong>Giảm giá:</strong></td>
                                        <td class="text-end text-danger discount">
                                            -<?= format_currency($order['discount_amount'] ?? 0) ?>
                                        </td>
                                        <?php if ($order['status'] == 'delivered'): ?>
                                            <td></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endif; ?>
                                <tr class="table-total" data-aos="fade-up" data-aos-delay="200">
                                    <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                                    <td class="text-end">
                                        <strong class="text-danger final-total">
                                            <?= format_currency($order['total_amount'] - ($order['discount_amount'] ?? 0)) ?>
                                        </strong>
                                    </td>
                                    <?php if ($order['status'] == 'delivered'): ?>
                                        <td></td>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Lịch sử trạng thái -->
            <div class="enhanced-card" data-aos="fade-left">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">
                        <i class="fas fa-history me-2"></i>Lịch sử trạng thái
                    </h5>
                    
                    <div class="timeline">
                        <?php foreach ($status_history as $index => $history): ?>
                            <div class="timeline-item" data-aos="fade-left" data-aos-delay="<?= $index * 150 ?>">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="status-badge <?= 
                                            $history['status'] == 'pending' ? 'bg-warning' : 
                                            ($history['status'] == 'processing' ? 'bg-info' : 
                                            ($history['status'] == 'shipped' ? 'bg-primary' : 
                                            ($history['status'] == 'delivered' ? 'bg-success' : 'bg-danger')))
                                        ?>">
                                            <?= ORDER_STATUS[$history['status']] ?>
                                        </span>
                                        <small class="text-muted status-time">
                                            <?= date('d/m/Y H:i', strtotime($history['changed_at'])) ?>
                                        </small>
                                    </div>
                                    <?php if ($history['note']): ?>
                                        <p class="mb-0 status-note">
                                            <i class="fas fa-comment-dots me-2"></i>
                                            <?= htmlspecialchars($history['note']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<button class="fab" id="scrollToTop" title="Scroll to top">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Enhanced JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize AOS (Animate On Scroll)
    AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: true,
        offset: 100
    });

    // Page Loader
    const pageLoader = document.getElementById('pageLoader');
    setTimeout(() => {
        pageLoader.classList.add('fade-out');
        setTimeout(() => {
            pageLoader.style.display = 'none';
        }, 500);
    }, 1000);

    // Animate numbers (prices)
    function animateNumbers() {
        const numbers = document.querySelectorAll('.price, .total-price, .subtotal, .discount, .final-total');
        
        numbers.forEach(element => {
            const text = element.textContent;
            const match = text.match(/[\d,]+/);
            if (match) {
                const number = parseInt(match[0].replace(/,/g, ''));
                const duration = 1000;
                const steps = 30;
                const stepValue = number / steps;
                let current = 0;
                let step = 0;

                const timer = setInterval(() => {
                    current += stepValue;
                    step++;
                    
                    const formattedNumber = Math.floor(current).toLocaleString('vi-VN');
                    element.textContent = text.replace(/[\d,]+/, formattedNumber);
                    
                    if (step >= steps) {
                        clearInterval(timer);
                        element.textContent = text; // Ensure final accuracy
                    }
                }, duration / steps);
            }
        });
    }

    // Trigger number animation when elements come into view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateNumbers();
                observer.unobserve(entry.target);
            }
        });
    });

    const tableElement = document.querySelector('.enhanced-table');
    if (tableElement) {
        observer.observe(tableElement);
    }

    // Enhanced Cancel Order functionality
    const cancelBtn = document.getElementById('cancelBtn');
    const cancelForm = document.getElementById('cancelForm');
    
    if (cancelBtn && cancelForm) {
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Xác nhận hủy đơn hàng',
                text: 'Bạn có chắc chắn muốn hủy đơn hàng này? Hành động này không thể hoàn tác!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Có, hủy đơn hàng!',
                cancelButtonText: 'Không, giữ lại',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: 'rgba(0, 0, 0, 0.7)',
                customClass: {
                    popup: 'animated bounceIn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Đang xử lý...',
                        text: 'Vui lòng chờ trong giây lát',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        background: 'rgba(255, 255, 255, 0.95)',
                        customClass: {
                            popup: 'animated pulse infinite'
                        },
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Add cancel_order input and submit
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'cancel_order';
                    hiddenInput.value = '1';
                    cancelForm.appendChild(hiddenInput);
                    
                    // Add CSRF token if it doesn't exist
                    if (!cancelForm.querySelector('[name="csrf_token"]')) {
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = '<?= $_SESSION['csrf_token'] ?>';
                        cancelForm.appendChild(csrfInput);
                    }
                    
                    cancelForm.submit();
                }
            });
        });
    }

    // Floating Action Button functionality
    const fab = document.getElementById('scrollToTop');
    
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            fab.style.opacity = '1';
            fab.style.transform = 'scale(1)';
        } else {
            fab.style.opacity = '0';
            fab.style.transform = 'scale(0.8)';
        }
    });

    fab.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // Product item hover effects
    const productItems = document.querySelectorAll('.product-item');
    productItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.background = 'linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08))';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.background = '';
        });
    });

    // Timeline animation enhancement
    const timelineItems = document.querySelectorAll('.timeline-item');
    const timelineObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateX(0)';
                }, index * 200);
            }
        });
    }, {
        threshold: 0.1
    });

    timelineItems.forEach(item => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-30px)';
        item.style.transition = 'all 0.6s ease-out';
        timelineObserver.observe(item);
    });

    // Status badge pulse effect
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.animation = 'pulse 0.6s ease-in-out';
        });
        
        badge.addEventListener('animationend', function() {
            this.style.animation = 'pulse 2s infinite';
        });
    });

    // Card hover parallax effect - REMOVED
    // const cards = document.querySelectorAll('.enhanced-card');
    // cards.forEach(card => {
    //     card.addEventListener('mousemove', function(e) {
    //         const rect = this.getBoundingClientRect();
    //         const x = e.clientX - rect.left;
    //         const y = e.clientY - rect.top;
    //         
    //         const centerX = rect.width / 2;
    //         const centerY = rect.height / 2;
    //         
    //         const rotateX = (y - centerY) / 10;
    //         const rotateY = (centerX - x) / 10;
    //         
    //         this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(5px)`;
    //     });
    //     
    //     card.addEventListener('mouseleave', function() {
    //         this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
    //     });
    // });

    // Add sparkle effect to important elements
    function createSparkle(element) {
        const sparkle = document.createElement('div');
        sparkle.innerHTML = '✨';
        sparkle.style.position = 'absolute';
        sparkle.style.pointerEvents = 'none';
        sparkle.style.fontSize = '12px';
        sparkle.style.zIndex = '1000';
        sparkle.style.animation = 'sparkleFloat 2s ease-out forwards';
        
        const rect = element.getBoundingClientRect();
        sparkle.style.left = (rect.left + Math.random() * rect.width) + 'px';
        sparkle.style.top = (rect.top + Math.random() * rect.height) + 'px';
        
        document.body.appendChild(sparkle);
        
        setTimeout(() => {
            sparkle.remove();
        }, 2000);
    }

    // Add sparkle animation CSS
    const sparkleCSS = `
        @keyframes sparkleFloat {
            0% {
                opacity: 1;
                transform: translateY(0) scale(0);
            }
            50% {
                opacity: 1;
                transform: translateY(-20px) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-40px) scale(0);
            }
        }
    `;
    
    const style = document.createElement('style');
    style.textContent = sparkleCSS;
    document.head.appendChild(style);

    // Add sparkles to final total when it comes into view
    const finalTotal = document.querySelector('.final-total');
    if (finalTotal) {
        const sparkleObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    for (let i = 0; i < 5; i++) {
                        setTimeout(() => {
                            createSparkle(entry.target);
                        }, i * 300);
                    }
                    sparkleObserver.unobserve(entry.target);
                }
            });
        });
        sparkleObserver.observe(finalTotal);
    }

    // Smooth reveal animation for info items
    const infoItems = document.querySelectorAll('.info-item');
    infoItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        item.style.transition = 'all 0.5s ease-out';
        
        setTimeout(() => {
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Add loading skeleton effect for images
    const images = document.querySelectorAll('.product-image img');
    images.forEach(img => {
        if (!img.complete) {
            img.style.opacity = '0';
            img.parentElement.classList.add('loading-shimmer');
            
            img.addEventListener('load', function() {
                this.style.opacity = '1';
                this.style.transition = 'opacity 0.5s ease-in';
                this.parentElement.classList.remove('loading-shimmer');
            });
        }
    });

    // Add success animation for delivered orders
    if (document.querySelector('.status-badge.bg-success')) {
        const successElements = document.querySelectorAll('.bg-success, .text-success');
        successElements.forEach(element => {
            element.classList.add('success-animation');
        });
    }

    // Add typing effect to order title
    const pageTitle = document.querySelector('.page-title');
    if (pageTitle) {
        const originalText = pageTitle.textContent;
        pageTitle.textContent = '';
        
        let i = 0;
        const typeTimer = setInterval(() => {
            pageTitle.textContent += originalText.charAt(i);
            i++;
            if (i > originalText.length - 1) {
                clearInterval(typeTimer);
            }
        }, 50);
    }

    // Add ripple effect to buttons
    function addRippleEffect(button) {
        button.addEventListener('click', function(e) {
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
    }

    // Add ripple CSS
    const rippleCSS = `
        .btn-enhanced {
            position: relative;
            overflow: hidden;
        }
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = rippleCSS;
    document.head.appendChild(rippleStyle);

    // Apply ripple effect to all enhanced buttons
    const enhancedButtons = document.querySelectorAll('.btn-enhanced');
    enhancedButtons.forEach(addRippleEffect);
    
    // Check if order has just been cancelled
    <?php if (isset($_SESSION['flash']) && $_SESSION['flash']['type'] === 'success' && strpos($_SESSION['flash']['message'], 'hủy đơn') !== false): ?>
    setTimeout(() => {
        // Show success animation
        Swal.fire({
            icon: 'success',
            title: 'Đã hủy đơn hàng',
            text: 'Đơn hàng #<?= $order_id ?> đã được hủy thành công',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            background: 'rgba(255, 255, 255, 0.95)',
            backdrop: 'rgba(0, 0, 0, 0.4)',
            customClass: {
                popup: 'animated zoomIn'
            }
        });
        
        // Update UI elements
        const statusBadges = document.querySelectorAll('.status-badge');
        statusBadges.forEach(badge => {
            badge.className = 'status-badge bg-danger';
            badge.textContent = '<?= ORDER_STATUS['cancelled'] ?>';
        });
        
        // Hide cancel button if it exists
        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) {
            cancelBtn.parentElement.style.display = 'none';
        }
    }, 500);
    <?php endif; ?>

    console.log('🎉 Enhanced Order Detail Page loaded successfully!');
});
</script>

<!-- AOS CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">

<?php include 'includes/footer.php'; ?>