<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Lấy danh sách danh mục để chọn danh mục cha
$sql = "
    SELECT c.*, parent.name as parent_name
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.category_id
    ORDER BY c.parent_id IS NULL DESC, c.name ASC
";
$stmt = $pdo->query($sql);
$all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xây dựng cây danh mục
$categories_tree = [];
$categories_map = [];

foreach ($all_categories as $cat) {
    $cat['children'] = [];
    $categories_map[$cat['category_id']] = $cat;
}

foreach ($categories_map as $id => &$cat) {
    if ($cat['parent_id']) {
        $categories_map[$cat['parent_id']]['children'][] = &$cat;
    } else {
        $categories_tree[] = &$cat;
    }
}

// Hàm đệ quy để tạo options cho select
function create_category_options($categories, $level = 0, $selected = null) {
    $html = '';
    
    foreach ($categories as $category) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level * 2);
        
        $html .= sprintf(
            '<option value="%d" %s>%s%s</option>',
            $category['category_id'],
            ($selected == $category['category_id']) ? 'selected' : '',
            $indent,
            htmlspecialchars($category['name'])
        );
        
        if (!empty($category['children'])) {
            $html .= create_category_options($category['children'], $level + 1, $selected);
        }
    }
    
    return $html;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
    } else {
        // Validate input
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        
        if (!$name) {
            $error = 'Vui lòng nhập tên danh mục';
        } else {
            try {
                // Kiểm tra tên danh mục đã tồn tại chưa
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Tên danh mục đã tồn tại';
                } else {
                    // Thêm danh mục mới
                    $stmt = $pdo->prepare("
                        INSERT INTO categories (name, description, parent_id)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $parent_id ?: null]);

                    flash('success', 'Thêm danh mục thành công!');
                    redirect('categories.php');
                }
            } catch (PDOException $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm danh mục mới - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- AOS Animation CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.7.32/sweetalert2.min.css" rel="stylesheet">

    <link rel="stylesheet" href=".../css/categories_add.css">

</head>
<body>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card mb-4" data-aos="fade-up">
                <div class="card-header pb-0">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <a href="categories.php" class="btn btn-back" data-aos="fade-right" data-aos-delay="100">
                                <i class="fas fa-arrow-left me-2"></i>
                                <span>Quay lại</span>
                            </a>
                        </div>
                        <div class="col-md-4 text-center">
                            <h6 class="mb-0">
                                <i class="fas fa-plus-circle me-2"></i>
                                Thêm danh mục mới
                            </h6>
                        </div>
                        <div class="col-md-4"></div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" data-aos="fade-in">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="categoryForm" data-aos="fade-up" data-aos-delay="200">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group has-icon">
                                    <label class="form-label">
                                        <i class="fas fa-tag"></i>
                                        Tên danh mục <span class="text-danger">*</span>
                                    </label>
                                    <div class="position-relative">
                                        <i class="fas fa-tag form-icon"></i>
                                        <input type="text" 
                                               class="form-control" 
                                               name="name" 
                                               required
                                               placeholder="Nhập tên danh mục..."
                                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                                    </div>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-sitemap"></i>
                                        Danh mục cha
                                    </label>
                                    <select class="form-select" name="parent_id">
                                        <option value="">-- Không có --</option>
                                        <?= create_category_options($categories_tree, 0, $_POST['parent_id'] ?? null) ?>
                                    </select>
                                    <small class="text-muted">Chọn danh mục cha nếu đây là danh mục con</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i>
                                Mô tả
                            </label>
                            <textarea class="form-control" 
                                      name="description" 
                                      rows="4"
                                      placeholder="Nhập mô tả cho danh mục..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <small class="text-muted">Mô tả ngắn gọn về danh mục này</small>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary" data-aos="fade-up" data-aos-delay="400">
                                <i class="fas fa-save me-2"></i>
                                Lưu danh mục
                            </button>
                        </div>
                    </form>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize AOS
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

    // Initialize tooltips
    try {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    } catch (error) {
        console.warn('Tooltip initialization failed:', error);
    }

    // Form elements
    const form = document.getElementById('categoryForm');
    const nameInput = form.querySelector('[name="name"]');
    const parentSelect = form.querySelector('[name="parent_id"]');
    const descriptionTextarea = form.querySelector('[name="description"]');

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

    // Real-time validation
    function validateField(field, validationFn, errorMessage) {
        const feedback = field.parentNode.querySelector('.invalid-feedback') || 
                        field.parentNode.parentNode.querySelector('.invalid-feedback');
        
        if (validationFn(field.value)) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            if (feedback) feedback.textContent = '';
            return true;
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            if (feedback) feedback.textContent = errorMessage;
            return false;
        }
    }

    // Name input validation
    nameInput.addEventListener('input', function() {
        validateField(this, 
            value => value.trim().length >= 2, 
            'Tên danh mục phải có ít nhất 2 ký tự'
        );
    });

    nameInput.addEventListener('blur', function() {
        if (this.value.trim()) {
            // Check for duplicate names (you can implement AJAX check here)
            validateField(this, 
                value => value.trim().length >= 2, 
                'Tên danh mục phải có ít nhất 2 ký tự'
            );
        }
    });

    // Description character counter
    const maxDescLength = 500;
    const charCounter = document.createElement('small');
    charCounter.className = 'text-muted float-end';
    descriptionTextarea.parentNode.appendChild(charCounter);

    function updateCharCounter() {
        const remaining = maxDescLength - descriptionTextarea.value.length;
        charCounter.textContent = `${descriptionTextarea.value.length}/${maxDescLength} ký tự`;
        charCounter.className = remaining < 50 ? 'text-warning float-end' : 'text-muted float-end';
    }

    descriptionTextarea.addEventListener('input', updateCharCounter);
    updateCharCounter();

    // Enhanced form validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const name = nameInput.value.trim();
        const parentId = parentSelect.value;
        let hasError = false;
        let errors = [];

        // Validate name
        if (!name) {
            errors.push('Vui lòng nhập tên danh mục');
            nameInput.classList.add('is-invalid');
            hasError = true;
        } else if (name.length < 2) {
            errors.push('Tên danh mục phải có ít nhất 2 ký tự');
            nameInput.classList.add('is-invalid');
            hasError = true;
        } else {
            nameInput.classList.remove('is-invalid');
            nameInput.classList.add('is-valid');
        }

        // Validate description length
        if (descriptionTextarea.value.length > maxDescLength) {
            errors.push(`Mô tả không được vượt quá ${maxDescLength} ký tự`);
            descriptionTextarea.classList.add('is-invalid');
            hasError = true;
        } else {
            descriptionTextarea.classList.remove('is-invalid');
        }

        if (hasError) {
            // Show errors with SweetAlert2
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Lỗi nhập liệu',
                    html: errors.map(error => `• ${error}`).join('<br>'),
                    icon: 'error',
                    confirmButtonText: 'Đã hiểu',
                    customClass: {
                        popup: 'swal-custom-popup'
                    }
                });
            } else {
                alert(errors.join('\n'));
            }
            return;
        }

        // Show loading and submit
        showLoading();
        
        // Add a small delay for better UX
        setTimeout(() => {
            form.submit();
        }, 500);
    });

    // Add floating animation to form
    const formElements = form.querySelectorAll('.form-control, .form-select, .btn');
    formElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
        element.classList.add('form-floating-animation');
    });

    // Auto-hide alerts
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

    // Enhanced input focus effects
    const inputs = form.querySelectorAll('.form-control, .form-select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentNode.style.transform = 'scale(1.02)';
            this.parentNode.style.transition = 'transform 0.2s ease';
        });

        input.addEventListener('blur', function() {
            this.parentNode.style.transform = 'scale(1)';
        });
    });

    // Add ripple effect to buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
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
});

// Add CSS for ripple effect
const style = document.createElement('style');
style.textContent = `
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
document.head.appendChild(style);
</script>

</body>
</html>