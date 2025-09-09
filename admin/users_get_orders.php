<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

// Validate CSRF token
if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Invalid CSRF token');
}

// Validate user_id
$user_id = (int)($_GET['user_id'] ?? 0);
if (!$user_id) {
    die('Invalid user ID');
}

try {
    $pdo = getPDO();

    // Lấy thông tin user
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        die('User không tồn tại');
    }

    // Lấy danh sách đơn hàng
    $stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count,
               (SELECT GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ')
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = o.order_id) as products
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo '<div class="text-center py-3">Người dùng chưa có đơn hàng nào.</div>';
    } else {
?>
        <table class="table align-items-center mb-0">
            <thead>
                <tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Mã ĐH</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Sản phẩm</th>
                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tổng tiền</th>
                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Trạng thái</th>
                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Ngày đặt</th>
                    <th class="text-secondary opacity-7"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <div class="d-flex px-2">
                            <div class="my-auto">
                                <h6 class="mb-0 text-sm">#<?= $order['order_id'] ?></h6>
                            </div>
                        </div>
                    </td>
                    <td>
                        <p class="text-sm mb-0">
                            <?= $order['item_count'] ?> sản phẩm<br>
                            <small class="text-muted">
                                <?= substr(sanitize($order['products']), 0, 100) ?>...
                            </small>
                        </p>
                    </td>
                    <td class="align-middle text-center text-sm">
                        <span class="font-weight-bold">
                            <?= format_currency($order['total_amount']) ?>
                        </span>
                    </td>
                    <td class="align-middle text-center">
                        <span class="badge badge-sm bg-gradient-<?= 
                            $order['status'] == 'pending' ? 'warning' : 
                            ($order['status'] == 'processing' ? 'info' : 
                            ($order['status'] == 'shipped' ? 'primary' : 
                            ($order['status'] == 'delivered' ? 'success' : 'danger')))
                        ?>">
                            <?= ORDER_STATUS[$order['status']] ?>
                        </span>
                    </td>
                    <td class="align-middle text-center">
                        <span class="text-secondary text-xs font-weight-bold">
                            <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                        </span>
                    </td>
                    <td class="align-middle">
                        <a href="order_detail.php?id=<?= $order['order_id'] ?>" 
                           class="text-secondary font-weight-bold text-xs"
                           target="_blank">
                            <i class="fas fa-external-link-alt"></i> Chi tiết
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($orders) >= 10): ?>
            <div class="text-center mt-3">
                <a href="orders.php?user_id=<?= $user_id ?>" class="btn btn-sm btn-outline-primary">
                    Xem tất cả đơn hàng
                </a>
            </div>
        <?php endif; ?>
<?php
    }
} catch (Exception $e) {
    die('Có lỗi xảy ra: ' . $e->getMessage());
}
?>