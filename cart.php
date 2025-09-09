<?php
// Tắt hiển thị lỗi để tránh xuất HTML không mong muốn
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config/config.php';
require_once 'config/functions.php';


$pdo = getPDO();

// Nếu user đã đăng nhập thì lấy giỏ hàng từ database, nếu chưa thì lấy từ session
$user_id = $_SESSION['user_id'] ?? 0;

// Khởi tạo giỏ hàng session nếu chưa có (cho khách vãng lai)
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Xử lý các action (chỉ áp dụng cho session cart, nếu muốn đồng bộ DB thì cần code thêm)
$action = $_GET['action'] ?? '';
$product_id = (int)($_GET['id'] ?? 0);
$quantity = (int)($_GET['quantity'] ?? 1);

// Kiểm tra đăng nhập cho các thao tác thêm/sửa/xóa giỏ hàng
if (($action === 'add' || $action === 'update' || $action === 'remove') && !is_logged_in()) {
    if (isset($_POST['action']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        // AJAX request - trả về JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng đăng nhập để sử dụng giỏ hàng',
            'redirect' => 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])
        ]);
        exit;
    } else {
        // GET request - redirect
        flash('error', 'Vui lòng đăng nhập để sử dụng giỏ hàng');
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

// Xử lý AJAX thêm vào giỏ hàng (chỉ với POST request)
if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'remove' || $_POST['action'] === 'update') && isset($_POST['id'])) {
    
    // Làm sạch output buffer để tránh HTML không mong muốn
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Lấy từ POST
    $action_ajax = $_POST['action'];
    $product_id_ajax = (int)$_POST['id'];
    $quantity_ajax = (int)($_POST['quantity'] ?? 1);

    $response = [
        'success' => false,
        'cart_count' => 0,
        'message' => 'Có lỗi xảy ra!'
    ];

    try {

    // Xử lý action remove
    if ($action_ajax === 'remove') {
        if ($user_id) {
            // Lấy cart_id của user
            $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $cart_id = $stmt->fetchColumn();
            if ($cart_id) {
                // Xóa sản phẩm khỏi cart_items trong database
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
                $stmt->execute([$cart_id, $product_id_ajax]);
                
                // Đếm lại số lượng sản phẩm trong giỏ
                $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE cart_id = ?");
                $stmt->execute([$cart_id]);
                $cart_count = (int)$stmt->fetchColumn();
                $response['cart_count'] = $cart_count;
                $response['success'] = true;
                $response['message'] = 'Đã xóa sản phẩm khỏi giỏ hàng';
            }
        } else {
            // Xóa khỏi session cho khách vãng lai
            unset($_SESSION['cart'][$product_id_ajax]);
            $response['cart_count'] = array_sum($_SESSION['cart']);
            $response['success'] = true;
            $response['message'] = 'Đã xóa sản phẩm khỏi giỏ hàng';
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Xử lý action add
    if ($action_ajax === 'add') {
        // Lấy thông tin sản phẩm
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ? AND status = 'active'");
        $stmt->execute([$product_id_ajax]);
        $product = $stmt->fetch();
        if ($product) {
            if ($product['stock'] < $quantity_ajax) {
                $response['message'] = 'Số lượng sản phẩm trong kho không đủ';
            } else {
                if ($user_id) {
                    // Lấy cart_id của user
                    $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $cart_id = $stmt->fetchColumn();
                    if (!$cart_id) {
                        $stmt = $pdo->prepare("INSERT INTO carts (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
                        $stmt->execute([$user_id]);
                        $cart_id = $pdo->lastInsertId();
                    }
                    // Kiểm tra xem sản phẩm đã có trong cart_items chưa
                    $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
                    $stmt->execute([$cart_id, $product_id_ajax]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $new_quantity = $row['quantity'] + $quantity_ajax;
                        if ($new_quantity > $product['stock']) {
                            $new_quantity = $product['stock'];
                            $response['message'] = 'Đã điều chỉnh số lượng theo tồn kho';
                        } else {
                            $response['message'] = 'Đã thêm sản phẩm vào giỏ hàng';
                        }
                        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
                        $stmt->execute([$new_quantity, $row['cart_item_id']]);
                    } else {
                        $add_quantity = min($quantity_ajax, $product['stock']);
                        $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                        $stmt->execute([$cart_id, $product_id_ajax, $add_quantity]);
                        if ($add_quantity < $quantity_ajax) {
                            $response['message'] = 'Đã điều chỉnh số lượng theo tồn kho';
                        } else {
                            $response['message'] = 'Đã thêm sản phẩm vào giỏ hàng';
                        }
                    }
                    // Đếm lại số lượng sản phẩm trong giỏ
                    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE cart_id = ?");
                    $stmt->execute([$cart_id]);
                    $cart_count = (int)$stmt->fetchColumn();
                    $response['cart_count'] = $cart_count;
                    $response['success'] = true;
                } else {
                    if (isset($_SESSION['cart'][$product_id_ajax])) {
                        $_SESSION['cart'][$product_id_ajax] += $quantity_ajax;
                    } else {
                        $_SESSION['cart'][$product_id_ajax] = $quantity_ajax;
                    }
                    if ($_SESSION['cart'][$product_id_ajax] > $product['stock']) {
                        $_SESSION['cart'][$product_id_ajax] = $product['stock'];
                        $response['message'] = 'Đã điều chỉnh số lượng theo tồn kho';
                    } else {
                        $response['message'] = 'Đã thêm sản phẩm vào giỏ hàng';
                    }
                    $response['cart_count'] = array_sum($_SESSION['cart']);
                    $response['success'] = true;
                }
            }
        } else {
            $response['message'] = 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh';
        }
    }

    // Xử lý action update
    if ($action_ajax === 'update') {
        if ($quantity_ajax <= 0) {
            // Nếu quantity = 0 thì xóa sản phẩm
            if ($user_id) {
                // Lấy cart_id của user
                $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $cart_id = $stmt->fetchColumn();
                if ($cart_id) {
                    // Xóa sản phẩm khỏi cart_items trong database
                    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
                    $stmt->execute([$cart_id, $product_id_ajax]);
                    
                    // Đếm lại số lượng sản phẩm trong giỏ
                    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE cart_id = ?");
                    $stmt->execute([$cart_id]);
                    $cart_count = (int)$stmt->fetchColumn();
                    $response['cart_count'] = $cart_count;
                    $response['success'] = true;
                    $response['message'] = 'Đã xóa sản phẩm khỏi giỏ hàng';
                }
            } else {
                // Xóa khỏi session cho khách vãng lai
                unset($_SESSION['cart'][$product_id_ajax]);
                $response['cart_count'] = array_sum($_SESSION['cart']);
                $response['success'] = true;
                $response['message'] = 'Đã xóa sản phẩm khỏi giỏ hàng';
            }
        } else {
            // Cập nhật số lượng bình thường
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE product_id = ? AND status = 'active'");
            $stmt->execute([$product_id_ajax]);
            $product = $stmt->fetch();
            if ($product) {
                $stock = $product['stock'];
                if ($quantity_ajax > $stock) {
                    $quantity_ajax = $stock;
                    $response['message'] = 'Đã điều chỉnh số lượng theo tồn kho';
                }
                
                if ($user_id) {
                    // Lấy cart_id của user
                    $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $cart_id = $stmt->fetchColumn();
                    if ($cart_id) {
                        // Kiểm tra xem sản phẩm đã có trong cart_items chưa
                        $stmt = $pdo->prepare("SELECT cart_item_id FROM cart_items WHERE cart_id = ? AND product_id = ?");
                        $stmt->execute([$cart_id, $product_id_ajax]);
                        $cart_item_id = $stmt->fetchColumn();
                        if ($cart_item_id) {
                            // Cập nhật số lượng
                            $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
                            $stmt->execute([$quantity_ajax, $cart_item_id]);
                        } else {
                            // Thêm mới nếu chưa có
                            $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                            $stmt->execute([$cart_id, $product_id_ajax, $quantity_ajax]);
                        }
                        
                        // Đếm lại số lượng sản phẩm trong giỏ
                        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart_items WHERE cart_id = ?");
                        $stmt->execute([$cart_id]);
                        $cart_count = (int)$stmt->fetchColumn();
                        $response['cart_count'] = $cart_count;
                        $response['success'] = true;
                        $response['message'] = $response['message'] ?? 'Đã cập nhật số lượng sản phẩm';
                    }
                } else {
                    $_SESSION['cart'][$product_id_ajax] = $quantity_ajax;
                    $response['cart_count'] = array_sum($_SESSION['cart']);
                    $response['success'] = true;
                    $response['message'] = $response['message'] ?? 'Đã cập nhật số lượng sản phẩm';
                }
            } else {
                $response['message'] = 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh';
            }
        }
    }

    } catch (Exception $e) {
        $response['message'] = 'Đã xảy ra lỗi: ' . $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Xử lý GET request thông thường (không phải AJAX)

if ($action && $product_id) {
    switch ($action) {
        case 'add':
            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ? AND status = 'active'");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if ($product) {
                if ($product['stock'] < $quantity) {
                    flash('error', 'Số lượng sản phẩm trong kho không đủ');
                } else {
                    if ($user_id) {
                        // Lấy cart_id của user
                        $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $cart_id = $stmt->fetchColumn();
                        if (!$cart_id) {
                            // Nếu chưa có cart, tạo mới
                            $stmt = $pdo->prepare("INSERT INTO carts (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
                            $stmt->execute([$user_id]);
                            $cart_id = $pdo->lastInsertId();
                        }
                        // Kiểm tra xem sản phẩm đã có trong cart_items chưa
                        $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
                        $stmt->execute([$cart_id, $product_id]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $new_quantity = $row['quantity'] + $quantity;
                            if ($new_quantity > $product['stock']) {
                                $new_quantity = $product['stock'];
                                flash('warning', 'Đã điều chỉnh số lượng theo tồn kho');
                            } else {
                                flash('success', 'Đã thêm sản phẩm vào giỏ hàng');
                            }
                            $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
                            $stmt->execute([$new_quantity, $row['cart_item_id']]);
                        } else {
                            $add_quantity = min($quantity, $product['stock']);
                            $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                            $stmt->execute([$cart_id, $product_id, $add_quantity]);
                            if ($add_quantity < $quantity) {
                                flash('warning', 'Đã điều chỉnh số lượng theo tồn kho');
                            } else {
                                flash('success', 'Đã thêm sản phẩm vào giỏ hàng');
                            }
                        }
                    } else {
                        if (isset($_SESSION['cart'][$product_id])) {
                            $_SESSION['cart'][$product_id] += $quantity;
                        } else {
                            $_SESSION['cart'][$product_id] = $quantity;
                        }
                        if ($_SESSION['cart'][$product_id] > $product['stock']) {
                            $_SESSION['cart'][$product_id] = $product['stock'];
                            flash('warning', 'Đã điều chỉnh số lượng theo tồn kho');
                        } else {
                            flash('success', 'Đã thêm sản phẩm vào giỏ hàng');
                        }
                    }
                }
            }
            // Redirect về cart page sau khi add thành công
            header('Location: cart.php');
            exit;
            break;
        case 'update':
            if ($quantity > 0) {
                $stmt = $pdo->prepare("SELECT stock FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $stock = $stmt->fetchColumn();
                if ($quantity > $stock) {
                    $quantity = $stock;
                    flash('warning', 'Đã điều chỉnh số lượng theo tồn kho');
                }
                if ($user_id) {
                    // Lấy cart_id của user
                    $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $cart_id = $stmt->fetchColumn();
                    if ($cart_id) {
                        // Kiểm tra xem sản phẩm đã có trong cart_items chưa
                        $stmt = $pdo->prepare("SELECT cart_item_id FROM cart_items WHERE cart_id = ? AND product_id = ?");
                        $stmt->execute([$cart_id, $product_id]);
                        $cart_item_id = $stmt->fetchColumn();
                        if ($cart_item_id) {
                            // Cập nhật số lượng
                            $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
                            $stmt->execute([$quantity, $cart_item_id]);
                        } else {
                            // Thêm mới nếu chưa có
                            $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                            $stmt->execute([$cart_id, $product_id, $quantity]);
                        }
                    }
                } else {
                    $_SESSION['cart'][$product_id] = $quantity;
                }
            } else {
                // Nếu quantity = 0 thì xóa sản phẩm
                if ($user_id) {
                    // Lấy cart_id của user
                    $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $cart_id = $stmt->fetchColumn();
                    if ($cart_id) {
                        // Xóa sản phẩm khỏi cart_items trong database
                        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
                        $stmt->execute([$cart_id, $product_id]);
                        flash('success', 'Đã xóa sản phẩm khỏi giỏ hàng');
                    }
                } else {
                    // Xóa khỏi session cho khách vãng lai
                    unset($_SESSION['cart'][$product_id]);
                    flash('success', 'Đã xóa sản phẩm khỏi giỏ hàng');
                }
            }
            break;
        case 'remove':
            if ($user_id) {
                // Lấy cart_id của user
                $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $cart_id = $stmt->fetchColumn();
                if ($cart_id) {
                    // Xóa sản phẩm khỏi cart_items trong database
                    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
                    $stmt->execute([$cart_id, $product_id]);
                }
            } else {
                // Xóa khỏi session cho khách vãng lai
                unset($_SESSION['cart'][$product_id]);
            }
            flash('success', 'Đã xóa sản phẩm khỏi giỏ hàng');
            break;
            
        case 'clear':
            if ($user_id) {
                $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $cart_id = $stmt->fetchColumn();
                if ($cart_id) {
                    // Xóa toàn bộ sản phẩm trong cart_items
                    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                    $stmt->execute([$cart_id]);
                }
            }
            // Xóa giỏ hàng session cho cả user và khách vãng lai
            unset($_SESSION['cart']);
            // Xóa mã khuyến mãi nếu có
            unset($_SESSION['promo']);
            flash('success', 'Đã xóa toàn bộ giỏ hàng!');
            break;
    }
    if (isset($_SERVER['HTTP_REFERER']) && !str_contains($_SERVER['HTTP_REFERER'], 'cart.php')) {
        redirect($_SERVER['HTTP_REFERER']);
    }
}

$cart_items = [];
$total = 0;
$discount = 0;
$promo_code = '';

if ($user_id) {
    // Lấy cart_id của user
    $stmt = $pdo->prepare("SELECT cart_id FROM carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_id = $stmt->fetchColumn();
    if ($cart_id) {
        $stmt = $pdo->prepare("
            SELECT ci.cart_item_id, ci.quantity, p.*, 
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
            $cart_items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
        }
    }
}

// Nếu chưa đăng nhập hoặc user không có cart trong DB thì lấy từ session
if (empty($cart_items) && !empty($_SESSION['cart'])) {
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
        $cart_items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}

// Xử lý mã khuyến mãi

// Áp dụng mã giảm giá theo bảng promotions
if (isset($_POST['apply_promo']) && isset($_POST['promo_code'])) {
    $promo_code = sanitize($_POST['promo_code']);
    
    if (empty(trim($promo_code))) {
        // Nếu mã giảm giá trống, xóa session promo và reset discount về 0
        unset($_SESSION['promo']);
        $discount = 0;
        $promo_code = '';
        flash('info', 'Đã xóa mã giảm giá');
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM promotions
            WHERE code = ?
            AND active = 1
            AND start_date <= CURDATE()
            AND end_date >= CURDATE()
        ");
        $stmt->execute([$promo_code]);
        $promo = $stmt->fetch();
        if ($promo) {
            $min_order = isset($promo['min_order_amount']) ? (float)$promo['min_order_amount'] : 0;
            $discount_percent = isset($promo['discount_percent']) ? (float)$promo['discount_percent'] : 0;
            if ($total >= $min_order) {
                $discount = round($total * $discount_percent / 100, 2);
                $_SESSION['promo'] = [
                    'code' => $promo_code,
                    'discount' => $discount
                ];
                flash('success', 'Đã áp dụng mã giảm giá');
            } else {
                flash('error', "Đơn hàng tối thiểu {$min_order}đ để sử dụng mã này");
            }
        } else {
            flash('error', 'Mã giảm giá không hợp lệ hoặc đã hết hạn');
        }
    }
}

if (isset($_SESSION['promo'])) {
    $promo_code = $_SESSION['promo']['code'];
    $discount = $_SESSION['promo']['discount'];
}

include 'includes/navbar.php';
?>

<link rel="stylesheet" href="css/cart.css">

<div class="page-wrapper">
    <div class="cart-container">
        <div class="container">
            <h1 class="cart-title bounce-in">
                <i class="fas fa-shopping-cart"></i> Giỏ hàng của bạn
            </h1>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart fade-in">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3 class="mb-3">Giỏ hàng của bạn đang trống</h3>
                <p class="text-muted mb-4">Hãy khám phá các sản phẩm tuyệt vời của chúng tôi!</p>
                <a href="products.php" class="btn btn-modern btn-continue">
                    <i class="fas fa-store"></i> Khám phá sản phẩm
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="cart-card fade-in">
                        <?php foreach ($cart_items as $index => $item): ?>
                            <div class="cart-item" style="animation-delay: <?= $index * 0.1 ?>s">
                                <div class="row align-items-center">
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center">
                                            <?php if ($item['product']['product_image']): ?>
                                                <img src="<?= $item['product']['product_image'] ?>" 
                                                     class="product-image me-3"
                                                     alt="<?= htmlspecialchars($item['product']['name']) ?>">
                                            <?php else: ?>
                                                <img src="assets/images/no-image.jpg" 
                                                     class="product-image me-3"
                                                     alt="No image">
                                            <?php endif; ?>
                                            <div class="product-info">
                                                <h6>
                                                    <a href="product.php?id=<?= $item['product']['product_id'] ?>"
                                                       class="text-decoration-none">
                                                        <?= htmlspecialchars($item['product']['name']) ?>
                                                    </a>
                                                </h6>
                                                <div class="stock-info">
                                                    <i class="fas fa-box"></i> Còn <?= $item['product']['stock'] ?> sản phẩm
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3 text-center">
                                        <div class="quantity-controls input-group">
                                            <button type="button" class="quantity-btn"
                                                    onclick="updateQuantity(<?= $item['product']['product_id'] ?>, -1)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" class="form-control quantity-input"
                                                   value="<?= $item['quantity'] ?>"
                                                   min="1" max="<?= $item['product']['stock'] ?>"
                                                   onchange="updateQuantity(<?= $item['product']['product_id'] ?>, this.value)">
                                            <button type="button" class="quantity-btn"
                                                    onclick="updateQuantity(<?= $item['product']['product_id'] ?>, 1)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2 text-center">
                                        <div class="price-display">
                                            <?= format_currency($item['product']['price']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2 text-center">
                                        <div class="subtotal-display">
                                            <?= format_currency($item['subtotal']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-auto text-end">
                                        <button type="button" class="remove-btn" 
                                                onclick="removeItem(<?= $item['product']['product_id'] ?>)"
                                                title="Xóa sản phẩm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="action-buttons fade-in">
                        <a href="products.php" class="btn btn-modern btn-continue">
                            <i class="fas fa-arrow-left"></i> Tiếp tục mua sắm
                        </a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="order-summary fade-in">
                        <div class="summary-header">
                            <i class="fas fa-receipt"></i> Tổng đơn hàng
                        </div>
                        
                        <div class="summary-body">
                            <!-- Promo code form -->
                            <form method="post" class="promo-form">
                                <div class="input-group">
                                    <input type="text" class="form-control promo-input" name="promo_code" 
                                           placeholder="Nhập mã giảm giá"
                                           value="<?= htmlspecialchars($promo_code) ?>">
                                    <button type="submit" name="apply_promo" class="btn promo-btn">
                                        <i class="fas fa-tags"></i>
                                    </button>
                                </div>
                            </form>

                            <div class="summary-row">
                                <span><i class="fas fa-calculator"></i> Tạm tính:</span>
                                <span><?= format_currency($total) ?></span>
                            </div>

                            <?php if ($discount > 0): ?>
                                <div class="summary-row">
                                    <span><i class="fas fa-percent"></i> Giảm giá:</span>
                                    <span class="text-warning">-<?= format_currency($discount) ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="summary-row">
                                <span><i class="fas fa-shipping-fast"></i> Vận chuyển:</span>
                                <span style="color: white;">Miễn phí</span>
                            </div>

                            <hr class="summary-divider">

                            <div class="total-row">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h5 mb-0">
                                        <i class="fas fa-coins"></i> Tổng cộng:
                                    </span>
                                    <span class="total-amount">
                                        <?= format_currency($total - $discount) ?>
                                    </span>
                                </div>
                            </div>

                            <button onclick="proceedToCheckout()" class="checkout-btn">
                                <i class="fas fa-lock"></i> Thanh toán an toàn
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<script>
// Enhanced JavaScript functionality
function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function updateQuantity(productId, quantity) {
    showLoading();
    
    if (typeof quantity !== 'number') {
        quantity = parseInt(quantity);
    }
    
    // Nếu là nút +/- thì cộng/trừ với số lượng hiện tại
    if (quantity === 1 || quantity === -1) {
        const input = document.querySelector(`input[onchange*="${productId}"]`);
        const currentQuantity = parseInt(input.value);
        quantity = currentQuantity + quantity;
    }
    
    if (quantity > 0) {
        // Add smooth transition
        setTimeout(() => {
            window.location.href = `cart.php?action=update&id=${productId}&quantity=${quantity}`;
        }, 500);
    } else {
        hideLoading();
    }
}

function removeItem(productId) {
    // Custom confirmation dialog
    showCustomConfirm(
        'Xác nhận xóa', 
        'Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?',
        'Xóa', 
        'Hủy'
    ).then(result => {
        if (result) {
            showLoading();
            
            // Add animation before redirect
            const item = document.querySelector(`button[onclick*="${productId}"]`).closest('.cart-item');
            item.style.animation = 'fadeOut 0.5s ease-out';
            
            // Try to use AJAX if available in the browser
            if (window.fetch) {
                fetch(`cart.php?action=update&id=${productId}&quantity=0`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=update&id=${productId}&quantity=0`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart count
                        const cartCountElement = document.querySelector('.cart-count');
                        if (cartCountElement) {
                            cartCountElement.textContent = data.cart_count;
                        }
                        
                        // Use window.updateCartCount if available (defined in navbar.php)
                        if (typeof window.updateCartCount === 'function') {
                            window.updateCartCount(data.cart_count);
                        }
                        
                        // Remove the item from DOM
                        setTimeout(() => {
                            item.remove();
                            
                            // If no items left, reload to show empty cart
                            const remainingItems = document.querySelectorAll('.cart-item');
                            if (remainingItems.length === 0) {
                                window.location.reload();
                                return;
                            }
                            
                            // Recalculate subtotal
                            recalculateCart();
                            
                            // Show notification
                            showNotification(data.message, 'success');
                            hideLoading();
                        }, 500);
                    } else {
                        showNotification(data.message || 'Có lỗi xảy ra', 'error');
                        hideLoading();
                        item.style.animation = '';
                    }
                })
                .catch(error => {
                    console.error('Error removing item:', error);
                    // Fallback to traditional page load if AJAX fails
                    setTimeout(() => {
                        window.location.href = `cart.php?action=update&id=${productId}&quantity=0`;
                    }, 500);
                });
            } else {
                // Fallback for browsers without fetch
                setTimeout(() => {
                    window.location.href = `cart.php?action=update&id=${productId}&quantity=0`;
                }, 500);
            }
        }
    });
}

// Function to recalculate cart totals
function recalculateCart() {
    // Calculate subtotal
    let subtotal = 0;
    document.querySelectorAll('.subtotal-display').forEach(elem => {
        const value = parseFloat(elem.textContent.replace(/[^\d]/g, ''));
        subtotal += value;
    });
    
    // Update subtotal display
    const subtotalElement = document.querySelector('.summary-row:first-child span:last-child');
    if (subtotalElement) {
        subtotalElement.textContent = formatCurrency(subtotal);
    }
    
    // Get discount if any
    let discount = 0;
    const discountElement = document.querySelector('.summary-row:nth-child(2) span:last-child');
    if (discountElement) {
        const discountText = discountElement.textContent;
        discount = parseFloat(discountText.replace(/[^\d]/g, '')) || 0;
    }
    
    // Update total
    const totalElement = document.querySelector('.total-amount');
    if (totalElement) {
        totalElement.textContent = formatCurrency(subtotal - discount);
    }
}

// Format currency function
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', { 
        style: 'decimal',
        maximumFractionDigits: 0 
    }).format(amount) + ' đ';
}

function clearCart() {
    showCustomConfirm(
        'Xóa toàn bộ giỏ hàng', 
        'Bạn có chắc chắn muốn xóa toàn bộ sản phẩm trong giỏ hàng?',
        'Xóa tất cả', 
        'Hủy'
    ).then(result => {
        if (result) {
            showLoading();
            setTimeout(() => {
                window.location.href = 'cart.php?action=clear';
            }, 500);
        }
    });
}

function proceedToCheckout() {
    showLoading();
    
    // Add a slight delay for better UX
    setTimeout(() => {
        window.location.href = 'checkout.php';
    }, 800);
}

function showCustomConfirm(title, message, confirmText, cancelText) {
    return new Promise((resolve) => {
        // Create modal elements
        const modalBackdrop = document.createElement('div');
        modalBackdrop.className = 'custom-modal-backdrop';
        
        const modalContainer = document.createElement('div');
        modalContainer.className = 'custom-modal-container animate__animated animate__zoomIn';
        
        // Modal content
        modalContainer.innerHTML = `
            <div class="custom-modal-header">
                <h5>${title}</h5>
                <button type="button" class="custom-modal-close" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="custom-modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-trash-alt custom-modal-icon"></i>
                </div>
                <p>${message}</p>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn custom-modal-btn-cancel">${cancelText || 'Hủy'}</button>
                <button type="button" class="btn custom-modal-btn-confirm">${confirmText || 'Xác nhận'}</button>
            </div>
        `;
        
        // Add elements to DOM
        document.body.appendChild(modalBackdrop);
        document.body.appendChild(modalContainer);
        
        // Add CSS for the modal
        const modalStyle = document.createElement('style');
        modalStyle.textContent = `
            .custom-modal-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(5px);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease-out;
            }
            
            .custom-modal-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
                width: 90%;
                max-width: 400px;
                overflow: hidden;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 10001;
                margin: 0 !important;
            }
            
            .custom-modal-header {
                padding: 1rem 1.5rem;
                border-bottom: 1px solid #f0f0f0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .custom-modal-header h5 {
                margin: 0;
                font-weight: 600;
            }
            
            .custom-modal-close {
                background: transparent;
                border: none;
                color: white;
                font-size: 1.2rem;
                cursor: pointer;
                opacity: 0.8;
                transition: all 0.3s ease;
            }
            
            .custom-modal-close:hover {
                opacity: 1;
                transform: scale(1.1);
            }
            
            .custom-modal-body {
                padding: 1.5rem;
            }
            
            .custom-modal-icon {
                font-size: 3rem;
                color: #f56565;
                animation: shake 0.8s ease-in-out;
            }
            
            .custom-modal-footer {
                padding: 1rem 1.5rem;
                border-top: 1px solid #f0f0f0;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            
            .custom-modal-btn-cancel {
                background: #f1f1f1;
                color: #666;
                border: none;
                border-radius: 10px;
                padding: 0.5rem 1.5rem;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            
            .custom-modal-btn-cancel:hover {
                background: #e5e5e5;
                transform: translateY(-2px);
            }
            
            .custom-modal-btn-confirm {
                background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
                color: white;
                border: none;
                border-radius: 10px;
                padding: 0.5rem 1.5rem;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            
            .custom-modal-btn-confirm:hover {
                background: linear-gradient(135deg, #ee5a5a, #e03131);
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(238, 90, 90, 0.3);
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes shake {
                0%, 100% { transform: rotate(0deg); }
                10%, 30%, 50%, 70%, 90% { transform: rotate(-5deg); }
                20%, 40%, 60%, 80% { transform: rotate(5deg); }
            }
        `;
        document.head.appendChild(modalStyle);
        
        // Event listeners
        const closeBtn = modalContainer.querySelector('.custom-modal-close');
        const cancelBtn = modalContainer.querySelector('.custom-modal-btn-cancel');
        const confirmBtn = modalContainer.querySelector('.custom-modal-btn-confirm');
        
        const closeModal = (result) => {
            modalContainer.classList.replace('animate__zoomIn', 'animate__zoomOut');
            modalBackdrop.style.opacity = '0';
            
            setTimeout(() => {
                document.body.removeChild(modalBackdrop);
                document.body.removeChild(modalContainer);
                resolve(result);
            }, 300);
        };
        
        closeBtn.addEventListener('click', () => closeModal(false));
        cancelBtn.addEventListener('click', () => closeModal(false));
        confirmBtn.addEventListener('click', () => closeModal(true));
        
        // Close on backdrop click
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) {
                closeModal(false);
            }
        });
        
        // Close on ESC key
        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escHandler);
                closeModal(false);
            }
        });
    });
}

// Enhanced animations and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add Animate.css if not already included
    if (!document.querySelector('link[href*="animate.css"]')) {
        const animateCSS = document.createElement('link');
        animateCSS.rel = 'stylesheet';
        animateCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css';
        document.head.appendChild(animateCSS);
    }
    
    // Add smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
    
    // Add quantity input validation
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('input', function() {
            const min = parseInt(this.min);
            const max = parseInt(this.max);
            let value = parseInt(this.value);
            
            if (value < min) this.value = min;
            if (value > max) this.value = max;
        });
    });
    
    // Add hover effects to cart items
    document.querySelectorAll('.cart-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Add pulse animation to total amount
    const totalAmount = document.querySelector('.total-amount');
    if (totalAmount) {
        setInterval(() => {
            totalAmount.style.animation = 'pulse 0.5s ease-in-out';
            setTimeout(() => {
                totalAmount.style.animation = '';
            }, 500);
        }, 3000);
    }
});

// Add CSS animation keyframes
const animationStyle = document.createElement('style');
animationStyle.textContent = `
    @keyframes fadeOut {
        to {
            opacity: 0;
            transform: translateX(-100px);
        }
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    @keyframes glow {
        0%, 100% {
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.5);
        }
        50% {
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.8);
        }
    }
`;
document.head.appendChild(animationStyle);

// Add notification system
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add notification styles
    const notificationStyle = document.createElement('style');
    notificationStyle.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            animation: slideInRight 0.5s ease-out;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .notification-success {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }
        
        .notification-error {
            background: linear-gradient(135deg, #f56565, #e53e3e);
        }
        
        .notification-warning {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0;
            margin-left: auto;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }
        
        .notification-close:hover {
            opacity: 1;
        }
        
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
    `;
    
    if (!document.querySelector('#notification-styles')) {
        notificationStyle.id = 'notification-styles';
        document.head.appendChild(notificationStyle);
    }
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideInRight 0.5s ease-out reverse';
            setTimeout(() => notification.remove(), 500);
        }
    }, 5000);
}

// Enhanced form validation
function validatePromoCode() {
    const promoInput = document.querySelector('input[name="promo_code"]');
    const promoBtn = document.querySelector('button[name="apply_promo"]');
    
    if (promoInput && promoBtn) {
        promoInput.addEventListener('input', function() {
            if (this.value.length >= 3) {
                promoBtn.style.background = 'rgba(72, 187, 120, 0.8)';
                promoBtn.style.color = 'white';
            } else {
                promoBtn.style.background = 'rgba(255, 255, 255, 0.2)';
                promoBtn.style.color = 'white';
            }
        });
        
        promoInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                promoBtn.click();
            }
        });
    }
}

// Initialize enhanced features
validatePromoCode();

// Add cart item counter animation
function animateCartCount() {
    const cartItems = document.querySelectorAll('.cart-item');
    const cartCount = cartItems.length;
    
    if (cartCount > 0) {
        cartItems.forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
            item.classList.add('fade-in');
        });
    }
}

// Initialize cart animations
animateCartCount();

// Add smooth quantity transitions
document.querySelectorAll('.quantity-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        this.style.animation = 'pulse 0.3s ease-in-out';
        setTimeout(() => {
            this.style.animation = '';
        }, 300);
    });
});

// Enhanced loading states
function enhancedUpdateQuantity(productId, quantity) {
    const quantityInput = document.querySelector(`input[onchange*="${productId}"]`);
    const cartItem = quantityInput.closest('.cart-item');
    
    // Add loading state to the specific item
    cartItem.style.opacity = '0.6';
    cartItem.style.pointerEvents = 'none';
    
    showLoading();
    
    if (typeof quantity !== 'number') {
        quantity = parseInt(quantity);
    }
    
    if (quantity === 1 || quantity === -1) {
        const currentQuantity = parseInt(quantityInput.value);
        quantity = currentQuantity + quantity;
    }
    
    if (quantity > 0) {
        setTimeout(() => {
            window.location.href = `cart.php?action=update&id=${productId}&quantity=${quantity}`;
        }, 500);
    } else {
        cartItem.style.opacity = '1';
        cartItem.style.pointerEvents = 'auto';
        hideLoading();
        showNotification('Số lượng không hợp lệ', 'error');
    }
}

// Replace the original updateQuantity function
window.updateQuantity = enhancedUpdateQuantity;

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // ESC key to close loading overlay
    if (e.key === 'Escape') {
        hideLoading();
    }
    
    // Ctrl+Enter to proceed to checkout
    if (e.ctrlKey && e.key === 'Enter') {
        const checkoutBtn = document.querySelector('.checkout-btn');
        if (checkoutBtn) {
            checkoutBtn.click();
        }
    }
});

// Add touch gestures for mobile
let touchStartX = 0;
let touchEndX = 0;

document.querySelectorAll('.cart-item').forEach(item => {
    item.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    item.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe(this);
    });
});

function handleSwipe(element) {
    const swipeThreshold = 100;
    const diff = touchStartX - touchEndX;
    
    if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
            // Swipe left - show remove option
            element.style.transform = 'translateX(-20px)';
            element.style.background = 'rgba(245, 101, 101, 0.1)';
            
            setTimeout(() => {
                element.style.transform = '';
                element.style.background = '';
            }, 2000);
        }
    }
}

