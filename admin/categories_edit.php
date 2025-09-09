<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();

// Lấy thông tin danh mục cần sửa
$category_id = (int)($_GET['id'] ?? 0);
if (!$category_id) {
    flash('error', 'Danh mục không tồn tại');
    redirect('categories.php');
}

$stmt = $pdo->prepare("
    SELECT c.*, parent.name as parent_name 
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.category_id
    WHERE c.category_id = ?
");
$stmt->execute([$category_id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    flash('error', 'Danh mục không tồn tại');
    redirect('categories.php');
}

// Lấy danh sách tất cả danh mục để chọn danh mục cha
$sql = "
    WITH RECURSIVE category_path AS (
        -- Tìm tất cả danh mục con của danh mục hiện tại
        SELECT category_id, parent_id, name, 0 as level
        FROM categories
        WHERE category_id = ?
        
        UNION ALL
        
        SELECT c.category_id, c.parent_id, c.name, cp.level + 1
        FROM categories c
        INNER JOIN category_path cp ON c.parent_id = cp.category_id
    )
    -- Lấy tất cả danh mục trừ các danh mục con (để tránh tạo vòng lặp)
    SELECT c.*, parent.name as parent_name
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.category_id
    WHERE c.category_id NOT IN (SELECT category_id FROM category_path WHERE category_id != ?)
    ORDER BY c.parent_id IS NULL DESC, c.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$category_id, $category_id]);
$all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xây dựng cây danh mục
$categories_tree = [];
$categories_map = [];

foreach ($all_categories as $cat) {
    $cat['children'] = [];
    $categories_map[$cat['category_id']] = $cat;
}

foreach ($categories_map as $id => &$cat) {
    if ($cat['parent_id']) {
        if (isset($categories_map[$cat['parent_id']])) {
            $categories_map[$cat['parent_id']]['children'][] = &$cat;
        } else {
            $categories_tree[] = &$cat;
        }
    } else {
        $categories_tree[] = &$cat;
    }
}

// Hàm đệ quy để tạo options cho select
function create_category_options($categories, $level = 0, $selected = null) {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
    $html = '';
    
    foreach ($categories as $category) {
        $html .= sprintf(
            '<option value="%d" %s>%s%s%s</option>',
            $category['category_id'],
            ($selected == $category['category_id']) ? 'selected' : '',
            $indent,
            ($level > 0 ? '├─ ' : ''),
            htmlspecialchars($category['name'])
        );
        
        if (!empty($category['children'])) {
            $html .= create_category_options($category['children'], $level + 1, $selected);
        }
    }
    
    return $html;
}

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
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        
        if (!$name) {
            $error = 'Vui lòng nhập tên danh mục';
        } else {
            try {
                // Kiểm tra tên danh mục đã tồn tại chưa (trừ chính nó)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM categories 
                    WHERE name = ? AND category_id != ?
                ");
                $stmt->execute([$name, $category_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Tên danh mục đã tồn tại';
                } else {
                    // Cập nhật danh mục
                    $stmt = $pdo->prepare("
                        UPDATE categories 
                        SET name = ?, description = ?, parent_id = ?
                        WHERE category_id = ?
                    ");
                    $stmt->execute([
                        $name, 
                        $description, 
                        $parent_id ?: null, 
                        $category_id
                    ]);

                    flash('success', 'Cập nhật danh mục thành công!');
                    redirect('categories.php');
                }
            } catch (PDOException $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}


?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Danh Mục - Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../css/categories_edit.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header pb-0">
                    <div class="row">
                        <div class="col-6">
                            <h6>Sửa danh mục</h6>
                        </div>
                        <div class="col-6 text-end">
                            <a href="categories.php" class="btn btn-outline-primary btn-sm mb-0">
                                <i class="fas fa-arrow-left"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="post" id="categoryForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" required
                                        value="<?= htmlspecialchars($_POST['name'] ?? $category['name']) ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Danh mục cha</label>
                                    <select class="form-select" name="parent_id">
                                        <option value="">-- Không có --</option>
                                        <?= create_category_options(
                                            $categories_tree, 
                                            0, 
                                            $_POST['parent_id'] ?? $category['parent_id']
                                        ) ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Một số danh mục có thể không hiển thị để tránh tạo vòng lặp.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? $category['description']) ?></textarea>
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
    // Form validation
    const form = document.getElementById('categoryForm');
    form.addEventListener('submit', function(e) {
        const name = form.querySelector('[name="name"]').value.trim();
        const parentId = form.querySelector('[name="parent_id"]').value;

        let hasError = false;
        let errorMessage = '';

        if (!name) {
            errorMessage += 'Vui lòng nhập tên danh mục\n';
            hasError = true;
        }

        // Ngăn chọn chính nó làm danh mục cha
        if (parentId === '<?= $category_id ?>') {
            errorMessage += 'Không thể chọn chính danh mục này làm danh mục cha\n';
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
            alert(errorMessage);
        }
    });
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php include '../includes/footer.php'; ?>