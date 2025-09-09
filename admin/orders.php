<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Xử lý filter
$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Show all items without pagination
$page = 1;
$limit = 100000; // A very large number to show all items
$offset = 0;

// Xây dựng query với filter
$where_clauses = [];
$params = [];

if ($status) {
    $where_clauses[] = "o.status = ?";
    $params[] = $status;
}

if ($payment) {
    $where_clauses[] = "o.payment_method = ?";
    $params[] = $payment;
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
    $where_clauses[] = "(u.full_name LIKE ? OR o.order_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Đếm tổng số đơn hàng
$count_sql = "
    SELECT COUNT(*)
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    $where_sql
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $limit);

// Query chính
$sql = "
    SELECT o.*, u.full_name, u.email, u.phone,
           a.recipient_name, a.address_line, a.city, a.district, a.ward,
           COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = o.order_id), 0) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN addresses a ON o.address_id = a.address_id
    $where_sql
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = flash('success');
$error = flash('error');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng - Admin Dashboard</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0idXJsKCNncmFkaWVudDApIi8+CjxwYXRoIGQ9Ik04IDEySDI0VjIwSDhWMTJaIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjIiIGZpbGw9Im5vbmUiLz4KPGRlZnM+CjxsaW5lYXJHcmFkaWVudCBpZD0iZ3JhZGllbnQwIiB4MT0iMCIgeTE9IjAiIHgyPSIzMiIgeTI9IjMyIiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+CjxzdG9wIHN0b3AtY29sb3I9IiM2NjdlZWEiLz4KPHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjNzY0YmEyIi8+CjwvbGluZWFyR3JhZGllbnQ+CjwvZGVmcz4KPHN2Zz4K">
</head>
<body style="font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh;">

<link rel="stylesheet" href="../css/admin_orders.css">
<!-- Enhanced CSS Styles -->


