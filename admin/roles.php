<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();
$stmt = $pdo->query("SELECT * FROM roles ORDER BY role_id ASC");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phân quyền hệ thống</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/roles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div>
                    <h2><i class="fas fa-shield-alt"></i> Phân quyền hệ thống</h2>
                    <p>Quản lý và phân quyền người dùng trong hệ thống</p>
                </div>
                <button class="back-btn" onclick="window.location.href='index.php'" title="Quay lại trang chủ">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </button>
            </div>
        </div>

        <div class="actions">
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value" id="totalRoles"><?= count($roles) ?></div>
                    <div class="stat-label">Tổng số quyền</div>
                </div>
            </div>

            <!-- Đã xóa ô tìm kiếm và nút thêm quyền mới -->
        </div>

        <div class="table-container">
            <div class="loading" id="loading">
                <i class="fas fa-spinner"></i>
                <p>Đang tải dữ liệu...</p>
            </div>

            <?php if (empty($roles)): ?>
            <div class="empty-state">
                <i class="fas fa-user-shield"></i>
                <h3>Chưa có quyền nào</h3>
                <p>Hãy thêm quyền đầu tiên cho hệ thống</p>
                
            </div>
            <?php else: ?>
            <table class="roles-table" id="rolesTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> ID</th>
                        <th><i class="fas fa-tag"></i> Tên quyền</th>
                        <th><i class="fas fa-cogs"></i> Thao tác</th>
                    </tr>
                </thead>
                <tbody id="rolesList">
                    <?php foreach ($roles as $r): ?>
                    <tr class="role-row" data-role-name="<?= strtolower(sanitize($r['role_name'])) ?>">
                        <td class="role-id">#<?= $r['role_id'] ?></td>
                        <td>
                            <span class="role-name"><?= sanitize($r['role_name']) ?></span>
                            <?php
                            $roleName = strtolower($r['role_name']);
                            $badgeClass = 'badge-default';
                            if (strpos($roleName, 'admin') !== false) $badgeClass = 'badge-admin';
                            elseif (strpos($roleName, 'user') !== false) $badgeClass = 'badge-user';
                            elseif (strpos($roleName, 'moderator') !== false) $badgeClass = 'badge-moderator';
                            ?>
                            <span class="role-badge <?= $badgeClass ?>"><?= sanitize($r['role_name']) ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="edit_role.php?id=<?= $r['role_id'] ?>" class="btn btn-secondary" title="Chỉnh sửa">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteRole(<?= $r['role_id'] ?>, '<?= sanitize($r['role_name']) ?>')" 
                                        class="btn btn-danger" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.role-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const roleName = row.getAttribute('data-role-name');
                const roleId = row.querySelector('.role-id').textContent.toLowerCase();
                
                if (roleName.includes(searchTerm) || roleId.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                    // Add fade in animation
                    row.style.animation = 'fadeIn 0.3s ease-in';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update stats
            document.getElementById('totalRoles').textContent = visibleCount;
        });

        // Delete role function
        function deleteRole(roleId, roleName) {
            if (confirm(`Bạn có chắc chắn muốn xóa quyền "${roleName}"?\n\nLưu ý: Thao tác này không thể hoàn tác!`)) {
                // Show loading
                const loading = document.getElementById('loading');
                loading.style.display = 'block';
                
                // Simulate API call (replace with actual implementation)
                setTimeout(() => {
                    // Remove row with animation
                    const row = document.querySelector(`[data-role-name*="${roleName.toLowerCase()}"]`);
                    if (row) {
                        row.style.animation = 'fadeOut 0.3s ease-out';
                        setTimeout(() => {
                            row.remove();
                            updateStats();
                        }, 300);
                    }
                    loading.style.display = 'none';
                }, 1000);
            }
        }

        // Update statistics
        function updateStats() {
            const totalRoles = document.querySelectorAll('.role-row').length;
            document.getElementById('totalRoles').textContent = totalRoles;
        }

        // Add fade animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes fadeOut {
                from { opacity: 1; transform: translateY(0); }
                to { opacity: 0; transform: translateY(-10px); }
            }
        `;
        document.head.appendChild(style);

        // Initialize page animations
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.role-row');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.animation = `fadeIn 0.5s ease-out ${index * 0.1}s forwards`;
            });

            // Add hover sound effect (optional)
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });

        // Add typing effect to search placeholder
        const searchInput = document.getElementById('searchInput');
        const originalPlaceholder = searchInput.placeholder;
        let placeholderIndex = 0;
        let isDeleting = false;

        function typeEffect() {
            const current = originalPlaceholder.substring(0, placeholderIndex);
            searchInput.placeholder = current;

            if (!isDeleting && placeholderIndex < originalPlaceholder.length) {
                placeholderIndex++;
                setTimeout(typeEffect, 100);
            } else if (isDeleting && placeholderIndex > 0) {
                placeholderIndex--;
                setTimeout(typeEffect, 50);
            } else if (!isDeleting && placeholderIndex === originalPlaceholder.length) {
                setTimeout(() => {
                    isDeleting = true;
                    typeEffect();
                }, 2000);
            } else if (isDeleting && placeholderIndex === 0) {
                isDeleting = false;
                setTimeout(typeEffect, 500);
            }
        }

        // Start typing effect after page load
        setTimeout(typeEffect, 2000);
    </script>
</body>
</html>