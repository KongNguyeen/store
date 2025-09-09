<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Lấy danh sách danh mục và nhà cung cấp
$categories = get_categories();
$stmt = $pdo->query("SELECT * FROM suppliers WHERE 1 ORDER BY name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        // Validate required fields
        if (!$name || !$price || !$category_id || !$supplier_id) {
            $error = 'Vui lòng điền đầy đủ thông tin bắt buộc';
        } elseif ($price <= 0) {
            $error = 'Giá sản phẩm phải lớn hơn 0';
        } elseif ($stock < 0) {
            $error = 'Số lượng tồn kho không hợp lệ';
        } else {
            try {
                $pdo->beginTransaction();

                // Thêm sản phẩm
                $stmt = $pdo->prepare("
                    INSERT INTO products (supplier_id, category_id, name, description, price, stock, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$supplier_id, $category_id, $name, $description, $price, $stock, $status]);
                $product_id = $pdo->lastInsertId();

                // Xử lý thuộc tính sản phẩm
                if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_attributes (product_id, attribute_name, attribute_value)
                        VALUES (?, ?, ?)
                    ");
                    foreach ($_POST['attributes'] as $attr) {
                        if (!empty($attr['name']) && !empty($attr['value'])) {
                            $stmt->execute([
                                $product_id,
                                sanitize($attr['name']),
                                sanitize($attr['value'])
                            ]);
                        }
                    }
                }

                // Tạo thư mục lưu ảnh sản phẩm
                $upload_dir = PRODUCT_IMG_PATH . $product_id;
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Xử lý upload ảnh
                if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, image_url, is_primary)
                        VALUES (?, ?, ?)
                    ");

                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['images']['name'][$key];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            
                            // Kiểm tra định dạng file
                            if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                continue;
                            }

                            // Tạo tên file mới
                            $new_name = uniqid() . '.' . $file_ext;
                            $destination = $upload_dir . '/' . $new_name;

                            if (move_uploaded_file($tmp_name, $destination)) {
                                // Ảnh đầu tiên sẽ là ảnh chính
                                $is_primary = ($key === 0) ? 1 : 0;
                                $image_url = str_replace(ROOT_PATH, '', $destination);
                                $stmt->execute([$product_id, $image_url, $is_primary]);
                            }
                        }
                    }
                }

                $pdo->commit();
                flash('success', 'Thêm sản phẩm thành công!');
                redirect('products.php');

            } catch (Exception $e) {
                $pdo->rollBack();
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
    <title>Thêm Sản Phẩm Mới</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Animate.css -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    
<link rel="stylesheet" href="../css/products_add.css">
   
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="main-container animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-plus-circle me-3"></i>Thêm Sản Phẩm Mới</h1>
                <a href="products.php" class="btn back-btn">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>
            </div>
        </div>

        <div class="container-fluid p-4">
            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger animate__animated animate__slideInDown">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Thông tin cơ bản -->
                        <div class="form-card form-section">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-info-circle"></i>
                                    Thông tin cơ bản
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row">
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required
                                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                            placeholder="Nhập tên sản phẩm...">
                                    </div>

                                    <div class="col-md-12 mb-4">
                                        <label class="form-label">Mô tả</label>
                                        <textarea class="form-control" name="description" rows="4"
                                            placeholder="Mô tả chi tiết về sản phẩm..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Giá <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="price" required min="0" step="0.01"
                                                value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                                                placeholder="0.00">
                                            <span class="input-group-text">VNĐ</span>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Số lượng tồn</label>
                                        <input type="number" class="form-control" name="stock" min="0"
                                            value="<?= htmlspecialchars($_POST['stock'] ?? '0') ?>"
                                            placeholder="0">
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Danh mục <span class="text-danger">*</span></label>
                                        <select class="form-select" name="category_id" required>
                                            <option value="">Chọn danh mục</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['category_id'] ?>"
                                                    <?= isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <label class="form-label">Nhà cung cấp <span class="text-danger">*</span></label>
                                        <select class="form-select" name="supplier_id" required>
                                            <option value="">Chọn nhà cung cấp</option>
                                            <?php foreach ($suppliers as $sup): ?>
                                                <option value="<?= $sup['supplier_id'] ?>"
                                                    <?= isset($_POST['supplier_id']) && $_POST['supplier_id'] == $sup['supplier_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($sup['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Trạng thái</label>
                                        <select class="form-select" name="status">
                                            <option value="active" <?= isset($_POST['status']) && $_POST['status'] == 'active' ? 'selected' : '' ?>>
                                                <i class="fas fa-check-circle"></i> Đang bán
                                            </option>
                                            <option value="inactive" <?= isset($_POST['status']) && $_POST['status'] == 'inactive' ? 'selected' : '' ?>>
                                                <i class="fas fa-pause-circle"></i> Ngừng bán
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Thuộc tính sản phẩm -->
                        <div class="form-card form-section">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-tags"></i>
                                        Thuộc tính sản phẩm
                                    </h5>
                                    <button type="button" class="btn btn-light btn-sm" id="addAttribute">
                                        <i class="fas fa-plus me-1"></i>Thêm thuộc tính
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div id="attributesContainer">
                                    <div class="text-center text-muted py-4" id="noAttributesMsg">
                                        <i class="fas fa-tags fa-2x mb-2"></i>
                                        <p>Chưa có thuộc tính nào. Nhấn "Thêm thuộc tính" để bắt đầu.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Upload ảnh -->
                        <div class="form-card form-section">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-images"></i>
                                    Hình ảnh sản phẩm
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                                    <h5>Kéo thả ảnh vào đây</h5>
                                    <p class="text-muted">hoặc nhấn để chọn file</p>
                                    <input type="file" class="d-none" name="images[]" multiple accept="image/*" id="imageInput">
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Ảnh đầu tiên sẽ là ảnh chính. Hỗ trợ: JPG, PNG, GIF
                                </small>

                                <div id="imagePreview" class="row g-2 mt-3">
                                    <!-- Preview ảnh sẽ được hiển thị ở đây -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating Save Button -->
                <button type="submit" class="btn btn-primary floating-save-btn" id="saveBtn">
                    <i class="fas fa-save me-2"></i>Lưu sản phẩm
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const attributesContainer = document.getElementById('attributesContainer');
            const addAttributeBtn = document.getElementById('addAttribute');
            const imageInput = document.getElementById('imageInput');
            const imagePreview = document.getElementById('imagePreview');
            const uploadArea = document.getElementById('uploadArea');
            const form = document.getElementById('productForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const noAttributesMsg = document.getElementById('noAttributesMsg');

            // Add attribute functionality
            let attributeCount = 0;
            
            addAttributeBtn.addEventListener('click', function() {
                addAttribute();
            });

            function addAttribute(name = '', value = '') {
                attributeCount++;
                
                // Hide no attributes message
                if (noAttributesMsg) {
                    noAttributesMsg.style.display = 'none';
                }

                const attributeRow = document.createElement('div');
                attributeRow.className = 'attribute-row animate__animated animate__fadeInUp';
                attributeRow.innerHTML = `
                    <div class="row align-items-center">
                        <div class="col-md-5 mb-2">
                            <input type="text" class="form-control" name="attributes[${attributeCount}][name]" 
                                placeholder="Tên thuộc tính (VD: Màu sắc)" value="${name}">
                        </div>
                        <div class="col-md-5 mb-2">
                            <input type="text" class="form-control" name="attributes[${attributeCount}][value]" 
                                placeholder="Giá trị (VD: Đỏ, Xanh)" value="${value}">
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-attribute">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                attributesContainer.appendChild(attributeRow);

                // Remove attribute functionality
                attributeRow.querySelector('.remove-attribute').addEventListener('click', function() {
                    attributeRow.classList.add('animate__fadeOutUp');
                    setTimeout(() => {
                        attributeRow.remove();
                        // Show no attributes message if no attributes left
                        if (attributesContainer.children.length === 1 && noAttributesMsg) {
                            noAttributesMsg.style.display = 'block';
                        }
                    }, 300);
                });

                // Focus on first input
                attributeRow.querySelector('input').focus();
            }

            // Image upload functionality
            uploadArea.addEventListener('click', () => imageInput.click());
            
            // Drag & Drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                const files = e.dataTransfer.files;
                handleFiles(files);
            });

            imageInput.addEventListener('change', function() {
                handleFiles(this.files);
            });

            function handleFiles(files) {
                imagePreview.innerHTML = '';
                
                if (files.length === 0) return;

                Array.from(files).forEach((file, index) => {
                    if (!file.type.startsWith('image/')) return;

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewContainer = document.createElement('div');
                        previewContainer.className = 'col-6 col-md-4 animate__animated animate__fadeInUp';
                        previewContainer.innerHTML = `
                            <div class="image-preview-container position-relative">
                                <img src="${e.target.result}" class="img-fluid">
                                <div class="image-preview-overlay">
                                    <div class="text-center">
                                        ${index === 0 ? '<i class="fas fa-crown mb-1"></i><br><small>Ảnh chính</small>' : '<i class="fas fa-image"></i>'}
                                    </div>
                                </div>
                            </div>
                        `;
                        imagePreview.appendChild(previewContainer);
                    }
                    reader.readAsDataURL(file);
                });
            }

            // Form validation with enhanced UX
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (validateForm()) {
                    showLoading();
                    // Simulate processing time for better UX
                    setTimeout(() => {
                        this.submit();
                    }, 1000);
                }
            });

            function validateForm() {
                const name = form.querySelector('[name="name"]').value.trim();
                const price = parseFloat(form.querySelector('[name="price"]').value);
                const categoryId = form.querySelector('[name="category_id"]').value;
                const supplierId = form.querySelector('[name="supplier_id"]').value;

                let isValid = true;
                let errors = [];

                // Reset previous error states
                document.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });

                if (!name) {
                    errors.push('Vui lòng nhập tên sản phẩm');
                    form.querySelector('[name="name"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (isNaN(price) || price <= 0) {
                    errors.push('Giá sản phẩm phải lớn hơn 0');
                    form.querySelector('[name="price"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!categoryId) {
                    errors.push('Vui lòng chọn danh mục');
                    form.querySelector('[name="category_id"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!supplierId) {
                    errors.push('Vui lòng chọn nhà cung cấp');
                    form.querySelector('[name="supplier_id"]').classList.add('is-invalid');
                    isValid = false;
                }

                if (!isValid) {
                    showErrorNotification(errors);
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }

                return isValid;
            }

            function showErrorNotification(errors) {
                const notification = document.createElement('div');
                notification.className = 'alert alert-danger animate__animated animate__slideInDown position-fixed';
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                        <div>
                            <strong>Vui lòng kiểm tra lại:</strong>
                            <ul class="mb-0 mt-1">
                                ${errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                        </div>
                        <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 5000);
            }

            function showLoading() {
                loadingOverlay.classList.add('show');
            }

            function hideLoading() {
                loadingOverlay.classList.remove('show');
            }

            // Enhanced form interactions
            document.querySelectorAll('.form-control, .form-select').forEach(input => {
                input.addEventListener('focus', function() {
                    this.closest('.form-card')?.classList.add('shadow-lg');
                });

                input.addEventListener('blur', function() {
                    this.closest('.form-card')?.classList.remove('shadow-lg');
                    this.classList.remove('is-invalid');
                });

                // Real-time validation feedback
                input.addEventListener('input', function() {
                    if (this.hasAttribute('required') && this.value.trim()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else if (this.hasAttribute('required')) {
                        this.classList.remove('is-valid');
                    }
                });
            });

            // Price formatting
            const priceInput = form.querySelector('[name="price"]');
            priceInput.addEventListener('input', function() {
                let value = this.value.replace(/[^\d.]/g, '');
                if (value.split('.').length > 2) {
                    value = value.substring(0, value.lastIndexOf('.'));
                }
                this.value = value;
            });

            // Auto-resize textarea
            const textarea = form.querySelector('textarea[name="description"]');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Success notification for demonstration
            function showSuccessNotification(message) {
                const notification = document.createElement('div');
                notification.className = 'alert alert-success animate__animated animate__slideInDown position-fixed';
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <span>${message}</span>
                        <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                    </div>
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 3000);
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl + S to save
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    form.dispatchEvent(new Event('submit'));
                }
                
                // Ctrl + A to add attribute
                if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    addAttribute();
                }
            });

            // Page visibility handling
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    // Save form data to localStorage when page becomes hidden
                    const formData = new FormData(form);
                    const data = {};
                    for (let [key, value] of formData.entries()) {
                        data[key] = value;
                    }
                    localStorage.setItem('productFormDraft', JSON.stringify(data));
                }
            });

            // Load draft data on page load
            const draftData = localStorage.getItem('productFormDraft');
            if (draftData) {
                try {
                    const data = JSON.parse(draftData);
                    Object.keys(data).forEach(key => {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input && !input.value) {
                            input.value = data[key];
                        }
                    });
                    
                    // Show notification about loaded draft
                    setTimeout(() => {
                        const notification = document.createElement('div');
                        notification.className = 'alert alert-info animate__animated animate__slideInDown position-fixed';
                        notification.style.cssText = 'top: 20px; left: 20px; z-index: 9999; min-width: 300px;';
                        notification.innerHTML = `
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <span>Đã khôi phục dữ liệu từ lần chỉnh sửa trước</span>
                                <button type="button" class="btn btn-sm btn-outline-light ms-2" onclick="localStorage.removeItem('productFormDraft'); this.parentElement.parentElement.remove();">
                                    Xóa
                                </button>
                            </div>
                        `;
                        document.body.appendChild(notification);
                        
                        setTimeout(() => {
                            if (notification.parentElement) {
                                notification.remove();
                            }
                        }, 5000);
                    }, 1000);
                } catch (e) {
                    localStorage.removeItem('productFormDraft');
                }
            }

            // Smooth scroll for better UX
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

            // Clear draft on successful form submission
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    localStorage.removeItem('productFormDraft');
                }, 100);
            });

            // Add some demo attributes for testing (remove in production)
            // setTimeout(() => {
            //     addAttribute('Màu sắc', 'Đỏ, Xanh, Vàng');
            //     addAttribute('Kích thước', 'S, M, L, XL');
            // }, 2000);
        });

        // Add custom CSS for invalid inputs
        const style = document.createElement('style');
        style.textContent = `
            .form-control.is-invalid,
            .form-select.is-invalid {
                border-color: #f093fb;
                animation: shake 0.5s ease-in-out;
            }
            
            .form-control.is-valid,
            .form-select.is-valid {
                border-color: #4facfe;
            }
            
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>