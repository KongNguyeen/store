<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$error = '';
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lấy thông tin nhà cung cấp
$supplier_id = (int)($_GET['id'] ?? 0);
if (!$supplier_id) {
    flash('error', 'Nhà cung cấp không tồn tại');
    redirect('suppliers.php');
}

$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    flash('error', 'Nhà cung cấp không tồn tại');
    redirect('suppliers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $contact_name = sanitize($_POST['contact_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        
        if (!$name || !$contact_name || !$phone || !$email) {
            $error = 'Vui lòng điền đầy đủ thông tin bắt buộc';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ';
        } elseif (!preg_match('/^[0-9\s\-\+\(\)]+$/', $phone)) {
            $error = 'Số điện thoại không hợp lệ';
        } else {
            try {
                $pdo->beginTransaction();

                // Kiểm tra email đã tồn tại chưa (trừ chính nó)
                $stmt = $pdo->prepare("
                    SELECT supplier_id 
                    FROM suppliers 
                    WHERE email = ? AND supplier_id != ?
                ");
                $stmt->execute([$email, $supplier_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Email đã được sử dụng bởi nhà cung cấp khác');
                }

                // Cập nhật thông tin
                $stmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET name = ?, contact_name = ?, phone = ?, 
                        email = ?, address = ?, updated_at = NOW()
                    WHERE supplier_id = ?
                ");
                $stmt->execute([
                    $name, $contact_name, $phone,
                    $email, $address, $supplier_id
                ]);

                // Xử lý logo
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = ROOT_PATH . 'assets/uploads/suppliers/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file = $_FILES['logo'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        throw new Exception('Logo phải là file ảnh (jpg, jpeg, png)');
                    }

                    if ($file['size'] > MAX_FILE_SIZE) {
                        throw new Exception('Kích thước file không được vượt quá ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
                    }

                    $filename = 'supplier_' . $supplier_id . '_' . time() . '.' . $ext;
                    $destination = $upload_dir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        // Xóa logo cũ nếu có
                        if (isset($supplier['logo']) && $supplier['logo'] && file_exists(ROOT_PATH . $supplier['logo'])) {
                            unlink(ROOT_PATH . $supplier['logo']);
                        }

                        $stmt = $pdo->prepare("
                            UPDATE suppliers 
                            SET logo = ? 
                            WHERE supplier_id = ?
                        ");
                        $stmt->execute([
                            'assets/uploads/suppliers/' . $filename,
                            $supplier_id
                        ]);
                    }
                }

                // Ghi log (chỉ nếu có bảng admin_logs)
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_logs (
                            admin_id,
                            action_type,
                            target_type,
                            target_id,
                            note,
                            created_at
                        ) VALUES (?, 'update', 'suppliers', ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $supplier_id,
                        "Cập nhật nhà cung cấp: $name"
                    ]);
                } catch (Exception $e) {
                    // Bỏ qua lỗi log nếu bảng không tồn tại
                }

                $pdo->commit();
                flash('success', 'Cập nhật nhà cung cấp thành công!');
                redirect('suppliers.php');

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}

include '../includes/navbar.php';
?>

<style>
.supplier-edit-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.edit-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: none;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    animation: slideInUp 0.8s ease-out;
}

.edit-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-header-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 20px 20px 0 0;
    padding: 1.5rem;
    border: none;
}

.form-section {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    animation: fadeInLeft 0.6s ease-out;
}

.form-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.section-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 1.5rem;
    position: relative;
    padding-bottom: 0.5rem;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 2px;
}

.form-control-modern {
    border: 2px solid #e3e8f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #f8f9ff;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    background: white;
    transform: translateY(-1px);
}

.form-control-modern:hover {
    border-color: #a0aec0;
    background: white;
}

