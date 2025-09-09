
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

// Xử lý filter
$status = $_GET['status'] ?? '';
$search = sanitize($_GET['search'] ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = USER_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Xây dựng query với filter
$where_clauses = ['o.user_id = ?'];
$params = [$user_id];

if ($status) {
    $where_clauses[] = "o.status = ?";
    $params[] = $status;
}

if ($start_date) {
    $where_clauses[] = "DATE(o.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $where_clauses[] = "DATE(o.created_at) <= ?";
    $params[] = $end_date;
}

if ($search) {
    $where_clauses[] = "(o.order_id LIKE ? OR a.recipient_name LIKE ? OR a.phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Đếm tổng số đơn hàng
$count_sql = "
    SELECT COUNT(*) 
    FROM orders o
    JOIN addresses a ON o.address_id = a.address_id
    $where_sql
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Query chính
$sql = "
    SELECT o.*, a.recipient_name, a.phone,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count
    FROM orders o
    JOIN addresses a ON o.address_id = a.address_id
    $where_sql
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

include 'includes/navbar.php';

// Lấy thông báo flash
$success = flash('success');
$error = flash('error');
?>
<link rel="stylesheet" href="css/orders.css">


<div class="orders-container">
    <div class="container">
        <!-- Hiển thị thông báo -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 15px; border: none; margin-bottom: 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);">
                <i class="fas fa-check-circle me-2"></i>
                <strong><?= htmlspecialchars($success) ?></strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 15px; border: none; margin-bottom: 20px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?= htmlspecialchars($error) ?></strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <h1 class="page-title">
            <i class="fas fa-shopping-bag me-3"></i>
            Đơn hàng của tôi
        </h1>

        <!-- Form lọc -->
        <div class="card filter-card">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Lọc đơn hàng
                        </h5>
                    </div>
                </div>
                <form method="get" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-search me-1"></i>Tìm kiếm
                        </label>
                        <input type="text" class="form-control" name="search" 
                            placeholder="Mã ĐH, người nhận, SĐT..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-tasks me-1"></i>Trạng thái
                        </label>
                        <select class="form-select" name="status">
                            <option value="">Tất cả trạng thái</option>
                            <?php foreach (ORDER_STATUS as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-calendar-alt me-1"></i>Từ ngày
                        </label>
                        <input type="date" class="form-control" name="start_date" 
                            value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-calendar-alt me-1"></i>Đến ngày
                        </label>
                        <input type="date" class="form-control" name="end_date" 
                            value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">&nbsp;</label>
                        <button type="submit" class="btn filter-btn w-100 d-block">
                            <i class="fas fa-search me-2"></i>Lọc
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="no-orders">
                <div class="no-orders-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>Chưa có đơn hàng nào</h3>
                <p class="lead">Bạn chưa có đơn hàng nào phù hợp với điều kiện lọc.</p>
                <a href="products.php" class="shop-now-btn">
                    <i class="fas fa-shopping-bag me-2"></i>
                    Mua sắm ngay
                </a>
            </div>
        <?php else: ?>
            <!-- Danh sách đơn hàng -->
            <div class="card orders-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-2"></i>Mã ĐH</th>
                                    <th><i class="fas fa-user me-2"></i>Người nhận</th>
                                    <th class="text-center"><i class="fas fa-boxes me-2"></i>Số SP</th>
                                    <th class="text-end"><i class="fas fa-money-bill-wave me-2"></i>Tổng tiền</th>
                                    <th class="text-center"><i class="fas fa-info-circle me-2"></i>Trạng thái</th>
                                    <th class="text-center"><i class="fas fa-clock me-2"></i>Ngày đặt</th>
                                    <th class="text-center"><i class="fas fa-cogs me-2"></i>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $index => $order): ?>
                                    <tr style="animation-delay: <?= $index * 0.1 ?>s" class="order-row">
                                        <td data-label="Mã ĐH">
                                            <span class="order-id">#<?= $order['order_id'] ?></span>
                                        </td>
                                        <td data-label="Người nhận">
                                            <div class="recipient-info">
                                                <span class="recipient-name"><?= htmlspecialchars($order['recipient_name']) ?></span>
                                                <span class="recipient-phone">
                                                    <i class="fas fa-phone-alt me-1"></i><?= $order['phone'] ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-center" data-label="Số SP">
                                            <span class="item-count"><?= $order['item_count'] ?></span>
                                        </td>
                                        <td class="text-end" data-label="Tổng tiền">
                                            <?php if (($order['discount_amount'] ?? 0) > 0): ?>
                                                <span class="text-decoration-line-through text-muted small">
                                                    <?= format_currency($order['total_amount']) ?>
                                                </span><br>
                                                <span class="total-amount text-success fw-bold">
                                                    <?= format_currency($order['total_amount'] - $order['discount_amount']) ?>
                                                </span><br>
                                                <small class="text-success">
                                                    <i class="fas fa-tag me-1"></i>Tiết kiệm <?= format_currency($order['discount_amount']) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="total-amount">
                                                    <?= format_currency($order['total_amount']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" data-label="Trạng thái">
                                            <span class="badge status-badge bg-gradient-<?= 
                                                $order['status'] == 'pending' ? 'warning' : 
                                                ($order['status'] == 'processing' ? 'info' : 
                                                ($order['status'] == 'shipped' ? 'primary' : 
                                                ($order['status'] == 'delivered' ? 'success' : 
                                                ($order['status'] == 'paid' ? 'paid' : 
                                                ($order['status'] == 'returned' ? 'secondary' : 'danger')))))
                                            ?>">
                                                <i class="fas fa-<?= 
                                                    $order['status'] == 'pending' ? 'clock' : 
                                                    ($order['status'] == 'processing' ? 'cog fa-spin' : 
                                                    ($order['status'] == 'shipped' ? 'shipping-fast' : 
                                                    ($order['status'] == 'delivered' ? 'check-circle' : 
                                                    ($order['status'] == 'paid' ? 'money-check-alt' : 
                                                    ($order['status'] == 'returned' ? 'undo' : 'times-circle')))))
                                                ?> me-1"></i>
                                                <?= ORDER_STATUS[$order['status']] ?>
                                            </span>
                                        </td>
                                        <td class="text-center" data-label="Ngày đặt">
                                            <span class="order-date">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= date('d/m/Y', strtotime($order['created_at'])) ?><br>
                                                <small><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                            </span>
                                        </td>
                                        <td class="text-center" data-label="Thao tác">
                                            <div class="action-buttons">
                                                <a href="order_detail.php?id=<?= $order['order_id'] ?>" 
                                                   class="btn-detail">
                                                    <i class="fas fa-eye"></i>
                                                    <span class="d-none d-sm-inline">Chi tiết</span>
                                                </a>
                                                <?php if ($order['status'] == 'pending'): ?>
                                                    <button type="button" 
                                                            class="btn-cancel cancel-order"
                                                            data-id="<?= $order['order_id'] ?>"
                                                            data-name="<?= htmlspecialchars($order['recipient_name']) ?>">
                                                        <i class="fas fa-times"></i>
                                                        <span class="d-none d-sm-inline">Hủy</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        <?= generate_pagination($page, $total_pages) ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Modal xác nhận hủy đơn -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Xác nhận hủy đơn hàng
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex align-items-center mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <div>
                        <strong>Lưu ý:</strong> Hành động này không thể hoàn tác!
                    </div>
                </div>
                <p class="mb-2">Bạn có chắc chắn muốn hủy đơn hàng này?</p>
                <div class="order-info bg-light p-3 rounded mt-3">
                    <div class="row">
                        <div class="col-6">
                            <strong>Mã đơn hàng:</strong>
                        </div>
                        <div class="col-6">
                            <span id="cancelOrderId"></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Người nhận:</strong>
                        </div>
                        <div class="col-6">
                            <span id="cancelOrderRecipient"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Không, giữ đơn hàng
                </button>
                <form method="post" action="" id="cancelForm" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="cancel_order" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Có, hủy đơn hàng
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initializeOrdersPage();
    
    function initializeOrdersPage() {
        // Modal initialization
        const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
        const cancelForm = document.getElementById('cancelForm');
        const loadingOverlay = document.getElementById('loadingOverlay');
        
        // Enhanced form interactions
        initializeFormEnhancements();
        
        // Order row animations
        initializeOrderAnimations();
        
        // Cancel order functionality
        initializeCancelOrders(cancelModal, cancelForm);
        
        // Auto-refresh functionality
        initializeAutoRefresh();
        
        // Keyboard shortcuts
        initializeKeyboardShortcuts();
        
        // Real-time updates
        initializeRealTimeUpdates();
    }
    
    function initializeFormEnhancements() {
        const formInputs = document.querySelectorAll('.form-control, .form-select');
        
        formInputs.forEach(input => {
            // Focus effects
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
                this.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
                this.style.transform = '';
            });
            
            // Input validation effects
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#10b981';
                    this.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        });
        
        // Auto-submit filter with debounce
        const filterForm = document.getElementById('filterForm');
        let filterTimeout;
        
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                clearTimeout(filterTimeout);
                showLoading();
                
                filterTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 800);
            });
        });
        
        // Search input enhancements
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                this.style.borderColor = '#f59e0b';
                
                searchTimeout = setTimeout(() => {
                    this.style.borderColor = '';
                    if (this.value.length >= 3 || this.value.length === 0) {
                        // Auto-submit if search term is meaningful
                        filterForm.submit();
                    }
                }, 1500);
            });
        }
    }
    
    function initializeOrderAnimations() {
        const orderRows = document.querySelectorAll('.order-row');
        
        // Stagger animation for order rows
        orderRows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.1}s`;
            row.style.animation = 'fadeInUp 0.6s ease-out forwards';
        });
        
        // Hover effects for order rows
        orderRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(10px) scale(1.02)';
                this.style.zIndex = '10';
                this.style.boxShadow = '0 10px 30px rgba(0, 0, 0, 0.15)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.zIndex = '';
                this.style.boxShadow = '';
            });
        });
        
        // Status badge animations
        const statusBadges = document.querySelectorAll('.status-badge');
        statusBadges.forEach(badge => {
            if (badge.textContent.includes('Đang xử lý')) {
                badge.style.animation = 'statusPulse 2s infinite';
            }
        });
    }
    
    function initializeCancelOrders(cancelModal, cancelForm) {
        document.querySelectorAll('.cancel-order').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const orderId = this.dataset.id;
                const recipientName = this.dataset.name;
                
                // Update modal content
                document.getElementById('cancelOrderId').textContent = `#${orderId}`;
                document.getElementById('cancelOrderRecipient').textContent = recipientName;
                
                // Set form action
                cancelForm.action = `order_detail.php?id=${orderId}`;
                
                // Add ripple effect to button
                createRippleEffect(this, e);
                
                // Show modal with animation
                cancelModal.show();
            });
        });
        
        // Enhanced form submission
        cancelForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang hủy...';
            submitBtn.disabled = true;
            
            showLoading();
        });
    }
    
    function initializeAutoRefresh() {
        // Auto-refresh every 5 minutes for pending orders
        const hasPendingOrders = document.querySelector('.bg-gradient-warning');
        if (hasPendingOrders) {
            setInterval(() => {
                // Subtle refresh without disrupting user
                fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Check if orders have changed
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');
                    const newTable = newDoc.querySelector('.orders-card tbody');
                    const currentTable = document.querySelector('.orders-card tbody');
                    
                    if (newTable && currentTable && newTable.innerHTML !== currentTable.innerHTML) {
                        // Show subtle notification
                        showUpdateNotification();
                    }
                })
                .catch(error => console.log('Auto-refresh failed:', error));
            }, 300000); // 5 minutes
        }
    }
    
    function initializeKeyboardShortcuts() {
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
            
            // R to refresh
            if (e.key === 'r' && !e.ctrlKey) {
                e.preventDefault();
                window.location.reload();
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    bootstrap.Modal.getInstance(openModal).hide();
                }
            }
        });
    }
    
    function initializeRealTimeUpdates() {
        // Check for updates every 30 seconds
        setInterval(checkForUpdates, 30000);
        
        function checkForUpdates() {
            const orderIds = Array.from(document.querySelectorAll('.order-id'))
                .map(el => el.textContent.replace('#', ''));
            
            if (orderIds.length === 0) return;
            
            fetch('api/check_order_updates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ order_ids: orderIds })
            })
            .then(response => response.json())
            .then(data => {
                if (data.updates && data.updates.length > 0) {
                    updateOrderStatuses(data.updates);
                }
            })
            .catch(error => console.log('Update check failed:', error));
        }
        
        function updateOrderStatuses(updates) {
            updates.forEach(update => {
                const orderRow = document.querySelector(`[data-order-id="${update.order_id}"]`);
                if (orderRow) {
                    const statusBadge = orderRow.querySelector('.status-badge');
                    if (statusBadge && statusBadge.textContent !== update.status_text) {
                        // Animate status change
                        statusBadge.style.animation = 'statusPulse 0.5s ease-in-out';
                        setTimeout(() => {
                            statusBadge.className = `badge status-badge bg-gradient-${update.status_class}`;
                            statusBadge.innerHTML = `<i class="fas fa-${update.status_icon} me-1"></i>${update.status_text}`;
                            
                            // Show notification
                            showStatusUpdateNotification(update.order_id, update.status_text);
                        }, 250);
                    }
                }
            });
        }
    }
    
    // Utility functions
    function showLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.animation = 'fadeIn 0.3s ease-out';
        }
    }
    
    function hideLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 300);
        }
    }
    
    function createRippleEffect(element, event) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;
        
        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    }
    
    function showUpdateNotification() {
        const notification = createNotification(
            'Có cập nhật mới!',
            'Một số đơn hàng đã được cập nhật. Tải lại trang để xem thay đổi.',
            'info',
            () => window.location.reload()
        );
        document.body.appendChild(notification);
    }
    
    function showStatusUpdateNotification(orderId, status) {
        const notification = createNotification(
            'Cập nhật đơn hàng',
            `Đơn hàng #${orderId} đã chuyển sang trạng thái: ${status}`,
            'success'
        );
        document.body.appendChild(notification);
    }
    
    function createNotification(title, message, type = 'info', action = null) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 1rem;
            z-index: 10000;
            animation: slideInRight 0.3s ease-out;
            border-left: 4px solid ${type === 'success' ? '#10b981' : type === 'info' ? '#3b82f6' : '#f59e0b'};
        `;
        
        notification.innerHTML = `
            <div class="d-flex align-items-start">
                <div class="me-3">
                    <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 
                        type === 'info' ? 'info-circle text-info' : 'exclamation-circle text-warning'}"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">${title}</h6>
                    <p class="mb-0 small text-muted">${message}</p>
                    ${action ? '<button class="btn btn-sm btn-primary mt-2" onclick="this.parentElement.parentElement.parentElement.click()">Tải lại</button>' : ''}
                </div>
                <button class="btn-close btn-sm ms-2" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        if (action) {
            notification.addEventListener('click', action);
            notification.style.cursor = 'pointer';
        }
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
        
        return notification;
    }
    
    // Export data functionality
    function exportOrders(format = 'csv') {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('export', format);
        
        showLoading();
        
        fetch(currentUrl.toString())
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `orders_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                hideLoading();
            })
            .catch(error => {
                console.error('Export failed:', error);
                hideLoading();
            });
    }
    
    // Add export buttons if needed
    if (document.querySelectorAll('.order-row').length > 0) {
        const toolbar = document.createElement('div');
        toolbar.className = 'export-toolbar d-flex justify-content-end mb-3';
        toolbar.innerHTML = `
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-2"></i>Xuất dữ liệu
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportOrders('csv')">
                        <i class="fas fa-file-csv me-2"></i>CSV
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportOrders('excel')">
                        <i class="fas fa-file-excel me-2"></i>Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>In danh sách
                    </a></li>
                </ul>
            </div>
        `;
        
        const ordersCard = document.querySelector('.orders-card');
        if (ordersCard) {
            ordersCard.parentElement.insertBefore(toolbar, ordersCard);
        }
    }
    
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Page transition effects
    window.addEventListener('beforeunload', function() {
        document.body.style.opacity = '0.8';
        document.body.style.transform = 'scale(0.98)';
    });
    
    // Hide loading on page load
    window.addEventListener('load', function() {
        hideLoading();
    });
});

// Additional CSS animations
const additionalStyles = `
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100px);
    }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

.notification {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.notification:hover {
    transform: translateY(-2px);
}

.export-toolbar {
    animation: slideInUp 0.6s ease-out;
}

.focused {
    transform: translateY(-2px);
}

/* Enhanced mobile responsiveness */
@media (max-width: 480px) {
    .page-title {
        font-size: 1.8rem;
    }
    
    .filter-card .row {
        margin: 0;
    }
    
    .filter-card .col-md-2,
    .filter-card .col-md-3 {
        padding: 0.25rem;
        margin-bottom: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .btn-detail,
    .btn-cancel {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .status-badge {
        border: 2px solid currentColor;
    }
    
    .btn-detail,
    .btn-cancel {
        border: 2px solid currentColor;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
`;

// Inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);
</script>

<?php include 'includes/footer.php'; ?>