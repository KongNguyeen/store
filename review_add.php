<?php
require_once 'config/config.php';
require_once 'config/functions.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    flash('error', 'Vui lòng đăng nhập để đánh giá sản phẩm');
    redirect('login.php');
}

// Kiểm tra CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    flash('error', 'Invalid CSRF token');
    redirect('index.php');
}

// Validate input
$product_id = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = sanitize($_POST['comment'] ?? '');

if (!$product_id || !$rating || !$comment) {
    flash('error', 'Vui lòng điền đầy đủ thông tin đánh giá');
    redirect("product.php?id=$product_id");
}

if ($rating < 1 || $rating > 5) {
    flash('error', 'Đánh giá không hợp lệ');
    redirect("product.php?id=$product_id");
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // Kiểm tra sản phẩm tồn tại
    $stmt = $pdo->prepare("SELECT product_id FROM products WHERE product_id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Sản phẩm không tồn tại hoặc đã ngừng kinh doanh');
    }

    // Kiểm tra user đã mua sản phẩm này chưa
    $stmt = $pdo->prepare("
        SELECT o.order_id
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = ? 
        AND oi.product_id = ?
        AND o.status = 'delivered'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Bạn cần mua sản phẩm trước khi đánh giá');
    }

    // Kiểm tra user đã đánh giá sản phẩm này chưa
    $stmt = $pdo->prepare("SELECT review_id FROM reviews WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    if ($stmt->fetch()) {
        throw new Exception('Bạn đã đánh giá sản phẩm này rồi');
    }

    // Thêm đánh giá mới
    $stmt = $pdo->prepare("
        INSERT INTO reviews (
            product_id, user_id, rating, comment, created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $product_id,
        $_SESSION['user_id'],
        $rating,
        $comment
    ]);

    $pdo->commit();
    flash('success', 'Cảm ơn bạn đã đánh giá sản phẩm!');

} catch (Exception $e) {
    $pdo->rollBack();
    flash('error', $e->getMessage());
}

redirect("product.php?id=$product_id");
?>