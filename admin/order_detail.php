<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';
include '../includes/navbar.php';

$order_id = intval($_GET['id'] ?? 0);
if (!$order_id) {
    flash('error', 'Đơn hàng không tồn tại');
    redirect('orders.php');
}

$pdo = getPDO();

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*,
           u.full_name, u.email, u.phone,
           a.recipient_name, a.phone as recipient_phone,
           a.address_line, a.city, a.district, a.ward
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN addresses a ON o.address_id = a.address_id
    WHERE o.order_id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    flash('error', 'Đơn hàng không tồn tại');
    redirect('orders.php');
}

// Lấy chi tiết sản phẩm
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.product_id,
           (SELECT image_url FROM product_images pi
            WHERE pi.product_id = p.product_id AND pi.is_primary = 1
            LIMIT 1) as product_image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy lịch sử trạng thái
$stmt = $pdo->prepare("
    SELECT h.*, u.full_name as changed_by_name
    FROM order_status_history h
    LEFT JOIN users u ON h.changed_by = u.user_id
    WHERE h.order_id = ?
    ORDER BY h.changed_at DESC
");
$stmt->execute([$order_id]);
$status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nếu không có lịch sử, tạo một record mặc định từ đơn hàng hiện tại
if (empty($status_history)) {
    $status_history = [
        [
            'status' => $order['status'],
            'changed_at' => $order['created_at'],
            'changed_by_name' => 'Hệ thống',
            'note' => 'Đơn hàng được tạo'
        ]
    ];
}

$success = flash('success');
$error = flash('error');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?= $order_id ?> - Admin Dashboard</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iOCIgZmlsbD0idXJsKCNncmFkaWVudDApIi8+CjxwYXRoIGQ9Ik04IDEySDI0VjIwSDhWMTJaIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjIiIGZpbGw9Im5vbmUiLz4KPGRlZnM+CjxsaW5lYXJHcmFkaWVudCBpZD0iZ3JhZGllbnQwIiB4MT0iMCIgeTE9IjAiIHgyPSIzMiIgeTI9IjMyIiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+CjxzdG9wIHN0b3AtY29sb3I9IiM2NjdlZWEiLz4KPHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjNzY0YmEyIi8+CjwvbGluZWFyR3JhZGllbnQ+CjwvZGVmcz4KPHN2Zz4K">

    <link rel="stylesheet" href="../css/admin_order_detail.css">
</head>

<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Order Header Card -->
            <div class="card mb-4 fade-in" data-aos="fade-down">
                <div class="card-header pb-0">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-0 fw-bold">
                                <i class="fas fa-receipt me-2"></i>
                                Chi tiết đơn hàng #<?= $order_id ?>
                            </h4>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="orders.php" class="btn-enhanced me-3" data-aos="fade-left" data-aos-delay="100">
                                <i class="fas fa-arrow-left"></i>
                                <span>Quay lại</span>
                            </a>
                            <button type="button" class="btn-enhanced btn-print" onclick="printOrder()" data-aos="fade-left" data-aos-delay="200">
                                <i class="fas fa-print"></i>
                                <span>In đơn hàng</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert" data-aos="fade-in">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert" data-aos="fade-in">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Order Summary -->
                    <div class="row mb-4 order-summary-row">
                        <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                            <div class="order-info-card order-card-1 text-center">
                                <i class="fas fa-shopping-cart mb-2"></i>
                                <h3><?= count($items) ?></h3>
                                <p class="mb-0">Sản phẩm</p>
                            </div>
                        </div>
                        <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                            <div class="order-info-card order-card-2 text-center">
                                <i class="fas fa-dollar-sign mb-2"></i>
                                <h3><?= number_format($order['total_amount'] - $order['discount_amount'], 0, ',', '.') ?></h3>
                                <p class="mb-0">VNĐ</p>
                            </div>
                        </div>
                        <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                            <div class="order-info-card order-card-3 text-center">
                                <i class="fas fa-calendar mb-2"></i>
                                <h3><?= date('d/m', strtotime($order['created_at'])) ?></h3>
                                <p class="mb-0"><?= date('Y', strtotime($order['created_at'])) ?></p>
                            </div>
                        </div>
                        <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                            <div class="order-info-card order-card-4 text-center">
                                <i class="fas fa-flag mb-2"></i>
                                <div class="status-badge status-<?= $order['status'] ?>">
                                    <?= ORDER_STATUS[$order['status']] ?>
                                </div>
                            </div>
                        </div>
                    </div>
                            
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Order Details -->
                        <div class="col-md-8">
                            <!-- Products List -->
                            <div class="card slide-up" data-aos="fade-right">
                                <div class="card-body">
                                    <h6 class="mb-4">
                                        <i class="fas fa-box me-2"></i>
                                        Danh sách sản phẩm (<?= count($items) ?> sản phẩm)
                                    </h6>
                                    
                                    <?php if (empty($items)): ?>
                                        <div class="alert alert-warning" role="alert">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Không tìm thấy sản phẩm nào trong đơn hàng này.
                                            <br><small>Đơn hàng ID: <?= $order_id ?></small>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($items as $index => $item): ?>
                                        <div class="product-item" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                                            <div class="row align-items-center">
                                                <div class="col-md-2">
                                                    <?php if (!empty($item['product_image'])): ?>
                                                        <img src="<?= $item['product_image'] ?>" 
                                                             alt="<?= sanitize($item['name'] ?? 'Sản phẩm') ?>" 
                                                             class="product-image">
                                                    <?php else: ?>
                                                        <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?= sanitize($item['name'] ?? 'Tên sản phẩm không có') ?></h6>
                                                    <p class="text-muted small mb-0">
                                                        <i class="fas fa-tag me-1"></i>
                                                        Mã SP: #<?= $item['product_id'] ?? 'N/A' ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-cube me-1"></i>
                                                        <?= $item['quantity'] ?? 0 ?>
                                                    </span>
                                                </div>
                                                <div class="col-md-2 text-end">
                                                    <div class="price-display">
                                                        <?= format_currency($item['price'] ?? 0) ?>
                                                    </div>
                                                    <small class="text-muted d-block">
                                                        = <?= format_currency(($item['price'] ?? 0) * ($item['quantity'] ?? 0)) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- Order Total -->
                                    <div class="row mt-4 pt-3 border-top">
                                        <div class="col-md-8"></div>
                                        <div class="col-md-4">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Tạm tính:</span>
                                                <span><?= format_currency($order['total_amount']) ?></span>
                                            </div>
                                            <?php if ($order['discount_amount'] > 0): ?>
                                            <div class="d-flex justify-content-between mb-2 text-success">
                                                <span>
                                                    <i class="fas fa-tags me-1"></i>Mã giảm giá:
                                                    <?php if (!empty($order['promotion_code'])): ?>
                                                        <small class="text-muted">(<?= sanitize($order['promotion_code']) ?>)</small>
                                                    <?php endif; ?>
                                                </span>
                                                <span>-<?= format_currency($order['discount_amount']) ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between fw-bold fs-5">
                                                <span>Tổng cộng:</span>
                                                <span class="price-display"><?= format_currency($order['total_amount'] - $order['discount_amount']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Customer & Order Info -->
                        <div class="col-md-4">
                            <!-- Customer Info -->
                            <div class="customer-info mb-4" data-aos="fade-left" data-aos-delay="100">
                                <h6 class="mb-3">
                                    <i class="fas fa-user me-2"></i>
                                    Thông tin khách hàng
                                </h6>
                                <p class="mb-2">
                                    <i class="fas fa-id-card me-2"></i>
                                    <strong><?= sanitize($order['full_name']) ?></strong>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-envelope me-2"></i>
                                    <?= sanitize($order['email']) ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-phone me-2"></i>
                                    <?= sanitize($order['phone']) ?>
                                </p>
                            </div>

                            <!-- Delivery Info -->
                            <div class="card" data-aos="fade-left" data-aos-delay="200">
                                <div class="card-body">
                                    <h6 class="mb-3">
                                        <i class="fas fa-shipping-fast me-2"></i>
                                        Thông tin giao hàng
                                    </h6>
                                    <p class="mb-2">
                                        <i class="fas fa-user-tag me-2"></i>
                                        <strong><?= sanitize($order['recipient_name']) ?></strong>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        <?= sanitize($order['recipient_phone']) ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <?= sanitize($order['address_line']) ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-city me-2"></i>
                                        <?= sanitize($order['ward']) ?>, <?= sanitize($order['district']) ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-globe me-2"></i>
                                        <?= sanitize($order['city']) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Payment & Shipping Info -->
                            <div class="card mt-4" data-aos="fade-left" data-aos-delay="300">
                                <div class="card-body">
                                    <h6 class="mb-3">
                                        <i class="fas fa-credit-card me-2"></i>
                                        Thanh toán & Vận chuyển
                                    </h6>
                                    <p class="mb-2">
                                        <i class="fas fa-wallet me-2"></i>
                                        <strong>PTTT:</strong> <?= PAYMENT_METHODS[$order['payment_method']] ?? 'N/A' ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-truck me-2"></i>
                                        <strong>PTVC:</strong> <?= $order['shipping_method'] ?? 'Tiêu chuẩn' ?>
                                    </p>
                                    <?php if ($order['discount_amount'] > 0): ?>
                                    <p class="mb-2 text-success">
                                        <i class="fas fa-tags me-2"></i>
                                        <strong>Mã giảm giá:</strong><br>
                                        <?php if (!empty($order['promotion_code'])): ?>
                                            <?= sanitize($order['promotion_code']) ?> 
                                        <?php endif; ?>
                                        <span class="text-success">(-<?= format_currency($order['discount_amount']) ?>)</span>
                                    </p>
                                    <?php endif; ?>
                                    <p class="mb-2">
                                        <i class="fas fa-calendar-plus me-2"></i>
                                        <strong>Ngày đặt:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-calendar-check me-2"></i>
                                        <strong>Cập nhật:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Status Update Section -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card fade-in" data-aos="fade-up" data-aos-delay="500">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Status Update Form -->
                                        <div class="col-md-6">
                                            <h6 class="mb-4">
                                                <i class="fas fa-edit me-2"></i>
                                                Cập nhật trạng thái đơn hàng
                                            </h6>
                                            <form id="updateStatusForm">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <div class="form-group">
                                                            <label class="form-label">Trạng thái mới</label>
                                                            <select class="form-select" name="status" id="orderStatus">
                                                                <?php foreach (ORDER_STATUS as $key => $label): ?>
                                                                    <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                                                                        <?= $label ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label class="form-label">&nbsp;</label>
                                                            <button type="submit" class="btn btn-primary w-100 btn-glow">
                                                                <i class="fas fa-save me-2"></i>
                                                                Cập nhật
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Ghi chú (không bắt buộc)</label>
                                                    <textarea class="form-control" name="note" rows="3" 
                                                              placeholder="Nhập ghi chú về việc thay đổi trạng thái..."></textarea>
                                                </div>
                                            </form>
                                        </div>

                                        <!-- Order Timeline -->
                                        <div class="col-md-6">
                                            <h6 class="mb-4">
                                                <i class="fas fa-history me-2"></i>
                                                Lịch sử đơn hàng
                                            </h6>
                                            <div class="timeline-container">
                                                <?php if (!empty($status_history)): ?>
                                                    <?php foreach ($status_history as $index => $history): ?>
                                                    <div class="timeline-item" data-aos="fade-left" data-aos-delay="<?= $index * 100 ?>">
                                                        <div class="timeline-marker">
                                                            <i class="fas fa-circle"></i>
                                                        </div>
                                                        <div class="timeline-content">
                                                            <div class="timeline-header">
                                                                <h6 class="timeline-title">
                                                                    <?= isset($history['status']) && isset(ORDER_STATUS[$history['status']]) 
                                                                        ? ORDER_STATUS[$history['status']] 
                                                                        : 'Trạng thái không xác định' ?>
                                                                </h6>
                                                                <span class="timeline-date">
                                                                    <?= isset($history['changed_at']) 
                                                                        ? date('d/m/Y H:i', strtotime($history['changed_at'])) 
                                                                        : 'N/A' ?>
                                                                </span>
                                                            </div>
                                                            <p class="timeline-description">
                                                                Bởi: <strong><?= isset($history['changed_by_name']) && $history['changed_by_name']
                                                                    ? sanitize($history['changed_by_name']) 
                                                                    : 'Hệ thống' ?></strong>
                                                                <?php if (isset($history['note']) && !empty($history['note'])): ?>
                                                                    <br><em><?= sanitize($history['note']) ?></em>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="timeline-item">
                                                        <div class="timeline-marker">
                                                            <i class="fas fa-info-circle"></i>
                                                        </div>
                                                        <div class="timeline-content">
                                                            <div class="timeline-header">
                                                                <h6 class="timeline-title">Chưa có lịch sử</h6>
                                                                <span class="timeline-date"><?= date('d/m/Y H:i') ?></span>
                                                            </div>
                                                            <p class="timeline-description">
                                                                Đơn hàng chưa có thay đổi trạng thái nào.
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Invoice Template -->
<div id="printTemplate" style="display: none;">
    <div style="max-width: 800px; margin: 0 auto; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6;">
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 30px; border-bottom: 3px solid #007bff; padding-bottom: 20px;">
            <h1 style="color: #007bff; margin-bottom: 10px; font-size: 28px; font-weight: bold;">HÓA ĐƠN BÁN HÀNG</h1>
            <p style="font-size: 18px; margin: 5px 0; color: #333;">Mã đơn hàng: <strong>#<?= $order_id ?></strong></p>
            <p style="margin: 5px 0; color: #666;">Ngày: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
        </div>

        <!-- Customer & Address Info -->
        <div style="margin-bottom: 30px; overflow: hidden;">
            <div style="float: left; width: 48%; box-sizing: border-box;">
                <h3 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-bottom: 15px; font-size: 16px;">Thông tin người mua</h3>
                <p style="margin: 8px 0; color: #333;"><strong>Họ tên:</strong> <?= sanitize($order['full_name']) ?></p>
                <p style="margin: 8px 0; color: #333;"><strong>SĐT:</strong> <?= $order['phone'] ?></p>
                <p style="margin: 8px 0; color: #333;"><strong>Email:</strong> <?= sanitize($order['email']) ?></p>
            </div>
            <div style="float: right; width: 48%; box-sizing: border-box;">
                <h3 style="color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-bottom: 15px; font-size: 16px;">Địa chỉ giao hàng</h3>
                <p style="margin: 8px 0; color: #333;"><strong>Người nhận:</strong> <?= sanitize($order['recipient_name']) ?></p>
                <p style="margin: 8px 0; color: #333;"><strong>SĐT:</strong> <?= $order['recipient_phone'] ?></p>
                <p style="margin: 8px 0; color: #333;"><strong>Địa chỉ:</strong> <?= sanitize($order['address_line']) ?></p>
                <p style="margin: 8px 0; color: #333;"><?= sanitize($order['ward']) ?>, <?= sanitize($order['district']) ?>, <?= sanitize($order['city']) ?></p>
            </div>
            <div style="clear: both;"></div>
        </div>

        <!-- Payment & Shipping Info -->
        <div style="margin-bottom: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
            <div style="overflow: hidden;">
                <div style="float: left; width: 48%;">
                    <h4 style="color: #333; margin: 0 0 10px 0; font-size: 14px;">Thông tin thanh toán</h4>
                    <p style="margin: 5px 0; color: #333;"><strong>Phương thức:</strong> <?= PAYMENT_METHODS[$order['payment_method']] ?? 'N/A' ?></p>
                    <p style="margin: 5px 0;"><strong>Trạng thái:</strong> <span style="color: #007bff; font-weight: bold;"><?= ORDER_STATUS[$order['status']] ?></span></p>
                </div>
                <div style="float: right; width: 48%;">
                    <h4 style="color: #333; margin: 0 0 10px 0; font-size: 14px;">Thông tin vận chuyển</h4>
                    <p style="margin: 5px 0; color: #333;"><strong>Phương thức:</strong> <?= $order['shipping_method'] ?? 'Tiêu chuẩn' ?></p>
                    <p style="margin: 5px 0; color: #333;"><strong>Cập nhật:</strong> <?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></p>
                </div>
                <div style="clear: both;"></div>
            </div>
        </div>

        <!-- Products Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background: linear-gradient(135deg, #007bff, #0056b3); color: white;">
                    <th style="border: 1px solid #ddd; padding: 15px; text-align: left; font-weight: bold;">Sản phẩm</th>
                    <th style="border: 1px solid #ddd; padding: 15px; text-align: right; font-weight: bold; width: 120px;">Đơn giá</th>
                    <th style="border: 1px solid #ddd; padding: 15px; text-align: center; font-weight: bold; width: 80px;">SL</th>
                    <th style="border: 1px solid #ddd; padding: 15px; text-align: right; font-weight: bold; width: 120px;">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="border: 1px solid #ddd; padding: 12px; vertical-align: top;">
                        <strong style="color: #333; font-size: 14px;"><?= sanitize($item['name']) ?></strong>
                        <?php if ($item['attribute_details']): ?>
                            <br><small style="color: #666; font-size: 12px;"><?= sanitize($item['attribute_details']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 12px; text-align: right; white-space: nowrap;">
                        <?= format_currency($item['price']) ?>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 12px; text-align: center;">
                        <?= $item['quantity'] ?>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 12px; text-align: right; white-space: nowrap;">
                        <?= format_currency($item['price'] * $item['quantity']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php if ($order['discount_amount'] > 0): ?>
                <tr>
                    <td colspan="3" style="border: 1px solid #ddd; padding: 12px; text-align: right; font-weight: bold;">
                        Tạm tính:
                    </td>
                    <td style="border: 1px solid #ddd; padding: 12px; text-align: right; font-weight: bold; white-space: nowrap;">
                        <?= format_currency($order['total_amount']) ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" style="border: 1px solid #ddd; padding: 12px; text-align: right; font-weight: bold; color: #28a745;">
                        Mã giảm giá<?php if (!empty($order['promotion_code'])): ?> (<?= sanitize($order['promotion_code']) ?>)<?php endif; ?>:
                    </td>
                    <td style="border: 1px solid #ddd; padding: 12px; text-align: right; font-weight: bold; color: #28a745; white-space: nowrap;">
                        -<?= format_currency($order['discount_amount']) ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr style="background-color: #f8f9fa;">
                    <td colspan="3" style="border: 1px solid #ddd; padding: 15px; text-align: right; font-weight: bold; font-size: 16px;">
                        TỔNG CỘNG:
                    </td>
                    <td style="border: 1px solid #ddd; padding: 15px; text-align: right; font-weight: bold; font-size: 16px; color: #007bff; white-space: nowrap;">
                        <?= format_currency($order['total_amount'] - $order['discount_amount']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 50px;">
            <div style="display: inline-block; width: 45%; text-align: center;">
                <p style="font-weight: bold;">Người mua hàng</p>
                <p style="margin-top: 80px; border-top: 1px solid #333; padding-top: 5px;">(Ký, ghi rõ họ tên)</p>
            </div>
            <div style="display: inline-block; width: 45%; text-align: center; margin-left: 10%;">
                <p style="font-weight: bold;">Người bán hàng</p>
                <p style="margin-top: 80px; border-top: 1px solid #333; padding-top: 5px;">(Ký, ghi rõ họ tên)</p>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            <p>Cảm ơn quý khách đã mua hàng!</p>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white;">
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p style="margin-top: 15px; font-size: 16px;">Đang cập nhật trạng thái...</p>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle me-2"></i>
                    Xác nhận cập nhật
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                </div>
                <h6 class="mb-3">Bạn có chắc chắn muốn cập nhật trạng thái đơn hàng?</h6>
                <p class="text-muted mb-0">Thao tác này sẽ thay đổi trạng thái đơn hàng và được ghi lại trong lịch sử.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Hủy
                </button>
                <button type="button" class="btn btn-primary" id="confirmUpdate">
                    <i class="fas fa-check me-1"></i>Xác nhận
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2"></i>
                <span id="toastMessage">Cập nhật trạng thái thành công!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- AOS Animation Library -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
// Initialize AOS
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({
        duration: 800,
        easing: 'ease-in-out-sine',
        once: true,
        offset: 100
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Form submission handling
    const updateStatusForm = document.getElementById('updateStatusForm');
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const successToast = new bootstrap.Toast(document.getElementById('successToast'));
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    let pendingFormData = null;

    updateStatusForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        pendingFormData = formData;
        
        // Show confirmation modal
        confirmModal.show();
    });

    // Confirm update handler
    document.getElementById('confirmUpdate').addEventListener('click', function() {
        if (pendingFormData) {
            confirmModal.hide();
            updateOrderStatus(pendingFormData);
        }
    });

    function updateOrderStatus(formData) {
        // Show loading overlay
        loadingOverlay.style.display = 'block';

        // Add order_id to form data
        formData.append('order_id', <?= $order_id ?>);

        fetch('orders_update_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loadingOverlay.style.display = 'none';
            
            if (data.success) {
                // Show success toast
                document.getElementById('toastMessage').textContent = data.message || 'Cập nhật trạng thái thành công!';
                successToast.show();
                
                // Reload page after short delay to show the toast
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showErrorAlert(data.message || 'Có lỗi xảy ra khi cập nhật trạng thái.');
            }
        })
        .catch(error => {
            loadingOverlay.style.display = 'none';
            console.error('Error:', error);
            showErrorAlert('Có lỗi xảy ra khi cập nhật trạng thái.');
        });
    }

    function showErrorAlert(message) {
        // Create and show error alert
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
});

// Print function with enhanced styling
function printOrder() {
    const printContent = document.getElementById('printTemplate').innerHTML;
    const originalContent = document.body.innerHTML;

    // Apply print styles
    const printStyles = `
        <style>
            @media print {
                body { margin: 0; padding: 20px; }
                * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
            }
        </style>
    `;

    document.head.innerHTML += printStyles;
    document.body.innerHTML = printContent;
    
    window.print();
    
    // Restore original content
    document.body.innerHTML = originalContent;
    location.reload(); // Reload to restore all functionality
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

// Add loading state to buttons on form submission
document.addEventListener('submit', function(e) {
    const submitButton = e.target.querySelector('button[type="submit"]');
    if (submitButton) {
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
        submitButton.disabled = true;
        
        // Restore button state after 3 seconds (fallback)
        setTimeout(() => {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }, 3000);
    }
});
</script>

<?php include '../includes/footer.php'; ?>