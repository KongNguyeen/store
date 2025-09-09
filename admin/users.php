<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Xử lý filter
$search = sanitize($_GET['search'] ?? '');
$role_id = (int)($_GET['role_id'] ?? 0);
$status = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Lấy danh sách roles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY role_name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xây dựng query với filter
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($role_id) {
    $where_clauses[] = "u.role_id = ?";
    $params[] = $role_id;
}

if ($status !== -1) {
    $where_clauses[] = "u.is_active = ?";
    $params[] = $status;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Đếm tổng số users
$count_sql = "SELECT COUNT(*) FROM users u $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Query chính
$sql = "
    SELECT u.*, r.role_name,
           (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) as order_count,
           (SELECT COUNT(*) FROM reviews rv WHERE rv.user_id = u.user_id) as review_count
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    $where_sql
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash message
$success = flash('success');
$error = flash('error');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/users.css">
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="container-fluid">
        <!-- Statistics -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <span class="stat-number"><?= $total_users ?></span>
                <div class="stat-label">Tổng người dùng</div>
            </div>
            <div class="stat-card">
                <span class="stat-number">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
                    echo $stmt->fetchColumn();
                    ?>
                </span>
                <div class="stat-label">Đang hoạt động</div>
            </div>
            <div class="stat-card">
                <span class="stat-number">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
                    echo $stmt->fetchColumn();
                    ?>
                </span>
                <div class="stat-label">Mới hôm nay</div>
            </div>

            </div>
        </div>

        <!-- Main Card -->
        <div class="main-card fade-in">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h1 class="card-title">
                👥 Quản lý người dùng
            </h1>
                    <button class="back-btn" onclick="window.location.href='index.php'" title="Quay lại trang chủ">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                    </button>
                </div>
            </div>

            <div class="card-body">
                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        ✅ <?= $success ?>
                        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        ❌ <?= $error ?>
                        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Filter Form -->
                <form method="get" class="filter-form slide-in">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                🔍 Tìm kiếm
                            </label>
                            <input type="text" class="form-control" name="search"
                                placeholder="Tên, email, số điện thoại..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                🏷️ Quyền
                            </label>
                            <select class="form-select" name="role_id">
                                <option value="">Tất cả quyền</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"
                                        <?= $role_id == $role['role_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                🔘 Trạng thái
                            </label>
                            <select class="form-select" name="status">
                                <option value="-1">Tất cả trạng thái</option>
                                <option value="1" <?= $status === 1 ? 'selected' : '' ?>>✅ Hoạt động</option>
                                <option value="0" <?= $status === 0 ? 'selected' : '' ?>>❌ Đã khóa</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                🔍 Lọc
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Users Table -->
                <div class="table-container slide-in">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>👤 Người dùng</th>
                                <th>🛡️ Quyền</th>
                                <th style="text-align: center;">🛍️ Đơn hàng</th>
                                <th style="text-align: center;">⭐ Đánh giá</th>
                                <th style="text-align: center;">📅 Ngày tạo</th>
                                <th style="text-align: center;">🔘 Trạng thái</th>
                                <th style="text-align: center;">⚙️ Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <h6><?= sanitize($u['full_name']) ?></h6>
                                            <p class="text-muted"><?= sanitize($u['email']) ?></p>
                                            <?php if ($u['phone']): ?>
                                                <p class="text-muted"><?= $u['phone'] ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-select role-select" 
                                            data-user-id="<?= $u['user_id'] ?>"
                                            <?= $u['user_id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= $role['role_id'] ?>"
                                                <?= $u['role_id'] == $role['role_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($role['role_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-primary"><?= $u['order_count'] ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-warning"><?= $u['review_count'] ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <small class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                                    </small>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                            <label class="toggle-switch">
                                                <input type="checkbox" class="toggle-input status-toggle"
                                                    data-user-id="<?= $u['user_id'] ?>"
                                                    <?= $u['is_active'] ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                            <small class="status-text" style="font-weight: 500; color: <?= $u['is_active'] ? 'var(--success-color)' : 'var(--danger-color)' ?>;">
                                                <?= $u['is_active'] ? 'Hoạt động' : 'Đã khóa' ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                            <span class="badge badge-success">✓ Hoạt động</span>
                                            <small style="color: #6b7280;">(Bạn)</small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; justify-content: center; gap: 4px;">
                                        <?php if ($u['order_count'] > 0): ?>
                                        <button type="button" class="action-btn btn-orders view-orders"
                                                data-user-id="<?= $u['user_id'] ?>"
                                                data-user-name="<?= htmlspecialchars($u['full_name']) ?>"
                                                title="Xem đơn hàng (<?= $u['order_count'] ?> đơn)">
                                            🛒
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($u['review_count'] > 0): ?>
                                        <button type="button" class="action-btn btn-reviews view-reviews"
                                                data-user-id="<?= $u['user_id'] ?>"
                                                data-user-name="<?= htmlspecialchars($u['full_name']) ?>"
                                                title="Xem đánh giá (<?= $u['review_count'] ?> đánh giá)">
                                            ⭐
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($u['order_count'] == 0 && $u['review_count'] == 0): ?>
                                        <small style="color: #9ca3af; font-style: italic;">
                                            Chưa có hoạt động
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?= generate_pagination($page, $total_pages) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Orders Modal -->
    <div class="modal" id="ordersModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h5 class="modal-title">
                    🛒 Đơn hàng của <span id="orderUserName"></span>
                </h5>
                <button class="modal-close" onclick="closeModal('ordersModal')">&times;</button>
            </div>
            <div class="modal-body" id="userOrders">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Reviews Modal -->
    <div class="modal" id="reviewsModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h5 class="modal-title">
                    ⭐ Đánh giá của <span id="reviewUserName"></span>
                </h5>
                <button class="modal-close" onclick="closeModal('reviewsModal')">&times;</button>
            </div>
            <div class="modal-body" id="userReviews">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let loadingOverlay = null;
        let toastContainer = null;

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            loadingOverlay = document.getElementById('loadingOverlay');
            toastContainer = document.getElementById('toastContainer');
            
            initializeEventListeners();
            animateElements();
        });

        // Show/Hide loading overlay
        function showLoading() {
            if (loadingOverlay) {
                loadingOverlay.classList.add('active');
            }
        }

        function hideLoading() {
            if (loadingOverlay) {
                loadingOverlay.classList.remove('active');
            }
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            if (!toastContainer) return;

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? '✅' : '❌';
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span>${icon}</span>
                    <span style="flex: 1;">${message}</span>
                    <button class="toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'slideOutRight 0.3s ease-out forwards';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }

        // Make AJAX request
        async function makeRequest(url, data) {
            showLoading();
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: data
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const result = await response.json();
                hideLoading();
                return result;
            } catch (error) {
                hideLoading();
                console.error('Error:', error);
                return { 
                    success: false, 
                    message: 'Có lỗi xảy ra khi xử lý yêu cầu.' 
                };
            }
        }

        // Initialize event listeners
        function initializeEventListeners() {
            console.log('Initializing event listeners...');
            console.log('Found .view-orders buttons:', document.querySelectorAll('.view-orders').length);
            console.log('Found .view-reviews buttons:', document.querySelectorAll('.view-reviews').length);
            // Role selection change
            document.querySelectorAll('.role-select').forEach(select => {
                // Store original value
                select.dataset.originalValue = select.value;
                
                select.addEventListener('change', async function() {
                    const userId = this.dataset.userId;
                    const newRole = this.value;
                    const originalValue = this.dataset.originalValue;
                    
                    if (confirm('Bạn có chắc chắn muốn thay đổi quyền của người dùng này?')) {
                        // Disable select during request
                        this.disabled = true;
                        this.style.opacity = '0.6';
                        
                        const data = `user_id=${userId}&role_id=${newRole}&csrf_token=<?= $_SESSION['csrf_token'] ?>`;
                        const result = await makeRequest('users_update_role.php', data);
                        
                        if (result.success) {
                            showToast('Thay đổi quyền thành công!', 'success');
                            this.dataset.originalValue = newRole;
                            
                            // Success animation
                            this.style.borderColor = 'var(--success-color)';
                            setTimeout(() => {
                                this.style.borderColor = '';
                            }, 2000);
                        } else {
                            showToast(result.message || 'Có lỗi xảy ra khi thay đổi quyền.', 'error');
                            this.value = originalValue;
                        }
                        
                        // Re-enable select
                        this.disabled = false;
                        this.style.opacity = '1';
                    } else {
                        this.value = originalValue;
                    }
                });
            });

            // Status toggle change
            document.querySelectorAll('.status-toggle').forEach(toggle => {
                toggle.addEventListener('change', async function() {
                    const userId = this.dataset.userId;
                    const newStatus = this.checked ? 1 : 0;
                    const row = this.closest('tr');
                    
                    const action = newStatus ? 'mở khóa' : 'khóa';
                    
                    if (confirm(`Bạn có chắc chắn muốn ${action} tài khoản này?`)) {
                        // Add loading state to row
                        row.style.opacity = '0.6';
                        row.style.pointerEvents = 'none';
                        
                        const data = `user_id=${userId}&status=${newStatus}&csrf_token=<?= $_SESSION['csrf_token'] ?>`;
                        const result = await makeRequest('users_update_status.php', data);
                        
                        if (result.success) {
                            showToast(`${action.charAt(0).toUpperCase() + action.slice(1)} tài khoản thành công!`, 'success');
                            
                            // Update status text
                            const statusText = row.querySelector('.status-text');
                            if (statusText) {
                                statusText.textContent = newStatus ? 'Hoạt động' : 'Đã khóa';
                                statusText.style.color = newStatus ? 'var(--success-color)' : 'var(--danger-color)';
                            }
                        } else {
                            showToast(result.message || 'Có lỗi xảy ra khi thay đổi trạng thái.', 'error');
                            this.checked = !this.checked;
                        }
                        
                        // Remove loading state
                        row.style.opacity = '1';
                        row.style.pointerEvents = 'auto';
                    } else {
                        this.checked = !this.checked;
                    }
                });
            });

            // View orders buttons
            document.querySelectorAll('.view-orders').forEach(button => {
                console.log('Adding click listener to orders button:', button);
                button.addEventListener('click', async function() {
                    console.log('Orders button clicked!');
                    const userId = this.dataset.userId;
                    const userName = this.dataset.userName;
                    
                    console.log('User ID:', userId, 'User Name:', userName);
                    
                    document.getElementById('orderUserName').textContent = userName;
                    showModal('ordersModal');
                    
                    // Show loading in modal
                    document.getElementById('userOrders').innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <div class="spinner" style="margin: 0 auto 16px;"></div>
                            <p>⏳ Đang tải danh sách đơn hàng...</p>
                        </div>
                    `;
                    
                    try {
                        const response = await fetch(`users_get_orders.php?user_id=${userId}&csrf_token=<?= $_SESSION['csrf_token'] ?>`);
                        const html = await response.text();
                        document.getElementById('userOrders').innerHTML = html;
                    } catch (error) {
                        console.error('Error loading orders:', error);
                        document.getElementById('userOrders').innerHTML = `
                            <div style="text-align: center; padding: 40px; color: var(--danger-color);">
                                <p style="font-size: 2rem; margin-bottom: 16px;">⚠️</p>
                                <p>Có lỗi xảy ra khi tải dữ liệu đơn hàng.</p>
                            </div>
                        `;
                    }
                });
            });

            // View reviews buttons
            document.querySelectorAll('.view-reviews').forEach(button => {
                console.log('Adding click listener to reviews button:', button);
                button.addEventListener('click', async function() {
                    console.log('Reviews button clicked!');
                    const userId = this.dataset.userId;
                    const userName = this.dataset.userName;
                    
                    console.log('User ID:', userId, 'User Name:', userName);
                    
                    document.getElementById('reviewUserName').textContent = userName;
                    showModal('reviewsModal');
                    
                    // Show loading in modal
                    document.getElementById('userReviews').innerHTML = `
                        <div style="text-align: center; padding: 40px;">
                            <div class="spinner" style="margin: 0 auto 16px;"></div>
                            <p>⏳ Đang tải danh sách đánh giá...</p>
                        </div>
                    `;
                    
                    try {
                        const response = await fetch(`users_get_reviews.php?user_id=${userId}&csrf_token=<?= $_SESSION['csrf_token'] ?>`);
                        const html = await response.text();
                        document.getElementById('userReviews').innerHTML = html;
                    } catch (error) {
                        console.error('Error loading reviews:', error);
                        document.getElementById('userReviews').innerHTML = `
                            <div style="text-align: center; padding: 40px; color: var(--danger-color);">
                                <p style="font-size: 2rem; margin-bottom: 16px;">⚠️</p>
                                <p>Có lỗi xảy ra khi tải dữ liệu đánh giá.</p>
                            </div>
                        `;
                    }
                });
            });

            // Modal close when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this.id);
                    }
                });
            });

            // ESC key to close modals
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal.show').forEach(modal => {
                        closeModal(modal.id);
                    });
                }
            });
        }

        // Show modal
        function showModal(modalId) {
            console.log('Showing modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
                console.log('Modal shown successfully');
            } else {
                console.error('Modal not found:', modalId);
            }
        }

        // Close modal
        function closeModal(modalId) {
            console.log('Closing modal:', modalId);
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
                console.log('Modal closed successfully');
            } else {
                console.error('Modal not found:', modalId);
            }
        }

        // Animate elements on page load
        function animateElements() {
            // Animate table rows
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });

            // Animate stat numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(statNumber => {
                const target = parseInt(statNumber.textContent);
                const increment = target / 50;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    statNumber.textContent = Math.floor(current);
                    
                    if (current >= target) {
                        statNumber.textContent = target;
                        clearInterval(timer);
                    }
                }, 30);
            });

            // Add hover effects to action buttons
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.05)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add hover effects to stat cards
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'var(--shadow)';
                });
            });
        }

        // Add slideOutRight animation
        const slideOutRightCSS = `
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100%);
                }
            }
        `;
        
        const style = document.createElement('style');
        style.textContent = slideOutRightCSS;
        document.head.appendChild(style);

        // Add function for edit user (placeholder)
        function editUser(userId) {
            showToast('🔧 Tính năng chỉnh sửa sẽ được thêm sau!', 'success');
        }
    </script>
</body>
</html>