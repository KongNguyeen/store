<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    flash('error', 'Vui lòng đăng nhập để thanh toán');
    redirect('login.php');
}

// Kiểm tra giỏ hàng
$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$cart_empty = false;
// Nếu user đã đăng nhập, kiểm tra cart_items trong database
$stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart_id = $stmt->fetchColumn();
if ($cart_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cart_id]);
    $count = $stmt->fetchColumn();
    if ($count == 0) $cart_empty = true;
} else {
    // Nếu không có cart_id (khách hoặc user chưa có cart), kiểm tra session
    if (empty($_SESSION['cart'])) $cart_empty = true;
}
if ($cart_empty) {
    flash('error', 'Giỏ hàng của bạn đang trống');
    redirect('cart.php');
}

$pdo = getPDO();
$error = '';
$user_id = $_SESSION['user_id'];

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Lấy địa chỉ giao hàng của user
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Lấy thông tin sản phẩm trong giỏ hàng
$cart_items = [];
$total = 0;
$discount = 0;

// Nếu user đã đăng nhập, lấy từ database
$stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart_id = $stmt->fetchColumn();
if ($cart_id) {
    $stmt = $pdo->prepare("
        SELECT ci.quantity, p.*, 
               (SELECT image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.product_id
        WHERE ci.cart_id = ?
    ");
    $stmt->execute([$cart_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $product) {
        $quantity = $product['quantity'];
        $subtotal = $product['price'] * $quantity;
        $total += $subtotal;
        // Kiểm tra lại tồn kho
        if ($quantity > $product['stock']) {
            flash('error', "Sản phẩm '{$product['name']}' không đủ số lượng trong kho");
            redirect('cart.php');
        }
        $cart_items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
} else if (!empty($_SESSION['cart'])) {
    // Nếu không có cart_id (hoặc là khách), lấy từ session
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT image_url FROM product_images pi 
                WHERE pi.product_id = p.product_id AND pi.is_primary = 1) as product_image
        FROM products p 
        WHERE p.product_id IN ($placeholders)
    ");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['product_id']];
        $subtotal = $product['price'] * $quantity;
        $total += $subtotal;
        // Kiểm tra lại tồn kho
        if ($quantity > $product['stock']) {
            flash('error', "Sản phẩm '{$product['name']}' không đủ số lượng trong kho");
            redirect('cart.php');
        }
        $cart_items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}

// Áp dụng giảm giá nếu có
if (isset($_SESSION['promo'])) {
    $discount = $_SESSION['promo']['discount'];
}





// Lưu total_amount vào session để sử dụng cho MoMo
$_SESSION['total_amount'] = $total - $discount;

// Kiểm tra tổng tiền hợp lệ trước khi cho phép đặt hàng
$total_amount = $_SESSION['total_amount'];
if ($total_amount < 10000 || $total_amount > 50000000) {
    echo "<h2>Lỗi số tiền không hợp lệ</h2>";
    echo "Số tiền thanh toán phải từ 10,000 VND đến 50,000,000 VND<br>";
    echo "Số tiền hiện tại: " . number_format($total_amount, 0, ',', '.') . " VND<br><br>";
    echo "<p><a href='cart.php'>Quay lại giỏ hàng</a></p>";
    exit;
}

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token';
    } else {
        $payment_method = trim($_POST['payment_method'] ?? '');
        if (!$payment_method || !array_key_exists($payment_method, PAYMENT_METHODS)) {
            $error = 'Phương thức thanh toán không hợp lệ';
        }
    }

    if (!$error) {
        
        // Nếu chọn MoMo, chuyển hướng đến trang thanh toán MoMo
        if ($payment_method === 'momo') {
            // Lưu thông tin đơn hàng tạm thời vào session
            $_SESSION['temp_order'] = [
                'address_id' => $_POST['address_id'] ?? null,
                'save_address' => $_POST['save_address'] ?? 0,
                'recipient_name' => $_POST['recipient_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'address_line' => $_POST['address_line'] ?? '',
                'ward' => $_POST['ward'] ?? '',
                'district' => $_POST['district'] ?? '',
                'city' => $_POST['city'] ?? '',
                'payment_method' => $payment_method,
                'shipping_method' => $_POST['shipping_method'] ?? 'standard',
                'cart_items' => $cart_items,
                'total' => $total,
                'discount' => $discount
            ];
            
            // Chuyển hướng đến trang thanh toán MoMo
            header('Location: momo_atm_payment.php');
            exit;
        }
        
        // Nếu chọn VNPay, tạo đơn hàng và trả về order_id
        if ($payment_method === 'vnpay') {
            try {
                $pdo->beginTransaction();

                // Lưu địa chỉ giao hàng mới nếu có
                if (isset($_POST['save_address']) && $_POST['save_address'] && 
                    !empty($_POST['recipient_name']) && !empty($_POST['phone']) && !empty($_POST['address_line'])
                ) {
                    $stmt = $pdo->prepare("
                        INSERT INTO addresses (
                            user_id, recipient_name, phone, 
                            address_line, ward, district, city, is_default
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([
                        $user_id,
                        trim($_POST['recipient_name'] ?? ''),
                        trim($_POST['phone'] ?? ''),
                        trim($_POST['address_line'] ?? ''),
                        trim($_POST['ward'] ?? ''),
                        trim($_POST['district'] ?? ''),
                        trim($_POST['city'] ?? '')
                    ]);
                    $address_id = $pdo->lastInsertId();
                } else {
                    $address_id = (int)$_POST['address_id'];
                }

                // Tạo đơn hàng mới
                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        user_id, address_id, 
                        total_amount, discount_amount,
                        payment_method, shipping_method,
                        status, payment_status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW(), NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $address_id,
                    $total,
                    $discount,
                    $payment_method,
                    trim($_POST['shipping_method'] ?? '')
                ]);
                $order_id = $pdo->lastInsertId();

                // Thêm chi tiết đơn hàng
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, quantity, price
                    ) VALUES (?, ?, ?, ?)
                ");

                foreach ($cart_items as $item) {
                    $stmt->execute([
                        $order_id,
                        $item['product']['product_id'],
                        $item['quantity'],
                        $item['product']['price']
                    ]);
                }

                $pdo->commit();
                
                // Trả về JSON response với order_id
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'order_id' => $order_id]);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }
        
        // Xử lý các phương thức thanh toán khác (COD, bank_transfer)
        try {
            $pdo->beginTransaction();

            // Lưu địa chỉ giao hàng mới nếu có
            if (
                isset($_POST['save_address']) && $_POST['save_address']
                && !empty($_POST['recipient_name'])
                && !empty($_POST['phone'])
                && !empty($_POST['address_line'])
            ) {
                $stmt = $pdo->prepare("
                    INSERT INTO addresses (
                        user_id, recipient_name, phone, address_line,
                        ward, district, city, is_default
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                ");
                    $stmt->execute([
                        $user_id,
                        trim($_POST['recipient_name'] ?? ''),
                        trim($_POST['phone'] ?? ''),
                        trim($_POST['address_line'] ?? ''),
                        trim($_POST['ward'] ?? ''),
                        trim($_POST['district'] ?? ''),
                        trim($_POST['city'] ?? '')
                    ]);
                $address_id = $pdo->lastInsertId();
            } else {
                $address_id = (int)$_POST['address_id'];
                if (!$address_id) {
                    throw new Exception('Vui lòng chọn địa chỉ giao hàng');
                }
            }

            // Tạo đơn hàng mới
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    user_id, address_id, 
                    total_amount, discount_amount,
                    payment_method, shipping_method,
                    status, payment_status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW(), NOW())
            ");
            $stmt->execute([
                $user_id,
                $address_id,
                $total,
                $discount,
                $payment_method,
                trim($_POST['shipping_method'] ?? '')
            ]);
            $order_id = $pdo->lastInsertId();

            // Thêm chi tiết đơn hàng
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, quantity, price
                ) VALUES (?, ?, ?, ?)
            ");

            foreach ($cart_items as $item) {
                $stmt->execute([
                    $order_id,
                    $item['product']['product_id'],
                    $item['quantity'],
                    $item['product']['price']
                ]);

                // Cập nhật tồn kho
                $update_stock = $pdo->prepare("
                    UPDATE products 
                    SET stock = stock - ?, updated_at = NOW()
                    WHERE product_id = ?
                ");
                $update_stock->execute([
                    $item['quantity'],
                    $item['product']['product_id']
                ]);
            }

            $pdo->commit();

            // Xóa giỏ hàng và mã giảm giá
            $_SESSION['cart'] = [];
            unset($_SESSION['promo']);
            unset($_SESSION['total_amount']);
            // Nếu user đã đăng nhập, xóa cart_items trong database
            if ($cart_id) {
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                $stmt->execute([$cart_id]);
            }

            flash('success', 'Đặt hàng thành công!');
            redirect("order_detail.php?id=$order_id");

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

include 'includes/navbar.php';
?>


    <link rel="stylesheet" href="css/checkout.css">

<div class="checkout-container">
    <div class="container">
        <h1 class="checkout-title fade-in">Thanh toán</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger fade-in"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Shipping address -->
                    <div class="card fade-in" style="animation-delay: 0.1s;">
                        <div class="card-body">
                            <h5 class="card-title">Địa chỉ giao hàng</h5>

                            <?php if ($addresses): ?>
                                <div class="address-container">
                                    <?php foreach ($addresses as $index => $addr): ?>
                                        <div class="address-card slide-in <?= $addr['is_default'] ? 'selected' : '' ?>" 
                                             style="animation-delay: <?= $index * 0.1 ?>s;"
                                             data-address-id="<?= $addr['address_id'] ?>">
                                            <input type="radio" class="form-check-input" name="address_id"
                                                   value="<?= $addr['address_id'] ?>" required
                                                   id="addr_<?= $addr['address_id'] ?>"
                                                   <?= $addr['is_default'] ? 'checked' : '' ?>>
                                            <?php if ($addr['is_default']): ?>
                                                <div class="default-badge">Mặc định</div>
                                            <?php endif; ?>
                                            <div class="address-name"><?= htmlspecialchars($addr['recipient_name']) ?></div>
                                            <div class="address-details">
                                                <?= htmlspecialchars($addr['address_line']) ?>, 
                                                <?= htmlspecialchars($addr['ward']) ?>, 
                                                <?= htmlspecialchars($addr['district']) ?>, 
                                                <?= htmlspecialchars($addr['city']) ?>
                                            </div>
                                            <div class="address-phone">
                                                <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($addr['phone']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="new-address-btn fade-in" id="newAddressToggle" style="animation-delay: 0.5s;">
                                <i class="fas fa-plus-circle"></i>
                                <div><span>Thêm địa chỉ mới</span></div>
                            </div>

                            <div class="new-address-form" id="newAddressForm">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Người nhận</label>
                                        <input type="text" class="form-control new-address" 
                                               name="recipient_name"
                                               value="<?= htmlspecialchars($user['full_name']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Số điện thoại</label>
                                        <input type="tel" class="form-control new-address" 
                                               name="phone"
                                               value="<?= htmlspecialchars($user['phone']) ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Địa chỉ</label>
                                        <input type="text" class="form-control new-address" 
                                               name="address_line">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Phường/Xã</label>
                                        <input type="text" class="form-control new-address" 
                                               name="ward">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Quận/Huyện</label>
                                        <input type="text" class="form-control new-address" 
                                               name="district">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tỉnh/Thành phố</label>
                                        <input type="text" class="form-control new-address" 
                                               name="city">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input"
                                                   name="save_address" value="1"
                                                   id="saveAddress" checked>
                                            <label class="form-check-label" for="saveAddress">
                                                Lưu địa chỉ này cho lần sau
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment & Shipping -->
                    <div class="card fade-in" style="animation-delay: 0.2s;">
                        <div class="card-body">
                            <h5 class="card-title">Phương thức thanh toán</h5>
                            
                            <div class="payment-methods">
                                <div class="payment-method-card payment-cod slide-in selected" style="animation-delay: 0.1s;" data-payment="cod">
                                    <div class="payment-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">Thanh toán khi nhận hàng (COD)</div>
                                        <div class="payment-description">Thanh toán bằng tiền mặt khi nhận hàng</div>
                                    </div>
                                    <input type="radio" class="form-check-input" name="payment_method"
                                           value="cod" required checked id="payment_cod">
                                </div>
                                
                                <div class="payment-method-card payment-bank slide-in" style="animation-delay: 0.2s;" data-payment="bank_transfer">
                                    <div class="payment-icon">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">Chuyển khoản ngân hàng</div>
                                        <div class="payment-description">Thanh toán qua chuyển khoản ngân hàng</div>
                                    </div>
                                    <input type="radio" class="form-check-input" name="payment_method"
                                           value="bank_transfer" required id="payment_bank">
                                </div>

                                <div class="payment-method-card payment-vnpay slide-in" style="animation-delay: 0.3s;" data-payment="vnpay">
                                    <div class="payment-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">VNPay</div>
                                        <div class="payment-description">Thanh toán qua cổng VNPay</div>
                                    </div>
                                    <input type="radio" class="form-check-input" name="payment_method"
                                           value="vnpay" required id="payment_vnpay">
                                </div>
                                
                                <div class="payment-method-card payment-momo slide-in" style="animation-delay: 0.4s;" data-payment="momo">
                                    <div class="payment-icon">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">ATM MoMo</div>
                                        <div class="payment-description">Thanh toán qua ví điện tử MoMo</div>
                                    </div>
                                    <input type="radio" class="form-check-input" name="payment_method"
                                           value="momo" required id="payment_momo">
                                </div>
                            </div>

                            <h5 class="card-title mt-5">Phương thức vận chuyển</h5>
                            
                            <div class="shipping-methods">
                                <div class="shipping-method-card shipping-standard slide-in selected" style="animation-delay: 0.5s;" data-shipping="standard">
                                    <div class="shipping-icon">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="shipping-details">
                                        <div class="shipping-name">Giao hàng tiêu chuẩn</div>
                                        <div class="shipping-description">Nhận hàng trong 2-3 ngày làm việc</div>
                                    </div>
                                    <div class="shipping-fee shipping-free">Miễn phí</div>
                                    <input type="radio" class="form-check-input" name="shipping_method"
                                           value="standard" required checked id="shipping_standard">
                                </div>
                                
                                <div class="shipping-method-card shipping-express slide-in" style="animation-delay: 0.6s;" data-shipping="express">
                                    <div class="shipping-icon">
                                        <i class="fas fa-shipping-fast"></i>
                                    </div>
                                    <div class="shipping-details">
                                        <div class="shipping-name">Giao hàng nhanh</div>
                                        <div class="shipping-description">Nhận hàng trong 1-2 ngày làm việc</div>
                                    </div>
                                    <div class="shipping-fee shipping-paid">+30,000đ</div>
                                    <input type="radio" class="form-check-input" name="shipping_method"
                                           value="express" required id="shipping_express">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Order summary -->
                    <div class="card order-summary fade-in" style="animation-delay: 0.3s;">
                        <div class="card-body">
                            <h5 class="card-title">Tổng đơn hàng</h5>

                            <div class="order-items-container">
                                <?php foreach ($cart_items as $index => $item): ?>
                                    <div class="order-item fade-in" style="animation-delay: <?= $index * 0.1 + 0.4 ?>s;">
                                        <div class="order-item-name"><?= htmlspecialchars($item['product']['name']) ?></div>
                                        <div class="order-item-quantity">SL: <?= $item['quantity'] ?></div>
                                        <div class="order-item-price"><?= format_currency($item['subtotal']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="summary-divider">

                            <div class="summary-row fade-in" style="animation-delay: 0.7s;">
                                <span>Tạm tính:</span>
                                <span><?= format_currency($total) ?></span>
                            </div>

                            <?php if ($discount > 0): ?>
                                <div class="summary-row fade-in" style="animation-delay: 0.8s;">
                                    <span>Giảm giá:</span>
                                    <span class="text-danger">-<?= format_currency($discount) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="summary-row fade-in" style="animation-delay: 0.9s;">
                                <span>Phí vận chuyển:</span>
                                <span id="shippingFee">Miễn phí</span>
                            </div>

                            <hr class="summary-divider">

                            <div class="total-row fade-in" style="animation-delay: 1s;">
                                <div class="total-label">Tổng cộng:</div>
                                <div class="total-price" id="totalPrice">
                                    <?= format_currency($total - $discount) ?>
                                </div>
                            </div>

                            <button type="submit" class="checkout-btn fade-in" style="animation-delay: 1.1s;">
                                <i class="fas fa-lock"></i> Đặt hàng
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Checkout JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Address card selection
    const addressCards = document.querySelectorAll('.address-card');
    addressCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selected class from all cards
            addressCards.forEach(c => c.classList.remove('selected'));
            // Add selected class to clicked card
            this.classList.add('selected');
            // Check the radio button
            const radioInput = this.querySelector('input[type="radio"]');
            radioInput.checked = true;
        });
    });

    // New address toggle
    const newAddressToggle = document.getElementById('newAddressToggle');
    const newAddressForm = document.getElementById('newAddressForm');
    
    if (newAddressToggle && newAddressForm) {
        newAddressToggle.addEventListener('click', function() {
            newAddressForm.classList.toggle('active');
            newAddressToggle.classList.toggle('active');
        });
    }

    // Payment method selection
    const paymentMethods = document.querySelectorAll('.payment-method-card');
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            // Remove selected class from all methods
            paymentMethods.forEach(m => m.classList.remove('selected'));
            // Add selected class to clicked method
            this.classList.add('selected');
            // Check the radio button
            const radioInput = this.querySelector('input[type="radio"]');
            radioInput.checked = true;
        });
    });

    // Shipping method selection
    const shippingMethods = document.querySelectorAll('.shipping-method-card');
    const totalPriceElement = document.getElementById('totalPrice');
    const shippingFeeElement = document.getElementById('shippingFee');
    
    // Get the initial total price
    let basePrice = <?= $total - $discount ?>;
    let currentPrice = basePrice;
    let expressShippingFee = 30000; // 30,000đ
    
    shippingMethods.forEach(method => {
        method.addEventListener('click', function() {
            // Remove selected class from all methods
            shippingMethods.forEach(m => m.classList.remove('selected'));
            // Add selected class to clicked method
            this.classList.add('selected');
            // Check the radio button
            const radioInput = this.querySelector('input[type="radio"]');
            radioInput.checked = true;
            
            // Update shipping fee and total price
            if (this.dataset.shipping === 'express') {
                shippingFeeElement.textContent = formatCurrency(expressShippingFee);
                shippingFeeElement.classList.add('shipping-fee-highlight');
                currentPrice = basePrice + expressShippingFee;
            } else {
                shippingFeeElement.textContent = 'Miễn phí';
                shippingFeeElement.classList.remove('shipping-fee-highlight');
                currentPrice = basePrice;
            }
            
            // Update total price with animation
            updatePriceWithAnimation(totalPriceElement, currentPrice);
        });
    });

    // Checkout form validation with visual feedback
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Check if an address is selected or new address fields are filled
            const addressSelected = document.querySelector('input[name="address_id"]:checked');
            const newAddressActive = newAddressForm.classList.contains('active');
            
            if (!addressSelected && !newAddressActive) {
                showValidationError('Vui lòng chọn địa chỉ giao hàng hoặc thêm địa chỉ mới');
                isValid = false;
            }
            
            if (newAddressActive) {
                const newAddressInputs = document.querySelectorAll('.new-address');
                newAddressInputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    }
                });
                
                if (!isValid) {
                    showValidationError('Vui lòng điền đầy đủ thông tin địa chỉ mới');
                }
            }
            
            if (!isValid) {
                event.preventDefault();
            } else {
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                if (paymentMethod === 'vnpay') {
                    event.preventDefault();
                    // Show loading state
                    const submitButton = checkoutForm.querySelector('button[type="submit"]');
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                    submitButton.disabled = true;

                    // Submit form data to create order first
                    const formData = new FormData(checkoutForm);
                    fetch(checkoutForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.order_id) {
                            // Redirect to VNPay payment
                            window.location.href = 'vnpay_php/vnpay_create_payment.php?order_id=' + data.order_id + '&amount=' + <?php echo $total - $discount; ?>;
                        } else {
                            showValidationError(data.message || 'Có lỗi xảy ra');
                        }
                    })
                    .catch(error => {
                        showValidationError('Có lỗi xảy ra');
                    });
                } else {
                    // Show loading state for COD
                    const submitButton = checkoutForm.querySelector('button[type="submit"]');
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
                    submitButton.disabled = true;
                    
                    // Add success animation
                    document.querySelector('.order-summary').classList.add('pulse');
                }
            }
        });
    }
    
    // Helper function to update price with animation
    function updatePriceWithAnimation(element, newPrice) {
        if (!element) return;
        
        // Add animation class
        element.classList.add('price-update');
        
        // Update the price after a short delay
        setTimeout(() => {
            element.textContent = formatCurrency(newPrice);
            
            // Remove animation class after animation completes
            setTimeout(() => {
                element.classList.remove('price-update');
            }, 300);
        }, 200);
    }
    
    // Helper function to show validation error
    function showValidationError(message) {
        // Create error element if it doesn't exist
        let errorElement = document.getElementById('validation-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.id = 'validation-error';
            errorElement.className = 'validation-error';
            document.querySelector('.checkout-container').appendChild(errorElement);
        }
        
        // Show error with animation
        errorElement.textContent = message;
        errorElement.classList.add('show');
        
        // Hide after 5 seconds
        setTimeout(() => {
            errorElement.classList.remove('show');
        }, 5000);
    }
    
    // Helper function to format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND',
            minimumFractionDigits: 0
        }).format(amount);
    }
    
    // Add some animations on page load
    setTimeout(() => {
        const elements = document.querySelectorAll('.fade-in, .slide-in');
        elements.forEach(el => {
            el.classList.add('show');
        });
    }, 100);
});
</script>

<?php include 'includes/footer.php'; ?>