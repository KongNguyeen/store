<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_products.css">
    
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="main-container animate__animated animate__fadeInUp">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-boxes me-3"></i>Quản lý sản phẩm</h1>
                    <p class="subtitle">Quản lý toàn bộ sản phẩm trong hệ thống</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="window.location.href='index.php'" title="Quay lại trang chủ">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                    </button>
                    <button class="btn btn-add-product" onclick="window.location.href='products_add.php'">
                        <i class="fas fa-plus me-2"></i>Thêm sản phẩm
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="p-4">
            <div class="row mb-4">
                <?php
                // Đảm bảo $pdo đã được khởi tạo
                require_once '../config/config.php';
                require_once '../config/functions.php';
                if (!isset($pdo) || !$pdo) {
                    $pdo = getPDO();
                }
                // Thống kê sản phẩm
                $total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
                $active_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
                $inactive_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'inactive'")->fetchColumn();
                $low_stock_products = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10 AND status = 'active'")->fetchColumn();
                ?>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, var(--info-color), #2563eb);">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?= number_format($total_products) ?></h3>
                        <p class="text-muted mb-0">Tổng sản phẩm</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, var(--success-color), #059669);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?= number_format($active_products) ?></h3>
                        <p class="text-muted mb-0">Đang bán</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, var(--warning-color), #d97706);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?= number_format($low_stock_products) ?></h3>
                        <p class="text-muted mb-0">Sắp hết hàng</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-icon" style="background: linear-gradient(135deg, var(--danger-color), #dc2626);">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-1"><?= number_format($inactive_products) ?></h3>
                        <p class="text-muted mb-0">Ngừng bán</p>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <div class="alert alert-success animate__animated animate__fadeIn" style="display: none;" id="successAlert">
                <i class="fas fa-check-circle me-2"></i>
                <span id="successMessage"></span>
            </div>
            <div class="alert alert-danger animate__animated animate__fadeIn" style="display: none;" id="errorAlert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span id="errorMessage"></span>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="get" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <div class="position-relative">
                            <i class="fas fa-search position-absolute" style="left: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af;"></i>
                            <input type="text" class="form-control ps-5" name="search" placeholder="Tìm kiếm sản phẩm..." value="<?= htmlspecialchars($search ?? '') ?>" id="searchInput">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="category_id" id="categorySelect">
                            <option value="">Tất cả danh mục</option>
                            <?php
                            $categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($categories as $cat):
                            ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($category_id ?? '') == $cat['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="supplier_id" id="supplierSelect">
                            <option value="">Tất cả nhà cung cấp</option>
                            <?php
                            $suppliers = $pdo->query("SELECT supplier_id, name FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($suppliers as $sup):
                            ?>
                                <option value="<?= $sup['supplier_id'] ?>" <?= ($supplier_id ?? '') == $sup['supplier_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sup['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status" id="statusSelect">
                            <option value="">Tất cả trạng thái</option>
                            <?php
                            $statusList = [
                                'active' => 'Đang bán',
                                'inactive' => 'Ngừng bán'
                            ];
                            foreach ($statusList as $key => $label):
                            ?>
                                <option value="<?= $key ?>" <?= ($status ?? '') == $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i>Tìm kiếm
                        </button>
                        <button type="button" class="btn btn-secondary" id="resetBtn">
                            <i class="fas fa-sync me-2"></i>Reset
                        </button>
                    </div>
                </form>
                
                <!-- Export Buttons -->
                <div class="mt-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-success me-2" id="exportExcelBtn">
                        <i class="fas fa-file-excel me-2"></i>Xuất Excel
                    </button>
                    <button type="button" class="btn btn-info" id="printBtn">
                        <i class="fas fa-print me-2"></i>In trang
                    </button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="table-container">
                <table class="table" id="productsTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-image me-2"></i>Sản phẩm</th>
                            <th><i class="fas fa-dollar-sign me-2"></i>Giá</th>
                            <th class="text-center"><i class="fas fa-warehouse me-2"></i>Kho</th>
                            <th class="text-center"><i class="fas fa-tags me-2"></i>Danh mục</th>
                            <th class="text-center"><i class="fas fa-truck me-2"></i>Nhà cung cấp</th>
                            <th class="text-center"><i class="fas fa-toggle-on me-2"></i>Trạng thái</th>
                            <th><i class="fas fa-cogs me-2"></i>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody">
                        <?php
                        // Kết nối và lấy danh sách sản phẩm
                        require_once '../config/config.php';
                        require_once '../config/functions.php';
                        $pdo = getPDO();
                        
                        // Xử lý tham số tìm kiếm và lọc
                        $search = $_GET['search'] ?? '';
                        $category_id = $_GET['category_id'] ?? '';
                        $supplier_id = $_GET['supplier_id'] ?? '';
                        $status = $_GET['status'] ?? '';
                        
                        // Xây dựng SQL query với điều kiện
                        $sql = "SELECT p.*, c.name as category_name, s.name as supplier_name,
                            (SELECT image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as product_image
                            FROM products p
                            JOIN categories c ON p.category_id = c.category_id
                            JOIN suppliers s ON p.supplier_id = s.supplier_id";
                        
                        $conditions = [];
                        $params = [];
                        
                        // Thêm điều kiện tìm kiếm theo tên
                        if (!empty($search)) {
                            $conditions[] = "p.name LIKE ?";
                            $params[] = "%$search%";
                        }
                        
                        // Thêm điều kiện lọc theo danh mục
                        if (!empty($category_id)) {
                            $conditions[] = "p.category_id = ?";
                            $params[] = $category_id;
                        }
                        
                        // Thêm điều kiện lọc theo nhà cung cấp
                        if (!empty($supplier_id)) {
                            $conditions[] = "p.supplier_id = ?";
                            $params[] = $supplier_id;
                        }
                        
                        // Thêm điều kiện lọc theo trạng thái
                        if (!empty($status)) {
                            $conditions[] = "p.status = ?";
                            $params[] = $status;
                        }
                        
                        // Nối các điều kiện vào SQL
                        if (!empty($conditions)) {
                            $sql .= " WHERE " . implode(" AND ", $conditions);
                        }
                        
                        $sql .= " ORDER BY p.created_at DESC";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Hiển thị thông báo kết quả tìm kiếm nếu có filter
                        if (!empty($search) || !empty($category_id) || !empty($supplier_id) || !empty($status)):
                        ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info animate__animated animate__fadeIn mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Kết quả tìm kiếm: <strong><?= count($products) ?></strong> sản phẩm
                    <?php if (!empty($search)): ?>
                        cho từ khóa "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php endif; ?>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="fas fa-times me-1"></i> Xóa bộ lọc
                    </a>
                </div>
                <div class="table-container">
                    <table class="table" id="productsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-image me-2"></i>Sản phẩm</th>
                                <th><i class="fas fa-dollar-sign me-2"></i>Giá</th>
                                <th class="text-center"><i class="fas fa-warehouse me-2"></i>Kho</th>
                                <th class="text-center"><i class="fas fa-tags me-2"></i>Danh mục</th>
                                <th class="text-center"><i class="fas fa-truck me-2"></i>Nhà cung cấp</th>
                                <th class="text-center"><i class="fas fa-toggle-on me-2"></i>Trạng thái</th>
                                <th><i class="fas fa-cogs me-2"></i>Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                        <?php endif; ?>
                        
                        <?php
                        foreach ($products as $index => $product):
                        ?>
                        <tr class="animate__animated animate__fadeInUp" style="animation-delay: <?= $index * 0.1 ?>s;" data-id="<?= $product['product_id'] ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    // Check if product_image exists and prepare the correct path
                                    $imagePath = 'assets/img/no-image.png';
                                    if (!empty($product['product_image'])) {
                                        // Handle both absolute URLs and relative paths
                                        if (strpos($product['product_image'], 'http') === 0) {
                                            // It's already a full URL
                                            $imagePath = $product['product_image'];
                                        } else {
                                            // It's a relative path - ensure it's relative to the root
                                            $imagePath = '../' . ltrim($product['product_image'], '/');
                                        }
                                    }
                                    ?>
                                     <img src="<?= htmlspecialchars($imagePath) ?>"
                                         class="product-image me-3" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy" decoding="async">
                                    <div>
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($product['name']) ?></h6>
                                        <p class="text-muted mb-0 small">ID: #<?= $product['product_id'] ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-primary">
                                    <?= number_format($product['price'], 0, ',', '.') ?>₫
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $product['stock'] <= 5 ? 'bg-danger' : ($product['stock'] <= 20 ? 'bg-warning' : 'bg-success') ?>">
                                    <?= $product['stock'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="text-muted"><?= htmlspecialchars($product['category_name']) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="text-muted"><?= htmlspecialchars($product['supplier_name']) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="status-badge <?= $product['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                                    <?= $product['status'] == 'active' ? 'Đang bán' : 'Ngừng bán' ?>
                                </span>
                            </td>
                            <td>
                                <a href="products_edit.php?id=<?= $product['product_id'] ?>" class="action-btn action-edit">
                                    <i class="fas fa-edit"></i> Sửa
                                </a>
                                <a href="#" class="action-btn action-delete delete-product"
                                   data-id="<?= $product['product_id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>">
                                    <i class="fas fa-trash"></i> Xóa
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content animate__animated animate__zoomIn border-0 shadow-lg">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-exclamation-triangle me-2 animate__animated animate__shakeX animate__infinite"></i>
                        Xác nhận xóa sản phẩm
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-4">
                        <div class="delete-icon-container">
                            <i class="fas fa-trash-alt text-danger animate__animated animate__bounceIn" style="font-size: 5rem;"></i>
                            <div class="warning-pulse"></div>
                        </div>
                    </div>
                    <h4 class="text-danger fw-bold mb-3">Bạn có chắc chắn muốn xóa?</h4>
                    <div class="alert alert-warning border-0 bg-light">
                        <p class="mb-2">
                            <i class="fas fa-info-circle text-warning me-2"></i>
                            Sản phẩm <strong id="productName" class="text-danger"></strong> sẽ bị xóa vĩnh viễn
                        </p>
                        <p class="text-muted mb-0 small">
                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                            Hành động này không thể hoàn tác!
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-light btn-lg px-4 me-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Hủy bỏ
                    </button>
                    <button type="button" class="btn btn-danger btn-lg px-4" id="confirmDelete">
                        <i class="fas fa-trash me-2"></i>
                        <span class="delete-text">Xóa ngay</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content animate__animated animate__bounceIn">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="text-success">Thành công!</h5>
                    <p class="text-muted mb-3" id="successModalMessage">Thao tác đã được thực hiện thành công.</p>
                    <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>Đồng ý
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js" defer></script>
    
    <!-- SheetJS (xlsx) for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" defer></script>
    
    <!-- html2canvas and jsPDF for better printing/PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>

    <script>
        // Lấy CSRF token từ PHP và gán vào window
        window.csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';
        // Global variables
        let deleteModal, successModal;
        let productIdToDelete = null;
        let currentTheme = 'light';

        // Initialize on DOM load
        document.addEventListener('DOMContentLoaded', function() {
            initializeComponents();
            setupEventListeners();
            initializeAnimations();
            setupExportPrint();
        });

        // Initialize Bootstrap components
        function initializeComponents() {
            deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            successModal = new bootstrap.Modal(document.getElementById('successModal'));
            
            // Thêm event listener cho modal events
            const deleteModalElement = document.getElementById('deleteModal');
            deleteModalElement.addEventListener('shown.bs.modal', function () {
                // Focus vào nút hủy khi modal hiển thị để dễ dàng ESC
                document.querySelector('[data-bs-dismiss="modal"]').focus();
            });
            
            deleteModalElement.addEventListener('hidden.bs.modal', function () {
                // Reset modal content khi đóng
                const modal = this;
                modal.querySelector('.modal-content').classList.remove('animate__zoomIn');
                const confirmBtn = document.getElementById('confirmDelete');
                const deleteText = confirmBtn.querySelector('.delete-text');
                const spinner = confirmBtn.querySelector('.spinner-border');
                
                confirmBtn.disabled = false;
                deleteText.textContent = 'Xóa ngay';
                spinner.classList.add('d-none');
            });
        }

        // Setup all event listeners
        function setupEventListeners() {
            setupDeleteHandlers();
            setupSearchHandlers();
            setupFilterHandlers();
            setupTableInteractions();
        }

        // Delete product handlers
        // Hàm xóa sản phẩm
        function deleteProduct(productId, productName) {
            // Hiển thị modal xác nhận
            document.getElementById('productName').textContent = `"${productName}"`;
            
            // Thêm hiệu ứng rung (nếu thiết bị hỗ trợ)
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            }
            
            // Thêm hiệu ứng animation cho modal
            const modal = document.getElementById('deleteModal');
            modal.querySelector('.modal-content').classList.add('animate__animated', 'animate__zoomIn');
            
            deleteModal.show();
            
            // Xử lý khi nhấn xác nhận xóa
            const confirmBtn = document.getElementById('confirmDelete');
            const deleteText = confirmBtn.querySelector('.delete-text');
            const spinner = confirmBtn.querySelector('.spinner-border');
            
            // Remove previous event listeners
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            
            newConfirmBtn.addEventListener('click', function() {
                // Disable button và hiển thị loading
                newConfirmBtn.disabled = true;
                deleteText.textContent = 'Đang xóa...';
                spinner.classList.remove('d-none');
                
                // Thêm hiệu ứng rung khi bắt đầu xóa
                if (navigator.vibrate) {
                    navigator.vibrate(200);
                }
                
                // Gửi request xóa
                fetch('products_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${encodeURIComponent(productId)}&csrf_token=${encodeURIComponent(window.csrfToken || '')}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Đóng modal
                        deleteModal.hide();
                        
                        // Hiển thị thông báo thành công với countdown
                        showSuccessMessage('Sản phẩm đã được xóa thành công! Đang cập nhật dữ liệu...');
                        
                        // Reload trang sau 1.5 giây để cập nhật thống kê, giữ lại các tham số tìm kiếm
                        setTimeout(() => {
                            // Giữ lại các tham số URL hiện tại
                            const currentUrl = new URL(window.location);
                            window.location.href = currentUrl.toString();
                        }, 1500);
                    } else {
                        // Đóng modal và hiển thị lỗi
                        deleteModal.hide();
                        
                        let debugInfo = '';
                        if (data.debug) {
                            debugInfo = '\n[Debug] product_id: ' + data.debug.product_id +
                                        ', csrf_token: ' + data.debug.csrf_token +
                                        ', session_csrf: ' + data.debug.session_csrf;
                        }
                        showErrorMessage((data.message || 'Có lỗi xảy ra khi xóa sản phẩm.') + debugInfo);
                    }
                })
                .catch(() => {
                    deleteModal.hide();
                    showErrorMessage('Có lỗi xảy ra khi xóa sản phẩm.');
                })
                .finally(() => {
                    // Reset button state
                    newConfirmBtn.disabled = false;
                    deleteText.textContent = 'Xóa ngay';
                    spinner.classList.add('d-none');
                });
            });
        }

        // Gán sự kiện cho nút xóa
        function setupDeleteHandlers() {
            document.querySelectorAll('.delete-product').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const productId = this.dataset.id;
                    const productName = this.dataset.name;
                    deleteProduct(productId, productName);
                });
            });
        }

        // Search functionality - Realtime search có thể gây lag nên chỉ submit form
        function setupSearchHandlers() {
            const searchInput = document.getElementById('searchInput');
            const filterForm = document.getElementById('filterForm');
            let searchTimeout;

            // Tự động submit form khi người dùng ngừng gõ 1 giây
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                // Add loading class to input
                this.classList.add('animate__animated', 'animate__pulse');
                
                searchTimeout = setTimeout(() => {
                    this.classList.remove('animate__animated', 'animate__pulse');
                    filterForm.submit(); // Submit form để server xử lý
                }, 1000);
            });
        }

        // Filter handlers
        function setupFilterHandlers() {
            const filterForm = document.getElementById('filterForm');
            const resetBtn = document.getElementById('resetBtn');

            // Reset button - redirect về trang không có parameter
            resetBtn.addEventListener('click', function() {
                window.location.href = window.location.pathname;
            });

            // Auto submit khi thay đổi select
            ['categorySelect', 'supplierSelect', 'statusSelect'].forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    select.addEventListener('change', function() {
                        this.classList.add('animate__animated', 'animate__pulse');
                        setTimeout(() => {
                            this.classList.remove('animate__animated', 'animate__pulse');
                        }, 500);
                        filterForm.submit();
                    });
                }
            });
        }

        // Table interaction handlers
        function setupTableInteractions() {
            const tableRows = document.querySelectorAll('#productsTableBody tr');
            
            tableRows.forEach(row => {
                // Row hover effects
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(10px)';
                    this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.boxShadow = 'none';
                });

                // Product image hover
                const productImage = row.querySelector('.product-image');
                if (productImage) {
                    productImage.addEventListener('click', function() {
                        showImagePreview(this.src, this.alt);
                    });
                }
            });

            // Action button hover effects
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.classList.add('animate__animated', 'animate__pulse');
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.classList.remove('animate__animated', 'animate__pulse');
                });
            });
        }

        // Show image preview modal
        function showImagePreview(src, alt) {
            // Create and show image preview modal
            const imageModal = document.createElement('div');
            imageModal.className = 'modal fade';
            imageModal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${alt}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${src}" class="img-fluid rounded" alt="${alt}">
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(imageModal);
            const modal = new bootstrap.Modal(imageModal);
            modal.show();
            
            // Remove modal after hide
            imageModal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(imageModal);
            });
        }

        // Animation initialization
        function initializeAnimations() {
            // Stagger table row animations
            const tableRows = document.querySelectorAll('#productsTableBody tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.1}s`;
            });

            // Stats cards animation
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.classList.add('animate__animated', 'animate__fadeInUp');
                card.style.animationDelay = `${index * 0.2}s`;
            });

            // Counter animation for stats
            animateCounters();
        }

        // Animate counter numbers
        function animateCounters() {
            const counters = document.querySelectorAll('.stats-card h3');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                const increment = target / 100;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 20);
            });
        }

        // Loading overlay functions
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }

        // Success message function
        function showSuccessMessage(message) {
            const successAlert = document.getElementById('successAlert');
            const successMessage = document.getElementById('successMessage');
            
            successMessage.textContent = message;
            successAlert.style.display = 'block';
            successAlert.classList.add('animate__animated', 'animate__fadeInDown');
            
            setTimeout(() => {
                successAlert.classList.add('animate__fadeOutUp');
                setTimeout(() => {
                    successAlert.style.display = 'none';
                    successAlert.classList.remove('animate__animated', 'animate__fadeInDown', 'animate__fadeOutUp');
                }, 500);
            }, 3000);
        }

        // Error message function
        function showErrorMessage(message) {
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');
            
            errorMessage.textContent = message;
            errorAlert.style.display = 'block';
            errorAlert.classList.add('animate__animated', 'animate__fadeInDown');
            
            setTimeout(() => {
                errorAlert.classList.add('animate__fadeOutUp');
                setTimeout(() => {
                    errorAlert.style.display = 'none';
                    errorAlert.classList.remove('animate__animated', 'animate__fadeInDown', 'animate__fadeOutUp');
                }, 500);
            }, 5000);
        }

        // Refresh table with animation
        function refreshTable() {
            const tableBody = document.getElementById('productsTableBody');
            tableBody.classList.add('animate__animated', 'animate__fadeOut');
            
            setTimeout(() => {
                // Here you would typically fetch new data from server
                tableBody.classList.remove('animate__fadeOut');
                tableBody.classList.add('animate__fadeIn');
                
                setTimeout(() => {
                    tableBody.classList.remove('animate__animated', 'animate__fadeIn');
                }, 1000);
            }, 500);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search focus
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            
            // ESC to close modals
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                });
            }
        });

        // Auto-save filters to localStorage
        function saveFilters() {
            const filters = {
                search: document.getElementById('searchInput').value,
                category: document.getElementById('categorySelect').value,
                supplier: document.getElementById('supplierSelect').value,
                status: document.getElementById('statusSelect').value
            };
            localStorage.setItem('productFilters', JSON.stringify(filters));
        }

        // Load filters from localStorage
        function loadFilters() {
            const savedFilters = localStorage.getItem('productFilters');
            if (savedFilters) {
                const filters = JSON.parse(savedFilters);
                document.getElementById('searchInput').value = filters.search || '';
                document.getElementById('categorySelect').value = filters.category || '';
                document.getElementById('supplierSelect').value = filters.supplier || '';
                document.getElementById('statusSelect').value = filters.status || '';
            }
        }

        // Load theme preference
        function loadTheme() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                toggleTheme();
            }
        }

        // Initialize everything when page loads
        window.addEventListener('load', function() {
            loadTheme();
            loadFilters();
            
            // Add entrance animation to main container
            document.querySelector('.main-container').classList.add('animate__animated', 'animate__fadeInUp');
            
            // Show page load complete message
            setTimeout(() => {
                showSuccessMessage('Trang quản lý sản phẩm đã tải thành công!');
            }, 1000);
        });

        // Save filters when page unloads
        window.addEventListener('beforeunload', function() {
            saveFilters();
        });

        // Handle online/offline status
        window.addEventListener('online', function() {
            showSuccessMessage('Đã kết nối internet trở lại!');
        });

        window.addEventListener('offline', function() {
            showErrorMessage('Mất kết nối internet. Một số tính năng có thể không hoạt động.');
        });

        // Setup Export to Excel and Print functionality
        function setupExportPrint() {
            // Export to Excel Button
            const exportExcelBtn = document.getElementById('exportExcelBtn');
            if (exportExcelBtn) {
                exportExcelBtn.addEventListener('click', exportToExcel);
            }
            
            // Print Button
            const printBtn = document.getElementById('printBtn');
            if (printBtn) {
                printBtn.addEventListener('click', printPage);
            }
        }
        
        // Export table data to Excel
        function exportToExcel() {
            showLoading();
            
            try {
                // Get current timestamp for filename
                const date = new Date();
                const timestamp = date.toISOString().replace(/[:.]/g, '-').substring(0, 19);
                const fileName = `products_export_${timestamp}.xlsx`;
                
                // Create workbook and worksheet
                const wb = XLSX.utils.book_new();
                
                // Get all product data from table
                const table = document.getElementById('productsTable');
                const rows = Array.from(table.querySelectorAll('tbody tr'));
                
                // Prepare header row
                const headers = [
                    'ID', 
                    'Tên Sản Phẩm', 
                    'Giá', 
                    'Kho', 
                    'Danh Mục', 
                    'Nhà Cung Cấp', 
                    'Trạng Thái'
                ];
                
                // Extract data from each row
                const data = [headers];
                
                rows.forEach(row => {
                    // Extract product ID from data-id attribute
                    const productId = row.getAttribute('data-id');
                    
                    // Extract product name from the first cell
                    const nameCell = row.querySelector('td:nth-child(1)');
                    const productName = nameCell.querySelector('h6').textContent.trim();
                    
                    // Extract price from the second cell (remove currency symbol and formatting)
                    const priceCell = row.querySelector('td:nth-child(2)');
                    const priceText = priceCell.textContent.trim();
                    const price = priceText.replace(/[^\d]/g, '');
                    
                    // Extract stock from the third cell
                    const stockCell = row.querySelector('td:nth-child(3)');
                    const stock = stockCell.querySelector('.badge').textContent.trim();
                    
                    // Extract category from the fourth cell
                    const categoryCell = row.querySelector('td:nth-child(4)');
                    const category = categoryCell.textContent.trim();
                    
                    // Extract supplier from the fifth cell
                    const supplierCell = row.querySelector('td:nth-child(5)');
                    const supplier = supplierCell.textContent.trim();
                    
                    // Extract status from the sixth cell
                    const statusCell = row.querySelector('td:nth-child(6)');
                    const status = statusCell.textContent.trim();
                    
                    data.push([
                        productId,
                        productName,
                        price,
                        stock,
                        category,
                        supplier,
                        status
                    ]);
                });
                
                // Create worksheet
                const ws = XLSX.utils.aoa_to_sheet(data);
                
                // Set column widths
                const colWidths = [
                    { wch: 10 },  // ID
                    { wch: 40 },  // Tên Sản Phẩm
                    { wch: 15 },  // Giá
                    { wch: 10 },  // Kho
                    { wch: 20 },  // Danh Mục
                    { wch: 25 },  // Nhà Cung Cấp
                    { wch: 15 },  // Trạng Thái
                ];
                
                ws['!cols'] = colWidths;
                
                // Add the worksheet to the workbook
                XLSX.utils.book_append_sheet(wb, ws, 'Danh sách sản phẩm');
                
                // Save the file
                XLSX.writeFile(wb, fileName);
                
                // Show success message
                showSuccessMessage('Xuất Excel thành công!');
            } catch (error) {
                console.error('Export error:', error);
                showErrorMessage('Có lỗi xảy ra khi xuất Excel: ' + error.message);
            } finally {
                hideLoading();
            }
        }
        
        // Print page
        function printPage() {
            try {
                // Show loading
                showLoading();
                
                // Create a clone of the products table to modify for printing
                const productTable = document.getElementById('productsTable');
                const cloneTable = productTable.cloneNode(true);
                
                // Remove action column and other unnecessary elements
                const actionCells = cloneTable.querySelectorAll('th:last-child, td:last-child');
                actionCells.forEach(cell => cell.remove());
                
                // Remove image column (first column containing product images)
                const headerRow = cloneTable.querySelector('thead tr');
                const firstHeader = headerRow.querySelector('th:first-child');
                
                // Replace first header with just the product name header
                if (firstHeader) {
                    firstHeader.innerHTML = '<i class="fas fa-box me-2"></i>Sản phẩm';
                }
                
                // Modify each product row to remove image and just keep product name
                cloneTable.querySelectorAll('tbody tr').forEach(row => {
                    const firstCell = row.querySelector('td:first-child');
                    if (firstCell) {
                        const productInfo = firstCell.querySelector('div > div');
                        if (productInfo) {
                            firstCell.innerHTML = '';
                            firstCell.appendChild(productInfo);
                        }
                    }
                });
                
                // Get statistics data
                const statsCards = document.querySelectorAll('.stats-card');
                const statsData = Array.from(statsCards).map(card => {
                    const title = card.querySelector('p').textContent.trim();
                    const value = card.querySelector('h3').textContent.trim();
                    return { title, value };
                });
                
                // Get filter selections
                const categorySelect = document.getElementById('categorySelect');
                const supplierSelect = document.getElementById('supplierSelect');
                const statusSelect = document.getElementById('statusSelect');
                const searchInput = document.getElementById('searchInput');
                
                const filters = {
                    search: searchInput.value,
                    category: categorySelect.options[categorySelect.selectedIndex].text,
                    supplier: supplierSelect.options[supplierSelect.selectedIndex].text,
                    status: statusSelect.options[statusSelect.selectedIndex].text
                };
                
                // Create print window
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                
                // Create print content
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Danh sách sản phẩm - In</title>
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
                            .stats-container {
                                display: flex;
                                justify-content: space-between;
                                margin-bottom: 20px;
                            }
                            .stat-box {
                                border: 1px solid #ddd;
                                padding: 10px;
                                text-align: center;
                                width: 23%;
                            }
                            .stat-value {
                                font-size: 18px;
                                font-weight: bold;
                            }
                            .stat-title {
                                font-size: 12px;
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
                            <h1 class="print-title">Danh sách sản phẩm</h1>
                            <div class="print-date">Ngày in: ${new Date().toLocaleString('vi-VN')}</div>
                        </div>
                        
                        <div class="stats-container">
                            ${statsData.map(stat => `
                                <div class="stat-box">
                                    <div class="stat-value">${stat.value}</div>
                                    <div class="stat-title">${stat.title}</div>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="filters-container">
                            <div class="filter-item">
                                <div class="filter-label">Tìm kiếm:</div>
                                <div class="filter-value">${filters.search || 'Không có'}</div>
                            </div>
                            <div class="filter-item">
                                <div class="filter-label">Danh mục:</div>
                                <div class="filter-value">${filters.category}</div>
                            </div>
                            <div class="filter-item">
                                <div class="filter-label">Nhà cung cấp:</div>
                                <div class="filter-value">${filters.supplier}</div>
                            </div>
                            <div class="filter-item">
                                <div class="filter-label">Trạng thái:</div>
                                <div class="filter-value">${filters.status}</div>
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
                showSuccessMessage('Đã mở chế độ in trang!');
            } catch (error) {
                console.error('Print error:', error);
                hideLoading();
                showErrorMessage('Có lỗi xảy ra khi chuẩn bị in: ' + error.message);
            }
        }

        // Add CSS for dark theme
        const darkThemeCSS = `
            <style id="darkThemeCSS">
                .dark-theme {
                    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
                }
                
                .dark-theme .main-container {
                    background: rgba(45, 55, 72, 0.95);
                    color: #f7fafc;
                }
                
                .dark-theme .filter-section,
                .dark-theme .table-container,
                .dark-theme .stats-card {
                    background: #2d3748;
                    color: #f7fafc;
                }
                
                .dark-theme .form-control,
                .dark-theme .form-select {
                    background: #4a5568;
                    border-color: #4a5568;
                    color: #f7fafc;
                }
                
                .dark-theme .table tbody tr:hover {
                    background: #4a5568;
                }
                
                .dark-theme .modal-content {
                    background: #2d3748;
                    color: #f7fafc;
                }
                
                .dark-theme #exportExcelBtn {
                    background: linear-gradient(135deg, #1c6634, #15a87c);
                }
                
                .dark-theme #printBtn {
                    background: linear-gradient(135deg, #0d6c80, #0a97b5);
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', darkThemeCSS);
    </script>
</body>
</html>