// Add performance optimizations
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Debounced quantity update
const debouncedQuantityUpdate = debounce((productId, quantity) => {
    enhancedUpdateQuantity(productId, quantity);
}, 300);

// Add real-time price calculation preview
function updatePricePreview(productId, newQuantity) {
    const cartItem = document.querySelector(`input[onchange*="${productId}"]`).closest('.cart-item');
    const priceElement = cartItem.querySelector('.price-display');
    const subtotalElement = cartItem.querySelector('.subtotal-display');
    
    if (priceElement && subtotalElement) {
        const unitPrice = parseFloat(priceElement.textContent.replace(/[^\d]/g, ''));
        const newSubtotal = unitPrice * newQuantity;
        
        // Animate the change
        subtotalElement.style.animation = 'glow 0.5s ease-in-out';
        setTimeout(() => {
            subtotalElement.style.animation = '';
        }, 500);
    }
}


// Enhanced error handling: chỉ thông báo lỗi khi có lỗi thực sự trong thao tác người dùng
// window.addEventListener('error', function(e) {
//     console.error('Cart error:', e.error);
//     showNotification('Đã xảy ra lỗi. Vui lòng thử lại.', 'error');
//     hideLoading();
// });

// Add page visibility API for better performance
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Pause animations when page is not visible
        document.querySelectorAll('*').forEach(el => {
            el.style.animationPlayState = 'paused';
        });
    } else {
        // Resume animations when page becomes visible
        document.querySelectorAll('*').forEach(el => {
            el.style.animationPlayState = 'running';
        });
    }
});

console.log('Enhanced cart functionality loaded successfully!');
</script>

<?php include 'includes/footer.php'; ?>