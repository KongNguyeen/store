<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Lấy thông tin sản phẩm
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) {
    flash('error', 'Sản phẩm không tồn tại');
    redirect('products.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, s.name AS supplier_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.category_id 
    JOIN suppliers s ON p.supplier_id = s.supplier_id 
    WHERE p.product_id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    flash('error', 'Sản phẩm không tồn tại');
    redirect('products.php');
}

// Lấy danh sách ảnh
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy thuộc tính sản phẩm
$stmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ?");
$stmt->execute([$product_id]);
$attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách danh mục và nhà cung cấp
$categories = get_categories();
$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name");
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

                // Cập nhật thông tin sản phẩm
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, description = ?, price = ?, stock = ?, 
                        category_id = ?, supplier_id = ?, status = ?, updated_at = NOW()
                    WHERE product_id = ?
                ");
                $stmt->execute([
                    $name, $description, $price, $stock,
                    $category_id, $supplier_id, $status, $product_id
                ]);

                // Xóa thuộc tính cũ
                $stmt = $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?");
                $stmt->execute([$product_id]);

                // Thêm thuộc tính mới
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

                // Xử lý xóa ảnh
                if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                    $stmt = $pdo->prepare("
                        DELETE FROM product_images 
                        WHERE product_id = ? AND image_id = ?
                    ");
                    foreach ($_POST['delete_images'] as $image_id) {
                        // Lấy đường dẫn ảnh trước khi xóa
                        $img_stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE image_id = ?");
                        $img_stmt->execute([$image_id]);
                        $image_url = $img_stmt->fetchColumn();

                        if ($image_url && file_exists(ROOT_PATH . $image_url)) {
                            unlink(ROOT_PATH . $image_url);
                        }

                        $stmt->execute([$product_id, $image_id]);
                    }
                }

                // Xử lý thêm ảnh mới
                if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                    $upload_dir = PRODUCT_IMG_PATH . $product_id;
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, image_url, is_primary)
                        VALUES (?, ?, ?)
                    ");

                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['images']['name'][$key];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            
                            if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                continue;
                            }

                            $new_name = uniqid() . '.' . $file_ext;
                            $destination = $upload_dir . '/' . $new_name;

                            if (move_uploaded_file($tmp_name, $destination)) {
                                // Kiểm tra xem có ảnh nào không, nếu chưa có thì set là ảnh chính
                                $img_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
                                $img_count_stmt->execute([$product_id]);
                                $is_primary = ($img_count_stmt->fetchColumn() === 0) ? 1 : 0;
                                
                                $image_url = str_replace(ROOT_PATH, '', $destination);
                                $stmt->execute([$product_id, $image_url, $is_primary]);
                            }
                        }
                    }
                }

                $pdo->commit();
                flash('success', 'Cập nhật sản phẩm thành công!');
                redirect('products.php');

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}

include '../includes/navbar.php';
?>

