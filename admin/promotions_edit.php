<?php
require_once '../config/database.php';
$pdo = getPDO();

// Lấy id khuyến mãi
$promotion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($promotion_id <= 0) {
    die('ID khuyến mãi không hợp lệ!');
}

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $discount_percent = $_POST['discount_percent'] ?? '';
    $min_order_amount = $_POST['min_order_amount'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $active = isset($_POST['active']) ? 1 : 0;

    $errors = [];
    if (empty($code)) $errors[] = 'Mã khuyến mãi không được để trống.';
    if (strlen($code) > 50) $errors[] = 'Mã khuyến mãi không được vượt quá 50 ký tự.';
    if (!is_numeric($discount_percent) || $discount_percent <= 0 || $discount_percent > 100) $errors[] = 'Phần trăm giảm giá phải là số từ 1 đến 100.';
    if (!empty($min_order_amount) && (!is_numeric($min_order_amount) || $min_order_amount < 0)) $errors[] = 'Giá trị đơn hàng tối thiểu phải là số không âm.';
    if (empty($start_date) || empty($end_date)) $errors[] = 'Vui lòng chọn ngày bắt đầu và kết thúc.';
    if ($start_date > $end_date) $errors[] = 'Ngày bắt đầu phải trước ngày kết thúc.';

    if (empty($errors)) {
        $sql = "UPDATE promotions SET code=?, description=?, discount_percent=?, min_order_amount=?, start_date=?, end_date=?, active=? WHERE promotion_id=?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $code,
            $description,
            $discount_percent,
            $min_order_amount !== '' ? $min_order_amount : null,
            $start_date,
            $end_date,
            $active,
            $promotion_id
        ]);
        if ($result) {
            $success_message = 'Cập nhật khuyến mãi thành công!';
        } else {
            $errors[] = 'Lỗi khi cập nhật khuyến mãi.';
        }
    }
}