<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0;">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <a href="index.php" class="btn btn-back" data-aos="fade-right" data-aos-delay="100" style="
                                background: rgba(255, 255, 255, 0.2);
                                color: white;
                                border: 2px solid rgba(255, 255, 255, 0.3);
                                border-radius: 25px;
                                padding: 10px 20px;
                                font-weight: 600;
                                text-decoration: none;
                                transition: all 0.3s ease;
                                display: inline-flex;
                                align-items: center;
                                gap: 8px;
                            " onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'; this.style.transform='translateY(-2px)'" 
                               onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'; this.style.transform='translateY(0)'">
                                <i class="fas fa-arrow-left me-2"></i>
                                <span>Quay lại Admin</span>
                            </a>
                        </div>
                        <div class="col-md-4 text-center">
                            <h4 class="mb-0 fw-bold">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Quản lý đơn hàng
                                <span class="badge bg-light text-primary ms-2"><?= $total_orders ?></span>
                            </h4>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" class="btn" id="exportExcelBtn" style="background: linear-gradient(135deg, #00b894, #55efc4); color: white; border: none; padding: 8px 16px; border-radius: 25px; font-weight: 600; margin-right: 8px;">
                                <i class="fas fa-file-excel me-2"></i>Xuất Excel
                            </button>
                            <button type="button" class="btn" id="printBtn" style="background: linear-gradient(135deg, #74b9ff, #0984e3); color: white; border: none; padding: 8px 16px; border-radius: 25px; font-weight: 600;">
                                <i class="fas fa-print me-2"></i>In trang
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 10px; border: none;">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 10px; border: none;">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Enhanced Filter Form -->
                    <form method="get" class="filter-form">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">
                                    <i class="fas fa-search me-1"></i>Tìm kiếm
                                </label>
                                <div class="search-container">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="form-control" name="search"
                                        placeholder="Nhập mã ĐH hoặc tên KH..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">
                                    <i class="fas fa-flag me-1"></i>Trạng thái
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
                                <label class="form-label">
                                    <i class="fas fa-credit-card me-1"></i>PTTT
                                </label>
                                <select class="form-select" name="payment">
                                    <option value="">Tất cả PTTT</option>
                                    <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $payment === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Từ ngày
                                </label>
                                <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Đến ngày
                                </label>
                                <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-light w-100" style="border-radius: 10px; font-weight: 600;">
                                    <i class="fas fa-filter me-2"></i> Lọc kết quả
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Enhanced Table -->
                    <div class="table-container">
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag me-2"></i>Mã ĐH / Khách hàng</th>
                                        <th><i class="fas fa-map-marker-alt me-2"></i>Địa chỉ</th>
                                        <th class="text-center"><i class="fas fa-dollar-sign me-2"></i>Tổng tiền</th>
                                        <th class="text-center"><i class="fas fa-credit-card me-2"></i>PTTT</th>
                                        <th class="text-center"><i class="fas fa-flag me-2"></i>Trạng thái</th>
                                        <th class="text-center"><i class="fas fa-clock me-2"></i>Ngày đặt</th>
                                        <th class="text-center"><i class="fas fa-cogs me-2"></i>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody id="ordersTableBody">
                                    <?php foreach ($orders as $index => $o): ?>
                                    <tr style="animation-delay: <?= $index * 0.1 ?>s;">
                                        <td>
                                            <div class="d-flex px-2 py-1">
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-1 text-sm" style="color: #667eea; font-weight: 700;">
                                                        <i class="fas fa-receipt me-1"></i>#<?= $o['order_id'] ?>
                                                    </h6>
                                                    <p class="text-xs mb-0" style="color: #6c757d;">
                                                        <i class="fas fa-user me-1"></i><?= sanitize($o['full_name']) ?><br>
                                                        <i class="fas fa-phone me-1"></i><?= $o['phone'] ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-1">
                                                <i class="fas fa-user-tag me-1" style="color: #667eea;"></i>
                                                <?= sanitize($o['recipient_name']) ?>
                                            </p>
                                            <p class="text-xs text-secondary mb-0">
                                                <i class="fas fa-home me-1"></i>
                                                <?= sanitize($o['address_line']) ?>,
                                                <?= sanitize($o['ward']) ?>,
                                                <?= sanitize($o['district']) ?>,
                                                <?= sanitize($o['city']) ?>
                                            </p>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php if ($o['discount_amount'] > 0): ?>
                                                <span class="currency text-decoration-line-through text-muted small">
                                                    <?= format_currency($o['total_amount']) ?>
                                                </span><br>
                                                <span class="currency text-success">
                                                    <?= format_currency($o['total_amount'] - $o['discount_amount']) ?>
                                                </span><br>
                                                <small class="text-success">
                                                    <i class="fas fa-tag me-1"></i>Giảm <?= format_currency($o['discount_amount']) ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="currency">
                                                    <?= format_currency($o['total_amount']) ?>
                                                </span>
                                            <?php endif; ?><br>
                                            <span class="text-xs" style="color: #6c757d;">
                                                <i class="fas fa-box me-1"></i><?= $o['item_count'] ?> sản phẩm
                                            </span>
                                        </td>
                                        <td class="align-middle text-center">
                                            <span class="payment-badge">
                                                <?= PAYMENT_METHODS[$o['payment_method']] ?? $o['payment_method'] ?>
                                            </span>
                                        </td>
                                        <td class="align-middle text-center">
                                            <select class="status-select" data-order-id="<?= $o['order_id'] ?>">
                                                <?php foreach (ORDER_STATUS as $key => $label): ?>
                                                    <option value="<?= $key ?>" <?= $o['status'] === $key ? 'selected' : '' ?>>
                                                        <?= $label ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="align-middle text-center">
                                            <span class="text-xs font-weight-bold" style="color: #6c757d;">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                <?= date('d/m/Y', strtotime($o['created_at'])) ?><br>
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('H:i', strtotime($o['created_at'])) ?>
                                            </span>
                                        </td>
                                        <td class="align-middle text-center">
                                            <a href="order_detail.php?id=<?= $o['order_id'] ?>" class="action-btn">
                                                <i class="fas fa-eye"></i> Chi tiết
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination removed to show all items -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate table rows on load
    const rows = document.querySelectorAll('#ordersTableBody tr');
    rows.forEach((row, index) => {
        setTimeout(() => {
            row.style.opacity = '1';
        }, index * 100);
    });

    // Show notification function
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    // Show loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').classList.add('active');
    }

    // Hide loading overlay
    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    }

    // Custom confirmation dialog
    function showCustomConfirm(message, onConfirm, onCancel) {
        return new Promise((resolve) => {
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'custom-confirm-overlay';
            
            // Create dialog
            const dialog = document.createElement('div');
            dialog.className = 'custom-confirm-dialog';
            
            dialog.innerHTML = `
                <div class="custom-confirm-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="custom-confirm-title">Xác nhận thao tác</div>
                <div class="custom-confirm-message">${message}</div>
                <div class="custom-confirm-buttons">
                    <button class="custom-confirm-btn cancel" onclick="closeCustomConfirm(false)">
                        <i class="fas fa-times me-2"></i>Hủy bỏ
                    </button>
                    <button class="custom-confirm-btn confirm" onclick="closeCustomConfirm(true)">
                        <i class="fas fa-check me-2"></i>Xác nhận
                    </button>
                </div>
            `;
            
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            
            // Show dialog
            setTimeout(() => {
                overlay.classList.add('active');
            }, 10);
            
            // Store callbacks
            window.customConfirmResolve = resolve;
            window.customConfirmOnConfirm = onConfirm;
            window.customConfirmOnCancel = onCancel;
        });
    }

    // Close custom confirm dialog
    window.closeCustomConfirm = function(confirmed) {
        const overlay = document.querySelector('.custom-confirm-overlay');
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => {
                document.body.removeChild(overlay);
            }, 300);
        }
        
        if (window.customConfirmResolve) {
            window.customConfirmResolve(confirmed);
            if (confirmed && window.customConfirmOnConfirm) {
                window.customConfirmOnConfirm();
            } else if (!confirmed && window.customConfirmOnCancel) {
                window.customConfirmOnCancel();
            }
        }
    };

    // Enhanced status update handling
    document.querySelectorAll('.status-select').forEach(select => {
        // Store original value
        select.dataset.originalValue = select.value;
        
        // Add change event listener
        select.addEventListener('change', function() {
            const orderId = this.dataset.orderId;
            const newStatus = this.value;
            const originalValue = this.dataset.originalValue;
            const selectElement = this;
            
            // Use custom confirmation dialog
            showCustomConfirm(
                `Bạn có chắc chắn muốn cập nhật trạng thái đơn hàng <strong>#${orderId}</strong> không?<br><br>
                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 10px;">
                    <strong>Trạng thái mới:</strong> 
                    <span style="color: #667eea; font-weight: 600;">${this.options[this.selectedIndex].text}</span>
                </div>`,
                () => {
                    // Confirmed - proceed with update
                    showLoading();
                    
                    // Animate the select while processing
                    selectElement.style.transform = 'scale(1.1)';
                    selectElement.style.transition = 'all 0.3s ease';
                    
                    fetch(`orders_update_status.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `order_id=${orderId}&status=${newStatus}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        
                        if (data.success) {
                            // Update stored original value
                            selectElement.dataset.originalValue = newStatus;
                            
                            // Success animation với màu xanh tạm thời
                            selectElement.style.transform = 'scale(1)';
                            selectElement.style.background = 'linear-gradient(135deg, #00b894, #55efc4)';
                            selectElement.style.color = 'white';
                            
                            // Sau 1.5s, cập nhật về màu của trạng thái mới
                            setTimeout(() => {
                                updateStatusColor(selectElement);
                            }, 1500);
                            
                            showNotification('✅ Cập nhật trạng thái thành công!', 'success');
                            
                            // Update row visual feedback
                            const row = selectElement.closest('tr');
                            row.style.background = 'linear-gradient(135deg, rgba(0, 184, 148, 0.1) 0%, rgba(85, 239, 196, 0.1) 100%)';
                            setTimeout(() => {
                                row.style.background = '';
                            }, 2000);
                            
                        } else {
                            // Error handling
                            selectElement.value = originalValue;
                            selectElement.style.transform = 'scale(1)';
                            selectElement.style.background = 'linear-gradient(135deg, #d63031, #fd7979)';
                            selectElement.style.color = 'white';
                            
                            setTimeout(() => {
                                selectElement.style.background = '';
                                selectElement.style.color = '';
                            }, 1500);
                            
                            showNotification(data.message || '❌ Có lỗi xảy ra khi cập nhật trạng thái.', 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        
                        // Reset to original value
                        selectElement.value = originalValue;
                        selectElement.style.transform = 'scale(1)';
                        selectElement.style.background = 'linear-gradient(135deg, #d63031, #fd7979)';
                        selectElement.style.color = 'white';
                        
                        setTimeout(() => {
                            selectElement.style.background = '';
                            selectElement.style.color = '';
                        }, 1500);
                        
                        showNotification('🔌 Có lỗi kết nối. Vui lòng thử lại sau.', 'error');
                    });
                },
                () => {
                    // Cancelled - reset to original value
                    selectElement.value = originalValue;
                    
                    // Cancel animation
                    selectElement.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        selectElement.style.transform = 'scale(1)';
                    }, 150);
                    
                    showNotification('❌ Đã hủy thao tác cập nhật trạng thái.', 'error');
                }
            );
        });

        // Add hover effects for status select
        select.addEventListener('mouseenter', function() {
            if (!this.matches(':focus')) {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.1)';
            }
        });

        select.addEventListener('mouseleave', function() {
            if (!this.matches(':focus')) {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            }
        });
    });

    // Enhanced search functionality with debounce
    const searchInput = document.querySelector('input[name="search"]');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchContainer = this.closest('.search-container');
            
            // Add searching animation
            searchContainer.style.position = 'relative';
            
            searchTimeout = setTimeout(() => {
                // Auto-submit search after 1 second of no typing
                if (this.value.length >= 3 || this.value.length === 0) {
                    showLoading();
                    this.closest('form').submit();
                }
            }, 1000);
        });

        // Add search icon animation
        searchInput.addEventListener('focus', function() {
            const icon = this.parentElement.querySelector('.search-icon');
            icon.style.color = '#667eea';
            icon.style.transform = 'translateY(-50%) scale(1.1)';
        });

        searchInput.addEventListener('blur', function() {
            const icon = this.parentElement.querySelector('.search-icon');
            icon.style.color = '#6c757d';
            icon.style.transform = 'translateY(-50%) scale(1)';
        });
    }

    // Enhanced table row hover effects
    const tableRows = document.querySelectorAll('#ordersTableBody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
            this.style.zIndex = '10';
        });

        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.zIndex = '';
        });
    });

    // Enhanced form animations
    const formInputs = document.querySelectorAll('.filter-form input, .filter-form select');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 6px 20px rgba(0, 0, 0, 0.15)';
        });

        input.addEventListener('blur', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
        });
    });

    // Print button enhancement - removed

    // Action button animations
    const actionBtns = document.querySelectorAll('.action-btn');
    actionBtns.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });

        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });

        btn.addEventListener('click', function() {
            showLoading();
            // The loading will be hidden when the new page loads
        });
    });

    // Filter form submit animation
    const filterForm = document.querySelector('.filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function() {
            showLoading();
            
            // Animate filter button
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.style.transform = 'scale(0.95)';
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Đang lọc...';
        });
    }

    // Pagination enhancement
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.parentElement.classList.contains('active')) {
                showLoading();
            }
        });
    });

    // Auto-refresh notification (optional)
    let refreshInterval;
    const startAutoRefresh = () => {
        refreshInterval = setInterval(() => {
            showNotification('🔄 Dữ liệu sẽ được làm mới sau 30 giây...', 'success');
        }, 300000); // 5 minutes
    };

    // Start auto-refresh if enabled
    // startAutoRefresh();

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + F for search focus
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Ctrl + P for print - removed

        // ESC to clear search
        if (e.key === 'Escape') {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput === document.activeElement) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });

    // Global function to update status color
    function updateStatusColor(selectElement) {
        const value = selectElement.value;
        selectElement.className = 'status-select';
        
        // Ensure options always have correct colors
        const options = selectElement.querySelectorAll('option');
        options.forEach(option => {
            option.style.background = 'white';
            option.style.color = '#333';
        });
        
        switch(value) {
            case 'pending':
                selectElement.style.borderColor = '#fdcb6e';
                selectElement.style.background = 'linear-gradient(135deg, #ffeaa7, #fdcb6e)';
                selectElement.style.color = '#333';
                break;
            case 'processing':
                selectElement.style.borderColor = '#74b9ff';
                selectElement.style.background = 'linear-gradient(135deg, #74b9ff, #0984e3)';
                selectElement.style.color = 'white';
                break;
            case 'shipped':
                selectElement.style.borderColor = '#a29bfe';
                selectElement.style.background = 'linear-gradient(135deg, #a29bfe, #6c5ce7)';
                selectElement.style.color = 'white';
                break;
            case 'delivered':
                selectElement.style.borderColor = '#55efc4';
                selectElement.style.background = 'linear-gradient(135deg, #55efc4, #00b894)';
                selectElement.style.color = 'white';
                break;
            case 'cancelled':
                selectElement.style.borderColor = '#fd7979';
                selectElement.style.background = 'linear-gradient(135deg, #fd7979, #d63031)';
                selectElement.style.color = 'white';
                break;
            case 'returned':
                selectElement.style.borderColor = '#fd79a8';
                selectElement.style.background = 'linear-gradient(135deg, #fd79a8, #e84393)';
                selectElement.style.color = 'white';
                break;
            default:
                selectElement.style.borderColor = '#e9ecef';
                selectElement.style.background = 'white';
                selectElement.style.color = '#333';
        }
    }

    // Status color coding based on urgency
    const statusSelects = document.querySelectorAll('.status-select');
    statusSelects.forEach(select => {
        // Initialize color on page load
        updateStatusColor(select);
        
        // Update color when value changes
        select.addEventListener('change', function() {
            // This will be handled by the status update logic above
        });
        
        // Add event listener for when dropdown opens
        select.addEventListener('focus', function() {
            const options = this.querySelectorAll('option');
            options.forEach(option => {
                option.style.background = 'white';
                option.style.color = '#333';
                option.style.fontWeight = '600';
            });
        });
    });

    // Add loading state to form inputs during filter
    const filterInputs = document.querySelectorAll('.filter-form input, .filter-form select');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.name !== 'search') {
                showLoading();
                setTimeout(() => {
                    this.closest('form').submit();
                }, 500);
            }
        });
    });

    // Performance monitoring
    const performanceObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
            if (entry.entryType === 'navigation') {
                console.log(`Page load time: ${entry.loadEventEnd - entry.loadEventStart}ms`);
            }
        }
    });
    
    try {
        performanceObserver.observe({entryTypes: ['navigation']});
    } catch (e) {
        // Fallback for browsers that don't support PerformanceObserver
        console.log('Performance monitoring not supported');
    }

    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

  

    // Set up Excel Export
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', exportToExcel);
    }
    
    // Set up Print functionality
    const printBtn = document.getElementById('printBtn');
    if (printBtn) {
        printBtn.addEventListener('click', printPage);
    }

    // Export Orders to Excel
    function exportToExcel() {
        showLoading();
        
        try {
            // Get current timestamp for filename
            const date = new Date();
            const timestamp = date.toISOString().replace(/[:.]/g, '-').substring(0, 19);
            const fileName = `orders_export_${timestamp}.xlsx`;
            
            // Create workbook and worksheet
            const wb = XLSX.utils.book_new();
            
            // Get all order data from table
            const table = document.querySelector('.table');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            
            // Prepare header row
            const headers = [
                'Mã ĐH', 
                'Khách hàng', 
                'SĐT',
                'Người nhận', 
                'Địa chỉ', 
                'Tổng tiền', 
                'PTTT',
                'Trạng thái',
                'Ngày đặt'
            ];
            
            // Extract data from each row
            const data = [headers];
            
            rows.forEach(row => {
                // Extract order ID
                const idCell = row.querySelector('td:nth-child(1)');
                const orderId = idCell.querySelector('h6').textContent.trim().replace('#', '');
                
                // Extract customer name
                const customerName = idCell.querySelector('p').textContent.trim().replace('', '');
                
                // Extract phone
                const phone = idCell.querySelector('p:nth-child(2)').textContent.trim();
                
                // Extract recipient name and address
                const addressCell = row.querySelector('td:nth-child(2)');
                const recipientName = addressCell.querySelector('p:nth-child(1)').textContent.trim();
                const address = addressCell.querySelector('p:nth-child(2)').textContent.trim();
                
                // Extract total amount
                const totalCell = row.querySelector('td:nth-child(3)');
                const totalAmount = totalCell.querySelector('.currency').textContent.trim();
                
                // Extract payment method
                const paymentCell = row.querySelector('td:nth-child(4)');
                const paymentMethod = paymentCell.textContent.trim();
                
                // Extract status
                const statusCell = row.querySelector('td:nth-child(5)');
                const status = statusCell.querySelector('select').options[statusCell.querySelector('select').selectedIndex].text.trim();
                
                // Extract date
                const dateCell = row.querySelector('td:nth-child(6)');
                const orderDate = dateCell.textContent.trim().replace(/\\s+/g, ' ');
                
                data.push([
                    orderId,
                    customerName,
                    phone,
                    recipientName,
                    address,
                    totalAmount,
                    paymentMethod,
                    status,
                    orderDate
                ]);
            });
            
            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(data);
            
            // Set column widths
            const colWidths = [
                { wch: 10 },  // Mã ĐH
                { wch: 25 },  // Khách hàng
                { wch: 15 },  // SĐT
                { wch: 25 },  // Người nhận
                { wch: 40 },  // Địa chỉ
                { wch: 15 },  // Tổng tiền
                { wch: 15 },  // PTTT
                { wch: 15 },  // Trạng thái
                { wch: 20 },  // Ngày đặt
            ];
            
            ws['!cols'] = colWidths;
            
            // Add the worksheet to the workbook
            XLSX.utils.book_append_sheet(wb, ws, 'Danh sách đơn hàng');
            
            // Save the file
            XLSX.writeFile(wb, fileName);
            
            // Show success message
            showNotification('✅ Xuất Excel thành công!', 'success');
        } catch (error) {
            console.error('Export error:', error);
            showNotification('❌ Có lỗi xảy ra khi xuất Excel: ' + error.message, 'error');
        } finally {
            hideLoading();
        }
    }
    
    // Print orders page
    function printPage() {
        try {
            // Show loading
            showLoading();
            
            // Create a clone of the orders table to modify for printing
            const ordersTable = document.querySelector('.table');
            const cloneTable = ordersTable.cloneNode(true);
            
            // Remove action column
            const actionCells = cloneTable.querySelectorAll('th:last-child, td:last-child');
            actionCells.forEach(cell => cell.remove());
            
            // Replace status select with text
            const statusCells = cloneTable.querySelectorAll('td:nth-child(5)');
            statusCells.forEach(cell => {
                const select = cell.querySelector('select');
                if (select) {
                    const statusText = select.options[select.selectedIndex].text;
                    cell.innerHTML = `<span class="status-badge">${statusText}</span>`;
                }
            });
            
            // Get filter selections
            const statusSelect = document.querySelector('select[name="status"]');
            const paymentSelect = document.querySelector('select[name="payment"]');
            const startDateInput = document.querySelector('input[name="start_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');
            const searchInput = document.querySelector('input[name="search"]');
            
            const filters = {
                search: searchInput?.value || '',
                status: statusSelect ? statusSelect.options[statusSelect.selectedIndex].text : 'Tất cả trạng thái',
                payment: paymentSelect ? paymentSelect.options[paymentSelect.selectedIndex].text : 'Tất cả PTTT',
                startDate: startDateInput?.value || '',
                endDate: endDateInput?.value || ''
            };
            
            // Create print window
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Create print content
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Danh sách đơn hàng - In</title>
                    <meta charset="UTF-8">
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 20px;
                            padding-bottom: 10px;
                            border-bottom: 2px solid #ddd;
                        }
                        .print-title {
                            font-size: 24px;
                            font-weight: bold;
                            margin: 0;
                            padding: 0;
                        }
                        .print-date {
                            margin-top: 5px;
                            font-size: 14px;
                            color: #666;
                        }
                        .filters-container {
                            margin-bottom: 20px;
                            padding: 10px;
                            border: 1px solid #ddd;
                            background: #f9f9f9;
                        }
                        .filter-item {
                            display: inline-block;
                            margin-right: 15px;
                        }
                        .filter-label {
                            font-weight: bold;
                            font-size: 12px;
                        }
                        .filter-value {
                            font-size: 12px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 8px;
                            text-align: left;
                        }
                        th {
                            background-color: #f2f2f2;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .status-badge {
                            padding: 5px 10px;
                            border-radius: 15px;
                            font-size: 12px;
                            font-weight: bold;
                            display: inline-block;
                            text-align: center;
                        }
                        .print-footer {
                            margin-top: 20px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                        }
                        @media print {
                            .no-print {
                                display: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1 class="print-title">Danh sách đơn hàng</h1>
                        <div class="print-date">Ngày in: ${new Date().toLocaleString('vi-VN')}</div>
                    </div>
                    
                    <div class="filters-container">
                        <div class="filter-item">
                            <div class="filter-label">Tìm kiếm:</div>
                            <div class="filter-value">${filters.search || 'Không có'}</div>
                        </div>
                        <div class="filter-item">
                            <div class="filter-label">Trạng thái:</div>
                            <div class="filter-value">${filters.status}</div>
                        </div>
                        <div class="filter-item">
                            <div class="filter-label">PTTT:</div>
                            <div class="filter-value">${filters.payment}</div>
                        </div>
                        <div class="filter-item">
                            <div class="filter-label">Từ ngày:</div>
                            <div class="filter-value">${filters.startDate || 'Không có'}</div>
                        </div>
                        <div class="filter-item">
                            <div class="filter-label">Đến ngày:</div>
                            <div class="filter-value">${filters.endDate || 'Không có'}</div>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        ${cloneTable.outerHTML}
                    </div>
                    
                    <div class="print-footer">
                        <p>© ${new Date().getFullYear()} - Hệ thống quản lý cửa hàng</p>
                    </div>
                    
                    <div class="no-print" style="margin-top: 20px; text-align: center;">
                        <button onclick="window.print()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            In ngay
                        </button>
                        <button onclick="window.close()" style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                            Đóng
                        </button>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // Focus the print window
            printWindow.focus();
            
            // Hide loading
            hideLoading();
            
            // Show success message
            showNotification('✅ Đã mở chế độ in trang!', 'success');
        } catch (error) {
            console.error('Print error:', error);
            showNotification('❌ Có lỗi xảy ra khi in trang: ' + error.message, 'error');
            hideLoading();
        }
    }

    console.log('🚀 Orders management page loaded successfully with enhanced features!');
});