<link rel="stylesheet" href="../css/products_edit.css">

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="row">
                        <div class="col-6">
                            <h6>Sửa sản phẩm</h6>
                        </div>
                        <div class="col-6 text-end">
                            <a href="products.php" class="btn btn-outline-primary btn-sm mb-0">
                                <i class="fas fa-arrow-left"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" id="productForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Thông tin cơ bản -->
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Thông tin cơ bản</h5>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="name" required
                                                value="<?= htmlspecialchars($product['name']) ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Mô tả</label>
                                            <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Giá <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" name="price" required min="0"
                                                    value="<?= $product['price'] ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Số lượng tồn</label>
                                                <input type="number" class="form-control" name="stock" min="0"
                                                    value="<?= $product['stock'] ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Danh mục <span class="text-danger">*</span></label>
                                                <select class="form-select" name="category_id" required>
                                                    <option value="">Chọn danh mục</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?= $cat['category_id'] ?>"
                                                            <?= $product['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Nhà cung cấp <span class="text-danger">*</span></label>
                                                <select class="form-select" name="supplier_id" required>
                                                    <option value="">Chọn nhà cung cấp</option>
                                                    <?php foreach ($suppliers as $sup): ?>
                                                        <option value="<?= $sup['supplier_id'] ?>"
                                                            <?= $product['supplier_id'] == $sup['supplier_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($sup['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Trạng thái</label>
                                            <select class="form-select" name="status">
                                                <option value="active" <?= $product['status'] == 'active' ? 'selected' : '' ?>>
                                                    Đang bán
                                                </option>
                                                <option value="inactive" <?= $product['status'] == 'inactive' ? 'selected' : '' ?>>
                                                    Ngừng bán
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Thuộc tính sản phẩm -->
                                <div class="card mt-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">Thuộc tính sản phẩm</h5>
                                            <button type="button" class="btn btn-primary btn-sm" id="addAttribute">
                                                <i class="fas fa-plus"></i> Thêm thuộc tính
                                            </button>
                                        </div>
                                        
                                        <div id="attributesContainer">
                                            <?php foreach ($attributes as $attr): ?>
                                            <div class="row mb-2 attribute-row">
                                                <div class="col-5">
                                                    <input type="text" class="form-control" name="attributes[][name]" 
                                                        value="<?= htmlspecialchars($attr['attribute_name']) ?>"
                                                        placeholder="Tên thuộc tính">
                                                </div>
                                                <div class="col-5">
                                                    <input type="text" class="form-control" name="attributes[][value]"
                                                        value="<?= htmlspecialchars($attr['attribute_value']) ?>"
                                                        placeholder="Giá trị">
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-outline-danger btn-sm remove-attribute">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <!-- Upload ảnh -->
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Hình ảnh sản phẩm</h5>
                                        
                                        <div class="mb-3">
                                            <label class="form-label d-block">Thêm ảnh mới</label>
                                            <div class="custom-file-input" id="dropzone">
                                                <i class="fas fa-cloud-upload-alt icon"></i>
                                                <span class="text">Kéo thả ảnh vào đây hoặc click để chọn</span>
                                                <input type="file" class="form-control" name="images[]" multiple accept="image/*" id="imageInput">
                                            </div>
                                        </div>

                                        <div id="existingImages" class="row g-2 mt-3">
                                            <h6 class="mb-2">Ảnh hiện tại</h6>
                                            <?php foreach ($images as $img): ?>
                                            <div class="col-6 mb-3">
                                                <div class="border rounded p-2 position-relative img-container">
                                                    <?php 
                                                    // Prepare the correct image path
                                                    $imagePath = $img['image_url'];
                                                    // If it's not an absolute URL, prepend with "../" to correct the path
                                                    if (!empty($imagePath) && strpos($imagePath, 'http') !== 0) {
                                                        $imagePath = '../' . ltrim($imagePath, '/');
                                                    }
                                                    ?>
                                                    <img src="<?= htmlspecialchars($imagePath) ?>" class="img-fluid rounded">
                                                    <?php if ($img['is_primary']): ?>
                                                        <span class="badge bg-primary position-absolute top-0 end-0 m-2">
                                                            <i class="fas fa-star me-1"></i> Ảnh chính
                                                        </span>
                                                    <?php endif; ?>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" name="delete_images[]" 
                                                            value="<?= $img['image_id'] ?>" id="img_<?= $img['image_id'] ?>">
                                                        <label class="form-check-label" for="img_<?= $img['image_id'] ?>">
                                                            <i class="far fa-trash-alt text-danger me-1"></i> Xóa ảnh này
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div id="imagePreview" class="row g-2 mt-3">
                                            <h6 class="mb-2 preview-title" style="display: none;">Ảnh mới</h6>
                                            <!-- Preview ảnh mới sẽ được hiển thị ở đây -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo các hiệu ứng
    initializeFormEffects();
    
    // Xử lý thêm thuộc tính
    initializeAttributeFeatures();
    
    // Xử lý upload và preview hình ảnh
    initializeImageUpload();
    
    // Xử lý validate form
    initializeFormValidation();
});

