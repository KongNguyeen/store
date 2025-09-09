<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();
$stmt = $pdo->query("SELECT * FROM promotions ORDER BY promotion_id DESC");
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khuyến mãi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/promotions.css">
    
        
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-tags"></i>
                        Quản lý khuyến mãi
                    </h1>
                </div>
                <div class="d-flex gap-3">
                    <button class="add-btn back-btn" onclick="window.location.href='index.php'" title="Quay lại trang chủ">
                        <i class="fas fa-arrow-left"></i>
                        Quay lại
                    </button>
                    <a href="promotions_add.php" class="add-btn">
                        <i class="fas fa-plus"></i>
                        Thêm mã khuyến mãi
                    </a>
                </div>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="totalPromotions">0</div>
                <div class="stat-label">Tổng khuyến mãi</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="activePromotions">0</div>
                <div class="stat-label">Đang hoạt động</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="expiredPromotions">0</div>
                <div class="stat-label">Đã hết hạn</div>
            </div>
        </div>

        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Tìm kiếm theo mã, mô tả...">
            </div>
            <button class="filter-btn active" data-filter="all">Tất cả</button>
            <button class="filter-btn" data-filter="active">Hoạt động</button>
            <button class="filter-btn" data-filter="inactive">Không hoạt động</button>
            <button class="filter-btn" data-filter="expired">Hết hạn</button>
        </div>

        <div class="loading">
            <div class="spinner"></div>
            <p>Đang tải dữ liệu...</p>
        </div>

        <div class="table-container">
            <table id="promotionsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã khuyến mãi</th>
                        <th>Mô tả</th>
                        <th>Giảm giá</th>
                        <th>Đơn tối thiểu</th>
                        <th>Ngày bắt đầu</th>
                        <th>Ngày kết thúc</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="promotionsTableBody">
                    <?php if (empty($promotions)): ?>
                    <tr>
                        <td colspan="9" class="no-data">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                            <br>Chưa có mã khuyến mãi nào
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($promotions as $p): ?>
                        <tr class="promotion-row fade-in" 
                            data-id="<?= $p['promotion_id'] ?>"
                            data-status="<?= $p['active'] ? 'active' : 'inactive' ?>"
                            data-expired="<?= (strtotime($p['end_date']) < time()) ? 'true' : 'false' ?>">
                            <td><?= $p['promotion_id'] ?></td>
                            <td>
                                <span class="promotion-code"><?= sanitize($p['code']) ?></span>
                            </td>
                            <td><?= sanitize($p['description']) ?></td>
                            <td>
                                <span class="discount-badge"><?= $p['discount_percent'] ?>%</span>
                            </td>
                            <td>
                                <span class="amount"><?= number_format($p['min_order_amount'], 0, ',', '.') ?>đ</span>
                            </td>
                            <td>
                                <span class="date"><?= date('d/m/Y', strtotime($p['start_date'])) ?></span>
                            </td>
                            <td>
                                <span class="date"><?= date('d/m/Y', strtotime($p['end_date'])) ?></span>
                            </td>
                            <td>
                                <?php
                                $isExpired = strtotime($p['end_date']) < time();
                                ?>
                                <div class="status-container">
                                    <?php if (!$isExpired): ?>
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               class="status-toggle" 
                                               data-id="<?= $p['promotion_id'] ?>"
                                               <?= $p['active'] ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                    <?php endif; ?>
                                    <span class="status-text <?= $isExpired ? 'expired' : ($p['active'] ? 'active' : 'inactive') ?>">
                                        <?= $isExpired ? 'Hết hạn' : ($p['active'] ? 'Kích hoạt' : 'Ẩn') ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="promotions_edit.php?id=<?= $p['promotion_id'] ?>" class="edit-btn">
                                        <i class="fas fa-edit"></i>
                                        Sửa
                                    </a>
                                    <button class="delete-btn" 
                                            data-id="<?= $p['promotion_id'] ?>"
                                            data-code="<?= sanitize($p['code']) ?>">
                                        <i class="fas fa-trash"></i>
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Xác nhận xóa</h3>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa mã khuyến mãi <strong id="deletePromotionCode"></strong>?</p>
                <p>Hành động này không thể hoàn tác.</p>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" id="cancelDelete">Hủy</button>
                <button class="btn-confirm" id="confirmDelete">Xóa</button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <div id="successMessage" class="success-message">
        <i class="fas fa-check-circle"></i>
        <span id="successText">Thao tác thành công!</span>
    </div>

    <script>
        class PromotionManager {
            constructor() {
                this.searchInput = document.getElementById('searchInput');
                this.filterButtons = document.querySelectorAll('.filter-btn');
                this.promotionRows = document.querySelectorAll('.promotion-row');
                this.tableBody = document.getElementById('promotionsTableBody');
                this.deleteModal = document.getElementById('deleteModal');
                this.successMessage = document.getElementById('successMessage');
                
                this.init();
                this.updateStats();
            }

            init() {
                // Search functionality
                this.searchInput.addEventListener('input', (e) => {
                    this.filterPromotions();
                });

                // Filter buttons
                this.filterButtons.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        this.setActiveFilter(e.target);
                        this.filterPromotions();
                    });
                });

                // Status toggle switches
                document.querySelectorAll('.status-toggle').forEach(toggle => {
                    toggle.addEventListener('change', (e) => {
                        this.togglePromotionStatus(e.target);
                    });
                });

                // Delete buttons
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        this.showDeleteModal(e.target);
                    });
                });

                // Modal events
                document.getElementById('cancelDelete').addEventListener('click', () => {
                    this.hideDeleteModal();
                });

                document.getElementById('confirmDelete').addEventListener('click', () => {
                    this.deletePromotion();
                });

                // Close modal when clicking outside
                this.deleteModal.addEventListener('click', (e) => {
                    if (e.target === this.deleteModal) {
                        this.hideDeleteModal();
                    }
                });

                // Add smooth scrolling and animations
                this.addAnimations();
            }

            setActiveFilter(activeBtn) {
                this.filterButtons.forEach(btn => btn.classList.remove('active'));
                activeBtn.classList.add('active');
            }

            filterPromotions() {
                const searchTerm = this.searchInput.value.toLowerCase();
                const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
                let visibleCount = 0;

                this.promotionRows.forEach(row => {
                    const code = row.querySelector('.promotion-code').textContent.toLowerCase();
                    const description = row.cells[2].textContent.toLowerCase();
                    const status = row.dataset.status;
                    const isExpired = row.dataset.expired === 'true';

                    // Search filter (chỉ tìm theo mã)
                    const matchesSearch = code.includes(searchTerm);

                    // Status filter
                    let matchesFilter = true;
                    switch(activeFilter) {
                        case 'active':
                            matchesFilter = status === 'active' && !isExpired;
                            break;
                        case 'inactive':
                            matchesFilter = status === 'inactive' && !isExpired;
                            break;
                        case 'expired':
                            matchesFilter = isExpired;
                            break;
                        default:
                            matchesFilter = true;
                    }

                    if (matchesSearch && matchesFilter) {
                        row.style.display = '';
                        row.style.animation = 'fadeIn 0.3s ease-in';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Show no data message if no results
                this.toggleNoDataMessage(visibleCount === 0);
            }

            toggleNoDataMessage(show) {
                let noDataRow = document.querySelector('.no-data-filtered');
                
                if (show && !noDataRow) {
                    noDataRow = document.createElement('tr');
                    noDataRow.className = 'no-data-filtered';
                    noDataRow.innerHTML = `
                        <td colspan="9" class="no-data">
                            <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                            <br>Không tìm thấy kết quả phù hợp
                        </td>
                    `;
                    this.tableBody.appendChild(noDataRow);
                } else if (!show && noDataRow) {
                    noDataRow.remove();
                }
            }

            async togglePromotionStatus(toggle) {
                const promotionId = toggle.dataset.id;
                const isActive = toggle.checked;
                const row = toggle.closest('tr');
                
                try {
                    // Gửi AJAX thực tế đến PHP
                    const formData = new FormData();
                    formData.append('id', promotionId);
                    formData.append('active', isActive ? 1 : 0);

                    const response = await fetch('promotions_update_status.php', {
                        method: 'POST',
                        body: formData
                    }).then(res => res.json());

                    if (response.success) {
                        row.dataset.status = isActive ? 'active' : 'inactive';
                        const statusText = row.querySelector('.status-text');
                        statusText.textContent = isActive ? 'Kích hoạt' : 'Ẩn';
                        statusText.className = `status-text ${isActive ? 'active' : 'inactive'}`;
                        this.showSuccessMessage(`Đã ${isActive ? 'kích hoạt' : 'ẩn'} mã khuyến mãi thành công!`);
                        this.updateStats();
                    } else {
                        toggle.checked = !isActive;
                        alert(response.message || 'Có lỗi xảy ra khi cập nhật trạng thái!');
                    }
                } catch (error) {
                    // Revert toggle on error
                    toggle.checked = !isActive;
                    alert('Lỗi kết nối! Vui lòng thử lại.');
                }
            }

            showDeleteModal(deleteBtn) {
                const promotionId = deleteBtn.dataset.id;
                const promotionCode = deleteBtn.dataset.code;
                
                document.getElementById('deletePromotionCode').textContent = promotionCode;
                document.getElementById('confirmDelete').dataset.id = promotionId;
                
                this.deleteModal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }

            hideDeleteModal() {
                this.deleteModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            async deletePromotion() {
                const promotionId = document.getElementById('confirmDelete').dataset.id;
                
                try {
                    // Gửi AJAX thực tế đến PHP để xóa khuyến mãi
                    const formData = new FormData();
                    formData.append('id', promotionId);

                    const response = await fetch('promotions_delete.php', {
                        method: 'POST',
                        body: formData
                    }).then(res => res.json());

                    if (response.success) {
                        const row = document.querySelector(`tr[data-id="${promotionId}"]`);
                        if (row) {
                            row.style.animation = 'fadeOut 0.3s ease-out';
                            setTimeout(() => {
                                row.remove();
                                this.updateStats();
                                this.filterPromotions();
                            }, 300);
                        }
                        
                        this.hideDeleteModal();
                        this.showSuccessMessage('Đã xóa mã khuyến mãi thành công!');
                    } else {
                        alert('Có lỗi xảy ra khi xóa mã khuyến mãi!');
                    }
                } catch (error) {
                    alert('Lỗi kết nối! Vui lòng thử lại.');
                }
            }

            showSuccessMessage(message) {
                const messageElement = this.successMessage;
                const textElement = document.getElementById('successText');
                
                textElement.textContent = message;
                messageElement.classList.add('show');
                
                setTimeout(() => {
                    messageElement.classList.remove('show');
                }, 3000);
            }

            // Simulate API call - replace with actual implementation
            simulateApiCall(url, data) {
                return new Promise((resolve) => {
                    setTimeout(() => {
                        // Simulate successful response
                        resolve({ success: true, data: data });
                    }, 500);
                });
            }

            updateStats() {
                const rows = document.querySelectorAll('.promotion-row:not([style*="display: none"])');
                const totalPromotions = rows.length;
                let activeCount = 0;
                let expiredCount = 0;

                rows.forEach(row => {
                    const isExpired = row.dataset.expired === 'true';
                    const isActive = row.dataset.status === 'active';

                    if (isExpired) {
                        expiredCount++;
                    } else if (isActive) {
                        activeCount++;
                    }
                });

                // Animate numbers
                this.animateNumber('totalPromotions', totalPromotions);
                this.animateNumber('activePromotions', activeCount);
                this.animateNumber('expiredPromotions', expiredCount);
            }

            animateNumber(elementId, targetNumber) {
                const element = document.getElementById(elementId);
                const duration = 1000;
                const startNumber = 0;
                const increment = targetNumber / (duration / 16);
                let currentNumber = startNumber;

                const timer = setInterval(() => {
                    currentNumber += increment;
                    if (currentNumber >= targetNumber) {
                        currentNumber = targetNumber;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(currentNumber);
                }, 16);
            }

            addAnimations() {
                // Add staggered animation to table rows
                const rows = document.querySelectorAll('.promotion-row');
                rows.forEach((row, index) => {
                    row.style.animationDelay = `${index * 50}ms`;
                });

                // Add hover effects to interactive elements
                const interactiveElements = document.querySelectorAll('.add-btn, .filter-btn, .stat-card');
                interactiveElements.forEach(element => {
                    element.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-2px)';
                    });
                    
                    element.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                    });
                });

                // Add fadeOut animation for deletions
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes fadeOut {
                        from { opacity: 1; transform: scale(1); }
                        to { opacity: 0; transform: scale(0.95); }
                    }
                `;
                document.head.appendChild(style);
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new PromotionManager();
        });

        // Add some extra visual effects
        document.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const header = document.querySelector('.header');
            
            if (scrolled > 50) {
                header.style.transform = 'translateY(-5px)';
                header.style.boxShadow = '0 5px 20px rgba(0,0,0,0.1)';
            } else {
                header.style.transform = 'translateY(0)';
                header.style.boxShadow = 'none';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // ESC to close modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('deleteModal');
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }
            
            // Ctrl+F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
</body>
</html>