.form-label-modern {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.required-star {
    color: #e53e3e;
    margin-left: 2px;
}

.file-upload-area {
    border: 3px dashed #cbd5e0;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    background: #f7fafc;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.file-upload-area:hover {
    border-color: #667eea;
    background: #edf2f7;
    transform: scale(1.02);
}

.file-upload-area.dragover {
    border-color: #667eea;
    background: #e6fffa;
    transform: scale(1.05);
}

.upload-icon {
    font-size: 3rem;
    color: #a0aec0;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.file-upload-area:hover .upload-icon {
    color: #667eea;
    transform: scale(1.1);
}

.btn-modern {
    border-radius: 10px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.btn-secondary-modern {
    background: #e2e8f0;
    color: #4a5568;
}

.btn-secondary-modern:hover {
    background: #cbd5e0;
    transform: translateY(-2px);
}

.logo-preview {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    animation: fadeInRight 0.6s ease-out;
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.logo-preview:hover {
    transform: scale(1.05);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.alert-modern {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
    animation: slideInDown 0.5s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-danger-modern {
    background: linear-gradient(135deg, #feb2b2 0%, #f56565 100%);
    color: white;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.loading-overlay.show {
    opacity: 1;
    visibility: visible;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.form-floating-modern {
    position: relative;
    margin-bottom: 1.5rem;
}

.form-floating-modern .form-control-modern {
    padding: 1rem;
    height: auto;
}

.form-floating-modern label {
    position: absolute;
    top: 1rem;
    left: 1rem;
    font-size: 0.9rem;
    color: #a0aec0;
    transition: all 0.3s ease;
    pointer-events: none;
    background: white;
    padding: 0 0.25rem;
}

.form-floating-modern .form-control-modern:focus + label,
.form-floating-modern .form-control-modern:not(:placeholder-shown) + label {
    top: -0.5rem;
    font-size: 0.75rem;
    color: #667eea;
    font-weight: 600;
}

.success-animation {
    animation: successPulse 0.6s ease-out;
}

@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Responsive */
@media (max-width: 768px) {
    .supplier-edit-container {
        padding: 1rem 0;
    }
    
    .form-section {
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .edit-card {
        margin: 0 0.5rem;
        border-radius: 15px;
    }
}
</style>

<div class="supplier-edit-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <div class="edit-card">
                    <div class="card-header-custom">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>
                                    Chỉnh sửa nhà cung cấp
                                </h4>
                                <p class="mb-0 opacity-75">Cập nhật thông tin nhà cung cấp</p>
                            </div>
                            <div class="col-auto">
                                <a href="suppliers.php" class="btn btn-secondary-modern">
                                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($error): ?>
                            <div class="alert alert-danger-modern"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data" id="supplierForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                            <div class="row g-0">
                                <div class="col-lg-8">
                                    <div class="form-section">
                                        <h5 class="section-title">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Thông tin cơ bản
                                        </h5>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-floating-modern">
                                                    <input type="text" class="form-control form-control-modern" 
                                                           name="name" id="name" placeholder=" " required
                                                           value="<?= htmlspecialchars($_POST['name'] ?? $supplier['name']) ?>">
                                                    <label for="name">Tên nhà cung cấp <span class="required-star">*</span></label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-floating-modern">
                                                    <input type="text" class="form-control form-control-modern" 
                                                           name="contact_name" id="contact_name" placeholder=" " required
                                                           value="<?= htmlspecialchars($_POST['contact_name'] ?? $supplier['contact_name']) ?>">
                                                    <label for="contact_name">Người liên hệ <span class="required-star">*</span></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-floating-modern">
                                                    <input type="tel" class="form-control form-control-modern" 
                                                           name="phone" id="phone" placeholder=" " required
                                                           value="<?= htmlspecialchars($_POST['phone'] ?? $supplier['phone']) ?>">
                                                    <label for="phone">Số điện thoại <span class="required-star">*</span></label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-floating-modern">
                                                    <input type="email" class="form-control form-control-modern" 
                                                           name="email" id="email" placeholder=" " required
                                                           value="<?= htmlspecialchars($_POST['email'] ?? $supplier['email']) ?>">
                                                    <label for="email">Email <span class="required-star">*</span></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-floating-modern">
                                            <textarea class="form-control form-control-modern" 
                                                      name="address" id="address" placeholder=" " rows="3"><?= htmlspecialchars($_POST['address'] ?? $supplier['address']) ?></textarea>
                                            <label for="address">Địa chỉ</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-section">
                                        <h5 class="section-title">
                                            <i class="fas fa-image me-2"></i>
                                            Logo nhà cung cấp
                                        </h5>
                                        
                                        <?php if (isset($supplier['logo']) && $supplier['logo']): ?>
                                            <div class="text-center mb-3">
                                                <div class="logo-preview">
                                                    <img src="<?= htmlspecialchars($supplier['logo']) ?>" 
                                                         class="img-fluid" 
                                                         style="max-height: 200px; width: 100%; object-fit: cover;">
                                                </div>
                                                <small class="text-muted mt-2 d-block">Logo hiện tại</small>
                                            </div>
                                        <?php endif; ?>

                                        <div class="file-upload-area" id="fileUploadArea">
                                            <div class="upload-icon">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                            </div>
                                            <h6>Tải lên logo mới</h6>
                                            <p class="text-muted mb-0">Kéo thả file hoặc click để chọn</p>
                                            <input type="file" class="d-none" name="logo" 
                                                   accept="image/*" id="logoInput">
                                            <small class="text-muted d-block mt-2">
                                                JPG, JPEG, PNG. Tối đa <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB
                                            </small>
                                        </div>

                                        <div id="logoPreview" class="text-center mt-3 d-none">
                                            <div class="logo-preview">
                                                <img src="" class="img-fluid" style="max-height: 200px; width: 100%; object-fit: cover;">
                                            </div>
                                            <small class="text-muted mt-2 d-block">Logo mới</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary-modern me-3" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>
                                        Lưu thay đổi
                                    </button>
                                    <a href="suppliers.php" class="btn btn-secondary-modern">
                                        <i class="fas fa-times me-2"></i>
                                        Hủy bỏ
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const form = document.getElementById('supplierForm');
    const logoInput = document.getElementById('logoInput');
    const logoPreview = document.getElementById('logoPreview');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const submitBtn = document.getElementById('submitBtn');

    // Animation delays for form sections
    const formSections = document.querySelectorAll('.form-section');
    formSections.forEach((section, index) => {
        section.style.animationDelay = `${index * 0.2}s`;
    });

    // File upload drag and drop
    fileUploadArea.addEventListener('click', () => logoInput.click());

    fileUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', () => {
        fileUploadArea.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            logoInput.files = files;
            handleFileSelect();
        }
    });

    // Logo preview
    logoInput.addEventListener('change', handleFileSelect);

    function handleFileSelect() {
        const file = logoInput.files[0];
        if (file) {
            // Validate file
            if (!file.type.match('image.*')) {
                showNotification('Vui lòng chọn file ảnh!', 'error');
                logoInput.value = '';
                return;
            }

            if (file.size > <?= MAX_FILE_SIZE ?>) {
                showNotification(`Kích thước file không được vượt quá <?= MAX_FILE_SIZE / 1024 / 1024 ?>MB!`, 'error');
                logoInput.value = '';
                return;
            }

            // Preview
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreview.querySelector('img').src = e.target.result;
                logoPreview.classList.remove('d-none');
                
                // Add preview animation
                logoPreview.style.opacity = '0';
                logoPreview.style.transform = 'scale(0.8)';
                
                setTimeout(() => {
                    logoPreview.style.transition = 'all 0.3s ease';
                    logoPreview.style.opacity = '1';
                    logoPreview.style.transform = 'scale(1)';
                }, 100);
            }
            reader.readAsDataURL(file);

            // Update upload area text
            const uploadIcon = fileUploadArea.querySelector('.upload-icon i');
            const uploadTitle = fileUploadArea.querySelector('h6');
            const uploadDesc = fileUploadArea.querySelector('p');
            
            uploadIcon.className = 'fas fa-check-circle';
            uploadIcon.style.color = '#48bb78';
            uploadTitle.textContent = 'File đã chọn';
            uploadDesc.textContent = file.name;
            fileUploadArea.style.borderColor = '#48bb78';
            fileUploadArea.style.background = '#f0fff4';
        } else {
            logoPreview.classList.add('d-none');
            resetUploadArea();
        }
    }

    function resetUploadArea() {
        const uploadIcon = fileUploadArea.querySelector('.upload-icon i');
        const uploadTitle = fileUploadArea.querySelector('h6');
        const uploadDesc = fileUploadArea.querySelector('p');
        
        uploadIcon.className = 'fas fa-cloud-upload-alt';
        uploadIcon.style.color = '';
        uploadTitle.textContent = 'Tải lên logo mới';
        uploadDesc.textContent = 'Kéo thả file hoặc click để chọn';
        fileUploadArea.style.borderColor = '';
        fileUploadArea.style.background = '';
    }

    // Form validation and submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }

        // Show loading
        showLoading();
        
        // Simulate processing delay for better UX
        setTimeout(() => {
            form.submit();
        }, 500);
    });

    function validateForm() {
        let isValid = true;
        const errors = [];

        // Required fields
        const requiredFields = [
            { name: 'name', label: 'Tên nhà cung cấp' },
            { name: 'contact_name', label: 'Người liên hệ' },
            { name: 'phone', label: 'Số điện thoại' },
            { name: 'email', label: 'Email' }
        ];

        requiredFields.forEach(field => {
            const input = form.querySelector(`[name="${field.name}"]`);
            const value = input.value.trim();
            
            if (!value) {
                errors.push(`${field.label} không được để trống`);
                highlightError(input);
                isValid = false;
            } else {
                removeError(input);
            }
        });

        // Email validation
        const email = form.querySelector('[name="email"]').value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (email && !emailRegex.test(email)) {
            errors.push('Email không hợp lệ');
            highlightError(form.querySelector('[name="email"]'));
            isValid = false;
        }

        // Phone validation
        const phone = form.querySelector('[name="phone"]').value.trim();
        const phoneRegex = /^[0-9\s\-\+\(\)]+$/;
        if (phone && !phoneRegex.test(phone)) {
            errors.push('Số điện thoại không hợp lệ');
            highlightError(form.querySelector('[name="phone"]'));
            isValid = false;
        }

        // Show errors
        if (!isValid) {
            showNotification(errors.join('<br>'), 'error');
        }

        return isValid;
    }

    function highlightError(input) {
        input.style.borderColor = '#e53e3e';
        input.style.boxShadow = '0 0 0 0.2rem rgba(229, 62, 62, 0.25)';
        
        // Add shake animation
        input.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            input.style.animation = '';
        }, 500);
    }

    function removeError(input) {
        input.style.borderColor = '';
        input.style.boxShadow = '';
    }

    // Real-time validation
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });

        input.addEventListener('input', function() {
            if (this.style.borderColor === 'rgb(229, 62, 62)') {
                removeError(this);
            }
        });
    });

    function validateField(input) {
        const value = input.value.trim();
        const isRequired = input.hasAttribute('required');

        if (isRequired && !value) {
            highlightError(input);
            return false;
        }

        if (input.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                highlightError(input);
                return false;
            }
        }

        if (input.type === 'tel' && value) {
            const phoneRegex = /^[0-9\s\-\+\(\)]+$/;
            if (!phoneRegex.test(value)) {
                highlightError(input);
                return false;
            }
        }

        removeError(input);
        return true;
    }

    // Utility functions
    function showLoading() {
        loadingOverlay.classList.add('show');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...';
    }

    function hideLoading() {
        loadingOverlay.classList.remove('show');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Lưu thay đổi';
    }

    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification-toast alert alert-${type === 'error' ? 'danger' : type} alert-modern`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease-out;
        `;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
    `;
    document.head.appendChild(style);

    // Success animation for save button
    function animateSuccess() {
        submitBtn.classList.add('success-animation');
        setTimeout(() => {
            submitBtn.classList.remove('success-animation');
        }, 600);
    }

    // Form auto-save (optional - saves to localStorage)
    let autoSaveTimeout;
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                saveFormData();
            }, 1000);
        });
    });

    function saveFormData() {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            if (key !== 'csrf_token' && key !== 'logo') {
                data[key] = value;
            }
        }
        localStorage.setItem('supplier_edit_form', JSON.stringify(data));
    }

    function loadFormData() {
        const savedData = localStorage.getItem('supplier_edit_form');
        if (savedData) {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input && input.type !== 'file') {
                    input.value = data[key];
                }
            });
        }
    }

    // Clear auto-save data on successful submit
    form.addEventListener('submit', function() {
        localStorage.removeItem('supplier_edit_form');
    });

    // Show success message if redirected back
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success')) {
        showNotification('Cập nhật nhà cung cấp thành công!', 'success');
    }
});
</script>

<?php include '../includes/footer.php'; ?>