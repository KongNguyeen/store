<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

// Validate CSRF token
if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
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

    // Lấy danh sách đánh giá
    $stmt = $pdo->prepare("
        SELECT r.*, p.name as product_name,
               (SELECT image_url FROM product_images pi 
                WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                LIMIT 1) as product_image
        FROM reviews r
        JOIN products p ON r.product_id = p.product_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($reviews)) {
        echo '<div class="text-center py-3">Người dùng chưa có đánh giá nào.</div>';
    } else {
?>
        <div class="list-group">
            <?php foreach ($reviews as $review): ?>
                <div class="list-group-item">
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <?php if ($review['product_image']): ?>
                                <img src="<?= $review['product_image'] ?>" 
                                     class="avatar avatar-sm rounded-circle me-3" loading="lazy" decoding="async">
                            <?php else: ?>
                                <img src="../assets/images/no-image.jpg" 
                                     class="avatar avatar-sm rounded-circle me-3" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><?= sanitize($review['product_name']) ?></h6>
                                <small class="text-muted">
                                    <?= date('d/m/Y H:i', strtotime($review['created_at'])) ?>
                                </small>
                            </div>
                            <div class="mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span style="color: <?= $i <= $review['rating'] ? '#ffc107' : '#ccc' ?>;">★</span>
                                <?php endfor; ?>
                            </div>
                            <p class="text-sm mb-0"><?= nl2br(sanitize($review['comment'])) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($reviews) >= 10): ?>
            <div class="text-center mt-3">
                <a href="reviews.php?user_id=<?= $user_id ?>" class="btn btn-sm btn-outline-primary">
                    Xem tất cả đánh giá
                </a>
            </div>
        <?php endif; ?>
<?php
    }
} catch (Exception $e) {
    die('Có lỗi xảy ra: ' . $e->getMessage());
}
?>