// Print styles removed
</script>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

<!-- SheetJS (xlsx) for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- html2canvas for better printing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Enhanced Footer -->
<footer style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-top: 50px;">
    <div class="container-fluid py-3">
        <div class="row align-items-center">
            <div class="col-md-12 text-center">
                <div class="d-flex justify-content-center align-items-center">
                    <div class="d-flex gap-2">
                        <a href="#" class="text-white-50" style="text-decoration: none; padding: 8px; border-radius: 50%; background: rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-white-50" style="text-decoration: none; padding: 8px; border-radius: 50%; background: rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-white-50" style="text-decoration: none; padding: 8px; border-radius: 50%; background: rgba(255,255,255,0.1); transition: all 0.3s ease;">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
/* Additional styles for navbar and footer */
.navbar-nav .nav-link:hover {
    background: rgba(255,255,255,0.15) !important;
    transform: translateY(-1px);
}

#quickSearch::placeholder {
    color: rgba(255,255,255,0.7);
}

#quickSearch:focus {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.4);
    box-shadow: 0 0 0 2px rgba(255,255,255,0.1);
    outline: none;
}

footer a:hover {
    background: rgba(255,255,255,0.2) !important;
    transform: translateY(-2px);
}

.dropdown-menu {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive navbar */
@media (max-width: 768px) {
    #quickSearch {
        width: 100% !important;
        margin: 10px 0;
    }
    
    .navbar-brand span {
        font-size: 1.3rem;
    }
    
    .d-flex.align-items-center {
        flex-direction: column;
        align-items: stretch !important;
    }
}
</style>

</body>
</html>