function initializeFormEffects() {
    // Hiệu ứng cho các input khi focus
    const formControls = document.querySelectorAll('.form-control, .form-select');
    formControls.forEach(input => {
        const formGroup = input.closest('.mb-3');
        if (!formGroup) return;
        
        // Thêm label động khi focus
        input.addEventListener('focus', function() {
            formGroup.classList.add('focused');
            this.classList.add('border-primary');
        });
        
        input.addEventListener('blur', function() {
            formGroup.classList.remove('focused');
            this.classList.remove('border-primary');
        });
        
        // Kiểm tra nếu đã có giá trị
        if (input.value.trim() !== '') {
            formGroup.classList.add('has-value');
        }
        
        input.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                formGroup.classList.add('has-value');
            } else {
                formGroup.classList.remove('has-value');
            }
        });
    });
    
    // Hiệu ứng ripple cho các button
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Hiệu ứng hiển thị cho card khi load trang
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * index);
    });
}

function initializeAttributeFeatures() {
    const attributesContainer = document.getElementById('attributesContainer');
    const addAttributeBtn = document.getElementById('addAttribute');

    // Hiệu ứng thêm thuộc tính với animation
    addAttributeBtn.addEventListener('click', function() {
        const attributeRow = document.createElement('div');
        attributeRow.className = 'row mb-2 attribute-row';
        attributeRow.style.opacity = '0';
        attributeRow.style.transform = 'translateY(20px)';
        attributeRow.innerHTML = `
            <div class="col-5">
                <input type="text" class="form-control" name="attributes[][name]" placeholder="Tên thuộc tính" required>
            </div>
            <div class="col-5">
                <input type="text" class="form-control" name="attributes[][value]" placeholder="Giá trị" required>
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-outline-danger btn-sm remove-attribute">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        attributesContainer.appendChild(attributeRow);
        
        // Hiệu ứng fade in
        setTimeout(() => {
            attributeRow.style.opacity = '1';
            attributeRow.style.transform = 'translateY(0)';
        }, 10);
        
        // Focus vào input đầu tiên
        const firstInput = attributeRow.querySelector('input');
        if (firstInput) firstInput.focus();
    });

    // Xử lý nút xóa thuộc tính với animation
    attributesContainer.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-attribute');
        if (removeBtn) {
            const row = removeBtn.closest('.attribute-row');
            
            // Hiệu ứng fade out
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                row.remove();
            }, 300);
        }
    });
    
    // Thêm sortable để có thể kéo thả sắp xếp thuộc tính
    if (typeof Sortable !== 'undefined') {
        new Sortable(attributesContainer, {
            animation: 150,
            handle: '.attribute-row',
            ghostClass: 'attribute-ghost'
        });
    }
}

function initializeImageUpload() {
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    const dropzone = document.getElementById('dropzone');
    const previewTitle = document.querySelector('.preview-title');

    // Xử lý kéo thả hình ảnh
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropzone.classList.add('border-primary');
    }
    
    function unhighlight() {
        dropzone.classList.remove('border-primary');
    }
    
    // Xử lý khi thả file
    dropzone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        imageInput.files = files;
        handleFiles(files);
    }
    
    // Xử lý khi chọn file thông thường
    imageInput.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    function handleFiles(files) {
        if (files.length > 0) {
            previewTitle.style.display = 'block';
            imagePreview.innerHTML = '';
            
            Array.from(files).forEach((file, index) => {
                if (!file.type.match('image.*')) return;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewContainer = document.createElement('div');
                    previewContainer.className = 'col-6 mb-3';
                    previewContainer.style.opacity = '0';
                    previewContainer.style.transform = 'translateY(20px)';
                    previewContainer.innerHTML = `
                        <div class="border rounded p-2 position-relative img-container">
                            <img src="${e.target.result}" class="img-fluid rounded">
                            <span class="badge bg-info position-absolute top-0 end-0 m-2">
                                <i class="fas fa-plus-circle me-1"></i> Mới
                            </span>
                        </div>
                    `;
                    imagePreview.appendChild(previewContainer);
                    
                    // Hiệu ứng fade in
                    setTimeout(() => {
                        previewContainer.style.opacity = '1';
                        previewContainer.style.transform = 'translateY(0)';
                    }, 50 * index);
                }
                reader.readAsDataURL(file);
            });
        } else {
            previewTitle.style.display = 'none';
        }
    }
    
    // Hiệu ứng hover cho ảnh
    const existingImages = document.getElementById('existingImages');
    
    [existingImages, imagePreview].forEach(container => {
        if (!container) return;
        
        container.addEventListener('mouseover', function(e) {
            const imgContainer = e.target.closest('.img-container');
            if (imgContainer) {
                const img = imgContainer.querySelector('img');
                if (img) {
                    img.style.transform = 'scale(1.05)';
                }
            }
        });
        
        container.addEventListener('mouseout', function(e) {
            const imgContainer = e.target.closest('.img-container');
            if (imgContainer) {
                const img = imgContainer.querySelector('img');
                if (img) {
                    img.style.transform = 'scale(1)';
                }
            }
        });
    });
}

function initializeFormValidation() {
    const form = document.getElementById('productForm');
    
    form.addEventListener('submit', function(e) {
        let hasError = validateForm();
        
        if (hasError) {
            e.preventDefault();
            
            // Hiển thị thông báo lỗi với hiệu ứng
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger mt-3';
            errorAlert.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i> Vui lòng kiểm tra lại thông tin đã nhập';
            
            // Thêm vào đầu form
            form.prepend(errorAlert);
            
            // Tự động xóa sau 5 giây
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    errorAlert.remove();
                }, 300);
            }, 5000);
            
            // Scroll to first error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        } else {
            // Hiển thị loading khi submit
            const submitBtn = form.querySelector('[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Đang lưu...';
            submitBtn.disabled = true;
        }
    });
    
    function validateForm() {
        // Reset validation
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });
        
        let hasError = false;
        
        // Validate tên sản phẩm
        const nameInput = form.querySelector('[name="name"]');
        if (!nameInput.value.trim()) {
            showError(nameInput, 'Vui lòng nhập tên sản phẩm');
            hasError = true;
        }
        
        // Validate giá
        const priceInput = form.querySelector('[name="price"]');
        const price = parseFloat(priceInput.value);
        if (isNaN(price) || price <= 0) {
            showError(priceInput, 'Giá sản phẩm phải lớn hơn 0');
            hasError = true;
        }
        
        // Validate số lượng
        const stockInput = form.querySelector('[name="stock"]');
        const stock = parseInt(stockInput.value);
        if (isNaN(stock) || stock < 0) {
            showError(stockInput, 'Số lượng tồn kho không hợp lệ');
            hasError = true;
        }
        
        // Validate danh mục
        const categoryInput = form.querySelector('[name="category_id"]');
        if (!categoryInput.value) {
            showError(categoryInput, 'Vui lòng chọn danh mục');
            hasError = true;
        }
        
        // Validate nhà cung cấp
        const supplierInput = form.querySelector('[name="supplier_id"]');
        if (!supplierInput.value) {
            showError(supplierInput, 'Vui lòng chọn nhà cung cấp');
            hasError = true;
        }
        
        return hasError;
    }
    
    function showError(input, message) {
        input.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.innerText = message;
        
        const parent = input.parentNode;
        parent.appendChild(errorDiv);
    }
}
</script>

<style>
/* Additional animation styles */
.card {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.5s ease, transform 0.5s ease, box-shadow 0.3s ease;
}

.attribute-row {
    transition: opacity 0.3s ease, transform 0.3s ease, background-color 0.3s ease;
}

.attribute-ghost {
    opacity: 0.5;
    background: #f8f9fa;
}

.btn {
    position: relative;
    overflow: hidden;
}

.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple 0.6s linear;
    pointer-events: none;
    width: 100px;
    height: 100px;
    margin-top: -50px;
    margin-left: -50px;
}

@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

.invalid-feedback {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.is-invalid {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
}

.focused .form-label {
    color: #5e72e4;
    font-weight: 500;
}

/* Image container effects */
#existingImages .img-container, 
#imagePreview .img-container {
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

#existingImages .img-container:hover, 
#imagePreview .img-container:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

#existingImages img, 
#imagePreview img {
    transition: transform 0.5s ease;
}

#existingImages .img-container:hover img, 
#imagePreview .img-container:hover img {
    transform: scale(1.05);
}
</style>

<?php include '../includes/footer.php'; ?>