<?php
// Xử lý khi submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    $pdo = getPDO();
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $discount_percent = $_POST['discount_percent'] ?? '';
    $min_order_amount = $_POST['min_order_amount'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $active = isset($_POST['active']) ? 1 : 0;

    // Validate dữ liệu
    $errors = [];
    if (empty($code)) $errors[] = 'Mã khuyến mãi không được để trống.';
    if (strlen($code) > 50) $errors[] = 'Mã khuyến mãi không được vượt quá 50 ký tự.';
    if (!is_numeric($discount_percent) || $discount_percent <= 0 || $discount_percent > 100) $errors[] = 'Phần trăm giảm giá phải là số từ 1 đến 100.';
    if (!empty($min_order_amount) && (!is_numeric($min_order_amount) || $min_order_amount < 0)) $errors[] = 'Giá trị đơn hàng tối thiểu phải là số không âm.';
    if (empty($start_date) || empty($end_date)) $errors[] = 'Vui lòng chọn ngày bắt đầu và kết thúc.';
    if ($start_date > $end_date) $errors[] = 'Ngày bắt đầu phải trước ngày kết thúc.';

    if (empty($errors)) {
        $sql = "INSERT INTO promotions (code, description, discount_percent, min_order_amount, start_date, end_date, active) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $code,
            $description,
            $discount_percent,
            $min_order_amount !== '' ? $min_order_amount : null,
            $start_date,
            $end_date,
            $active
        ]);
        if ($result) {
            $success_message = 'Thêm khuyến mãi thành công!';
        } else {
            $errors[] = 'Lỗi khi thêm khuyến mãi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm khuyến mãi mới</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/promotions_add.css">
  
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="container">
        <div class="header">
            <a href="promotions.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Quay lại
            </a>
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i>
                Thêm khuyến mãi mới
            </h1>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= $error ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-tips">
            <h4><i class="fas fa-lightbulb"></i> Mẹo tạo khuyến mãi hiệu quả</h4>
            <ul>
                <li>Tên khuyến mãi nên ngắn gọn và dễ nhớ</li>
                <li>Mô tả chi tiết điều kiện áp dụng</li>
                <li>Giá trị giảm hợp lý để thu hút khách hàng</li>
                <li>Thời gian khuyến mãi phù hợp với chiến lược kinh doanh</li>
            </ul>
        </div>

        <div class="form-container">
            <form method="POST" id="promotionForm" novalidate>
                <div class="form-group">
                    <label for="code" class="form-label">
                        <i class="fas fa-barcode"></i> Mã khuyến mãi
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="code" name="code" 
                               value="<?= isset($_POST['code']) ? htmlspecialchars($_POST['code']) : '' ?>"
                               placeholder="Ví dụ: SUMMER2025" maxlength="50" required>
                        <i class="input-icon fas fa-barcode"></i>
                    </div>
                    <div class="field-validation" id="codeValidation"></div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left"></i> Mô tả
                    </label>
                    <textarea class="form-control" id="description" name="description" 
                              placeholder="Mô tả chi tiết về khuyến mãi..." maxlength="500"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                    <div class="character-count">
                        <span id="descriptionCount">0</span>/500 ký tự
                    </div>
                    <div class="field-validation" id="descriptionValidation"></div>
                </div>

                <div class="form-group">
                    <label for="discount_percent" class="form-label">
                        <i class="fas fa-percentage"></i> Phần trăm giảm giá (%)
                    </label>
                    <div class="discount-input">
                        <input type="number" class="form-control" id="discount_percent" name="discount_percent" 
                               value="<?= isset($_POST['discount_percent']) ? htmlspecialchars($_POST['discount_percent']) : '' ?>"
                               placeholder="10" min="1" max="100" required>
                    </div>
                    <div class="field-validation" id="discountPercentValidation"></div>
                </div>

                <div class="form-group">
                    <label for="min_order_amount" class="form-label">
                        <i class="fas fa-money-bill-wave"></i> Giá trị đơn hàng tối thiểu (VNĐ)
                    </label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" id="min_order_amount" name="min_order_amount" 
                               value="<?= isset($_POST['min_order_amount']) ? htmlspecialchars($_POST['min_order_amount']) : '' ?>"
                               placeholder="0" min="0">
                        <i class="input-icon fas fa-money-bill-wave"></i>
                    </div>
                    <div class="field-validation" id="minOrderAmountValidation"></div>
                </div>

                <div class="date-range">
                    <div class="form-group">
                        <label for="start_date" class="form-label">
                            <i class="fas fa-calendar-alt"></i> Ngày bắt đầu
                        </label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?= isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : '' ?>" required>
                        <div class="field-validation" id="startDateValidation"></div>
                    </div>

                    <div class="form-group">
                        <label for="end_date" class="form-label">
                            <i class="fas fa-calendar-check"></i> Ngày kết thúc
                        </label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?= isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : '' ?>" required>
                        <div class="field-validation" id="endDateValidation"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-toggle-on"></i> Kích hoạt
                    </label>
                    <input type="checkbox" id="active" name="active" <?= isset($_POST['active']) ? 'checked' : '' ?>>
                    <label for="active">Khuyến mãi đang hoạt động</label>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-plus-circle"></i>
                    Tạo khuyến mãi
                </button>
            </form>
        </div>
    </div>

    <script>
        class PromotionFormManager {
            constructor() {
                this.form = document.getElementById('promotionForm');
                this.progressFill = document.getElementById('progressFill');
                this.submitBtn = document.getElementById('submitBtn');
                this.loadingOverlay = document.getElementById('loadingOverlay');

                this.fields = {
                    code: document.getElementById('code'),
                    description: document.getElementById('description'),
                    discount_percent: document.getElementById('discount_percent'),
                    min_order_amount: document.getElementById('min_order_amount'),
                    start_date: document.getElementById('start_date'),
                    end_date: document.getElementById('end_date'),
                    active: document.getElementById('active')
                };

                this.validations = {
                    code: document.getElementById('codeValidation'),
                    description: document.getElementById('descriptionValidation'),
                    discount_percent: document.getElementById('discountPercentValidation'),
                    min_order_amount: document.getElementById('minOrderAmountValidation'),
                    start_date: document.getElementById('startDateValidation'),
                    end_date: document.getElementById('endDateValidation')
                };

                this.init();
            }

            init() {
                // Set minimum date to today
                const today = new Date().toISOString().split('T')[0];
                this.fields.start_date.min = today;
                this.fields.end_date.min = today;

                // Add event listeners
                Object.keys(this.fields).forEach(fieldName => {
                    if (fieldName === 'active') return; // skip checkbox for input validation
                    this.fields[fieldName].addEventListener('input', () => {
                        this.validateField(fieldName);
                        this.updateProgress();
                    });

                    this.fields[fieldName].addEventListener('blur', () => {
                        this.validateField(fieldName);
                    });
                });

                // Character counter for description
                this.fields.description.addEventListener('input', () => {
                    this.updateCharacterCount();
                });

                // Form submission
                this.form.addEventListener('submit', (e) => {
                    this.handleSubmit(e);
                });

                // Auto-update end date when start date changes
                this.fields.start_date.addEventListener('change', () => {
                    if (this.fields.start_date.value) {
                        this.fields.end_date.min = this.fields.start_date.value;
                        if (this.fields.end_date.value < this.fields.start_date.value) {
                            this.fields.end_date.value = '';
                        }
                    }
                });

                // Initial validation
                this.updateProgress();
                this.updateCharacterCount();
            }

            validateField(fieldName) {
                const field = this.fields[fieldName];
                const validation = this.validations[fieldName];
                let isValid = false;
                let message = '';

                switch(fieldName) {
                    case 'code':
                        if (field.value.trim() === '') {
                            message = 'Mã khuyến mãi không được để trống';
                        } else if (field.value.length > 50) {
                            message = 'Mã khuyến mãi không được vượt quá 50 ký tự';
                        } else {
                            isValid = true;
                            message = 'Mã hợp lệ';
                        }
                        break;

                    case 'description':
                        if (field.value.length > 500) {
                            message = 'Mô tả không được vượt quá 500 ký tự';
                        } else {
                            isValid = true;
                            message = field.value.length > 0 ? 'Mô tả hợp lệ' : '';
                        }
                        break;

                    case 'discount_percent':
                        const percent = parseFloat(field.value);
                        if (field.value === '') {
                            message = 'Phần trăm giảm giá không được để trống';
                        } else if (isNaN(percent) || percent <= 0 || percent > 100) {
                            message = 'Phần trăm giảm giá phải từ 1 đến 100';
                        } else {
                            isValid = true;
                            message = 'Phần trăm hợp lệ';
                        }
                        break;

                    case 'min_order_amount':
                        if (field.value !== '' && (isNaN(field.value) || parseFloat(field.value) < 0)) {
                            message = 'Giá trị đơn hàng tối thiểu phải là số không âm';
                        } else {
                            isValid = true;
                            message = 'Giá trị hợp lệ';
                        }
                        break;

                    case 'start_date':
                        if (field.value === '') {
                            message = 'Vui lòng chọn ngày bắt đầu';
                        } else {
                            const startDate = new Date(field.value);
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            if (startDate < today) {
                                message = 'Ngày bắt đầu không được trước hôm nay';
                            } else {
                                isValid = true;
                                message = 'Ngày hợp lệ';
                            }
                        }
                        break;

                    case 'end_date':
                        if (field.value === '') {
                            message = 'Vui lòng chọn ngày kết thúc';
                        } else if (this.fields.start_date.value && field.value <= this.fields.start_date.value) {
                            message = 'Ngày kết thúc phải sau ngày bắt đầu';
                        } else {
                            isValid = true;
                            message = 'Ngày hợp lệ';
                        }
                        break;
                }

                // Update field appearance
                field.classList.remove('error', 'success');
                if (field.value !== '') {
                    field.classList.add(isValid ? 'success' : 'error');
                }

                // Update validation message
                if (validation) {
                    validation.className = `field-validation ${isValid ? 'success' : 'error'}`;
                    validation.innerHTML = message ? `<i class="fas fa-${isValid ? 'check' : 'times'}"></i> ${message}` : '';
                }

                return isValid;
            }

            updateProgress() {
                let filledFields = 0;
                const totalFields = Object.keys(this.fields).length - 1; // exclude active

                Object.keys(this.fields).forEach(fieldName => {
                    if (fieldName === 'active') return;
                    if (this.fields[fieldName].value.trim() !== '') {
                        filledFields++;
                    }
                });

                const progress = (filledFields / totalFields) * 100;
                this.progressFill.style.width = progress + '%';
            }

            updateCharacterCount() {
                const count = this.fields.description.value.length;
                document.getElementById('descriptionCount').textContent = count;

                const counter = document.querySelector('.character-count');
                if (count > 450) {
                    counter.style.color = '#dc3545';
                } else if (count > 400) {
                    counter.style.color = '#ffc107';
                } else {
                    counter.style.color = '#8e9aaf';
                }
            }

            validateForm() {
                let isFormValid = true;

                Object.keys(this.fields).forEach(fieldName => {
                    if (fieldName === 'active') return;
                    if (!this.validateField(fieldName)) {
                        isFormValid = false;
                    }
                });

                return isFormValid;
            }

            handleSubmit(e) {
                e.preventDefault();

                if (!this.validateForm()) {
                    this.showError('Vui lòng kiểm tra lại thông tin!');
                    return;
                }

                this.showLoading();
                this.submitBtn.disabled = true;

                // Simulate processing time
                setTimeout(() => {
                    this.form.submit();
                }, 1000);
            }

            showLoading() {
                this.loadingOverlay.style.display = 'flex';
            }

            hideLoading() {
                this.loadingOverlay.style.display = 'none';
            }

            showError(message) {
                // Create temporary error alert
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger';
                alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;

                const container = document.querySelector('.container');
                const formContainer = document.querySelector('.form-container');
                container.insertBefore(alert, formContainer);

                // Remove after 5 seconds
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new PromotionFormManager();
        });

        // Add smooth animations for form interactions
        document.addEventListener('DOMContentLoaded', () => {
            // Animate form fields on focus
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                control.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Add ripple effect to submit button
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255,255,255,0.6);
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    transform: scale(0);
                    animation: ripple 0.6s ease-out;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Add ripple animation CSS
        const rippleCSS = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        
        const style = document.createElement('style');
        style.textContent = rippleCSS;
        document.head.appendChild(style);
    </script>
</body>
</html>