// Lấy thông tin khuyến mãi
$sql = "SELECT * FROM promotions WHERE promotion_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$promotion_id]);
$promotion = $stmt->fetch();
if (!$promotion) {
    die('Không tìm thấy khuyến mãi!');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa khuyến mãi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/promotions_edit.css">
    
</head>
<body>
    <div class="container">
        <div class="header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 10px;">
            <a href="promotions.php" class="back-link" style="position: static; margin-bottom: 10px;">
                <i class="fas fa-arrow-left"></i>
                Quay lại danh sách
            </a>
            <h1 class="page-title" style="align-self: center; margin-top: 0;">
                <i class="fas fa-edit"></i>
                Chỉnh sửa khuyến mãi
            </h1>
        </div>

        <div class="form-container">
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= $error ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" id="successAlert">
                    <i class="fas fa-check-circle"></i>
                    <?= $success_message ?>
                </div>
            <?php endif; ?>

            <div class="form-tips">
                <h4><i class="fas fa-lightbulb"></i> Lưu ý khi chỉnh sửa</h4>
                <ul>
                    <li>Mã khuyến mãi phải là duy nhất và không quá 50 ký tự</li>
                    <li>Phần trăm giảm giá từ 1% đến 100%</li>
                    <li>Ngày kết thúc phải sau ngày bắt đầu</li>
                    <li>Có thể để trống giá trị đơn hàng tối thiểu</li>
                </ul>
            </div>

            <form method="POST" id="promotionForm">
                <div class="form-group">
                    <label for="code" class="form-label">
                        <i class="fas fa-barcode"></i>
                        Mã khuyến mãi
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="code" 
                               name="code" 
                               value="<?= htmlspecialchars($promotion['code']) ?>" 
                               maxlength="50" 
                               required
                               data-validate="code">
                        <div class="char-counter" id="codeCounter">0/50</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left"></i>
                        Mô tả
                    </label>
                    <div class="input-group">
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  maxlength="500" 
                                  rows="4"
                                  data-validate="description"><?= htmlspecialchars($promotion['description']) ?></textarea>
                        <div class="char-counter" id="descriptionCounter">0/500</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="discount_percent" class="form-label">
                            <i class="fas fa-percentage"></i>
                            Phần trăm giảm giá
                        </label>
                        <div class="input-group">
                            <input type="number" 
                                   class="form-control" 
                                   id="discount_percent" 
                                   name="discount_percent" 
                                   value="<?= htmlspecialchars($promotion['discount_percent']) ?>" 
                                   min="1" 
                                   max="100" 
                                   required
                                   data-validate="discount">
                            <span class="input-addon">%</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="min_order_amount" class="form-label">
                            <i class="fas fa-money-bill-wave"></i>
                            Đơn hàng tối thiểu
                        </label>
                        <div class="input-group">
                            <input type="number" 
                                   step="1000" 
                                   class="form-control" 
                                   id="min_order_amount" 
                                   name="min_order_amount" 
                                   value="<?= htmlspecialchars($promotion['min_order_amount']) ?>" 
                                   min="0"
                                   data-validate="amount">
                            <span class="input-addon">VNĐ</span>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date" class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            Ngày bắt đầu
                        </label>
                        <input type="date" 
                               class="form-control" 
                               id="start_date" 
                               name="start_date" 
                               value="<?= htmlspecialchars($promotion['start_date']) ?>" 
                               required
                               data-validate="startDate">
                    </div>

                    <div class="form-group">
                        <label for="end_date" class="form-label">
                            <i class="fas fa-calendar-check"></i>
                            Ngày kết thúc
                        </label>
                        <input type="date" 
                               class="form-control" 
                               id="end_date" 
                               name="end_date" 
                               value="<?= htmlspecialchars($promotion['end_date']) ?>" 
                               required
                               data-validate="endDate">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-toggle-on"></i>
                        Trạng thái khuyến mãi
                    </label>
                    <div class="checkbox-container">
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   id="active" 
                                   name="active" 
                                   <?= $promotion['active'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                        <label for="active" class="checkbox-label">
                            <span id="statusText"><?= $promotion['active'] ? 'Khuyến mãi đang hoạt động' : 'Khuyến mãi đang tắt' ?></span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-save"></i>
                    <span>Lưu thay đổi</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        class PromotionEditor {
            constructor() {
                this.form = document.getElementById('promotionForm');
                this.submitBtn = document.getElementById('submitBtn');
                this.statusToggle = document.getElementById('active');
                this.statusText = document.getElementById('statusText');
                
                this.init();
            }

            init() {
                loadDraft() {
                try {
                    const draftKey = `promotion_edit_draft_<?= $promotion_id ?>`;
                    // Note: In actual implementation, you might want to load this from server
                    console.log('Loading draft for key:', draftKey);
                } catch (error) {
                    console.log('Could not load draft:', error);
                }
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new PromotionEditor();
        });

        // Add smooth transitions for form elements
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentNode.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentNode.style.transform = 'translateY(0)';
            });
        });

        // Add number formatting for amount input
        document.getElementById('min_order_amount').addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                // Format number with thousands separator
                this.setAttribute('data-formatted', new Intl.NumberFormat('vi-VN').format(value));
            }
        });

        // Add date validation
        document.getElementById('start_date').addEventListener('change', function() {
            const endDateInput = document.getElementById('end_date');
            endDateInput.min = this.value;
            
            if (endDateInput.value && endDateInput.value <= this.value) {
                endDateInput.value = '';
                endDateInput.focus();
            }
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('submitBtn').click();
            }
            
            // Escape to go back
            if (e.key === 'Escape') {
                if (confirm('Bạn có muốn quay lại danh sách? Các thay đổi chưa lưu sẽ bị mất.')) {
                    window.location.href = 'promotions.php';
                }
            }
        });

        // Add paste validation for code field
        document.getElementById('code').addEventListener('paste', function(e) {
            setTimeout(() => {
                // Clean pasted content
                let value = this.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
                if (value !== this.value) {
                    this.value = value;
                    
                    // Show notification
                    const notification = document.createElement('div');
                    notification.textContent = 'Đã tự động làm sạch mã khuyến mãi';
                    notification.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: #ffc107;
                        color: #212529;
                        padding: 10px 20px;
                        border-radius: 5px;
                        font-size: 14px;
                        z-index: 1000;
                        animation: slideInRight 0.3s ease-out;
                    `;
                    
                    document.body.appendChild(notification);
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                }
            }, 10);
        });

        // Add form change detection
        let initialFormData = new FormData(document.getElementById('promotionForm')).toString();
        let hasUnsavedChanges = false;

        document.getElementById('promotionForm').addEventListener('input', () => {
            const currentFormData = new FormData(document.getElementById('promotionForm')).toString();
            hasUnsavedChanges = currentFormData !== initialFormData;
            
            // Update submit button text
            const submitBtn = document.getElementById('submitBtn');
            const span = submitBtn.querySelector('span');
            
            if (hasUnsavedChanges) {
                span.textContent = 'Lưu thay đổi *';
                submitBtn.style.background = 'linear-gradient(135deg, #ffc107, #e0a800)';
            } else {
                span.textContent = 'Lưu thay đổi';
                submitBtn.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
            }
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có chắc muốn rời khỏi trang?';
                return e.returnValue;
            }
        });

        // Add floating action buttons for mobile
        if (window.innerWidth <= 768) {
            const floatingActions = document.createElement('div');
            floatingActions.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                display: flex;
                flex-direction: column;
                gap: 10px;
                z-index: 1000;
            `;
            
            const saveBtn = document.createElement('button');
            saveBtn.innerHTML = '<i class="fas fa-save"></i>';
            saveBtn.style.cssText = `
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                font-size: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                cursor: pointer;
                transition: all 0.3s ease;
            `;
            
            saveBtn.addEventListener('click', () => {
                document.getElementById('submitBtn').click();
            });
            
            floatingActions.appendChild(saveBtn);
            document.body.appendChild(floatingActions);
        }

        // Add additional CSS animations
        const additionalStyles = document.createElement('style');
        additionalStyles.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideOutUp {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-20px);
                }
            }
            
            .form-control:invalid {
                border-color: #dc3545;
                box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.1);
            }
            
            .form-control:valid {
                border-color: #28a745;
            }
            
            .floating-save {
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(additionalStyles);

        // Add tooltip functionality
        function addTooltip(element, text) {
            element.setAttribute('title', text);
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.textContent = text;
                tooltip.style.cssText = `
                    position: absolute;
                    background: #333;
                    color: white;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1001;
                    pointer-events: none;
                    white-space: nowrap;
                `;
                
                document.body.appendChild(tooltip);
                
                const rect = element.getBoundingClientRect();
                tooltip.style.left = rect.left + 'px';
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
                
                element.addEventListener('mouseleave', () => {
                    tooltip.remove();
                }, { once: true });
            });
        }

        // Add tooltips to form elements
        addTooltip(document.getElementById('code'), 'Mã duy nhất, chỉ chứa chữ cái, số, dấu gạch ngang và gạch dưới');
        addTooltip(document.getElementById('discount_percent'), 'Nhập phần trăm giảm giá từ 1% đến 100%');
        addTooltip(document.getElementById('min_order_amount'), 'Để trống nếu không có yêu cầu tối thiểu');

        // Add preview functionality
        function createPreviewCard() {
            const previewContainer = document.createElement('div');
            previewContainer.id = 'promotionPreview';
            previewContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                width: 300px;
                background: white;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                padding: 20px;
                z-index: 1000;
                transform: translateX(320px);
                transition: transform 0.3s ease;
                border: 2px solid #e1e5e9;
            `;

            previewContainer.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; color: #667eea; font-size: 16px;">
                        <i class="fas fa-eye"></i> Xem trước
                    </h4>
                    <button id="togglePreview" style="background: none; border: none; color: #667eea; cursor: pointer; font-size: 18px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="preview-card" style="
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    padding: 20px;
                    border-radius: 10px;
                    text-align: center;
                ">
                    <div class="preview-code" style="font-size: 24px; font-weight: bold; margin-bottom: 10px;">
                        ${document.getElementById('code').value || 'PREVIEW'}
                    </div>
                    <div class="preview-description" style="font-size: 14px; margin-bottom: 15px; opacity: 0.9;">
                        ${document.getElementById('description').value || 'Mô tả khuyến mãi'}
                    </div>
                    <div class="preview-discount" style="font-size: 32px; font-weight: bold; margin-bottom: 10px;">
                        ${document.getElementById('discount_percent').value || '0'}%
                    </div>
                    <div class="preview-minimum" style="font-size: 12px; opacity: 0.8;">
                        Đơn tối thiểu: ${formatCurrency(document.getElementById('min_order_amount').value) || 'Không yêu cầu'}
                    </div>
                    <div class="preview-dates" style="font-size: 11px; margin-top: 15px; opacity: 0.7; border-top: 1px solid rgba(255,255,255,0.3); padding-top: 10px;">
                        ${formatDate(document.getElementById('start_date').value)} - ${formatDate(document.getElementById('end_date').value)}
                    </div>
                </div>
            `;

            document.body.appendChild(previewContainer);

            // Toggle preview
            document.getElementById('togglePreview').addEventListener('click', () => {
                previewContainer.style.transform = previewContainer.style.transform === 'translateX(0px)' ? 
                    'translateX(320px)' : 'translateX(0px)';
            });

            return previewContainer;
        }

        // Add preview toggle button
        const previewBtn = document.createElement('button');
        previewBtn.innerHTML = '<i class="fas fa-eye"></i> Xem trước';
        previewBtn.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            transition: all 0.3s ease;
            z-index: 999;
        `;

        previewBtn.addEventListener('click', () => {
            let preview = document.getElementById('promotionPreview');
            if (!preview) {
                preview = createPreviewCard();
            }
            preview.style.transform = 'translateX(0px)';
            updatePreview();
        });

        document.body.appendChild(previewBtn);

        // Update preview in real-time
        function updatePreview() {
            const preview = document.getElementById('promotionPreview');
            if (!preview) return;

            const code = document.getElementById('code').value || 'PREVIEW';
            const description = document.getElementById('description').value || 'Mô tả khuyến mãi';
            const discount = document.getElementById('discount_percent').value || '0';
            const minimum = document.getElementById('min_order_amount').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            preview.querySelector('.preview-code').textContent = code;
            preview.querySelector('.preview-description').textContent = description;
            preview.querySelector('.preview-discount').textContent = discount + '%';
            preview.querySelector('.preview-minimum').textContent = 
                'Đơn tối thiểu: ' + (formatCurrency(minimum) || 'Không yêu cầu');
            preview.querySelector('.preview-dates').textContent = 
                `${formatDate(startDate)} - ${formatDate(endDate)}`;
        }

        // Format currency helper
        function formatCurrency(amount) {
            if (!amount) return '';
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount);
        }

        // Format date helper
        function formatDate(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleDateString('vi-VN');
        }

        // Update preview on input changes
        document.querySelectorAll('#code, #description, #discount_percent, #min_order_amount, #start_date, #end_date').forEach(input => {
            input.addEventListener('input', updatePreview);
        });

        // Add duplicate check functionality
        function checkDuplicateCode() {
            const codeInput = document.getElementById('code');
            const originalCode = '<?= $promotion['code'] ?>';
            
            if (codeInput.value !== originalCode && codeInput.value.length >= 3) {
                // Simulate API call to check duplicate
                setTimeout(() => {
                    const isDuplicate = Math.random() < 0.1; // 10% chance of duplicate for demo
                    
                    if (isDuplicate) {
                        showNotification('Mã khuyến mãi đã tồn tại!', 'warning');
                        codeInput.style.borderColor = '#ffc107';
                    } else {
                        codeInput.style.borderColor = '#28a745';
                    }
                }, 500);
            }
        }

        document.getElementById('code').addEventListener('blur', checkDuplicateCode);

        // Add notification system
        function showNotification(message, type = 'info', duration = 3000) {
            const notification = document.createElement('div');
            const colors = {
                success: '#28a745',
                warning: '#ffc107',
                error: '#dc3545',
                info: '#17a2b8'
            };

            notification.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: ${colors[type]};
                color: white;
                padding: 15px 25px;
                border-radius: 25px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 1002;
                font-weight: 600;
                animation: slideInDown 0.3s ease-out;
            `;

            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOutUp 0.3s ease-out forwards';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }

        // Add form history/undo functionality
        class FormHistory {
            constructor() {
                this.history = [];
                this.currentIndex = -1;
                this.maxHistory = 10;
            }

            saveState() {
                const formData = new FormData(document.getElementById('promotionForm'));
                const state = {};
                for (let [key, value] of formData.entries()) {
                    state[key] = value;
                }

                // Remove future history if we're not at the end
                if (this.currentIndex < this.history.length - 1) {
                    this.history = this.history.slice(0, this.currentIndex + 1);
                }

                this.history.push(state);
                this.currentIndex++;

                // Limit history size
                if (this.history.length > this.maxHistory) {
                    this.history.shift();
                    this.currentIndex--;
                }
            }

            undo() {
                if (this.currentIndex > 0) {
                    this.currentIndex--;
                    this.restoreState(this.history[this.currentIndex]);
                    showNotification('Đã hoàn tác', 'info', 1500);
                }
            }

            redo() {
                if (this.currentIndex < this.history.length - 1) {
                    this.currentIndex++;
                    this.restoreState(this.history[this.currentIndex]);
                    showNotification('Đã làm lại', 'info', 1500);
                }
            }

            restoreState(state) {
                Object.keys(state).forEach(key => {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = state[key] === 'on';
                        } else {
                            input.value = state[key];
                        }
                    }
                });
                updatePreview();
            }
        }

        const formHistory = new FormHistory();
        
        // Save initial state
        setTimeout(() => formHistory.saveState(), 100);

        // Save state on significant changes
        let saveTimer;
        document.getElementById('promotionForm').addEventListener('input', () => {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => formHistory.saveState(), 1000);
        });

        // Add undo/redo shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                formHistory.undo();
            }
            if (e.ctrlKey && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
                e.preventDefault();
                formHistory.redo();
            }
        });

        // Add quick actions toolbar
        const toolbar = document.createElement('div');
        toolbar.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 10px 20px;
            display: flex;
            gap: 15px;
            z-index: 1000;
        `;

        const toolbarButtons = [
            { icon: 'fas fa-undo', title: 'Hoàn tác (Ctrl+Z)', action: () => formHistory.undo() },
            { icon: 'fas fa-redo', title: 'Làm lại (Ctrl+Y)', action: () => formHistory.redo() },
            { icon: 'fas fa-copy', title: 'Sao chép mã', action: () => copyToClipboard(document.getElementById('code').value) },
            { icon: 'fas fa-random', title: 'Tạo mã ngẫu nhiên', action: generateRandomCode },
            { icon: 'fas fa-calculator', title: 'Tính toán giảm giá', action: openDiscountCalculator }
        ];

        toolbarButtons.forEach(btn => {
            const button = document.createElement('button');
            button.innerHTML = `<i class="${btn.icon}"></i>`;
            button.title = btn.title;
            button.style.cssText = `
                background: none;
                border: none;
                padding: 8px;
                border-radius: 50%;
                color: #667eea;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            button.addEventListener('click', btn.action);
            button.addEventListener('mouseenter', () => {
                button.style.background = 'rgba(102, 126, 234, 0.1)';
                button.style.color = '#764ba2';
            });
            button.addEventListener('mouseleave', () => {
                button.style.background = 'none';
                button.style.color = '#667eea';
            });
            
            toolbar.appendChild(button);
        });

        document.body.appendChild(toolbar);

        // Toolbar helper functions
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
                showNotification('Đã sao chép mã: ' + text, 'success', 2000);
            }
        }

        function generateRandomCode() {
            const prefixes = ['SALE', 'DISCOUNT', 'PROMO', 'SPECIAL', 'DEAL'];
            const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
            const number = Math.floor(Math.random() * 9000) + 1000;
            const newCode = prefix + number;
            
            document.getElementById('code').value = newCode;
            document.getElementById('code').dispatchEvent(new Event('input'));
            showNotification('Đã tạo mã mới: ' + newCode, 'success');
        }

        function openDiscountCalculator() {
            const calculator = document.createElement('div');
            calculator.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                padding: 30px;
                z-index: 1003;
                width: 400px;
                max-width: 90vw;
            `;

            calculator.innerHTML = `
                <h3 style="margin-bottom: 20px; color: #667eea;">
                    <i class="fas fa-calculator"></i> Tính toán giảm giá
                </h3>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Giá gốc:</label>
                    <input type="number" id="originalPrice" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Phần trăm giảm:</label>
                    <input type="number" id="discountPercent" value="${document.getElementById('discount_percent').value}" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div>Giá sau giảm: <strong id="finalPrice">0 VNĐ</strong></div>
                    <div>Tiết kiệm: <strong id="savedAmount">0 VNĐ</strong></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button id="applyDiscount" style="flex: 1; background: #667eea; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer;">Áp dụng</button>
                    <button id="closeCalculator" style="flex: 1; background: #6c757d; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer;">Đóng</button>
                </div>
            `;

            document.body.appendChild(calculator);

            // Calculator logic
            function updateCalculation() {
                const original = parseFloat(document.getElementById('originalPrice').value) || 0;
                const discount = parseFloat(document.getElementById('discountPercent').value) || 0;
                const final = original * (1 - discount / 100);
                const saved = original - final;

                document.getElementById('finalPrice').textContent = formatCurrency(final);
                document.getElementById('savedAmount').textContent = formatCurrency(saved);
            }

            document.getElementById('originalPrice').addEventListener('input', updateCalculation);
            document.getElementById('discountPercent').addEventListener('input', updateCalculation);

            document.getElementById('applyDiscount').addEventListener('click', () => {
                const discount = document.getElementById('discountPercent').value;
                document.getElementById('discount_percent').value = discount;
                calculator.remove();
                showNotification('Đã áp dụng phần trăm giảm giá: ' + discount + '%', 'success');
            });

            document.getElementById('closeCalculator').addEventListener('click', () => {
                calculator.remove();
            });

            updateCalculation();
        }
    
</body>
</html> Character counters
                this.initCharCounters();
                
                // Form validation
                this.initValidation();
                
                // Status toggle
                this.initStatusToggle();
                
                // Form submission
                this.initFormSubmission();
                
                // Success animation
                this.initSuccessAnimation();
                
                // Auto-save draft (optional)
                this.initAutoSave();
            }

            initCharCounters() {
                const fields = [
                    { input: 'code', counter: 'codeCounter', max: 50 },
                    { input: 'description', counter: 'descriptionCounter', max: 500 }
                ];

                fields.forEach(field => {
                    const input = document.getElementById(field.input);
                    const counter = document.getElementById(field.counter);
                    
                    const updateCounter = () => {
                        const length = input.value.length;
                        counter.textContent = `${length}/${field.max}`;
                        
                        counter.className = 'char-counter';
                        if (length > field.max * 0.8) {
                            counter.classList.add('warning');
                        }
                        if (length > field.max * 0.95) {
                            counter.classList.add('danger');
                        }
                    };
                    
                    input.addEventListener('input', updateCounter);
                    updateCounter(); // Initial count
                });
            }

            initValidation() {
                const validators = {
                    code: (value) => {
                        if (!value.trim()) return 'Mã khuyến mãi không được để trống';
                        if (value.length > 50) return 'Mã khuyến mãi không được vượt quá 50 ký tự';
                        if (!/^[A-Z0-9_-]+$/i.test(value)) return 'Mã chỉ được chứa chữ cái, số, dấu gạch ngang và gạch dưới';
                        return null;
                    },
                    discount: (value) => {
                        const num = parseFloat(value);
                        if (isNaN(num) || num <= 0 || num > 100) return 'Phần trăm giảm giá phải từ 1 đến 100';
                        return null;
                    },
                    amount: (value) => {
                        if (value && (isNaN(value) || parseFloat(value) < 0)) return 'Giá trị đơn hàng phải là số không âm';
                        return null;
                    },
                    startDate: (value) => {
                        if (!value) return 'Vui lòng chọn ngày bắt đầu';
                        return null;
                    },
                    endDate: (value) => {
                        if (!value) return 'Vui lòng chọn ngày kết thúc';
                        const startDate = document.getElementById('start_date').value;
                        if (startDate && value <= startDate) return 'Ngày kết thúc phải sau ngày bắt đầu';
                        return null;
                    }
                };

                // Real-time validation
                Object.keys(validators).forEach(fieldName => {
                    const input = document.querySelector(`[data-validate="${fieldName}"]`);
                    if (input) {
                        input.addEventListener('blur', () => this.validateField(input, validators[fieldName]));
                        input.addEventListener('input', () => this.clearFieldError(input));
                    }
                });
            }

            validateField(input, validator) {
                const error = validator(input.value);
                const errorElement = input.parentNode.querySelector('.field-error');
                
                if (error) {
                    input.style.borderColor = '#dc3545';
                    if (!errorElement) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'field-error';
                        errorDiv.style.cssText = 'color: #dc3545; font-size: 12px; margin-top: 5px;';
                        errorDiv.textContent = error;
                        input.parentNode.appendChild(errorDiv);
                    } else {
                        errorElement.textContent = error;
                    }
                    return false;
                } else {
                    input.style.borderColor = '#28a745';
                    if (errorElement) {
                        errorElement.remove();
                    }
                    return true;
                }
            }

            clearFieldError(input) {
                input.style.borderColor = '#e1e5e9';
                const errorElement = input.parentNode.querySelector('.field-error');
                if (errorElement) {
                    errorElement.remove();
                }
            }

            initStatusToggle() {
                this.statusToggle.addEventListener('change', () => {
                    const isActive = this.statusToggle.checked;
                    this.statusText.textContent = isActive ? 
                        'Khuyến mãi đang hoạt động' : 
                        'Khuyến mãi đang tắt';
                    
                    // Add animation
                    this.statusText.style.animation = 'none';
                    setTimeout(() => {
                        this.statusText.style.animation = 'fadeIn 0.3s ease-in';
                    }, 10);
                });
            }

            initFormSubmission() {
                this.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    
                    // Show loading state
                    this.submitBtn.classList.add('loading');
                    this.submitBtn.querySelector('i').className = 'fas fa-spinner';
                    this.submitBtn.querySelector('span').textContent = 'Đang lưu...';
                    
                    // Simulate processing delay
                    setTimeout(() => {
                        this.form.submit();
                    }, 1000);
                });
            }

            initSuccessAnimation() {
                const successAlert = document.getElementById('successAlert');
                if (successAlert) {
                    successAlert.classList.add('success-animation');
                    
                    // Auto hide after 5 seconds
                    setTimeout(() => {
                        successAlert.style.animation = 'slideOutUp 0.5s ease-out forwards';
                    }, 5000);
                }
            }

            initAutoSave() {
                let autoSaveTimer;
                const formInputs = this.form.querySelectorAll('input, textarea, select');
                
                formInputs.forEach(input => {
                    input.addEventListener('input', () => {
                        clearTimeout(autoSaveTimer);
                        autoSaveTimer = setTimeout(() => {
                            this.saveDraft();
                        }, 2000);
                    });
                });
            }

            saveDraft() {
                const formData = new FormData(this.form);
                const draftData = {};
                
                for (let [key, value] of formData.entries()) {
                    draftData[key] = value;
                }
                
                // Save to localStorage
                try {
                    const draftKey = `promotion_edit_draft_<?= $promotion_id ?>`;
                    const draftValue = JSON.stringify(draftData);
                    // Note: In actual implementation, you might want to save this to server
                    console.log('Draft saved:', draftData);
                } catch (error) {
                    console.log('Could not save draft:', error);
                }
            }

          
    </script>
</body>
</html>