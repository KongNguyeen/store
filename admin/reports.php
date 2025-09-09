<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo doanh thu - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <!-- SheetJS (xlsx) for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- html2canvas for capturing charts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
   <link rel="stylesheet" href="../css/reports.css">
</head>
<body>
    <div class="container fade-in">
        <div class="header">
            <div class="header-content">
                <div class="header-info">
                    <h1><i class="fas fa-chart-line"></i> Báo cáo doanh thu</h1>
                    <p>Tổng quan hiệu suất kinh doanh và phân tích chi tiết</p>
                </div>
                <div class="header-actions">
                    <button id="exportExcelBtn" class="action-btn excel-btn">
                        <i class="fas fa-file-excel"></i>
                        Xuất Excel
                    </button>
                    <button id="printBtn" class="action-btn print-btn">
                        <i class="fas fa-print"></i>
                        In báo cáo
                    </button>
                    <a href="index.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                        Quay lại Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Debug information (hidden in production) -->
        <div class="debug-info" id="debugInfo" style="display:none;">
            <strong>Debug Information:</strong><br>
            <span id="debugContent"></span>
        </div>

        <div class="stats-grid">
            <div class="stat-card pulse">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value" id="totalRevenue">0 đ</div>
                <div class="stat-label">Tổng doanh thu</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value" id="totalProducts">0</div>
                <div class="stat-label">Sản phẩm bán</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-value" id="totalQuantity">0</div>
                <div class="stat-label">Số lượng bán</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-value" id="avgDaily">0 đ</div>
                <div class="stat-label">TB hàng ngày</div>
            </div>
        </div>

        <div class="charts-section">
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-chart-area"></i> Biểu đồ doanh thu theo thời gian
                    </div>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i> Top sản phẩm bán chạy
                    </div>
                    <canvas id="productChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-section">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-table"></i> Chi tiết báo cáo
                </div>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Tìm kiếm sản phẩm...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table id="reportsTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Ngày</th>
                            <th><i class="fas fa-tag"></i> ID SP</th>
                            <th><i class="fas fa-box"></i> Tên sản phẩm</th>
                            <th><i class="fas fa-sort-numeric-up"></i> Số lượng</th>
                            <th><i class="fas fa-money-bill-wave"></i> Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="5" class="loading">
                                <div class="spinner"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Dữ liệu từ VIEW revenue_report
        <?php
        try {
            // Kết nối database (thay đổi thông tin kết nối theo config của bạn)
            $host = '127.0.0.1';
            $dbname = 'store';
            $username = 'root'; // Thay đổi username
            $password = '';     // Thay đổi password
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Truy vấn dữ liệu từ view revenue_report
            $stmt = $pdo->query("
                SELECT 
                    order_date,
                    product_id,
                    product_name,
                    total_quantity_sold,
                    total_revenue
                FROM revenue_report 
                ORDER BY order_date DESC, total_revenue DESC
                LIMIT 100
            ");
            
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert dữ liệu cho JavaScript
            $jsData = [];
            foreach ($reports as $report) {
                $jsData[] = [
                    'order_date' => $report['order_date'],
                    'product_id' => (int)$report['product_id'],
                    'product_name' => $report['product_name'],
                    'total_quantity_sold' => (int)$report['total_quantity_sold'],
                    'total_revenue' => (float)$report['total_revenue']
                ];
            }
            
            echo "let allData = " . json_encode($jsData, JSON_UNESCAPED_UNICODE) . ";";
            echo "\nconsole.log('Loaded " . count($jsData) . " records from database');";
            
        } catch (PDOException $e) {
            // Nếu lỗi database, sử dụng dữ liệu mẫu
            echo "console.error('Database error: " . addslashes($e->getMessage()) . "');";
            echo "let allData = [
                {
                    'order_date': '2025-07-26',
                    'product_id': 1,
                    'product_name': 'Smartphone X',
                    'total_quantity_sold': 3,
                    'total_revenue': 2099.97
                },
                {
                    'order_date': '2025-07-26',
                    'product_id': 2,
                    'product_name': 'Wireless Earbuds',
                    'total_quantity_sold': 1,
                    'total_revenue': 129.99
                },
                {
                    'order_date': '2025-07-26',
                    'product_id': 3,
                    'product_name': 'Vacuum Cleaner',
                    'total_quantity_sold': 1,
                    'total_revenue': 249.99
                }
            ];";
            echo "\nconsole.log('Using sample data due to database connection error');";
        }
        ?>

        // Debug function (disabled in production)
        function showDebugInfo(message) {
            // Uncomment the lines below to enable debug mode
            // const debugDiv = document.getElementById('debugInfo');
            // const debugContent = document.getElementById('debugContent');
            // debugContent.innerHTML = message;
            // debugDiv.style.display = 'block';
            console.log('DEBUG:', message);
        }

        // Tính toán thống kê
        function calculateStats() {
            try {
                showDebugInfo(`Calculating stats with ${allData.length} records`);
                
                if (!allData || allData.length === 0) {
                    document.getElementById('totalRevenue').textContent = '0 đ';
                    document.getElementById('totalProducts').textContent = '0';
                    document.getElementById('totalQuantity').textContent = '0';
                    document.getElementById('avgDaily').textContent = '0 đ';
                    return;
                }

                const totalRevenue = allData.reduce((sum, item) => {
                    const revenue = parseFloat(item.total_revenue) || 0;
                    return sum + revenue;
                }, 0);
                
                const totalProducts = new Set(allData.map(item => item.product_id)).size;
                const totalQuantity = allData.reduce((sum, item) => sum + (parseInt(item.total_quantity_sold) || 0), 0);
                const uniqueDates = new Set(allData.map(item => item.order_date)).size;
                const avgDaily = uniqueDates > 0 ? totalRevenue / uniqueDates : 0;

                showDebugInfo(`Stats: Revenue=${totalRevenue}, Products=${totalProducts}, Quantity=${totalQuantity}, AvgDaily=${avgDaily}`);

                // Cập nhật UI ngay lập tức trước, sau đó animate
                document.getElementById('totalRevenue').textContent = formatCurrency(totalRevenue);
                document.getElementById('totalProducts').textContent = totalProducts;
                document.getElementById('totalQuantity').textContent = totalQuantity;
                document.getElementById('avgDaily').textContent = formatCurrency(avgDaily);

                // Animate counters
                animateCounter('totalRevenue', 0, totalRevenue, 2000, (val) => formatCurrency(val));
                animateCounter('totalProducts', 0, totalProducts, 1500);
                animateCounter('totalQuantity', 0, totalQuantity, 1800);
                animateCounter('avgDaily', 0, avgDaily, 2200, (val) => formatCurrency(val));
            } catch (error) {
                console.error('Error calculating stats:', error);
                showDebugInfo(`Error calculating stats: ${error.message}`);
            }
        }

        // Hiệu ứng đếm số
        function animateCounter(elementId, start, end, duration, formatter = null) {
            const element = document.getElementById(elementId);
            const startTime = Date.now();
            
            function update() {
                const now = Date.now();
                const progress = Math.min((now - startTime) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                
                element.textContent = formatter ? formatter(value) : value;
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }
            
            update();
        }

        // Format tiền tệ theo định dạng Việt Nam
        function formatCurrency(amount) {
            if (isNaN(amount) || amount === null || amount === undefined) return '0 đ';
            return new Intl.NumberFormat('vi-VN').format(Math.round(amount)) + ' đ';
        }

        // Format ngày theo định dạng Việt Nam
        function formatDate(dateString) {
            try {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                return date.toLocaleDateString('vi-VN');
            } catch (error) {
                console.error('Error formatting date:', error);
                return dateString || 'N/A';
            }
        }

        // Tạo biểu đồ doanh thu
        function createRevenueChart() {
            try {
                showDebugInfo('Creating revenue chart...');
                const ctx = document.getElementById('revenueChart').getContext('2d');
                
                // Nhóm dữ liệu theo ngày
                const revenueByDate = {};
                allData.forEach(item => {
                    if (!revenueByDate[item.order_date]) {
                        revenueByDate[item.order_date] = 0;
                    }
                    revenueByDate[item.order_date] += parseFloat(item.total_revenue) || 0;
                });

                const dates = Object.keys(revenueByDate).sort((a, b) => new Date(a) - new Date(b));
                const revenues = dates.map(date => revenueByDate[date]);
                const formattedDates = dates.map(date => formatDate(date));

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: formattedDates,
                        datasets: [{
                            label: 'Doanh thu (VND)',
                            data: revenues,
                            borderColor: 'rgb(102, 126, 234)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: 'rgb(102, 126, 234)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
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
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        },
                        animation: {
                            duration: 2000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
                showDebugInfo('Revenue chart created successfully');
            } catch (error) {
                console.error('Error creating revenue chart:', error);
                showDebugInfo(`Error creating revenue chart: ${error.message}`);
            }
        }

        // Tạo biểu đồ sản phẩm
        function createProductChart() {
            try {
                showDebugInfo('Creating product chart...');
                const ctx = document.getElementById('productChart').getContext('2d');
                
                // Nhóm dữ liệu theo sản phẩm
                const productRevenue = {};
                allData.forEach(item => {
                    if (!productRevenue[item.product_name]) {
                        productRevenue[item.product_name] = 0;
                    }
                    productRevenue[item.product_name] += parseFloat(item.total_revenue) || 0;
                });

                // Lấy top 5 sản phẩm
                const sortedProducts = Object.entries(productRevenue)
                    .sort(([,a], [,b]) => b - a)
                    .slice(0, 5);

                const labels = sortedProducts.map(([name]) => name.length > 20 ? name.substring(0, 20) + '...' : name);
                const data = sortedProducts.map(([,revenue]) => revenue);
                
                const colors = [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(118, 75, 162, 0.8)',
                    'rgba(52, 152, 219, 0.8)',
                    'rgba(46, 204, 113, 0.8)',
                    'rgba(241, 196, 15, 0.8)'
                ];

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: colors,
                            borderColor: colors.map(color => color.replace('0.8', '1')),
                            borderWidth: 2,
                            hoverOffset: 10
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
                        },
                        animation: {
                            animateRotate: true,
                            duration: 2000
                        }
                    }
                });
                showDebugInfo('Product chart created successfully');
            } catch (error) {
                console.error('Error creating product chart:', error);
                showDebugInfo(`Error creating product chart: ${error.message}`);
            }
        }

        // Hiển thị bảng dữ liệu
        function renderTable(data = allData) {
            try {
                showDebugInfo(`Rendering table with ${data.length} records`);
                const tbody = document.getElementById('tableBody');
                
                if (!data || data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #95a5a6; padding: 30px;">Không tìm thấy dữ liệu</td></tr>';
                    return;
                }

                tbody.innerHTML = data.map(item => `
                    <tr>
                        <td>${formatDate(item.order_date)}</td>
                        <td><span class="product-id">SP${item.product_id}</span></td>
                        <td>${item.product_name || 'N/A'}</td>
                        <td style="text-align: center;">${item.total_quantity_sold || 0}</td>
                        <td class="revenue-cell">${formatCurrency(item.total_revenue || 0)}</td>
                    </tr>
                `).join('');
                
                showDebugInfo('Table rendered successfully');
            } catch (error) {
                console.error('Error rendering table:', error);
                showDebugInfo(`Error rendering table: ${error.message}`);
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="5" style="text-align: center; color: #e74c3c; padding: 30px;">Lỗi hiển thị dữ liệu</td></tr>';
            }
        }

        // Tìm kiếm
        function setupSearch() {
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                const filteredData = allData.filter(item => 
                    (item.product_name && item.product_name.toLowerCase().includes(searchTerm)) ||
                    (item.product_id && item.product_id.toString().includes(searchTerm)) ||
                    (item.order_date && item.order_date.includes(searchTerm))
                );
                renderTable(filteredData);
            });
        }

        // Khởi tạo trang
        function init() {
            try {
                showDebugInfo(`Initializing page with ${allData.length} records`);
                
                // Render table ngay lập tức
                renderTable();
                
                // Kiểm tra dữ liệu
                if (!allData || allData.length === 0) {
                    showDebugInfo('No data available - using sample data');
                    return;
                }

                // Khởi tạo các thành phần khác
                calculateStats();
                createRevenueChart();
                createProductChart();
                setupSearch();
                
                // Set up export and print buttons
                const exportExcelBtn = document.getElementById('exportExcelBtn');
                if (exportExcelBtn) {
                    exportExcelBtn.addEventListener('click', exportToExcel);
                }
                
                const printBtn = document.getElementById('printBtn');
                if (printBtn) {
                    printBtn.addEventListener('click', printReport);
                }
                
                showDebugInfo('Page initialized successfully');
                
                // Debug info is disabled in production
                // If you need to enable debug mode, uncomment the showDebugInfo function above
                
            } catch (error) {
                console.error('Error initializing page:', error);
                showDebugInfo(`Error initializing page: ${error.message}`);
            }
        }

        // Chạy khi trang load xong
        document.addEventListener('DOMContentLoaded', init);

        // Export to Excel function
        function exportToExcel() {
            try {
                showDebugInfo('Starting Excel export...');
                
                // Show loading notification
                const notification = document.createElement('div');
                notification.className = 'loading-notification';
                notification.innerHTML = '<div class="spinner"></div><p>Đang chuẩn bị dữ liệu Excel...</p>';
                notification.style.cssText = `
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: white;
                    padding: 30px;
                    border-radius: 15px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    z-index: 9999;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 15px;
                `;
                document.body.appendChild(notification);
                
                setTimeout(async () => {
                    try {
                        // Create a new workbook
                        const wb = XLSX.utils.book_new();
                        
                        // Create revenue data worksheet
                        const tableData = getTableDataForExcel();
                        const ws = XLSX.utils.aoa_to_sheet(tableData);
                        
                        // Set column widths
                        const colWidths = [
                            { wch: 15 },  // Ngày
                            { wch: 10 },  // ID SP
                            { wch: 40 },  // Tên sản phẩm
                            { wch: 15 },  // Số lượng
                            { wch: 20 },  // Doanh thu
                        ];
                        ws['!cols'] = colWidths;
                        
                        // Add the worksheet to the workbook
                        XLSX.utils.book_append_sheet(wb, ws, 'Dữ liệu doanh thu');
                        
                        // Create statistics worksheet
                        const statsData = getStatsDataForExcel();
                        const statsWs = XLSX.utils.aoa_to_sheet(statsData);
                        
                        // Set column widths for stats
                        const statsColWidths = [
                            { wch: 25 },  // Metric
                            { wch: 25 },  // Value
                        ];
                        statsWs['!cols'] = statsColWidths;
                        
                        // Add the stats worksheet to the workbook
                        XLSX.utils.book_append_sheet(wb, statsWs, 'Thống kê');
                        
                        // Create charts worksheet
                        await exportChartsToExcel(wb);
                        
                        // Generate timestamp for filename
                        const now = new Date();
                        const timestamp = now.toISOString().replace(/[:.]/g, '-').slice(0, 19);
                        const filename = `bao-cao-doanh-thu-${timestamp}.xlsx`;
                        
                        // Export the workbook
                        XLSX.writeFile(wb, filename);
                        
                        showDebugInfo('Excel export completed successfully');
                        
                        // Remove loading notification
                        document.body.removeChild(notification);
                        
                        // Show success message
                        showSuccessMessage('Xuất Excel thành công!');
                    } catch (error) {
                        console.error('Error during Excel export:', error);
                        showDebugInfo(`Excel export error: ${error.message}`);
                        
                        // Remove loading notification
                        document.body.removeChild(notification);
                        
                        // Show error message
                        showErrorMessage('Có lỗi khi xuất Excel: ' + error.message);
                    }
                }, 500);
            } catch (error) {
                console.error('Error in exportToExcel:', error);
                showDebugInfo(`Error in exportToExcel: ${error.message}`);
                
                // Show error message
                showErrorMessage('Có lỗi khi xuất Excel: ' + error.message);
            }
        }
        
        // Get table data formatted for Excel
        function getTableDataForExcel() {
            // Create header row
            const headers = ['Ngày', 'ID sản phẩm', 'Tên sản phẩm', 'Số lượng', 'Doanh thu'];
            
            // Create data rows
            const rows = allData.map(item => [
                item.order_date,
                `SP${item.product_id}`,
                item.product_name || 'N/A',
                item.total_quantity_sold || 0,
                item.total_revenue || 0
            ]);
            
            // Combine headers and rows
            return [headers, ...rows];
        }
        
        // Get statistics data formatted for Excel
        function getStatsDataForExcel() {
            // Calculate statistics
            const totalRevenue = allData.reduce((sum, item) => sum + (parseFloat(item.total_revenue) || 0), 0);
            const totalProducts = new Set(allData.map(item => item.product_id)).size;
            const totalQuantity = allData.reduce((sum, item) => sum + (parseInt(item.total_quantity_sold) || 0), 0);
            const uniqueDates = new Set(allData.map(item => item.order_date)).size;
            const avgDaily = uniqueDates > 0 ? totalRevenue / uniqueDates : 0;
            
            // Format data for Excel
            return [
                ['Chỉ số', 'Giá trị'],
                ['Tổng doanh thu', totalRevenue],
                ['Số lượng sản phẩm', totalProducts],
                ['Tổng số lượng bán', totalQuantity],
                ['Trung bình hàng ngày', avgDaily],
                ['Thời gian xuất báo cáo', new Date().toLocaleString('vi-VN')]
            ];
        }
        
        // Export charts to Excel
        async function exportChartsToExcel(workbook) {
            try {
                // Create a worksheet for charts info
                const chartsWs = XLSX.utils.aoa_to_sheet([
                    ['Biểu đồ doanh thu và sản phẩm bán chạy'],
                    ['Lưu ý: Biểu đồ không thể hiển thị trực tiếp trong Excel. Vui lòng xem báo cáo web để xem biểu đồ trực quan.'],
                    [''],
                    ['Doanh thu theo thời gian:'],
                ]);
                
                // Add revenue by date data
                const revenueByDate = {};
                allData.forEach(item => {
                    if (!revenueByDate[item.order_date]) {
                        revenueByDate[item.order_date] = 0;
                    }
                    revenueByDate[item.order_date] += parseFloat(item.total_revenue) || 0;
                });
                
                const dates = Object.keys(revenueByDate).sort((a, b) => new Date(a) - new Date(b));
                const revenueData = [['Ngày', 'Doanh thu']];
                dates.forEach(date => {
                    revenueData.push([date, revenueByDate[date]]);
                });
                
                // Append revenue data
                XLSX.utils.sheet_add_aoa(chartsWs, revenueData, { origin: 'A6' });
                
                // Add top products data
                const productRevenue = {};
                allData.forEach(item => {
                    if (!productRevenue[item.product_name]) {
                        productRevenue[item.product_name] = 0;
                    }
                    productRevenue[item.product_name] += parseFloat(item.total_revenue) || 0;
                });
                
                const sortedProducts = Object.entries(productRevenue)
                    .sort(([,a], [,b]) => b - a)
                    .slice(0, 5);
                
                const productRows = [['', ''], ['', ''], ['Top 5 sản phẩm bán chạy:'], ['Tên sản phẩm', 'Doanh thu']];
                sortedProducts.forEach(([name, revenue]) => {
                    productRows.push([name, revenue]);
                });
                
                // Append product data starting several rows down
                XLSX.utils.sheet_add_aoa(chartsWs, productRows, { origin: 'A' + (8 + revenueData.length) });
                
                // Add the worksheet to the workbook
                XLSX.utils.book_append_sheet(workbook, chartsWs, 'Biểu đồ');
                
                return workbook;
            } catch (error) {
                console.error('Error exporting charts to Excel:', error);
                showDebugInfo(`Error exporting charts: ${error.message}`);
                throw error;
            }
        }
        
        // Print report function
        function printReport() {
            try {
                showDebugInfo('Preparing print view...');
                
                // Create a new window for printing
                const printWindow = window.open('', '_blank', 'width=1200,height=800');
                
                // Get formatted date for header
                const now = new Date();
                const dateStr = now.toLocaleDateString('vi-VN');
                const timeStr = now.toLocaleTimeString('vi-VN');
                
                // Calculate statistics for print view
                const totalRevenue = allData.reduce((sum, item) => sum + (parseFloat(item.total_revenue) || 0), 0);
                const totalProducts = new Set(allData.map(item => item.product_id)).size;
                const totalQuantity = allData.reduce((sum, item) => sum + (parseInt(item.total_quantity_sold) || 0), 0);
                
                // Create a clone of the reports table for printing
                const reportsTable = document.querySelector('#reportsTable').cloneNode(true);
                
                // Generate HTML content for the print window
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Báo cáo doanh thu - In</title>
                        <meta charset="UTF-8">
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                margin: 20px;
                                color: #333;
                            }
                            .print-header {
                                text-align: center;
                                margin-bottom: 20px;
                                padding-bottom: 10px;
                                border-bottom: 2px solid #ddd;
                            }
                            .print-title {
                                font-size: 24px;
                                font-weight: bold;
                                margin: 0;
                                padding: 0;
                            }
                            .print-date {
                                margin-top: 5px;
                                font-size: 14px;
                                color: #666;
                            }
                            .stats-container {
                                display: flex;
                                justify-content: space-between;
                                margin-bottom: 20px;
                            }
                            .stat-box {
                                border: 1px solid #ddd;
                                padding: 10px;
                                text-align: center;
                                width: 23%;
                            }
                            .stat-value {
                                font-size: 18px;
                                font-weight: bold;
                            }
                            .stat-title {
                                font-size: 12px;
                                color: #666;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                                margin-top: 20px;
                            }
                            th, td {
                                border: 1px solid #ddd;
                                padding: 8px;
                                text-align: left;
                            }
                            th {
                                background-color: #f2f2f2;
                            }
                            tr:nth-child(even) {
                                background-color: #f9f9f9;
                            }
                            .product-id {
                                background: #667eea;
                                color: white;
                                padding: 5px 10px;
                                border-radius: 20px;
                                font-size: 0.8rem;
                                font-weight: bold;
                            }
                            .revenue-cell {
                                font-weight: bold;
                                color: #27ae60;
                            }
                            .print-footer {
                                margin-top: 20px;
                                text-align: center;
                                font-size: 12px;
                                color: #666;
                                padding-top: 10px;
                                border-top: 1px solid #ddd;
                            }
                            .print-note {
                                font-style: italic;
                                color: #666;
                                margin-top: 20px;
                                font-size: 12px;
                            }
                            .no-print {
                                display: none;
                            }
                            @media print {
                                .no-print {
                                    display: none !important;
                                }
                                body {
                                    margin: 0;
                                    padding: 15px;
                                }
                                .print-buttons {
                                    display: none !important;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h1 class="print-title">Báo cáo doanh thu</h1>
                            <div class="print-date">Ngày in: ${dateStr} lúc ${timeStr}</div>
                        </div>
                        
                        <div class="stats-container">
                            <div class="stat-box">
                                <div class="stat-value">${formatCurrency(totalRevenue)}</div>
                                <div class="stat-title">Tổng doanh thu</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">${totalProducts}</div>
                                <div class="stat-title">Sản phẩm bán</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">${totalQuantity}</div>
                                <div class="stat-title">Số lượng bán</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">${allData.length}</div>
                                <div class="stat-title">Tổng số giao dịch</div>
                            </div>
                        </div>
                        
                        <div class="print-note">
                            <p>Ghi chú: Báo cáo này chỉ hiển thị dữ liệu bảng. Để xem biểu đồ chi tiết, vui lòng xem báo cáo trên web.</p>
                        </div>
                        
                        <div class="table-container">
                            ${reportsTable.outerHTML}
                        </div>
                        
                        <div class="print-footer">
                            <p>© ${new Date().getFullYear()} - Hệ thống quản lý cửa hàng</p>
                        </div>
                        
                        <div class="print-buttons no-print" style="margin-top: 20px; text-align: center;">
                            <button onclick="window.print()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                In ngay
                            </button>
                            <button onclick="window.close()" style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                                Đóng
                            </button>
                        </div>
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                
                // Focus the print window
                printWindow.focus();
                
                showDebugInfo('Print view prepared successfully');
            } catch (error) {
                console.error('Error preparing print view:', error);
                showDebugInfo(`Error preparing print: ${error.message}`);
                showErrorMessage('Có lỗi khi chuẩn bị in: ' + error.message);
            }
        }
        
        // Show success message
        function showSuccessMessage(message) {
            const notification = document.createElement('div');
            notification.className = 'success-notification';
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #00b894, #00cec9);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                z-index: 9999;
                animation: slideInRight 0.5s ease-out;
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.5s ease-in forwards';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 500);
            }, 3000);
        }
        
        // Show error message
        function showErrorMessage(message) {
            const notification = document.createElement('div');
            notification.className = 'error-notification';
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #d63031, #e17055);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                z-index: 9999;
                animation: slideInRight 0.5s ease-out;
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.5s ease-in forwards';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 500);
            }, 4000);
        }
        
        // Animation for notifications
        document.head.insertAdjacentHTML('beforeend', `
            <style>
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            </style>
        `);

        // Hiệu ứng parallax cho header
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const header = document.querySelector('.header');
            if (header) {
                header.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });
    </script>
</body>
</html>