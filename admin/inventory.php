<?php
// Kết nối database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "store";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Kết nối thất bại: " . $e->getMessage());
}

// Lấy dữ liệu sản phẩm từ database
function getProducts($pdo) {
    $sql = "SELECT p.product_id, p.name, p.stock, p.status, 
                   c.name as category_name, s.name as supplier_name
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            ORDER BY p.product_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy thống kê
function getInventoryStats($pdo) {
    // Tổng sản phẩm
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    
    // Sản phẩm sắp hết hàng (stock <= 10 và > 0)
    $lowStockProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 10")->fetchColumn();
    
    // Sản phẩm hết hàng (stock = 0)
    $outOfStockProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
    
    // Sản phẩm đang hoạt động
    $activeProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    
    return [
        'total' => $totalProducts,
        'low_stock' => $lowStockProducts,
        'out_of_stock' => $outOfStockProducts,
        'active' => $activeProducts
    ];
}

// Lấy dữ liệu cho biểu đồ phân bố tồn kho
function getStockDistribution($pdo) {
    $highStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 50")->fetchColumn();
    $mediumStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 10 AND stock <= 50")->fetchColumn();
    $lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 10")->fetchColumn();
    $outOfStock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
    
    return [
        'high' => $highStock,
        'medium' => $mediumStock,
        'low' => $lowStock,
        'out' => $outOfStock
    ];
}

// Lấy top 5 sản phẩm có tồn kho cao nhất
function getTopStockProducts($pdo) {
    $sql = "SELECT name, stock FROM products ORDER BY stock DESC LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy sản phẩm còn nhiều hàng (stock > 50)
function getHighStockProducts($pdo) {
    $sql = "SELECT product_id, name, stock, price, status FROM products WHERE stock > 50 ORDER BY stock DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy sản phẩm sắp hết hàng (stock <= 10 và > 0)
function getLowStockProducts($pdo) {
    $sql = "SELECT product_id, name, stock, price, status FROM products WHERE stock > 0 AND stock <= 10 ORDER BY stock ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy sản phẩm hết hàng (stock = 0)
function getOutOfStockProducts($pdo) {
    $sql = "SELECT product_id, name, stock, price, status FROM products WHERE stock = 0 ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy dữ liệu
$products = getProducts($pdo);
$stats = getInventoryStats($pdo);
$stockDistribution = getStockDistribution($pdo);
$topProducts = getTopStockProducts($pdo);
$highStockProducts = getHighStockProducts($pdo);
$lowStockProducts = getLowStockProducts($pdo);
$outOfStockProducts = getOutOfStockProducts($pdo);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý kho - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="../css/inventory.css">
</head>
<body>
    <div class="container">
        <div class="header fade-in">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div>
                    <h1>
                        <i class="fas fa-warehouse"></i>
                        Quản lý kho
                    </h1>
                    <p>Theo dõi và quản lý tồn kho sản phẩm một cách hiệu quả</p>
                </div>
                <button class="back-btn" onclick="window.location.href='index.php'" title="Quay lại trang chủ">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </button>
            </div>
        </div>

        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3 id="totalProducts"><?php echo $stats['total']; ?></h3>
                        <p>Tổng sản phẩm</p>
                    </div>
                    <div class="stat-icon stat-total">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3 id="lowStockProducts"><?php echo $stats['low_stock']; ?></h3>
                        <p>Sắp hết hàng</p>
                    </div>
                    <div class="stat-icon stat-low">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3 id="outOfStockProducts"><?php echo $stats['out_of_stock']; ?></h3>
                        <p>Hết hàng</p>
                    </div>
                    <div class="stat-icon stat-out">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3 id="activeProducts"><?php echo $stats['active']; ?></h3>
                        <p>Đang hoạt động</p>
                    </div>
                    <div class="stat-icon stat-active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="charts-section fade-in">
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Phân bố tồn kho
                </div>
                <canvas id="stockDistributionChart"></canvas>
            </div>
            
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-chart-bar"></i>
                    Top sản phẩm tồn kho cao
                </div>
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>

        <!-- Phần phân tích tồn kho chi tiết -->
        <div class="analysis-section fade-in">
            <div class="analysis-card">
                <div class="analysis-title high-stock">
                    <i class="fas fa-arrow-up"></i>
                    Sản phẩm còn nhiều hàng (>50)
                </div>
                <?php if(count($highStockProducts) > 0): ?>
                    <?php foreach($highStockProducts as $product): ?>
                    <div class="product-item high-stock">
                        <div class="product-info">
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p>ID: #<?php echo $product['product_id']; ?> | Trạng thái: <?php echo $product['status'] === 'active' ? 'Hoạt động' : 'Ngừng'; ?></p>
                        </div>
                        <div class="product-stock">
                            <div class="stock-number high"><?php echo $product['stock']; ?></div>
                            <div class="price-tag"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>Không có sản phẩm nào còn nhiều hàng</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="analysis-card">
                <div class="analysis-title low-stock">
                    <i class="fas fa-exclamation-triangle"></i>
                    Sản phẩm sắp hết hàng (≤10)
                </div>
                <?php if(count($lowStockProducts) > 0): ?>
                    <?php foreach($lowStockProducts as $product): ?>
                    <div class="product-item low-stock">
                        <div class="product-info">
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p>ID: #<?php echo $product['product_id']; ?> | Trạng thái: <?php echo $product['status'] === 'active' ? 'Hoạt động' : 'Ngừng'; ?></p>
                        </div>
                        <div class="product-stock">
                            <div class="stock-number low"><?php echo $product['stock']; ?></div>
                            <div class="price-tag"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>Tất cả sản phẩm đều có đủ hàng</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="analysis-card">
                <div class="analysis-title out-stock">
                    <i class="fas fa-times-circle"></i>
                    Sản phẩm hết hàng (0)
                </div>
                <?php if(count($outOfStockProducts) > 0): ?>
                    <?php foreach($outOfStockProducts as $product): ?>
                    <div class="product-item out-stock">
                        <div class="product-info">
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p>ID: #<?php echo $product['product_id']; ?> | Trạng thái: <?php echo $product['status'] === 'active' ? 'Hoạt động' : 'Ngừng'; ?></p>
                        </div>
                        <div class="product-stock">
                            <div class="stock-number out">0</div>
                            <div class="price-tag"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-smile"></i>
                        <p>Không có sản phẩm nào hết hàng</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container fade-in">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i>
                    Danh sách sản phẩm
                </div>
                <div class="action-buttons">
                    <button id="exportExcel" class="btn-export">
                        <i class="fas fa-file-excel"></i> Xuất Excel
                    </button>
                    <button id="printPage" class="btn-print">
                        <i class="fas fa-print"></i> In trang
                    </button>
                </div>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Tìm kiếm sản phẩm...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">Tất cả</button>
                <button class="filter-tab" data-filter="active">Hoạt động</button>
                <button class="filter-tab" data-filter="inactive">Ngừng hoạt động</button>
                <button class="filter-tab" data-filter="low-stock">Sắp hết hàng</button>
                <button class="filter-tab" data-filter="out-of-stock">Hết hàng</button>
            </div>
            
            <table class="modern-table" id="productsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên sản phẩm</th>
                        <th>Số lượng tồn</th>
                        <th>Mức tồn kho</th>
                        <th>Trạng thái</th>
                        
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach($products as $product): ?>
                    <tr data-status="<?php echo $product['status']; ?>" data-stock="<?php echo $product['stock']; ?>">
                        <td><strong>#<?php echo $product['product_id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><strong><?php echo $product['stock']; ?></strong></td>
                        <td>
                            <div class="stock-level">
                                <div class="stock-bar">
                                    <div class="stock-fill <?php echo getStockLevelClass($product['stock']); ?>" 
                                         style="width: <?php echo getStockPercentage($product['stock']); ?>%"></div>
                                </div>
                                <span><?php echo getStockLevelText($product['stock']); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $product['status']; ?>">
                                <?php echo $product['status'] === 'active' ? 'Hoạt động' : 'Ngừng'; ?>
                            </span>
                        </td>
                       
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Dữ liệu từ PHP
        const stockDistribution = {
            high: <?php echo $stockDistribution['high']; ?>,
            medium: <?php echo $stockDistribution['medium']; ?>,
            low: <?php echo $stockDistribution['low']; ?>,
            out: <?php echo $stockDistribution['out']; ?>
        };

        const topProducts = <?php echo json_encode($topProducts); ?>;

        // Initialize charts
        function initCharts() {
            // Stock Distribution Chart
            const ctx1 = document.getElementById('stockDistributionChart').getContext('2d');
            
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Tồn kho cao', 'Tồn kho trung bình', 'Sắp hết hàng', 'Hết hàng'],
                    datasets: [{
                        data: [stockDistribution.high, stockDistribution.medium, stockDistribution.low, stockDistribution.out],
                        backgroundColor: [
                            '#4facfe',
                            '#43e97b',
                            '#fa709a',
                            '#f093fb'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });

            // Top Products Chart
            const ctx2 = document.getElementById('topProductsChart').getContext('2d');

            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: topProducts.map(p => p.name),
                    datasets: [{
                        label: 'Số lượng tồn',
                        data: topProducts.map(p => p.stock),
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        borderWidth: 2,
                        borderRadius: 10,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function getStockLevelClass(stock) {
            if (stock > 50) return 'stock-high';
            if (stock > 10) return 'stock-medium';
            return 'stock-low';
        }

        function getStockPercentage(stock) {
            return Math.min((stock / 200) * 100, 100);
        }

        function getStockLevelText(stock) {
            if (stock === 0) return 'Hết hàng';
            if (stock <= 10) return 'Thấp';
            if (stock <= 50) return 'Trung bình';
            return 'Cao';
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                const productName = row.cells[1].textContent.toLowerCase();
                if (productName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const rows = document.querySelectorAll('#tableBody tr');
                
                rows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    const stock = parseInt(row.getAttribute('data-stock'));
                    let show = false;
                    
                    switch(filter) {
                        case 'all':
                            show = true;
                            break;
                        case 'active':
                            show = status === 'active';
                            break;
                        case 'inactive':
                            show = status === 'inactive';
                            break;
                        case 'low-stock':
                            show = stock > 0 && stock <= 10;
                            break;
                        case 'out-of-stock':
                            show = stock === 0;
                            break;
                    }
                    
                    row.style.display = show ? '' : 'none';
                });
            });
        });

        function editProduct(id) {
            alert(`Chỉnh sửa sản phẩm ID: ${id}`);
        }

        // Initialize everything
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            
            // Add pulse animation to low stock items
            setTimeout(() => {
                const lowStockCard = document.querySelectorAll('.stat-card')[1];
                const outOfStockCard = document.querySelectorAll('.stat-card')[2];
                
                if (<?php echo $stats['low_stock']; ?> > 0) {
                    lowStockCard.classList.add('pulse');
                }
                if (<?php echo $stats['out_of_stock']; ?> > 0) {
                    outOfStockCard.classList.add('pulse');
                }
            }, 2000);

            // Initialize export and print buttons
            document.getElementById('exportExcel').addEventListener('click', exportToExcel);
            document.getElementById('printPage').addEventListener('click', printInventory);
        });

        // Xuất dữ liệu ra file Excel
        function exportToExcel() {
            try {
                // Tạo workbook mới
                const wb = XLSX.utils.book_new();
                
                // Lấy dữ liệu bảng sản phẩm
                const inventoryTable = document.getElementById('productsTable');
                const wsData = [];
                
                // Thêm tiêu đề
                wsData.push(['THỐNG KÊ TỒN KHO']);
                wsData.push(['Ngày xuất: ' + new Date().toLocaleDateString('vi-VN')]);
                wsData.push([]);
                
                // Thêm thông tin tổng quan
                wsData.push(['TỔNG QUAN']);
                wsData.push(['Tổng sản phẩm', document.getElementById('totalProducts').textContent]);
                wsData.push(['Sắp hết hàng', document.getElementById('lowStockProducts').textContent]);
                wsData.push(['Hết hàng', document.getElementById('outOfStockProducts').textContent]);
                wsData.push(['Đang hoạt động', document.getElementById('activeProducts').textContent]);
                wsData.push([]);
                
                // Thêm dữ liệu phân bố tồn kho
                wsData.push(['PHÂN BỐ TỒN KHO']);
                wsData.push(['Tồn kho cao', stockDistribution.high]);
                wsData.push(['Tồn kho trung bình', stockDistribution.medium]);
                wsData.push(['Sắp hết hàng', stockDistribution.low]);
                wsData.push(['Hết hàng', stockDistribution.out]);
                wsData.push([]);
                
                // Thêm dữ liệu top sản phẩm tồn kho cao
                wsData.push(['TOP SẢN PHẨM TỒN KHO CAO']);
                wsData.push(['Tên sản phẩm', 'Số lượng tồn']);
                topProducts.forEach(product => {
                    wsData.push([product.name, product.stock]);
                });
                wsData.push([]);
                
                // Thêm header của bảng
                const headers = [];
                inventoryTable.querySelectorAll('thead th').forEach(th => {
                    headers.push(th.textContent.trim());
                });
                wsData.push(headers);
                
                // Lấy dữ liệu từ tbody
                inventoryTable.querySelectorAll('tbody tr').forEach(tr => {
                    if (tr.style.display !== 'none') { // Chỉ xuất các hàng đang hiển thị
                        const row = [];
                        tr.querySelectorAll('td').forEach((td, index) => {
                            if (index === 3) { // Cột mức tồn kho
                                row.push(td.querySelector('span').textContent.trim());
                            } else {
                                row.push(td.textContent.trim());
                            }
                        });
                        wsData.push(row);
                    }
                });
                
                // Tạo worksheet và thêm vào workbook
                const ws = XLSX.utils.aoa_to_sheet(wsData);
                XLSX.utils.book_append_sheet(wb, ws, 'Inventory');
                
                // Xuất file Excel
                XLSX.writeFile(wb, 'inventory_report_' + new Date().toISOString().slice(0, 10) + '.xlsx');
                
                // Thông báo thành công
                alert('Xuất dữ liệu Excel thành công!');
            } catch (error) {
                console.error('Lỗi khi xuất Excel:', error);
                alert('Có lỗi khi xuất Excel: ' + error.message);
            }
        }

        // In trang
        function printInventory() {
            try {
                // Tạo phần tử chứa nội dung in
                const printContent = document.createElement('div');
                printContent.classList.add('print-section');
                
                // Thêm tiêu đề
                const header = document.createElement('div');
                header.classList.add('print-header');
                header.innerHTML = `
                    <h1>BÁO CÁO TỒN KHO</h1>
                    <p>Store Admin Dashboard</p>
                `;
                printContent.appendChild(header);
                
                // Thêm ngày in
                const printDate = document.createElement('div');
                printDate.classList.add('print-date');
                printDate.textContent = 'Ngày in: ' + new Date().toLocaleDateString('vi-VN');
                printContent.appendChild(printDate);
                
                // Thêm thông tin tổng quan
                const statsSection = document.createElement('div');
                statsSection.innerHTML = `
                    <h2 style="margin-bottom: 10px;">Thông tin tổng quan</h2>
                    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <tr>
                            <th style="text-align: left; background: #f5f5f5;">Chỉ số</th>
                            <th style="text-align: left; background: #f5f5f5;">Giá trị</th>
                        </tr>
                        <tr>
                            <td>Tổng sản phẩm</td>
                            <td>${document.getElementById('totalProducts').textContent}</td>
                        </tr>
                        <tr>
                            <td>Sắp hết hàng</td>
                            <td>${document.getElementById('lowStockProducts').textContent}</td>
                        </tr>
                        <tr>
                            <td>Hết hàng</td>
                            <td>${document.getElementById('outOfStockProducts').textContent}</td>
                        </tr>
                        <tr>
                            <td>Đang hoạt động</td>
                            <td>${document.getElementById('activeProducts').textContent}</td>
                        </tr>
                    </table>
                `;
                printContent.appendChild(statsSection);
                
                // Thêm biểu đồ (sử dụng html2canvas)
                const chartsSection = document.createElement('div');
                chartsSection.style.display = 'flex';
                chartsSection.style.justifyContent = 'space-between';
                chartsSection.style.flexWrap = 'wrap';
                chartsSection.style.marginBottom = '20px';
                
                Promise.all([
                    html2canvas(document.getElementById('stockDistributionChart')),
                    html2canvas(document.getElementById('topProductsChart'))
                ]).then(canvases => {
                    // Thêm biểu đồ 1
                    const chart1Div = document.createElement('div');
                    chart1Div.classList.add('charts-print');
                    chart1Div.innerHTML = '<h3 style="text-align: center; margin-bottom: 10px;">Phân bố tồn kho</h3>';
                    chart1Div.appendChild(canvases[0]);
                    chartsSection.appendChild(chart1Div);
                    
                    // Thêm biểu đồ 2
                    const chart2Div = document.createElement('div');
                    chart2Div.classList.add('charts-print');
                    chart2Div.innerHTML = '<h3 style="text-align: center; margin-bottom: 10px;">Top sản phẩm tồn kho cao</h3>';
                    chart2Div.appendChild(canvases[1]);
                    chartsSection.appendChild(chart2Div);
                    
                    printContent.appendChild(chartsSection);
                    
                    // Thêm bảng sản phẩm
                    const tableSection = document.createElement('div');
                    tableSection.innerHTML = '<h2 style="margin-bottom: 10px;">Danh sách sản phẩm</h2>';
                    
                    // Sao chép bảng sản phẩm
                    const tableClone = document.getElementById('productsTable').cloneNode(true);
                    
                    // Chỉ giữ lại các hàng đang hiển thị
                    Array.from(tableClone.querySelectorAll('tbody tr')).forEach(tr => {
                        if (tr.style.display === 'none') {
                            tr.remove();
                        }
                    });
                    
                    // Style lại bảng cho in ấn
                    tableClone.style.width = '100%';
                    tableClone.style.borderCollapse = 'collapse';
                    tableClone.querySelectorAll('th, td').forEach(cell => {
                        cell.style.border = '1px solid #ddd';
                        cell.style.padding = '8px';
                        cell.style.textAlign = 'left';
                    });
                    tableClone.querySelectorAll('th').forEach(th => {
                        th.style.backgroundColor = '#f5f5f5';
                    });
                    
                    // Xử lý thanh tiến trình tồn kho
                    tableClone.querySelectorAll('.stock-level').forEach(stockLevel => {
                        const text = stockLevel.querySelector('span').textContent;
                        stockLevel.innerHTML = text;
                    });
                    
                    tableSection.appendChild(tableClone);
                    printContent.appendChild(tableSection);
                    
                    // Append vào body, in và xóa
                    document.body.appendChild(printContent);
                    window.print();
                    document.body.removeChild(printContent);
                }).catch(error => {
                    console.error('Lỗi khi tạo ảnh biểu đồ:', error);
                    alert('Có lỗi khi tạo ảnh biểu đồ: ' + error.message);
                });
            } catch (error) {
                console.error('Lỗi khi in trang:', error);
                alert('Có lỗi khi in trang: ' + error.message);
            }
        }
    </script>
</body>
</html>

<?php
// Helper functions
function getStockLevelClass($stock) {
    if ($stock > 50) return 'stock-high';
    if ($stock > 10) return 'stock-medium';
    return 'stock-low';
}

function getStockPercentage($stock) {
    return min(($stock / 200) * 100, 100);
}

function getStockLevelText($stock) {
    if ($stock === 0) return 'Hết hàng';
    if ($stock <= 10) return 'Thấp';
    if ($stock <= 50) return 'Trung bình';
    return 'Cao';
}
?>