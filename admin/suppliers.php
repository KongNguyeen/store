<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Xử lý filter
$search = sanitize($_GET['search'] ?? '');

// Xây dựng query với filter
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params = array_fill(0, 4, "%$search%");
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Đếm tổng số nhà cung cấp
$count_sql = "SELECT COUNT(*) FROM suppliers $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_suppliers = $stmt->fetchColumn();

// Query chính - hiển thị tất cả nhà cung cấp
$sql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.supplier_id) as product_count
    FROM suppliers s
    $where_sql
    ORDER BY s.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash message
$success = flash('success');
$error = flash('error');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý nhà cung cấp</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/suppliers.css">
</head>
<body>
    <!-- Stats Overview -->
    <div class="container-fluid">
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-number"><?= $total_suppliers ?></div>
                <div class="stat-label">Tổng nhà cung cấp</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= array_sum(array_column($suppliers, 'product_count')) ?></div>
                <div class="stat-label">Tổng sản phẩm</div>
            </div>
            
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <h6><i class="fas fa-truck me-2"></i>Quản lý nhà cung cấp</h6>
                            <div class="header-actions">
                                <button class="btn btn-secondary btn-sm back-btn" onclick="window.location.href='index.php'" title="Quay lại trang chủ">
                                    <i class="fas fa-arrow-left"></i> Quay lại
                                </button>
                                <a href="suppliers_add.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Thêm mới
                                </a>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="exportSuppliers()">
                                    <i class="fas fa-download"></i> Xuất Excel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                            </div>
                        <?php endif; ?>

                        <!-- Form tìm kiếm -->
                        <form method="get" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search"
                                        placeholder="Tìm theo tên, liên hệ, email, SĐT..."
                                        value="<?= htmlspecialchars($search) ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Tìm
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Bảng nhà cung cấp -->
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th>Nhà cung cấp</th>
                                        <th>Liên hệ</th>
                                        <th class="text-center">Sản phẩm</th>
                                        <th class="text-center">Ngày tạo</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="supplier-info">
                                                <div class="supplier-avatar">
                                                    <?= strtoupper(substr($s['name'], 0, 2)) ?>
                                                </div>
                                                <div class="supplier-details">
                                                    <h6><?= sanitize($s['name']) ?></h6>
                                                    <p><i class="fas fa-map-marker-alt me-2"></i><?= sanitize($s['address']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <p class="font-weight-bold">
                                                    <i class="fas fa-user me-2"></i><?= sanitize($s['contact_name']) ?>
                                                </p>
                                                <p><i class="fas fa-phone me-2"></i><?= $s['phone'] ?></p>
                                                <p><i class="fas fa-envelope me-2"></i><?= sanitize($s['email']) ?></p>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge"><?= $s['product_count'] ?> sản phẩm</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-secondary text-sm font-weight-bold">
                                                <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="suppliers_edit.php?id=<?= $s['supplier_id'] ?>" class="action-btn edit">
                                                    <i class="fas fa-edit"></i> Sửa
                                                </a>
                                                <a href="javascript:void(0)" class="action-btn delete delete-supplier"
                                                   data-id="<?= $s['supplier_id'] ?>"
                                                   data-name="<?= htmlspecialchars($s['name']) ?>"
                                                   data-count="<?= $s['product_count'] ?>">
                                                    <i class="fas fa-trash"></i> Xóa
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal xác nhận xóa -->
    <div class="modal fade" id="deleteModal" tabindex="-1" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1050;">
        <div class="modal-dialog" style="margin: 0 auto; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 500px; width: 90%;">
            <div class="modal-content" style="box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3); border-radius: 15px; overflow: hidden; animation: modalFadeIn 0.3s ease;">
                <div class="modal-header" style="background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%); padding: 20px; border: none;">
                    <h5 class="modal-title" style="color: white; font-weight: 600; margin: 0; display: flex; align-items: center;">
                        <i class="fas fa-exclamation-triangle me-2"></i>Xác nhận xóa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; opacity: 0.8; transition: opacity 0.3s ease;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 25px 20px; background: white;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="width: 70px; height: 70px; background: rgba(253, 121, 168, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto;">
                            <i class="fas fa-trash" style="font-size: 30px; color: #e84393;"></i>
                        </div>
                        <p style="font-size: 1.1rem; color: #333; margin-bottom: 5px;">Bạn có chắc chắn muốn xóa nhà cung cấp</p>
                        <p style="font-size: 1.2rem; font-weight: 700; color: #e84393; margin-bottom: 0;">"<span id="supplierName"></span>"?</p>
                    </div>
                    <div id="supplierWarning" class="alert alert-warning d-none" style="background: rgba(255, 193, 7, 0.15); border-left: 4px solid #ffc107; color: #856404; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Nhà cung cấp này đang có <strong id="productCount" style="color: #e84393; display: inline-block; margin: 0 5px;"></strong> sản phẩm.
                        <div style="margin-top: 8px;">Tất cả sản phẩm sẽ được chuyển sang nhà cung cấp mặc định.</div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 15px 20px 20px; border: none; display: flex; justify-content: center; gap: 15px; background: #f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: #e2e8f0; color: #4a5568; border: none; border-radius: 50px; padding: 10px 25px; font-weight: 600; transition: all 0.3s ease; min-width: 120px;">
                        <i class="fas fa-times me-2"></i>Hủy
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" style="background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%); color: white; border: none; border-radius: 50px; padding: 10px 25px; font-weight: 600; transition: all 0.3s ease; min-width: 120px; box-shadow: 0 8px 15px rgba(232, 67, 147, 0.2);">
                        <i class="fas fa-trash me-2"></i>Xóa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <script>
        // Simple modal implementation
        class SimpleModal {
            constructor(element) {
                this.element = element;
                this.backdrop = null;
            }
            
            show() {
                // Modal already has backdrop included in the styling
                this.element.style.display = 'block';
                this.element.style.opacity = '0';
                
                setTimeout(() => {
                    this.element.style.opacity = '1';
                }, 10);
                
                document.body.style.overflow = 'hidden';
            }
            
            hide() {
                this.element.style.opacity = '0';
                setTimeout(() => {
                    this.element.style.display = 'none';
                    document.body.style.overflow = '';
                }, 300);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const deleteModal = new SimpleModal(document.getElementById('deleteModal'));
            let supplierIdToDelete = null;

            // Close modal buttons
            document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
                btn.addEventListener('click', () => deleteModal.hide());
            });

            // Delete supplier buttons
            document.querySelectorAll('.delete-supplier').forEach(button => {
                button.addEventListener('click', function() {
                    const supplierId = this.dataset.id;
                    const supplierName = this.dataset.name;
                    const productCount = parseInt(this.dataset.count);
                    
                    supplierIdToDelete = supplierId;
                    document.getElementById('supplierName').textContent = supplierName;
                    
                    const warning = document.getElementById('supplierWarning');
                    if (productCount > 0) {
                        document.getElementById('productCount').textContent = productCount;
                        warning.classList.remove('d-none');
                    } else {
                        warning.classList.add('d-none');
                    }
                    
                    deleteModal.show();
                });
            });

            // Confirm delete
            document.getElementById('confirmDelete').addEventListener('click', function() {
                if (supplierIdToDelete) {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<span class="loading"></span> Đang xóa...';
                    this.disabled = true;
                    
                    fetch('suppliers_delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `id=${supplierIdToDelete}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('Xóa nhà cung cấp thành công!', 'success');
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            showNotification(data.message || 'Có lỗi xảy ra khi xóa nhà cung cấp.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Có lỗi xảy ra khi xóa nhà cung cấp.', 'error');
                    })
                    .finally(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        deleteModal.hide();
                    });
                }
            });

            // Table row hover effects
            document.querySelectorAll('.table tbody tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

            // Button click effects
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255, 255, 255, 0.4);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add ripple animation
            const rippleStyle = document.createElement('style');
            rippleStyle.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(2);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(rippleStyle);
        });

        // Export suppliers function
        function exportSuppliers() {
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<span class="loading"></span> Đang xuất...';
            button.disabled = true;
            
            // Create progress notification
            showNotification('Đang chuẩn bị xuất file Excel...', 'info');
            
            setTimeout(() => {
                window.location.href = `suppliers_export.php?<?= $_SERVER['QUERY_STRING'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>`;
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showNotification('Xuất file Excel thành công!', 'success');
                }, 2000);
            }, 1000);
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
            
            const colors = {
                success: 'linear-gradient(135deg, #00b894 0%, #00a085 100%)',
                error: 'linear-gradient(135deg, #fd79a8 0%, #e84393 100%)',
                info: 'linear-gradient(135deg, #74b9ff 0%, #0984e3 100%)'
            };
            
            notification.innerHTML = `
                <i class="fas ${icons[type]} me-2"></i>
                ${message}
            `;
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                padding: 15px 20px;
                background: ${colors[type]};
                color: white;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                font-weight: 600;
                animation: slideInRight 0.5s ease;
                cursor: pointer;
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.5s ease';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 500);
            }, 4000);
            
            // Click to dismiss
            notification.addEventListener('click', () => {
                notification.style.animation = 'slideOutRight 0.5s ease';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 500);
            });
        }

        // Add notification animations
        const notificationStyle = document.createElement('style');
        notificationStyle.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(notificationStyle);

        // Search enhancement
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchValue = this.value;
                
                // Visual feedback
                this.style.borderColor = searchValue ? '#ffc107' : '#e9ecef';
                
                searchTimeout = setTimeout(() => {
                    this.style.borderColor = '#667eea';
                }, 1000);
            });
            
            // Add clear button
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.innerHTML = '<i class="fas fa-times"></i>';
            clearBtn.style.cssText = `
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #6c757d;
                cursor: pointer;
                padding: 5px;
                border-radius: 3px;
                transition: all 0.3s ease;
            `;
            
            clearBtn.addEventListener('click', () => {
                searchInput.value = '';
                window.location.href = window.location.pathname;
            });
            
            clearBtn.addEventListener('mouseenter', () => {
                clearBtn.style.color = '#dc3545';
                clearBtn.style.background = 'rgba(220, 53, 69, 0.1)';
            });
            
            clearBtn.addEventListener('mouseleave', () => {
                clearBtn.style.color = '#6c757d';
                clearBtn.style.background = 'none';
            });
            
            if (searchInput.value) {
                const inputGroup = searchInput.closest('.input-group');
                inputGroup.style.position = 'relative';
                inputGroup.appendChild(clearBtn);
            }
        }

        // Enhanced loading states
        function setLoading(element, loading = true) {
            if (loading) {
                element.dataset.originalText = element.innerHTML;
                element.innerHTML = '<span class="loading"></span> Đang xử lý...';
                element.disabled = true;
            } else {
                element.innerHTML = element.dataset.originalText;
                element.disabled = false;
            }
        }

        // Smooth scroll for better UX
        function smoothScrollTo(element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('deleteModal');
                if (modal.style.display === 'block') {
                    new SimpleModal(modal).hide();
                }
            }
            
            // Ctrl/Cmd + N for new supplier
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'suppliers_add.php';
            }
        });

        // Add tooltips to action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            // Skip if this button already has tooltip functionality (like delete-supplier)
            if (btn.classList.contains('delete-supplier')) return;
            
            const tooltip = document.createElement('div');
            tooltip.style.cssText = `
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 0.8rem;
                white-space: nowrap;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                z-index: 1000;
                margin-bottom: 5px;
                pointer-events: none;
            `;
            
            tooltip.textContent = btn.classList.contains('edit') ? 
                'Chỉnh sửa nhà cung cấp' : 'Xóa nhà cung cấp';
            
            btn.style.position = 'relative';
            btn.appendChild(tooltip);
            
            btn.addEventListener('mouseenter', () => {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            });
            
            btn.addEventListener('mouseleave', () => {
                tooltip.style.opacity = '0';
                tooltip.style.visibility = 'hidden';
            });
        });

        // Table sorting functionality
        function sortTable(columnIndex, sortType = 'text') {
            const table = document.querySelector('.table tbody');
            const rows = Array.from(table.rows);
            
            rows.sort((a, b) => {
                const aVal = a.cells[columnIndex].textContent.trim();
                const bVal = b.cells[columnIndex].textContent.trim();
                
                if (sortType === 'number') {
                    return parseInt(aVal) - parseInt(bVal);
                } else if (sortType === 'date') {
                    return new Date(aVal) - new Date(bVal);
                } else {
                    return aVal.localeCompare(bVal, 'vi');
                }
            });
            
            // Re-append sorted rows with animation
            rows.forEach((row, index) => {
                row.style.animation = `fadeInUp 0.3s ease ${index * 0.05}s both`;
                table.appendChild(row);
            });
        }

        // Add fadeInUp animation
        const fadeUpStyle = document.createElement('style');
        fadeUpStyle.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(fadeUpStyle);

        // Auto-refresh functionality (optional)
        let autoRefreshInterval;
        function startAutoRefresh(minutes = 5) {
            autoRefreshInterval = setInterval(() => {
                showNotification('Đang làm mới dữ liệu...', 'info');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }, minutes * 60 * 1000);
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        // Initialize auto-refresh (uncomment to enable)
        // startAutoRefresh(5); // Refresh every 5 minutes

        // Page visibility API to pause auto-refresh when tab is not active
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                // startAutoRefresh(5); // Uncomment to re-enable auto-refresh
            }
        });

        // Print functionality
        function printTable() {
            const originalContents = document.body.innerHTML;
            const printContents = document.querySelector('.table-responsive').outerHTML;
            
            document.body.innerHTML = `
                <html>
                <head>
                    <title>Danh sách nhà cung cấp</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .action-buttons { display: none; }
                    </style>
                </head>
                <body>
                    <h2>Danh sách nhà cung cấp</h2>
                    ${printContents}
                </body>
                </html>
            `;
            
            window.print();
            document.body.innerHTML = originalContents;
            
            // Re-initialize event listeners after restore
            setTimeout(() => {
                window.location.reload();
            }, 100);
        }

        // Performance monitoring
        const perfObserver = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (entry.duration > 100) {
                    console.warn(`Slow operation detected: ${entry.name} took ${entry.duration}ms`);
                }
            }
        });

        if ('PerformanceObserver' in window) {
            perfObserver.observe({ entryTypes: ['measure', 'navigation'] });
        }

        // Error handling
        window.addEventListener('error', (e) => {
            console.error('JavaScript error:', e.error);
            showNotification('Đã xảy ra lỗi. Vui lòng tải lại trang.', 'error');
        });

        window.addEventListener('unhandledrejection', (e) => {
            console.error('Unhandled promise rejection:', e.reason);
            showNotification('Đã xảy ra lỗi kết nối. Vui lòng thử lại.', 'error');
        });

        console.log('🚀 Suppliers management page loaded successfully!');
    </script>

    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>