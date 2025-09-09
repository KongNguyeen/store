<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

$pdo = getPDO();
try {
    // Lấy dữ liệu gốc từ bảng shipments
    $debug_stmt = $pdo->query("SELECT * FROM shipments");
    $raw_shipments = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query chính với JOIN để hiển thị chi tiết
    $stmt = $pdo->query("SELECT s.*, o.order_id, o.user_id, u.full_name 
                         FROM shipments s 
                         LEFT JOIN orders o ON s.order_id = o.order_id
                         LEFT JOIN users u ON o.user_id = u.user_id 
                         ORDER BY s.shipment_id DESC");
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    echo '<div style="color:red; padding:20px; background:#ffeeee; margin:20px; border-radius:5px;">
            <strong>Lỗi SQL:</strong> ' . $e->getMessage() . '
          </div>';
    $shipments = [];
    $raw_shipments = [];
}


?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý giao hàng</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/shipments.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Quay lại Admin
            </a>
            <h2><i class="fas fa-shipping-fast"></i> Quản lý giao hàng</h2>
            <div class="subtitle">Theo dõi và quản lý tình trạng giao hàng</div>
        </div>

        <div class="search-filter-bar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Tìm kiếm theo mã đơn hàng, mã vận đơn...">
                <i class="fas fa-search"></i>
            </div>
            <select class="filter-select" id="statusFilter">
                <option value="">Tất cả trạng thái</option>
                <option value="pending">Chờ gửi</option>
                <option value="shipped">Đã gửi</option>
                <option value="in_transit">Đang vận chuyển</option>
                <option value="delivered">Đã giao</option>
                <option value="cancelled">Đã hủy</option>
            </select>
            <select class="filter-select" id="companyFilter">
                <option value="">Tất cả công ty</option>
            </select>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <div>Đang tải dữ liệu...</div>
        </div>

        <div class="table-container">
            <?php if (empty($shipments)): ?>
                <div class="no-data">
                    <i class="fas fa-box-open"></i>
                    <div>Chưa có đơn hàng nào được giao</div>
                </div>
            <?php else: ?>
                <table class="shipments-table" id="shipmentsTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">ID <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(1)">Đơn hàng <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(2)">Khách hàng <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(3)">Cty vận chuyển <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(4)">Mã vận đơn <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(5)">Trạng thái <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(6)">Gửi lúc <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(7)">Nhận lúc <i class="fas fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shipments as $index => $s): ?>
                        <tr style="animation-delay: <?= $index * 0.1 ?>s">
                            <td><?= $s['shipment_id'] ?></td>
                            <td><strong>#<?= $s['order_id'] ?></strong></td>
                            <td><?= $s['full_name'] ?? 'Không có thông tin' ?></td>
                            <td>
                                <div class="company-badge">
                                    <i class="fas fa-truck"></i>
                                    <?= $s['shipping_company'] ? sanitize($s['shipping_company']) : 'Không xác định' ?>
                                </div>
                            </td>
                            <td>
                                <span class="tracking-number" onclick="copyToClipboard('<?= $s['tracking_number'] ? sanitize($s['tracking_number']) : 'N/A' ?>')" title="Click để copy">
                                    <?= $s['tracking_number'] ? sanitize($s['tracking_number']) : 'N/A' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= strtolower($s['status']) ?>">
                                    <?php
                                    $statusMap = [
                                        'pending' => 'Chờ gửi',
                                        'shipped' => 'Đã gửi',
                                        'in_transit' => 'Đang vận chuyển',
                                        'delivered' => 'Đã giao',
                                        'cancelled' => 'Đã hủy'
                                    ];
                                    echo $statusMap[$s['status']] ?? $s['status'];
                                    ?>
                                </span>
                            </td>
                            <td class="date-time">
                                <?= $s['shipped_at'] ? date('d/m/Y H:i', strtotime($s['shipped_at'])) : '<span style="color: #ccc;">Chưa gửi</span>' ?>
                            </td>
                            <td class="date-time">
                                <?= $s['delivered_at'] ? date('d/m/Y H:i', strtotime($s['delivered_at'])) : '<span style="color: #000;">Chưa giao</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (!empty($shipments)): ?>
        <div class="stats-bar">
            <div class="stat-item" onclick="filterByStatus('')">
                <span class="stat-number" id="totalCount"><?= count($shipments) ?></span>
                <span class="stat-label">Tổng số</span>
            </div>
            <div class="stat-item" onclick="filterByStatus('pending')">
                <span class="stat-number" id="pendingCount">
                    <?= count(array_filter($shipments, fn($s) => $s['status'] === 'pending')) ?>
                </span>
                <span class="stat-label">Chờ gửi</span>
            </div>
            <div class="stat-item" onclick="filterByStatus('shipped')">
                <span class="stat-number" id="shippedCount">
                    <?= count(array_filter($shipments, fn($s) => $s['status'] === 'shipped')) ?>
                </span>
                <span class="stat-label">Đã gửi</span>
            </div>
            <div class="stat-item" onclick="filterByStatus('in_transit')">
                <span class="stat-number" id="inTransitCount">
                    <?= count(array_filter($shipments, fn($s) => $s['status'] === 'in_transit')) ?>
                </span>
                <span class="stat-label">Đang vận chuyển</span>
            </div>
            <div class="stat-item" onclick="filterByStatus('delivered')">
                <span class="stat-number" id="deliveredCount">
                    <?= count(array_filter($shipments, fn($s) => $s['status'] === 'delivered')) ?>
                </span>
                <span class="stat-label">Đã giao</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeFilters();
            animateRows();
        });

        // Animate table rows
        function animateRows() {
            const rows = document.querySelectorAll('.shipments-table tbody tr');
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.style.opacity = '1';
                }, index * 100);
            });
        }

        // Initialize filter options
        function initializeFilters() {
            const companyFilter = document.getElementById('companyFilter');
            const companies = new Set();
            
            document.querySelectorAll('.company-badge').forEach(badge => {
                companies.add(badge.textContent.trim());
            });
            
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company;
                option.textContent = company;
                companyFilter.appendChild(option);
            });
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            filterTable();
        });

        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function() {
            filterTable();
        });

        // Company filter
        document.getElementById('companyFilter').addEventListener('change', function() {
            filterTable();
        });

        // Filter table function
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const companyFilter = document.getElementById('companyFilter').value;
            
            const rows = document.querySelectorAll('.shipments-table tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const shipmentId = cells[0].textContent.toLowerCase();
                const orderId = cells[1].textContent.toLowerCase();
                const customerName = cells[2].textContent.toLowerCase();
                const company = cells[3].textContent.toLowerCase();
                const trackingNumber = cells[4].textContent.toLowerCase();
                const statusElement = cells[5].querySelector('.status-badge');
                const status = statusElement ? statusElement.className.split(' ')[1].replace('status-', '') : '';
                
                const matchesSearch = shipmentId.includes(searchTerm) || 
                                    orderId.includes(searchTerm) || 
                                    customerName.includes(searchTerm) ||
                                    trackingNumber.includes(searchTerm) ||
                                    company.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesCompany = !companyFilter || company.includes(companyFilter.toLowerCase());
                
                if (matchesSearch && matchesStatus && matchesCompany) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show no results message if needed
            const table = document.getElementById('shipmentsTable');
            let noResultsRow = document.getElementById('noResultsRow');
            
            if (visibleCount === 0) {
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'noResultsRow';
                    noResultsRow.innerHTML = '<td colspan="7" class="no-data"><i class="fas fa-search"></i><div>Không tìm thấy kết quả nào</div></td>';
                    table.querySelector('tbody').appendChild(noResultsRow);
                }
                noResultsRow.style.display = '';
            } else {
                if (noResultsRow) {
                    noResultsRow.style.display = 'none';
                }
            }
        }

        // Filter by status (for stats bar clicks)
        function filterByStatus(status) {
            document.getElementById('statusFilter').value = status;
            filterTable();
        }

        // Sort table functionality
        let sortDirection = {};

        function sortTable(columnIndex) {
            const table = document.getElementById('shipmentsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr:not(#noResultsRow)'));
            
            const direction = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
            sortDirection[columnIndex] = direction;
            
            rows.sort((a, b) => {
                const aVal = a.cells[columnIndex].textContent.trim();
                const bVal = b.cells[columnIndex].textContent.trim();
                
                // Handle numeric sorting for ID column
                if (columnIndex === 0) {
                    return direction === 'asc' ? 
                        parseInt(aVal) - parseInt(bVal) : 
                        parseInt(bVal) - parseInt(aVal);
                }
                
                // Handle date sorting
                if (columnIndex === 5 || columnIndex === 6) {
                    const aDate = new Date(aVal);
                    const bDate = new Date(bVal);
                    return direction === 'asc' ? aDate - bDate : bDate - aDate;
                }
                
                // String sorting
                return direction === 'asc' ? 
                    aVal.localeCompare(bVal) : 
                    bVal.localeCompare(aVal);
            });
            
            // Clear tbody and re-append sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
            
            // Re-add no results row if it exists
            const noResultsRow = document.getElementById('noResultsRow');
            if (noResultsRow) {
                tbody.appendChild(noResultsRow);
            }
        }

        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('Đã copy mã vận đơn: ' + text);
            }).catch(function() {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('Đã copy mã vận đơn: ' + text);
            });
        }

        // Show notification
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                    color: white;
                    padding: 15px 25px;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    z-index: 1000;
                    animation: slideInRight 0.3s ease-out;
                    font-weight: 500;
                ">
                    <i class="fas fa-check-circle"></i> ${message}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Add CSS animations for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100px);
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
                    transform: translateX(100px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>