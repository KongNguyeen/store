<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Lấy danh sách danh mục với thông tin chi tiết
$sql = "
    SELECT c.*,
           parent.name as parent_name,
           COUNT(p.product_id) as product_count
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.category_id
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id
    ORDER BY c.parent_id IS NULL DESC, c.name ASC
";
$stmt = $pdo->query($sql);
$all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xây dựng cây danh mục
$categories_tree = [];
$categories_map = [];

// Tạo map các danh mục
foreach ($all_categories as $cat) {
    $cat['children'] = [];
    $categories_map[$cat['category_id']] = $cat;
}

// Xây dựng cây
foreach ($categories_map as $id => &$cat) {
    if ($cat['parent_id']) {
        $categories_map[$cat['parent_id']]['children'][] = &$cat;
    } else {
        $categories_tree[] = &$cat;
    }
}

// Flash message
$success = flash('success');
$error = flash('error');

// Hàm đệ quy để render danh mục
function render_category_row($category, $level = 0) {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
    $indent_class = $level > 0 ? 'ms-' . ($level * 3) : '';
    ?>
    <tr class="category-row" data-level="<?= $level ?>" data-aos="fade-up" data-aos-delay="<?= $level * 50 ?>">
        <td class="category-name-cell">
            <div class="category-name-wrapper">
                <span class="category-indent <?= $indent_class ?>">
                    <?php if ($level > 0): ?>
                        <i class="fas fa-angle-right category-branch me-2"></i>
                    <?php endif; ?>
                    <span class="category-name-text"><?= sanitize($category['name']) ?></span>
                </span>
            </div>
        </td>
        <td class="category-description">
            <span class="description-text"><?= sanitize($category['description'] ?? '') ?></span>
        </td>
        <td class="parent-category">
            <span class="parent-name"><?= sanitize($category['parent_name'] ?? 'Danh mục gốc') ?></span>
        </td>
        <td class="text-center product-count-cell">
            <span class="product-count-badge">
                <i class="fas fa-box me-1"></i>
                <?= $category['product_count'] ?>
            </span>
        </td>
        <td class="action-buttons">
            <div class="btn-group-actions">
                <a href="categories_edit.php?id=<?= $category['category_id'] ?>"
                   class="btn-action btn-edit"
                   data-bs-toggle="tooltip"
                   title="Chỉnh sửa danh mục">
                    <i class="fas fa-edit"></i>
                </a>
                <button type="button"
                        class="btn-action btn-delete delete-category"
                        data-id="<?= $category['category_id'] ?>"
                        data-name="<?= htmlspecialchars($category['name']) ?>"
                        data-count="<?= $category['product_count'] ?>"
                        data-bs-toggle="tooltip"
                        title="Xóa danh mục">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php
    if (!empty($category['children'])) {
        foreach ($category['children'] as $child) {
            render_category_row($child, $level + 1);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- AOS Animation CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.7.32/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
   
    <link rel="stylesheet" href="../css/categories.css">
   
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4" data-aos="fade-up">
                <div class="card-header pb-0">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <a href="../admin/index.php" class="btn btn-back" data-aos="fade-right" data-aos-delay="100">
                                <i class="fas fa-arrow-left me-2"></i>
                                <span>Quay lại</span>
                            </a>
                        </div>
                        <div class="col-md-4 text-center">
                            <h5 class="mb-0">
                                <i class="fas fa-sitemap me-2"></i>
                                Quản lý danh mục
                            </h5>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="categories_add.php" class="btn btn-primary btn-sm mb-0 me-2" data-aos="fade-left" data-aos-delay="200">
                                <i class="fas fa-plus me-1"></i> Thêm danh mục
                            </a>
                            <button type="button" class="btn btn-success btn-sm mb-0 me-2" id="exportExcelBtn" data-aos="fade-left" data-aos-delay="300">
                                <i class="fas fa-file-excel me-1"></i> Xuất Excel
                            </button>
                            <button type="button" class="btn btn-info btn-sm mb-0" id="printBtn" data-aos="fade-left" data-aos-delay="400">
                                <i class="fas fa-print me-1"></i> In trang
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <?php if ($success): ?>
                        <div class="alert alert-success" data-aos="fade-in">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger" data-aos="fade-in">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0" id="categoriesTable">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        <i class="fas fa-tag me-1"></i>Tên danh mục
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                        <i class="fas fa-align-left me-1"></i>Mô tả
                                    </th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                        <i class="fas fa-folder me-1"></i>Danh mục cha
                                    </th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                        <i class="fas fa-box me-1"></i>Số sản phẩm
                                    </th>
                                    <th class="text-center text-secondary opacity-7">
                                        <i class="fas fa-cogs me-1"></i>Hành động
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories_tree as $category): ?>
                                    <?php render_category_row($category); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay d-none">
    <div class="loading-spinner"></div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<!-- AOS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.7.32/sweetalert2.min.js"></script>

<!-- SheetJS (xlsx) for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- html2canvas and jsPDF for better printing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize AOS with error handling
    try {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 50
        });
    } catch (error) {
        console.warn('AOS initialization failed:', error);
    }

    // Initialize tooltips with error handling
    try {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    } catch (error) {
        console.warn('Tooltip initialization failed:', error);
    }

    // Loading overlay functions
    function showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.remove('d-none');
        }
    }

    function hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.add('d-none');
        }
    }
    
    // Excel Export Function
    function exportToExcel() {
        showLoading();
        
        try {
            // Get current timestamp for filename
            const date = new Date();
            const timestamp = date.toISOString().replace(/[:.]/g, '-').substring(0, 19);
            const fileName = `categories_export_${timestamp}.xlsx`;
            
            // Create workbook and worksheet
            const wb = XLSX.utils.book_new();
            
            // Get all category data from table
            const table = document.getElementById('categoriesTable');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            
            // Prepare header row
            const headers = [
                'Tên Danh Mục', 
                'Mô Tả', 
                'Danh Mục Cha', 
                'Số Sản Phẩm'
            ];
            
            // Extract data from each row
            const data = [headers];
            
            rows.forEach(row => {
                // Get category name - extract the actual text without the indentation
                const nameCell = row.querySelector('.category-name-cell');
                const nameText = nameCell.querySelector('.category-name-text').textContent.trim();
                
                // Get level of indentation for tree structure
                const level = parseInt(row.getAttribute('data-level') || '0');
                const indentedName = '  '.repeat(level) + (level > 0 ? '└─ ' : '') + nameText;
                
                // Get description
                const descriptionCell = row.querySelector('.category-description');
                const description = descriptionCell.textContent.trim();
                
                // Get parent category
                const parentCell = row.querySelector('.parent-category');
                const parent = parentCell.textContent.trim();
                
                // Get product count
                const countCell = row.querySelector('.product-count-cell');
                const countBadge = countCell.querySelector('.product-count-badge');
                const count = countBadge ? countBadge.textContent.trim().replace(/[^\d]/g, '') : '0';
                
                data.push([
                    indentedName,
                    description,
                    parent,
                    count
                ]);
            });
            
            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(data);
            
            // Set column widths
            const colWidths = [
                { wch: 40 },  // Tên Danh Mục
                { wch: 40 },  // Mô Tả
                { wch: 25 },  // Danh Mục Cha
                { wch: 15 },  // Số Sản Phẩm
            ];
            
            ws['!cols'] = colWidths;
            
            // Add the worksheet to the workbook
            XLSX.utils.book_append_sheet(wb, ws, 'Danh sách danh mục');
            
            // Save the file
            XLSX.writeFile(wb, fileName);
            
            // Show success message
            Swal.fire({
                title: 'Thành công!',
                text: 'Xuất Excel thành công!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } catch (error) {
            console.error('Export error:', error);
            Swal.fire({
                title: 'Lỗi!',
                text: 'Có lỗi xảy ra khi xuất Excel: ' + error.message,
                icon: 'error',
                confirmButtonText: 'Đóng'
            });
        } finally {
            hideLoading();
        }
    }
    
    // Print function
    function printPage() {
        try {
            // Show loading
            showLoading();
            
            // Create a clone of the categories table to modify for printing
            const categoriesTable = document.getElementById('categoriesTable');
            const cloneTable = categoriesTable.cloneNode(true);
            
            // Remove action column
            const headerRow = cloneTable.querySelector('thead tr');
            const lastHeader = headerRow.querySelector('th:last-child');
            if (lastHeader) lastHeader.remove();
            
            cloneTable.querySelectorAll('tbody tr').forEach(row => {
                const lastCell = row.querySelector('td:last-child');
                if (lastCell) lastCell.remove();
            });
            
            // Count total categories
            const totalCategories = cloneTable.querySelectorAll('tbody tr').length;
            
            // Count root and child categories
            const rootCategories = cloneTable.querySelectorAll('tbody tr[data-level="0"]').length;
            const childCategories = totalCategories - rootCategories;
            
            // Count categories with products
            let categoriesWithProducts = 0;
            cloneTable.querySelectorAll('tbody tr').forEach(row => {
                const countCell = row.querySelector('.product-count-cell');
                const countText = countCell.textContent.trim();
                const count = parseInt(countText.replace(/[^\d]/g, ''));
                if (count > 0) categoriesWithProducts++;
            });
            
            // Create print window
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Create print content
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Danh sách danh mục - In</title>
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
                            width: 30%;
                        }
                        .stat-value {
                            font-size: 18px;
                            font-weight: bold;
                        }
                        .stat-title {
                            font-size: 12px;
                            color: #666;
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
                        .category-indent {
                            padding-left: 20px;
                        }
                        .category-branch {
                            display: inline-block;
                            width: 15px;
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
                        <h1 class="print-title">Danh sách danh mục</h1>
                        <div class="print-date">Ngày in: ${new Date().toLocaleString('vi-VN')}</div>
                    </div>
                    
                    <div class="stats-container">
                        <div class="stat-box">
                            <div class="stat-value">${totalCategories}</div>
                            <div class="stat-title">Tổng danh mục</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">${rootCategories}</div>
                            <div class="stat-title">Danh mục gốc</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">${categoriesWithProducts}</div>
                            <div class="stat-title">Danh mục có sản phẩm</div>
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
            Swal.fire({
                title: 'Thành công!',
                text: 'Đã mở chế độ in trang!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } catch (error) {
            console.error('Print error:', error);
            hideLoading();
            Swal.fire({
                title: 'Lỗi!',
                text: 'Có lỗi xảy ra khi chuẩn bị in: ' + error.message,
                icon: 'error',
                confirmButtonText: 'Đóng'
            });
        }
    }
    
    // Set up export and print buttons
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', exportToExcel);
    }
    
    const printBtn = document.getElementById('printBtn');
    if (printBtn) {
        printBtn.addEventListener('click', printPage);
    }

    // Enhanced delete functionality with SweetAlert2
    document.querySelectorAll('.delete-category').forEach(button => {
        button.addEventListener('click', function() {
            const categoryId = this.dataset.id;
            const categoryName = this.dataset.name;
            const productCount = parseInt(this.dataset.count) || 0;
            
            let warningText = '';
            let icon = 'warning';
            
            if (productCount > 0) {
                warningText = `Danh mục này đang có ${productCount} sản phẩm. Việc xóa danh mục sẽ ảnh hưởng đến các sản phẩm này.`;
                icon = 'error';
            }
            
            // Check if SweetAlert2 is available
            if (typeof Swal === 'undefined') {
                if (confirm(`Bạn có chắc chắn muốn xóa danh mục "${categoryName}"?`)) {
                    window.location.href = `categories_delete.php?id=${categoryId}`;
                }
                return;
            }
            
            Swal.fire({
                title: 'Xác nhận xóa danh mục',
                html: `
                    <div class="text-start">
                        <p class="mb-2">Bạn có chắc chắn muốn xóa danh mục:</p>
                        <p class="fw-bold text-primary mb-3">"${categoryName}"</p>
                        ${warningText ? `<div class="alert alert-warning">${warningText}</div>` : ''}
                    </div>
                `,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash me-1"></i>Xóa ngay',
                cancelButtonText: '<i class="fas fa-times me-1"></i>Hủy bỏ',
                reverseButtons: true,
                customClass: {
                    popup: 'swal-custom-popup',
                    confirmButton: 'swal-confirm-btn',
                    cancelButton: 'swal-cancel-btn'
                },
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return fetch(`categories_delete.php?id=${categoryId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?? '' ?>')
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Có lỗi xảy ra khi xóa danh mục');
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Lỗi: ${error.message}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    // Add fade out animation to the row
                    const row = this.closest('.category-row');
                    if (row) {
                        row.style.transition = 'all 0.5s ease-out';
                        row.style.transform = 'translateX(-100%)';
                        row.style.opacity = '0';
                    }
                    
                    setTimeout(() => {
                        Swal.fire({
                            title: 'Đã xóa!',
                            text: 'Danh mục đã được xóa thành công.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    }, 500);
                }
            });
        });
    });

    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.category-row');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });

    // Add click effect to action buttons
    document.querySelectorAll('.btn-action').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Create ripple effect
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
                if (ripple.parentNode) {
                    ripple.remove();
                }
            }, 600);
        });
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.transition = 'all 0.5s ease-out';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }
        }, 5000);
    });

    // Add smooth scroll to top functionality
    const scrollToTop = document.createElement('button');
    scrollToTop.innerHTML = '<i class="fas fa-chevron-up"></i>';
    scrollToTop.className = 'scroll-to-top';
    scrollToTop.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--primary-gradient);
        color: white;
        border: none;
        cursor: pointer;
        opacity: 0;
        transform: translateY(100px);
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: var(--shadow-medium);
    `;
    
    document.body.appendChild(scrollToTop);
    
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollToTop.style.opacity = '1';
            scrollToTop.style.transform = 'translateY(0)';
        } else {
            scrollToTop.style.opacity = '0';
            scrollToTop.style.transform = 'translateY(100px)';
        }
    });
    
    scrollToTop.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});
</script>

</body>
</html>