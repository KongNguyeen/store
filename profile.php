<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    flash('error', 'Vui lòng đăng nhập');
    redirect('login.php');
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Xử lý thêm/sửa địa chỉ
if (isset($_POST['save_address'])) {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash('error', 'Invalid CSRF token');
    } else {
        $address_id = (int)($_POST['address_id'] ?? 0);
        $recipient_name = sanitize($_POST['recipient_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address_line = sanitize($_POST['address_line'] ?? '');
        $ward = sanitize($_POST['ward'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        $city = sanitize($_POST['city'] ?? '');

        try {
            if (!$recipient_name || !$phone || !$address_line || !$ward || !$district || !$city) {
                throw new Exception('Vui lòng điền đầy đủ thông tin');
            }

            if ($address_id > 0) {
                // Kiểm tra địa chỉ tồn tại và thuộc về user
                $stmt = $pdo->prepare("
                    SELECT address_id 
                    FROM addresses 
                    WHERE address_id = ? AND user_id = ?
                ");
                $stmt->execute([$address_id, $user_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('Địa chỉ không tồn tại hoặc không thuộc về bạn');
                }

                // Cập nhật địa chỉ
                $stmt = $pdo->prepare("
                    UPDATE addresses 
                    SET recipient_name = ?, phone = ?, 
                        address_line = ?, ward = ?, district = ?, city = ?
                    WHERE address_id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $recipient_name, $phone,
                    $address_line, $ward, $district, $city,
                    $address_id, $user_id
                ]);
                flash('success', 'Đã cập nhật địa chỉ thành công');
            } else {
                // Thêm địa chỉ mới
                $is_default = false;
                // Nếu là địa chỉ đầu tiên, đặt làm mặc định
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetchColumn() == 0) {
                    $is_default = true;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO addresses (
                        user_id, recipient_name, phone,
                        address_line, ward, district, city,
                        is_default
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $recipient_name, $phone,
                    $address_line, $ward, $district, $city,
                    $is_default
                ]);
                flash('success', 'Đã thêm địa chỉ mới thành công');
            }
            
            redirect('profile.php');
            
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
    }
}

// Xử lý xóa địa chỉ
if (isset($_POST['delete_address'])) {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash('error', 'Invalid CSRF token');
    } else {
        $address_id = (int)($_POST['address_id'] ?? 0);
        
        try {
            // Kiểm tra địa chỉ tồn tại và thuộc về user
            $stmt = $pdo->prepare("
                SELECT is_default 
                FROM addresses 
                WHERE address_id = ? AND user_id = ?
            ");
            $stmt->execute([$address_id, $user_id]);
            $address = $stmt->fetch();

            if (!$address) {
                throw new Exception('Địa chỉ không tồn tại hoặc không thuộc về bạn');
            }

            if ($address['is_default']) {
                throw new Exception('Không thể xóa địa chỉ mặc định');
            }

            // Xóa địa chỉ
            $stmt = $pdo->prepare("
                DELETE FROM addresses 
                WHERE address_id = ? AND user_id = ?
            ");
            $stmt->execute([$address_id, $user_id]);

            flash('success', 'Đã xóa địa chỉ thành công');
            redirect('profile.php');
            
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
    }
}

// Lấy danh sách địa chỉ
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Xử lý cập nhật thông tin
if (isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
    } else {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');

        if (!$full_name || !$phone || !$email) {
            $error = 'Vui lòng điền đầy đủ thông tin';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email không hợp lệ';
        } else {
            try {
                // Kiểm tra email đã tồn tại chưa (trừ email hiện tại)
                $stmt = $pdo->prepare("
                    SELECT user_id FROM users 
                    WHERE email = ? AND user_id != ?
                ");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Email đã được sử dụng bởi tài khoản khác');
                }

                // Upload avatar nếu có
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['avatar'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        throw new Exception('Avatar phải là file ảnh (jpg, jpeg, png)');
                    }

                    if ($file['size'] > MAX_FILE_SIZE) {
                        throw new Exception('Kích thước file không được vượt quá ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
                    }

                    $upload_dir = ROOT_PATH . 'assets/uploads/avatars/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    // Đảm bảo thư mục có quyền ghi
                    if (!is_writable($upload_dir)) {
                        throw new Exception('Không thể ghi vào thư mục upload. Vui lòng kiểm tra quyền truy cập.');
                    }

                    $filename = 'avatar_' . $user_id . '.' . $ext;
                    $destination = $upload_dir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        // Xóa avatar cũ nếu có
                        if ($user['avatar'] && file_exists(ROOT_PATH . $user['avatar']) && $user['avatar'] != 'assets/images/avatar-default.jpg') {
                            unlink(ROOT_PATH . $user['avatar']);
                        }

                        $avatar = 'assets/uploads/avatars/' . $filename;
                    } else {
                        // Phân tích lỗi upload
                        $upload_error = 'Không thể tải lên ảnh: ';
                        
                        switch($_FILES['avatar']['error']) {
                            case UPLOAD_ERR_INI_SIZE:
                            case UPLOAD_ERR_FORM_SIZE:
                                $upload_error .= 'Kích thước file quá lớn';
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $upload_error .= 'File chỉ được tải lên một phần';
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $upload_error .= 'Không có file nào được tải lên';
                                break;
                            case UPLOAD_ERR_NO_TMP_DIR:
                                $upload_error .= 'Thiếu thư mục tạm';
                                break;
                            case UPLOAD_ERR_CANT_WRITE:
                                $upload_error .= 'Không thể ghi file vào đĩa';
                                break;
                            default:
                                $upload_error .= 'Lỗi không xác định';
                        }
                        
                        throw new Exception($upload_error);
                    }
                }

                // Cập nhật thông tin
                $sql = "UPDATE users SET full_name = ?, phone = ?, email = ?, updated_at = NOW()";
                $params = [$full_name, $phone, $email];

                if (isset($avatar)) {
                    $sql .= ", avatar = ?";
                    $params[] = $avatar;
                }

                $sql .= " WHERE user_id = ?";
                $params[] = $user_id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Update session with new user info
                if (isset($avatar)) {
                    $_SESSION['avatar'] = $avatar;
                }
                $_SESSION['full_name'] = $full_name;
                $_SESSION['phone'] = $phone;
                $_SESSION['email'] = $email;

                $_SESSION['flash_type'] = 'success';
                $_SESSION['flash_message'] = 'Cập nhật thông tin thành công!';
                redirect('profile.php');

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Xử lý đổi mật khẩu
if (isset($_POST['change_password'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$current_password || !$new_password || !$confirm_password) {
            $error = 'Vui lòng điền đầy đủ thông tin';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Mật khẩu mới không khớp';
        } elseif (strlen($new_password) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự';
        } elseif ($current_password !== $user['password']) {
            $error = 'Mật khẩu hiện tại không đúng';
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([
                $new_password,
                $user_id
            ]);

                $_SESSION['flash_type'] = 'success';
                $_SESSION['flash_message'] = 'Đổi mật khẩu thành công!';
                redirect('profile.php');
        }
    }
}// Xử lý thêm địa chỉ mới
if (isset($_POST['save_address'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash('error', 'Invalid CSRF token');
    } else {
        $address_id = (int)($_POST['address_id'] ?? 0);
        $recipient_name = sanitize($_POST['recipient_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address_line = sanitize($_POST['address_line'] ?? '');
        $ward = sanitize($_POST['ward'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (!$recipient_name || !$phone || !$address_line || !$ward || !$district || !$city) {
            flash('error', 'Vui lòng điền đầy đủ thông tin địa chỉ');
            redirect('profile.php');
        } else {
            try {
                $pdo->beginTransaction();

                // Nếu là địa chỉ mặc định, reset các địa chỉ khác
                if ($is_default) {
                    $stmt = $pdo->prepare("
                        UPDATE addresses 
                        SET is_default = 0 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                }

                if ($address_id > 0) {
                    // Cập nhật địa chỉ hiện có
                    $stmt = $pdo->prepare("
                        UPDATE addresses 
                        SET recipient_name = ?, 
                            phone = ?,
                            address_line = ?,
                            ward = ?,
                            district = ?,
                            city = ?,
                            is_default = ?,
                            updated_at = NOW()
                        WHERE address_id = ? AND user_id = ?
                    ");
                    $stmt->execute([
                        $recipient_name,
                        $phone,
                        $address_line,
                        $ward,
                        $district,
                        $city,
                        $is_default,
                        $address_id,
                        $user_id
                    ]);
                } else {
                    // Thêm địa chỉ mới
                    $stmt = $pdo->prepare("
                        INSERT INTO addresses (
                            user_id,
                            recipient_name,
                            phone,
                            address_line,
                            ward,
                            district,
                            city,
                            is_default,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $recipient_name,
                        $phone,
                        $address_line,
                        $ward,
                        $district,
                        $city,
                        $is_default
                    ]);
                }

                if ($is_default) {
                    // Reset default address
                    $stmt = $pdo->prepare("
                        UPDATE addresses 
                        SET is_default = 0 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                }

                if ($address_id) {
                    // Cập nhật địa chỉ
                    $stmt = $pdo->prepare("
                        UPDATE addresses 
                        SET recipient_name = ?, phone = ?, 
                            address_line = ?, ward = ?, 
                            district = ?, city = ?,
                            is_default = ?
                        WHERE address_id = ? AND user_id = ?
                    ");
                    $stmt->execute([
                        $recipient_name, $phone,
                        $address_line, $ward,
                        $district, $city,
                        $is_default,
                        $address_id, $user_id
                    ]);
                } else {
                    // Thêm địa chỉ mới
                    $stmt = $pdo->prepare("
                        INSERT INTO addresses (
                            user_id, recipient_name, phone,
                            address_line, ward, district, city,
                            is_default
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, $recipient_name, $phone,
                        $address_line, $ward, $district, $city,
                        $is_default
                    ]);
                }

                $pdo->commit();
                flash('success', 'Lưu địa chỉ thành công!');
                redirect('profile.php');

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// Xử lý xóa địa chỉ
if (isset($_POST['delete_address'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token';
    } else {
        $address_id = (int)($_POST['address_id'] ?? 0);

        try {
            $stmt = $pdo->prepare("
                DELETE FROM addresses 
                WHERE address_id = ? AND user_id = ? AND is_default = 0
            ");
            $stmt->execute([$address_id, $user_id]);

            if ($stmt->rowCount() == 0) {
                throw new Exception('Không thể xóa địa chỉ mặc định');
            }

            flash('success', 'Xóa địa chỉ thành công!');
            redirect('profile.php');

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

include 'includes/navbar.php';
?>
<link rel="stylesheet" href="css/profile.css">

<div class="container-fluid profile-container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="row">
                <div class="col-lg-4">
                    <!-- Profile Info Card -->
                    <div class="profile-card mb-4">
                        <div class="card-body text-center p-4">
                            <div class="avatar-container" onclick="document.getElementById('avatarInput').click()">
                                <?php if (isset($user['avatar']) && $user['avatar']): ?>
                                    <img src="<?= $user['avatar'] ?>" class="avatar-image" 
                                         alt="<?= htmlspecialchars($user['full_name']) ?>">
                                <?php else: ?>
                                    <img src="assets/images/avatar-default.jpg" class="avatar-image" 
                                         alt="Default avatar">
                                <?php endif; ?>
                                <div class="avatar-overlay">
                                    <i class="fas fa-camera fa-2x text-white"></i>
                                </div>
                            </div>
                            
                            <h4 class="mb-1 font-weight-bold"><?= htmlspecialchars($user['full_name']) ?></h4>
                            <p class="text-muted mb-3">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Thành viên từ <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                            </p>
                            
                            <div class="stats-card">
                                <h5 class="mb-0"><?= count($addresses) ?></h5>
                                <small>Địa chỉ đã lưu</small>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="profile-card">
                        <div class="card-body p-4">
                            <h5 class="section-title">
                                <i class="fas fa-key me-2"></i>Đổi mật khẩu
                            </h5>
                            <form method="post" id="passwordForm">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock me-2"></i>Mật khẩu hiện tại
                                    </label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock me-2"></i>Mật khẩu mới
                                    </label>
                                    <input type="password" class="form-control" name="new_password" required
                                           minlength="6" id="newPassword">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock me-2"></i>Xác nhận mật khẩu mới
                                    </label>
                                    <input type="password" class="form-control" name="confirm_password" required
                                           minlength="6" id="confirmPassword">
                                </div>

                                <button type="submit" name="change_password" class="btn btn-gradient w-100">
                                    <i class="fas fa-save me-2"></i>Đổi mật khẩu
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['flash_type']) && isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show">
                            <i class="fas fa-<?= $_SESSION['flash_type'] == 'success' ? 'check-circle' : 'info-circle' ?> me-2"></i>
                            <?= $_SESSION['flash_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php 
                        unset($_SESSION['flash_type']);
                        unset($_SESSION['flash_message']);
                        ?>
                    <?php endif; ?>

                    <!-- Update Profile Card -->
                    <div class="profile-card mb-4">
                        <div class="card-body p-4">
                            <h5 class="section-title">
                                <i class="fas fa-user me-2"></i>Thông tin cá nhân
                            </h5>
                            <form method="post" enctype="multipart/form-data" id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="file" id="avatarInput" name="avatar" accept="image/*" 
                                       style="display: none;" onchange="previewAvatar(this)">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-user me-2"></i>Họ tên
                                        </label>
                                        <input type="text" class="form-control" name="full_name" required
                                               value="<?= htmlspecialchars($user['full_name']) ?>">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-phone me-2"></i>Số điện thoại
                                        </label>
                                        <input type="tel" class="form-control" name="phone" required
                                               value="<?= htmlspecialchars($user['phone']) ?>">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email
                                    </label>
                                    <input type="email" class="form-control" name="email" required
                                           value="<?= htmlspecialchars($user['email']) ?>">
                                </div>

                                <button type="submit" name="update_profile" class="btn btn-gradient btn-success">
                                    <i class="fas fa-save me-2"></i>Lưu thay đổi
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Addresses Card -->
                    <div class="profile-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="section-title mb-0">
                                    <i class="fas fa-map-marker-alt me-2"></i>Sổ địa chỉ
                                </h5>
                                <button type="button" class="btn btn-gradient btn-success" onclick="showAddressForm()">
                                    <i class="fas fa-plus me-2"></i>Thêm địa chỉ mới
                                </button>
                            </div>

                            <div id="addressList">
                                <?php if (empty($addresses)): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                                        <h5>Chưa có địa chỉ nào</h5>
                                        <p>Hãy thêm địa chỉ đầu tiên của bạn để thuận tiện cho việc giao hàng!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($addresses as $addr): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="address-card h-100 <?= $addr['is_default'] ? 'default' : '' ?>">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                            <h6 class="mb-1 font-weight-bold">
                                                                <i class="fas fa-user me-2"></i>
                                                                <?= htmlspecialchars($addr['recipient_name']) ?>
                                                            </h6>
                                                            <?php if ($addr['is_default']): ?>
                                                                <span class="badge badge-gradient">
                                                                    <i class="fas fa-star me-1"></i>Mặc định
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="action-buttons">
                                                            <button type="button" class="btn btn-icon btn-outline-primary"
                                                                    onclick='editAddressForm(<?= json_encode($addr) ?>)'>
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <?php if (!$addr['is_default']): ?>
                                                            <button type="button" class="btn btn-icon btn-outline-danger"
                                                                    onclick="deleteAddress(<?= $addr['address_id'] ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <p class="mb-2 text-muted">
                                                        <i class="fas fa-phone me-2"></i><?= $addr['phone'] ?>
                                                    </p>
                                                    
                                                    <p class="mb-0">
                                                        <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($addr['address_line']) ?>,
                                                        <?= htmlspecialchars($addr['ward']) ?>,
                                                        <?= htmlspecialchars($addr['district']) ?>,
                                                        <?= htmlspecialchars($addr['city']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            </div> <!-- Close addressList -->
                        </div>
                    </div>
                    
                    <!-- Inline Address Form Container -->
                    <div id="addressFormContainer" class="profile-card mt-4" style="display: none;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 id="addressFormTitle" class="section-title mb-0">
                                    <i class="fas fa-map-marker-alt me-2"></i>Thêm địa chỉ mới
                                </h5>
                                <button type="button" class="btn btn-secondary" onclick="hideAddressForm()">
                                    <i class="fas fa-times me-2"></i>Đóng
                                </button>
                            </div>
                            
                            <form method="post" id="addressForm">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="address_id" id="addressId">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-user me-2"></i>Người nhận
                                        </label>
                                        <input type="text" class="form-control" name="recipient_name" required
                                               id="recipientName" placeholder="Nhập tên người nhận">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-phone me-2"></i>Số điện thoại
                                        </label>
                                        <input type="tel" class="form-control" name="phone" required
                                               id="addressPhone" placeholder="Nhập số điện thoại">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-home me-2"></i>Địa chỉ cụ thể
                                    </label>
                                    <input type="text" class="form-control" name="address_line" required
                                           id="addressLine" placeholder="Số nhà, tên đường...">
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-map-pin me-2"></i>Phường/Xã
                                        </label>
                                        <input type="text" class="form-control" name="ward" required
                                               id="addressWard" placeholder="Nhập phường/xã">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-map-pin me-2"></i>Quận/Huyện
                                        </label>
                                        <input type="text" class="form-control" name="district" required
                                               id="addressDistrict" placeholder="Nhập quận/huyện">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-map-pin me-2"></i>Tỉnh/Thành phố
                                        </label>
                                        <input type="text" class="form-control" name="city" required
                                               id="addressCity" placeholder="Nhập tỉnh/thành phố">
                                    </div>
                                </div>

                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" name="is_default" 
                                           id="isDefault">
                                    <label class="form-check-label" for="isDefault">
                                        <i class="fas fa-star me-2"></i>Đặt làm địa chỉ mặc định
                                    </label>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-secondary me-2" onclick="hideAddressForm()">
                                        <i class="fas fa-times me-2"></i>Hủy
                                    </button>
                                    <button type="submit" name="save_address" class="btn btn-gradient btn-success">
                                        <i class="fas fa-save me-2"></i>Lưu địa chỉ
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Xác nhận xóa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                <h5>Bạn có chắc chắn muốn xóa địa chỉ này?</h5>
                <p class="text-muted">Hành động này không thể hoàn tác!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Không
                </button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="address_id" id="deleteAddressId">
                    <button type="submit" name="delete_address" class="btn btn-gradient btn-danger">
                        <i class="fas fa-trash me-2"></i>Xóa
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div class="loading-spinner"></div>
</div>

<script>
// Initialize modals after DOM is loaded and Bootstrap is available
let deleteModal;

// Password strength indicator
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Bootstrap to be available
    if (typeof bootstrap !== 'undefined') {
        // Initialize modals
        const deleteModalElement = document.getElementById('deleteModal');
        if (deleteModalElement) {
            deleteModal = new bootstrap.Modal(deleteModalElement);
        }
        
        // Initialize dropdowns
        const dropdownElements = document.querySelectorAll('.dropdown-toggle');
        console.log('Dropdown elements found:', dropdownElements.length);
        
        dropdownElements.forEach(element => {
            new bootstrap.Dropdown(element);
        });
    } else {
        console.warn('Bootstrap is not available');
    }
    
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            validatePasswordStrength(this.value);
        });
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            validatePasswordMatch();
        });
    }
    
    // Hide loading on page load
    hideLoading();
    
    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.animation = 'slideOutUp 0.5s ease-out forwards';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

function validatePasswordStrength(password) {
    const strengthIndicator = document.getElementById('passwordStrength');
    if (!strengthIndicator) {
        // Create strength indicator if it doesn't exist
        const indicator = document.createElement('div');
        indicator.id = 'passwordStrength';
        indicator.className = 'password-strength mt-2';
        const newPasswordElement = document.getElementById('newPassword');
        if (newPasswordElement && newPasswordElement.parentNode) {
            newPasswordElement.parentNode.appendChild(indicator);
        }
    }
    
    const strength = getPasswordStrength(password);
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745'];
    const texts = ['Yếu', 'Trung bình', 'Khá', 'Mạnh'];
    
    const passwordStrengthElement = document.getElementById('passwordStrength');
    if (passwordStrengthElement) {
        passwordStrengthElement.innerHTML = `
            <div class="progress" style="height: 5px;">
                <div class="progress-bar" style="width: ${strength * 25}%; background-color: ${colors[strength - 1] || '#dc3545'}"></div>
            </div>
            <small class="text-muted">Độ mạnh: ${texts[strength - 1] || 'Rất yếu'}</small>
        `;
    }
}

function getPasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return Math.min(score, 4);
}

function validatePasswordMatch() {
    const newPasswordElement = document.getElementById('newPassword');
    const confirmPasswordElement = document.getElementById('confirmPassword');
    
    if (!newPasswordElement || !confirmPasswordElement) {
        return; // Elements don't exist
    }
    
    const newPassword = newPasswordElement.value;
    const confirmPassword = confirmPasswordElement.value;
    const matchIndicator = document.getElementById('passwordMatch');
    
    if (!matchIndicator) {
        const indicator = document.createElement('div');
        indicator.id = 'passwordMatch';
        indicator.className = 'password-match mt-2';
        if (confirmPasswordElement.parentNode) {
            confirmPasswordElement.parentNode.appendChild(indicator);
        }
    }
    
    if (confirmPassword) {
        const passwordMatchElement = document.getElementById('passwordMatch');
        if (passwordMatchElement) {
            if (newPassword === confirmPassword) {
                passwordMatchElement.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Mật khẩu khớp</small>';
            } else {
                passwordMatchElement.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Mật khẩu không khớp</small>';
            }
        }
    }
}

function deleteAddress(addressId) {
    const deleteAddressIdInput = document.getElementById('deleteAddressId');
    if (deleteAddressIdInput) {
        deleteAddressIdInput.value = addressId;
    } else {
        console.error('deleteAddressId element not found');
        return;
    }
    
    // Add entrance animation
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.animation = 'slideInDown 0.5s ease-out';
    }
    
    if (deleteModal) {
        deleteModal.show();
    } else {
        console.error('deleteModal is not initialized');
        // Fallback: try to initialize modal
        if (typeof bootstrap !== 'undefined') {
            const deleteModalElement = document.getElementById('deleteModal');
            if (deleteModalElement) {
                deleteModal = new bootstrap.Modal(deleteModalElement);
                deleteModal.show();
            }
        }
    }
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const avatarImage = document.querySelector('.avatar-image');
            
            // Fade out old image
            avatarImage.style.opacity = '0';
            
            setTimeout(() => {
                avatarImage.src = e.target.result;
                // Fade in new image
                avatarImage.style.opacity = '1';
            }, 300);
        };
        
        reader.readAsDataURL(input.files[0]);
        
        // Hiển thị thông báo cho người dùng biết họ cần click "Lưu thay đổi"
        const saveBtn = document.querySelector('button[name="update_profile"]');
        saveBtn.classList.add('btn-pulse');
        saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Lưu thay đổi để cập nhật avatar';
        
        // Thêm hiệu ứng nhắc nhở
        setTimeout(() => {
            saveBtn.classList.remove('btn-pulse');
            saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Lưu thay đổi';
        }, 3000);
    }
}

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';
    overlay.style.animation = 'fadeIn 0.3s ease-out';
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.animation = 'fadeOut 0.3s ease-out';
    setTimeout(() => {
        overlay.style.display = 'none';
    }, 300);
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    // Add shake animation
                    field.style.animation = 'shake 0.5s ease-out';
                    setTimeout(() => {
                        field.style.animation = '';
                    }, 500);
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Vui lòng điền đầy đủ thông tin!', 'error');
            } else {
                // Chỉ hiển thị loading khi form hợp lệ
                showLoadingWithTimeout();
            }
        });
    });
});

function showLoadingWithTimeout() {
    showLoading();
    // Tự động ẩn loading sau 10 giây để tránh treo
    setTimeout(() => {
        hideLoading();
    }, 10000);
}

// Add CSS animations
const profileStyle = document.createElement('style');
profileStyle.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
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
    
    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
    
    .is-valid {
        border-color: #28a745 !important;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
    }
    
    .password-strength .progress {
        border-radius: 10px;
        overflow: hidden;
    }
    
    .password-strength .progress-bar {
        transition: all 0.3s ease;
    }
`;
document.head.appendChild(profileStyle);

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification-toast`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        animation: slideInRight 0.5s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.5s ease-out';
        setTimeout(() => notification.remove(), 500);
    }, 5000);
}

// Add slide animations for notifications
const notificationStyle = document.createElement('style');
notificationStyle.textContent = `
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
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(notificationStyle);

// Auto-format phone numbers
document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.slice(0, 3) + ' ' + value.slice(3);
                } else {
                    value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 10);
                }
            }
            this.value = value;
        });
    });
});

// Smooth scrolling for form errors
function scrollToError() {
    const errorElement = document.querySelector('.is-invalid');
    if (errorElement) {
        errorElement.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
    }
}

// Address Modal Functions - sẽ được định nghĩa lại ở cuối file

</script>

</div> <!-- Close profile container -->

<!-- Bootstrap JS and dependencies -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<script>
// Address Form Functions - Defined directly in HTML
function showAddressForm() {
    console.log('Showing address form for new address');
    
    const addressFormContainer = document.getElementById('addressFormContainer');
    const addressList = document.getElementById('addressList');
    
    if (addressFormContainer && addressList) {
        // Hide address list
        addressList.style.display = 'none';
        
        // Show form container
        addressFormContainer.style.display = 'block';
        
        // Clear form for new address
        clearAddressForm();
        
        // Update title
        updateFormTitle('Thêm địa chỉ mới');
        
        // Scroll to form
        addressFormContainer.scrollIntoView({ behavior: 'smooth' });
    } else {
        console.error('Form container or address list not found');
        alert('Không thể hiển thị form địa chỉ');
    }
}

function editAddressForm(address) {
    console.log('Editing address:', address);
    
    if (!address || !address.address_id) {
        console.error('Invalid address data');
        alert('Dữ liệu địa chỉ không hợp lệ');
        return;
    }
    
    const addressFormContainer = document.getElementById('addressFormContainer');
    const addressList = document.getElementById('addressList');
    
    if (addressFormContainer && addressList) {
        // Hide address list
        addressList.style.display = 'none';
        
        // Show form container
        addressFormContainer.style.display = 'block';
        
        // Populate form with address data
        populateAddressForm(address);
        
        // Update title
        updateFormTitle('Chỉnh sửa địa chỉ');
        
        // Scroll to form
        addressFormContainer.scrollIntoView({ behavior: 'smooth' });
    } else {
        console.error('Form container or address list not found');
        alert('Không thể hiển thị form địa chỉ');
    }
}

function hideAddressForm() {
    console.log('Hiding address form');
    
    const addressFormContainer = document.getElementById('addressFormContainer');
    const addressList = document.getElementById('addressList');
    
    if (addressFormContainer && addressList) {
        // Hide form container
        addressFormContainer.style.display = 'none';
        
        // Show address list
        addressList.style.display = 'block';
        
        // Clear form
        clearAddressForm();
    }
}

function populateAddressForm(address) {
    console.log('Populating form with address data');
    
    try {
        // Set address ID
        const addressIdEl = document.getElementById('addressId');
        if (addressIdEl) addressIdEl.value = address.address_id || '';
        
        // Set recipient name
        const recipientNameEl = document.getElementById('recipientName');
        if (recipientNameEl) recipientNameEl.value = address.recipient_name || '';
        
        // Set phone
        const phoneEl = document.getElementById('addressPhone');
        if (phoneEl) phoneEl.value = address.phone || '';
        
        // Set address line
        const addressLineEl = document.getElementById('addressLine');
        if (addressLineEl) addressLineEl.value = address.address_line || '';
        
        // Set ward
        const wardEl = document.getElementById('addressWard');
        if (wardEl) wardEl.value = address.ward || '';
        
        // Set district
        const districtEl = document.getElementById('addressDistrict');
        if (districtEl) districtEl.value = address.district || '';
        
        // Set city
        const cityEl = document.getElementById('addressCity');
        if (cityEl) cityEl.value = address.city || '';
        
        // Set default checkbox
        const isDefaultEl = document.getElementById('isDefault');
        if (isDefaultEl) isDefaultEl.checked = address.is_default == 1;
        
        console.log('Form populated successfully');
    } catch (error) {
        console.error('Error populating form:', error);
        alert('Có lỗi khi tải dữ liệu địa chỉ');
    }
}

function clearAddressForm() {
    console.log('Clearing address form');
    
    try {
        const form = document.getElementById('addressForm');
        if (form) {
            form.reset();
        }
        
        // Clear hidden address ID
        const addressIdEl = document.getElementById('addressId');
        if (addressIdEl) addressIdEl.value = '';
        
        console.log('Form cleared successfully');
    } catch (error) {
        console.error('Error clearing form:', error);
    }
}

function updateFormTitle(title) {
    const formTitle = document.getElementById('addressFormTitle');
    if (formTitle) {
        formTitle.innerHTML = '<i class="fas fa-map-marker-alt me-2"></i>' + title;
    }
}

// Initialize tooltips when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title], [data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    console.log('Profile page components initialized');
    console.log('Address functions loaded:', typeof showAddressForm, typeof editAddressForm);
});
</script>

<?php include 'includes/footer.php'; ?>