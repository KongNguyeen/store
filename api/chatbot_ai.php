<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

// Debug error reporting
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
    ini_set('display_errors', 0);
}

header('Content-Type: application/json');

// Allow same-origin CORS requests only
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = '';
if ($origin) {
    $origin_host = parse_url($origin, PHP_URL_HOST);
    $server_host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin_host && $server_host && strcasecmp($origin_host, $server_host) === 0) {
        $allowed_origin = $origin;
    }
}
if ($allowed_origin) {
    header('Access-Control-Allow-Origin: ' . $allowed_origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage()
    ]);
    exit;
}

try {
    $raw_input = file_get_contents('php://input');
    if ($raw_input === false) {
        throw new Exception('Không thể đọc dữ liệu đầu vào');
    }
    
    $input = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dữ liệu JSON không hợp lệ: ' . json_last_error_msg());
    }
    
    $message = trim($input['message'] ?? '');
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log("Chatbot received message: " . $message);
    }
    
    if (!$message) {
        throw new Exception('Tin nhắn không được để trống');
    }
    
    // Phân tích tin nhắn để tìm hiểu ý định của người dùng
    $intent = analyzeUserIntent($message, $pdo);
    
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log("Analyzed intent: " . json_encode($intent));
    }
    
    $response = generateResponse($pdo, $message, $intent, $user_id);
    
    echo json_encode([
        'success' => true,
        'response' => $response['text'],
        'products' => $response['products'] ?? [],
        'suggestions' => $response['suggestions'] ?? []
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function analyzeUserIntent($message, $pdo) {
    $message = trim(strtolower($message));
    
    $intent = [
        'type' => 'search',
        'action' => 'search',
        'keywords' => [],
        'category' => null,
        'price_range' => null,
        'colors' => [],
        'brands' => []
    ];
    
    // Xử lý các câu gợi ý cụ thể với "Xem thêm"
    if (preg_match('/^xem thêm\s+(.+)$/i', $message, $matches)) {
        $category = trim($matches[1]);
        $intent['category'] = $category;
        $intent['keywords'][] = $category;
        $intent['action'] = 'view_category';
        error_log("Detected view_category: " . $category);
        return $intent;
    }
    
    // Xử lý sản phẩm theo giá
    if (preg_match('/sản phẩm\s+(?:giá\s+)?dưới\s+(\d+)([km]?)/i', $message, $matches)) {
        $amount = intval($matches[1]);
        $unit = isset($matches[2]) ? $matches[2] : '';
        
        if ($unit === 'k') {
            $max_price = $amount * 1000;
        } elseif ($unit === 'm' || $amount < 100) {
            $max_price = $amount * 1000000; // triệu
        } else {
            $max_price = $amount * 1000; // nghìn
        }
        
        $intent['price_range'] = [
            'min' => 0,
            'max' => $max_price
        ];
        $intent['action'] = 'price_filter';
        error_log("Detected price_filter under: " . $max_price);
        return $intent;
    }
    
    // Xử lý kết hợp từ khóa sản phẩm + thương hiệu (ví dụ: "giày nike", "áo adidas")
    if (preg_match('/(giày|áo|quần|tai\s*nghe|laptop|điện\s*thoại|sách|bút|tivi|tủ\s*lạnh|máy\s*giặt)\s+(nike|adidas|samsung|apple|sony|xiaomi|dell|hp|asus|iphone|lg|panasonic|electrolux|sharp)/i', $message, $matches)) {
        $product_type = strtolower(trim($matches[1]));
        $brand = strtolower(trim($matches[2]));
        
        $intent['keywords'] = [$product_type, $brand];
        $intent['action'] = 'product_brand_search';
        $intent['product_type'] = $product_type;
        $intent['brand'] = $brand;
        
        error_log("Detected product + brand search: " . $product_type . " + " . $brand);
        return $intent;
    }
    
    // Xử lý kết hợp từ khóa với danh mục cụ thể (ví dụ: "giày thể thao", "áo nam")
    if (preg_match('/(giày|áo|quần|laptop|điện\s*thoại|tivi)\s+(thể\s*thao|nam|nữ|gaming|văn\s*phòng|32\s*inch|55\s*inch|65\s*inch)/i', $message, $matches)) {
        $product_type = strtolower(trim($matches[1]));
        $category_type = strtolower(trim($matches[2]));
        
        $intent['keywords'] = [$product_type, $category_type];
        $intent['action'] = 'product_category_search';
        $intent['product_type'] = $product_type;
        $intent['category_type'] = $category_type;
        
        error_log("Detected product + category search: " . $product_type . " + " . $category_type);
        return $intent;
    }
    
    // Xử lý kết hợp từ khóa sản phẩm + giá (ví dụ: "iphone dưới 1 triệu")
    if (preg_match('/(iphone|samsung|xiaomi|laptop|tai\s*nghe|sách).*?dưới\s+(\d+)\s*(triệu|tr|k|m)?/i', $message, $matches)) {
        $product_type = strtolower(trim($matches[1]));
        $amount = intval($matches[2]);
        $unit = isset($matches[3]) ? strtolower($matches[3]) : '';
        
        // Xác định giá tối đa
        if ($unit === 'k') {
            $max_price = $amount * 1000;
        } elseif ($unit === 'm' || $unit === 'triệu' || $unit === 'tr' || $amount < 100) {
            $max_price = $amount * 1000000;
        } else {
            $max_price = $amount * 1000;
        }
        
        $intent['price_range'] = [
            'min' => 0,
            'max' => $max_price
        ];
        
        // Xử lý theo loại sản phẩm
        switch ($product_type) {
            case 'iphone':
                $intent['action'] = 'iphone_price_search';
                $intent['keywords'] = ['iphone'];
                break;
            case 'samsung':
                $intent['action'] = 'samsung_price_search';
                $intent['keywords'] = ['samsung'];
                break;
            case 'xiaomi':
                $intent['action'] = 'xiaomi_price_search';
                $intent['keywords'] = ['xiaomi'];
                break;
            case 'laptop':
                $intent['action'] = 'laptop_price_search';
                $intent['keywords'] = ['laptop'];
                break;
            case 'tai nghe':
                $intent['action'] = 'headphone_price_search';
                $intent['keywords'] = ['tai nghe'];
                break;
            case 'sách':
                $intent['action'] = 'book_price_search';
                $intent['keywords'] = ['sách'];
                break;
            default:
                $intent['action'] = 'price_filter';
        }
        
        error_log("Detected product + price search: " . $product_type . " under " . $max_price);
        return $intent;
    }
    
    // Xử lý sản phẩm trên giá
    if (preg_match('/sản phẩm\s+(?:giá\s+)?trên\s+(\d+)([km]?)/i', $message, $matches)) {
        $amount = intval($matches[1]);
        $unit = isset($matches[2]) ? $matches[2] : '';
        
        if ($unit === 'k') {
            $min_price = $amount * 1000;
        } elseif ($unit === 'm' || $amount < 100) {
            $min_price = $amount * 1000000;
        } else {
            $min_price = $amount * 1000;
        }
        
        $intent['price_range'] = [
            'min' => $min_price,
            'max' => 999999999
        ];
        $intent['action'] = 'price_filter';
        error_log("Detected price_filter over: " . $min_price);
        return $intent;
    }
    
    // Xử lý khoảng giá
    if (preg_match('/sản phẩm\s+(?:từ\s+)?(\d+)([km]?)\s+(?:đến|tới|-)\s+(\d+)([km]?)/i', $message, $matches)) {
        $min_amount = intval($matches[1]);
        $min_unit = isset($matches[2]) ? $matches[2] : '';
        $max_amount = intval($matches[3]);
        $max_unit = isset($matches[4]) ? $matches[4] : '';
        
        $min_price = ($min_unit === 'k') ? $min_amount * 1000 : 
                    (($min_unit === 'm' || $min_amount < 100) ? $min_amount * 1000000 : $min_amount * 1000);
        $max_price = ($max_unit === 'k') ? $max_amount * 1000 : 
                    (($max_unit === 'm' || $max_amount < 100) ? $max_amount * 1000000 : $max_amount * 1000);
        
        $intent['price_range'] = [
            'min' => $min_price,
            'max' => $max_price
        ];
        $intent['action'] = 'price_range';
        error_log("Detected price_range: " . $min_price . " - " . $max_price);
        return $intent;
    }
    
    // Xử lý sản phẩm mới nhất
    if (preg_match('/sản phẩm\s+mới\s*nhất|hàng\s+mới|mới\s+về/i', $message)) {
        $intent['action'] = 'newest';
        error_log("Detected newest products");
        return $intent;
    }
    
    // Xử lý sản phẩm bán chạy
    if (preg_match('/sản phẩm\s+bán\s+chạy|best\s*seller|hot\s*trend/i', $message)) {
        $intent['action'] = 'bestseller';
        error_log("Detected bestseller products");
        return $intent;
    }
    
    // Xử lý sản phẩm khuyến mãi
    if (preg_match('/sản phẩm\s+(?:khuyến mãi|giảm giá|sale|promotion)/i', $message)) {
        $intent['action'] = 'promotion';
        error_log("Detected promotion products");
        return $intent;
    }
    
    // Lấy danh mục từ database để sử dụng trong pattern matching
    $categories = getCategoriesFromDB($pdo);
    $products = getProductKeywordsFromDB($pdo);
    
    // Tạo danh sách tên danh mục để sử dụng trong regex
    $category_names = [];
    foreach ($categories as $category) {
        $category_names[] = strtolower($category['name']);
    }
    
    // Xử lý kết hợp danh mục + từ khóa đặc biệt
    foreach ($category_names as $category_name) {
        // Pattern: "[danh mục] + mới nhất"
        if (preg_match('/'.preg_quote($category_name, '/').'.*?(?:mới\s*nhất|newest)/i', $message)) {
            $intent['category'] = $category_name;
            $intent['action'] = 'category_newest';
            $intent['keywords'] = [$category_name, 'mới nhất'];
            error_log("Detected category newest: " . $category_name);
            return $intent;
        }
        
        // Pattern: "[danh mục] + bán chạy"
        if (preg_match('/'.preg_quote($category_name, '/').'.*?(?:bán\s*chạy|bestseller|hot)/i', $message)) {
            $intent['category'] = $category_name;
            $intent['action'] = 'category_bestseller';
            $intent['keywords'] = [$category_name, 'bán chạy'];
            error_log("Detected category bestseller: " . $category_name);
            return $intent;
        }
        
        // Pattern: "[danh mục] + khuyến mãi"
        if (preg_match('/'.preg_quote($category_name, '/').'.*?(?:khuyến\s*mãi|giảm\s*giá|sale|promotion)/i', $message)) {
            $intent['category'] = $category_name;
            $intent['action'] = 'category_promotion';
            $intent['keywords'] = [$category_name, 'khuyến mãi'];
            error_log("Detected category promotion: " . $category_name);
            return $intent;
        }
        
        // Pattern: "[danh mục] + dưới [giá]"
        if (preg_match('/'.preg_quote($category_name, '/').'.*?dưới\s+(\d+)\s*(triệu|tr|k|m)?/i', $message, $matches)) {
            $amount = intval($matches[1]);
            $unit = isset($matches[2]) ? strtolower($matches[2]) : '';
            
            if ($unit === 'k') {
                $max_price = $amount * 1000;
            } elseif ($unit === 'm' || $unit === 'triệu' || $unit === 'tr' || $amount < 100) {
                $max_price = $amount * 1000000;
            } else {
                $max_price = $amount * 1000;
            }
            
            $intent['category'] = $category_name;
            $intent['price_range'] = ['min' => 0, 'max' => $max_price];
            $intent['action'] = 'category_price_filter';
            $intent['keywords'] = [$category_name, 'giá'];
            error_log("Detected category price filter: " . $category_name . " under " . $max_price);
            return $intent;
        }
        
        // Pattern: "[danh mục] + trên [giá]"
        if (preg_match('/'.preg_quote($category_name, '/').'.*?trên\s+(\d+)\s*(triệu|tr|k|m)?/i', $message, $matches)) {
            $amount = intval($matches[1]);
            $unit = isset($matches[2]) ? strtolower($matches[2]) : '';
            
            if ($unit === 'k') {
                $min_price = $amount * 1000;
            } elseif ($unit === 'm' || $unit === 'triệu' || $unit === 'tr' || $amount < 100) {
                $min_price = $amount * 1000000;
            } else {
                $min_price = $amount * 1000;
            }
            
            $intent['category'] = $category_name;
            $intent['price_range'] = ['min' => $min_price, 'max' => 999999999];
            $intent['action'] = 'category_price_filter';
            $intent['keywords'] = [$category_name, 'giá'];
            error_log("Detected category price filter: " . $category_name . " over " . $min_price);
            return $intent;
        }
    }
    
    // Từ khóa về màu sắc
    $color_keywords = ['đen', 'trắng', 'đỏ', 'xanh', 'vàng', 'hồng', 'tím', 'nâu', 'xám'];
    
    // Từ khóa về thương hiệu
    $brand_keywords = ['apple', 'samsung', 'sony', 'nike', 'adidas', 'canon', 'lg', 'iphone', 'xiaomi', 'oppo', 'vivo'];
    
    // Phát hiện khoảng giá trong câu thông thường
    if (preg_match('/(\d+).*?(?:đến|tới|-|to).*?(\d+).*?(?:triệu|tr)/i', $message, $matches)) {
        $intent['price_range'] = [
            'min' => intval($matches[1]) * 1000000,
            'max' => intval($matches[2]) * 1000000
        ];
    } elseif (preg_match('/dưới.*?(\d+).*?(?:triệu|tr)/i', $message, $matches)) {
        $intent['price_range'] = [
            'min' => 0,
            'max' => intval($matches[1]) * 1000000
        ];
    } elseif (preg_match('/trên.*?(\d+).*?(?:triệu|tr)/i', $message, $matches)) {
        $intent['price_range'] = [
            'min' => intval($matches[1]) * 1000000,
            'max' => 999999999
        ];
    }
    
    // Xử lý đặc biệt cho từ khóa "điện thoại" - ưu tiên danh mục điện thoại
    if (preg_match('/điện\s*thoại|smartphone|phone|mobile/i', $message)) {
        // Tìm trong các danh mục điện thoại
        $phone_categories = ['iphone', 'samsung', 'xiaomi', 'oppo', 'vivo'];
        foreach ($categories as $category) {
            $category_name = strtolower($category['name']);
            if (in_array($category_name, $phone_categories)) {
                // Chỉ tìm trong danh mục điện thoại, không thêm từ "điện" vào keywords
                $intent['category'] = $category_name;
                $intent['keywords'] = ['smartphone']; // Dùng từ khóa chung
                $intent['action'] = 'phone_search';
                error_log("Detected phone search for category: " . $category_name);
                return $intent;
            }
        }
        // Nếu không tìm thấy danh mục cụ thể, tìm chung cho điện thoại
        $intent['keywords'] = ['smartphone', 'phone'];
        $intent['action'] = 'phone_search';
        error_log("Detected general phone search");
        return $intent;
    }
    
    // Xử lý đặc biệt cho từ khóa "laptop"
    if (preg_match('/laptop|máy\s*tính\s*xách\s*tay/i', $message)) {
        $intent['keywords'] = ['laptop'];
        $intent['action'] = 'laptop_search';
        error_log("Detected laptop search");
        return $intent;
    }
    
    // Xử lý đặc biệt cho từ khóa "tai nghe"
    if (preg_match('/tai\s*nghe|headphone|earphone|airpods/i', $message)) {
        $intent['keywords'] = ['tai nghe'];
        $intent['action'] = 'headphone_search';
        error_log("Detected headphone search");
        return $intent;
    }
    
    // Xử lý đặc biệt cho từ khóa "sách"
    if (preg_match('/sách|book|truyện/i', $message)) {
        $intent['keywords'] = ['sách'];
        $intent['action'] = 'book_search';
        error_log("Detected book search");
        return $intent;
    }
    
    // Phát hiện danh mục từ database
    foreach ($categories as $category) {
        $category_name = strtolower($category['name']);
        if (strpos($message, $category_name) !== false) {
            $intent['category'] = $category_name;
            $intent['keywords'][] = $category_name;
            break;
        }
    }
    
    // Phát hiện tên sản phẩm từ database
    foreach ($products as $product) {
        $product_name = strtolower($product['name']);
        $words = explode(' ', $product_name);
        foreach ($words as $word) {
            if (strlen($word) > 2 && strpos($message, $word) !== false) {
                $intent['keywords'][] = $word;
                if (!$intent['category'] && $product['category']) {
                    $intent['category'] = strtolower($product['category']);
                }
            }
        }
    }
    
    // Phát hiện màu sắc
    foreach ($color_keywords as $color) {
        if (strpos($message, $color) !== false) {
            $intent['colors'][] = $color;
            $intent['keywords'][] = $color;
        }
    }
    
    // Phát hiện thương hiệu
    foreach ($brand_keywords as $brand) {
        if (strpos($message, $brand) !== false) {
            $intent['brands'][] = $brand;
            $intent['keywords'][] = $brand;
        }
    }
    
    // Trích xuất từ khóa tìm kiếm chính
    $words = explode(' ', $message);
    $stop_words = ['tôi', 'muốn', 'cần', 'có', 'là', 'của', 'một', 'và', 'với', 'cho', 'bạn', 'anh', 'chị', 'tìm', 'giá', 'dưới', 'trên', 'sản', 'phẩm', 'xem', 'thêm'];
    
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) > 2 && !in_array($word, $stop_words) && !in_array($word, $intent['keywords'])) {
            $intent['keywords'][] = $word;
        }
    }
    
    // Giới hạn số lượng từ khóa
    $intent['keywords'] = array_slice(array_unique($intent['keywords']), 0, 5);
    
    error_log("Analyzed intent: " . json_encode($intent));
    return $intent;
    
    // Phát hiện từ khóa tìm kiếm
    foreach ($search_keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $intent['type'] = 'search';
            break;
        }
    }
    
    // Phát hiện danh mục - ưu tiên từ khóa cụ thể
    foreach ($category_keywords as $main_category => $synonyms) {
        foreach ($synonyms as $synonym) {
            if (strpos($message, $synonym) !== false) {
                $intent['category'] = $main_category;
                // Chỉ thêm từ khóa chính, không phải tất cả từ đồng nghĩa
                if (!in_array($synonym, $intent['keywords'])) {
                    $intent['keywords'][] = $synonym;
                }
                break 2;
            }
        }
    }
    
    // Phát hiện màu sắc
    foreach ($color_keywords as $color) {
        if (strpos($message, $color) !== false) {
            $intent['colors'][] = $color;
            $intent['keywords'][] = $color;
        }
    }
    
    // Phát hiện thương hiệu
    foreach ($brand_keywords as $brand) {
        if (strpos($message, $brand) !== false) {
            $intent['brands'][] = $brand;
            $intent['keywords'][] = $brand;
        }
    }
    
    // Phát hiện từ khóa giá
    foreach ($price_keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $intent['action'] = 'price_inquiry';
            break;
        }
    }
    
    // Phát hiện khoảng giá cụ thể
    if (preg_match('/(\d+).*?(?:đến|tới|-|to).*?(\d+)/', $message, $matches)) {
        $intent['price_range'] = [
            'min' => intval($matches[1]) * 1000000, // Triệu VND
            'max' => intval($matches[2]) * 1000000
        ];
    } elseif (preg_match('/dưới.*?(\d+).*?(?:triệu|tr|million)/i', $message, $matches)) {
        $intent['price_range'] = [
            'min' => 0,
            'max' => intval($matches[1]) * 1000000
        ];
    } elseif (preg_match('/trên.*?(\d+).*?(?:triệu|tr|million)/i', $message, $matches)) {
        $intent['price_range'] = [
            'min' => intval($matches[1]) * 1000000,
            'max' => 999999999
        ];
    }
    
    // Trích xuất từ khóa tìm kiếm chính - chỉ lấy từ khóa quan trọng
    $words = explode(' ', $message);
    $stop_words = ['tôi', 'muốn', 'cần', 'có', 'là', 'của', 'một', 'và', 'với', 'cho', 'bạn', 'anh', 'chị', 'tìm', 'giá', 'dưới', 'trên'];
    
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) > 2 && !in_array($word, $stop_words) && !in_array($word, $search_keywords)) {
            // Chỉ thêm nếu chưa có trong keywords
            if (!in_array($word, $intent['keywords'])) {
                $intent['keywords'][] = $word;
            }
        }
    }
    
    // Giới hạn số lượng từ khóa để tránh tìm kiếm quá rộng
    $intent['keywords'] = array_slice(array_unique($intent['keywords']), 0, 3);
    
    return $intent;
}

// Hàm lấy danh mục từ database
function getCategoriesFromDB($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT category_id, name FROM categories ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting categories: " . $e->getMessage());
        return [];
    }
}

// Hàm lấy từ khóa sản phẩm từ database
function getProductKeywordsFromDB($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.name, c.name as category 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            WHERE p.status = 'active' 
            ORDER BY p.name
            LIMIT 1000
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting product keywords: " . $e->getMessage());
        return [];
    }
}

// Hàm xử lý sản phẩm mới nhất
function getNewestProducts($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            ORDER BY p.product_id DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("getNewestProducts found: " . count($products) . " products");
        return $products;
    } catch (Exception $e) {
        error_log("Error getting newest products: " . $e->getMessage());
        return [];
    }
}

// Hàm xử lý sản phẩm bán chạy
function getBestsellerProducts($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            ORDER BY sold_count DESC, p.product_id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting bestseller products: " . $e->getMessage());
        return [];
    }
}

// Hàm xử lý sản phẩm khuyến mãi
function getPromotionProducts($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            AND p.sale_price IS NOT NULL 
            AND p.sale_price > 0
            AND p.sale_price < p.price 
            ORDER BY ((p.price - p.sale_price) / p.price) DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting promotion products: " . $e->getMessage());
        return [];
    }
}

// Hàm tìm kiếm chỉ trong danh mục điện thoại
function searchPhoneProducts($pdo, $limit = 20) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            AND LOWER(c.name) IN ('iphone', 'samsung', 'xiaomi', 'oppo', 'vivo', 'điện thoại', 'smartphone')
            ORDER BY p.product_id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("searchPhoneProducts found: " . count($products) . " phone products");
        return $products;
    } catch (Exception $e) {
        error_log("Error searching phone products: " . $e->getMessage());
        return [];
    }
}

// Hàm tìm kiếm theo danh mục cụ thể
function searchByCategory($pdo, $categories, $limit = 20) {
    try {
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            AND LOWER(c.name) IN ($placeholders)
            ORDER BY p.product_id DESC
            LIMIT ?
        ");
        
        $params = array_merge($categories, [$limit]);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("searchByCategory found: " . count($products) . " products for categories: " . implode(', ', $categories));
        return $products;
    } catch (Exception $e) {
        error_log("Error searching by category: " . $e->getMessage());
        return [];
    }
}

// Hàm tìm kiếm theo thương hiệu và khoảng giá
function searchProductsByBrandAndPrice($pdo, $brand, $price_range, $limit = 20) {
    try {
        $brand_categories = [
            'iphone' => ['iphone'],
            'samsung' => ['samsung'],
            'xiaomi' => ['xiaomi'],
            'laptop' => ['laptop gaming', 'laptop văn phòng'],
            'tai nghe' => ['tai nghe'],
            'sách' => ['sách']
        ];
        
        $categories = $brand_categories[$brand] ?? [$brand];
        $placeholders = str_repeat('?,', count($categories) - 1) . '?';
        
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            AND LOWER(c.name) IN ($placeholders)
            AND p.price >= ? AND p.price <= ?
            ORDER BY p.price ASC
            LIMIT ?
        ");
        
        $params = array_merge($categories, [$price_range['min'], $price_range['max'], $limit]);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("searchProductsByBrandAndPrice found: " . count($products) . " products for " . $brand . " in price range " . $price_range['min'] . " - " . $price_range['max']);
        return $products;
    } catch (Exception $e) {
        error_log("Error searching products by brand and price: " . $e->getMessage());
        return [];
    }
}

// Hàm tìm kiếm theo sản phẩm và thương hiệu
function searchByProductAndBrand($pdo, $product_type, $brand, $limit = 20) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            AND (LOWER(p.name) LIKE ? OR LOWER(c.name) LIKE ?)
            AND (LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ?)
            ORDER BY p.product_id DESC
            LIMIT ?
        ");
        
        $product_pattern = '%' . $product_type . '%';
        $brand_pattern = '%' . $brand . '%';
        
        $stmt->execute([$product_pattern, $product_pattern, $brand_pattern, $brand_pattern, $limit]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("searchByProductAndBrand found: " . count($products) . " products for " . $product_type . " + " . $brand);
        return $products;
    } catch (Exception $e) {
        error_log("Error searching by product and brand: " . $e->getMessage());
        return [];
    }
}

// Hàm tìm kiếm theo sản phẩm và danh mục
function searchByProductAndCategory($pdo, $product_type, $category_type, $limit = 20) {
    try {
        $category_mapping = [
            'thể thao' => ['trang phục thể thao', 'dụng cụ thể thao'],
            'nam' => ['áo nam', 'quần nam'],
            'nữ' => ['áo nữ', 'quần nữ'],
            'gaming' => ['laptop gaming'],
            'văn phòng' => ['laptop văn phòng', 'văn phòng phẩm'],
            '32 inch' => ['tivi 32 inch'],
            '55 inch' => ['tivi 55 inch'],
            '65 inch' => ['tivi 65 inch']
        ];
        
        $categories = $category_mapping[$category_type] ?? [$category_type];
        $category_conditions = str_repeat('LOWER(c.name) LIKE ? OR ', count($categories));
        $category_conditions = rtrim($category_conditions, ' OR ');
        
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name,
                   (SELECT image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    LIMIT 1) as product_image,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
                   (SELECT COUNT(*) FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.order_id 
                    WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
            FROM products p
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            AND (LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ?)
            AND ($category_conditions)
            ORDER BY p.product_id DESC
            LIMIT ?
        ");
        
        $product_pattern = '%' . $product_type . '%';
        $params = [$product_pattern, $product_pattern];
        
        foreach ($categories as $cat) {
            $params[] = '%' . $cat . '%';
        }
        $params[] = $limit;
        
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("searchByProductAndCategory found: " . count($products) . " products for " . $product_type . " + " . $category_type);
        return $products;
    } catch (Exception $e) {
        error_log("Error searching by product and category: " . $e->getMessage());
        return [];
    }
}

function generateResponse($pdo, $message, $intent, $user_id) {
    $response = [
        'text' => '',
        'products' => [],
        'suggestions' => []
    ];
    
    // Log để debug
    error_log("Generating response for intent: " . json_encode($intent));
    
    // Xử lý các action đặc biệt
    switch ($intent['action']) {
        case 'category_newest':
            $products = getCategoryNewestProducts($pdo, $intent['category']);
            if (!empty($products)) {
                $response['text'] = ucfirst($intent['category']) . " mới nhất:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $response['text'] = "Xin lỗi, hiện tại không có " . $intent['category'] . " mới nào. Bạn có thể xem các sản phẩm khác:";
                $products = searchByCategory($pdo, [$intent['category']]);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'category_bestseller':
            $products = getCategoryBestsellerProducts($pdo, $intent['category']);
            if (!empty($products)) {
                $response['text'] = ucfirst($intent['category']) . " bán chạy nhất:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $response['text'] = "Xin lỗi, hiện tại không có " . $intent['category'] . " bán chạy nào. Bạn có thể xem các sản phẩm khác:";
                $products = searchByCategory($pdo, [$intent['category']]);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'category_promotion':
            $products = getCategoryPromotionProducts($pdo, $intent['category']);
            if (!empty($products)) {
                $response['text'] = ucfirst($intent['category']) . " đang khuyến mãi:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $response['text'] = "Xin lỗi, hiện tại không có " . $intent['category'] . " khuyến mãi nào. Bạn có thể xem các sản phẩm khác:";
                $products = searchByCategory($pdo, [$intent['category']]);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'category_price_filter':
            $products = getCategoryProductsByPrice($pdo, $intent['category'], $intent['price_range']);
            if (!empty($products)) {
                $min = number_format($intent['price_range']['min']);
                $max = number_format($intent['price_range']['max']);
                
                if ($intent['price_range']['max'] >= 999999999) {
                    $response['text'] = ucfirst($intent['category']) . " có giá từ {$min}đ trở lên:";
                } elseif ($intent['price_range']['min'] <= 0) {
                    $response['text'] = ucfirst($intent['category']) . " có giá dưới {$max}đ:";
                } else {
                    $response['text'] = ucfirst($intent['category']) . " trong khoảng giá {$min}đ - {$max}đ:";
                }
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $max = number_format($intent['price_range']['max']);
                $response['text'] = "Xin lỗi, hiện tại không có " . $intent['category'] . " trong khoảng giá này. Bạn có thể xem các sản phẩm khác:";
                $products = searchByCategory($pdo, [$intent['category']]);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'product_brand_search':
            $products = searchByProductAndBrand($pdo, $intent['product_type'], $intent['brand']);
            if (!empty($products)) {
                $response['text'] = ucfirst($intent['product_type']) . " " . ucfirst($intent['brand']) . " có sẵn:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $response['text'] = "Xin lỗi, hiện tại không có " . $intent['product_type'] . " " . $intent['brand'] . " nào. Bạn có thể xem các sản phẩm tương tự:";
                $products = searchProducts($pdo, [$intent['product_type']], null, null, [], [$intent['brand']]);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'product_category_search':
            $products = searchByProductAndCategory($pdo, $intent['product_type'], $intent['category_type']);
            if (!empty($products)) {
                $response['text'] = ucfirst($intent['product_type']) . " " . $intent['category_type'] . " có sẵn:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $response['text'] = "Xin lỗi, hiện tại không có " . $intent['product_type'] . " " . $intent['category_type'] . " nào. Bạn có thể xem các sản phẩm tương tự:";
                $products = searchProducts($pdo, [$intent['product_type']], null, null, [], []);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'iphone_price_search':
            $products = searchProductsByBrandAndPrice($pdo, 'iphone', $intent['price_range']);
            if (!empty($products)) {
                $min = number_format($intent['price_range']['min']);
                $max = number_format($intent['price_range']['max']);
                $response['text'] = "iPhone trong khoảng giá dưới {$max}đ:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $max = number_format($intent['price_range']['max']);
                $response['text'] = "Xin lỗi, hiện tại không có iPhone nào dưới {$max}đ. Bạn có thể xem các iPhone khác:";
                $products = searchByCategory($pdo, ['iphone']);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'samsung_price_search':
            $products = searchProductsByBrandAndPrice($pdo, 'samsung', $intent['price_range']);
            if (!empty($products)) {
                $max = number_format($intent['price_range']['max']);
                $response['text'] = "Samsung trong khoảng giá dưới {$max}đ:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $max = number_format($intent['price_range']['max']);
                $response['text'] = "Xin lỗi, hiện tại không có Samsung nào dưới {$max}đ. Bạn có thể xem các Samsung khác:";
                $products = searchByCategory($pdo, ['samsung']);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'xiaomi_price_search':
        case 'laptop_price_search':
        case 'headphone_price_search':
        case 'book_price_search':
            $brand_map = [
                'xiaomi_price_search' => 'xiaomi',
                'laptop_price_search' => 'laptop',
                'headphone_price_search' => 'tai nghe',
                'book_price_search' => 'sách'
            ];
            $brand = $brand_map[$intent['action']];
            $products = searchProductsByBrandAndPrice($pdo, $brand, $intent['price_range']);
            if (!empty($products)) {
                $max = number_format($intent['price_range']['max']);
                $response['text'] = ucfirst($brand) . " trong khoảng giá dưới {$max}đ:";
                $response['products'] = array_slice($products, 0, 6);
            } else {
                $max = number_format($intent['price_range']['max']);
                $response['text'] = "Xin lỗi, hiện tại không có " . $brand . " nào dưới {$max}đ. Bạn có thể xem các sản phẩm " . $brand . " khác:";
                $products = searchByCategory($pdo, [$brand]);
                $response['products'] = array_slice($products, 0, 3);
            }
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'phone_search':
            $products = searchPhoneProducts($pdo);
            $response['text'] = "Đây là các điện thoại có sẵn:";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'laptop_search':
            $products = searchByCategory($pdo, ['laptop gaming', 'laptop văn phòng']);
            $response['text'] = "Đây là các laptop có sẵn:";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'headphone_search':
            $products = searchByCategory($pdo, ['tai nghe']);
            $response['text'] = "Đây là các tai nghe có sẵn:";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'book_search':
            $products = searchByCategory($pdo, ['sách']);
            $response['text'] = "Đây là các sách có sẵn:";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'newest':
            $products = getNewestProducts($pdo, 10);
            $response['text'] = "Đây là các sản phẩm mới nhất của chúng tôi:";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'bestseller':
            $products = getBestsellerProducts($pdo, 10);
            $response['text'] = "Đây là các sản phẩm bán chạy nhất:";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'promotion':
            $products = getPromotionProducts($pdo, 10);
            $response['text'] = "Đây là các sản phẩm đang khuyến mãi:";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'price_filter':
        case 'price_range':
            $products = searchProducts($pdo, [], null, $intent['price_range'], [], []);
            $min = number_format($intent['price_range']['min']);
            $max = number_format($intent['price_range']['max']);
            
            if ($intent['price_range']['max'] >= 999999999) {
                $response['text'] = "Sản phẩm có giá từ {$min}đ trở lên:";
            } elseif ($intent['price_range']['min'] <= 0) {
                $response['text'] = "Sản phẩm có giá dưới {$max}đ:";
            } else {
                $response['text'] = "Sản phẩm trong khoảng giá {$min}đ - {$max}đ:";
            }
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
            
        case 'view_category':
            $products = searchProducts($pdo, [], $intent['category'], null, [], []);
            $response['text'] = "Sản phẩm trong danh mục '{$intent['category']}':";
            $response['products'] = array_slice($products, 0, 6);
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            return $response;
    }
    
    if ($intent['type'] === 'search' || !empty($intent['keywords'])) {
        // Tìm kiếm sản phẩm
        $products = searchProducts($pdo, $intent['keywords'], $intent['category'], $intent['price_range'], $intent['colors'], $intent['brands']);
        
        // Log kết quả tìm kiếm
        error_log("Search found " . count($products) . " products");
        
        if (!empty($products)) {
            $count = count($products);
            
            // Tạo thông điệp phản hồi chi tiết hơn
            $response_message = "Tôi đã tìm thấy {$count} sản phẩm";
            
            if ($intent['category']) {
                $response_message .= " trong danh mục " . $intent['category'];
            }
            
            if ($intent['price_range']) {
                $min = number_format($intent['price_range']['min']);
                $max = number_format($intent['price_range']['max']);
                if ($intent['price_range']['max'] >= 999999999) {
                    $response_message .= " với giá từ {$min}đ trở lên";
                } elseif ($intent['price_range']['min'] <= 0) {
                    $response_message .= " với giá dưới {$max}đ";
                } else {
                    $response_message .= " trong khoảng giá {$min}đ - {$max}đ";
                }
            }
            
            if (!empty($intent['colors'])) {
                $response_message .= " màu " . implode(', ', $intent['colors']);
            }
            
            if (!empty($intent['brands'])) {
                $response_message .= " thương hiệu " . implode(', ', $intent['brands']);
            }
            
            $response_message .= ":";
            
            $response['text'] = $response_message;
            $response['products'] = array_slice($products, 0, 6); // Giới hạn 6 sản phẩm
            
            // Gợi ý tìm kiếm thêm dựa trên kết quả
            $response['suggestions'] = generateSmartSuggestions($pdo, $intent, $products);
            
        } else {
            $search_terms = implode(', ', array_unique($intent['keywords']));
            $response['text'] = "Xin lỗi, tôi không tìm thấy sản phẩm nào phù hợp với từ khóa \"{$search_terms}\". Bạn có thể thử tìm kiếm với từ khóa khác không?";
            
            // Gợi ý dựa trên danh mục phổ biến và từ khóa tương tự
            $response['suggestions'] = generateAlternativeSuggestions($pdo, $intent);
        }
    } else {
        // Phản hồi chung
        $greetings = [
            "Xin chào! Tôi là trợ lý AI của cửa hàng. Tôi có thể giúp bạn tìm kiếm sản phẩm. Hãy cho tôi biết bạn đang tìm gì nhé!",
            "Chào bạn! Tôi có thể giúp bạn tìm sản phẩm mong muốn. Bạn cần tìm gì hôm nay?",
            "Hi! Tôi là chatbot tìm kiếm sản phẩm thông minh. Hãy nói với tôi bạn muốn mua gì nhé!"
        ];
        
        $response['text'] = $greetings[array_rand($greetings)];
        
        // Gợi ý tìm kiếm dựa trên sản phẩm phổ biến
        $response['suggestions'] = [
            "Tìm điện thoại iPhone",
            "Laptop gaming",
            "Sản phẩm giá dưới 500k", 
            "Sản phẩm mới nhất",
            "Tai nghe bluetooth",
            "Sách hay"
        ];
    }
    
    // Lưu lịch sử chat nếu người dùng đã đăng nhập
    if ($user_id) {
        saveChatHistory($pdo, $user_id, $message, $response['text']);
    }
    
    // Log response để debug
    error_log("Generated response: " . json_encode($response['text']));
    
    return $response;
}

function searchProducts($pdo, $keywords, $category = null, $price_range = null, $colors = [], $brands = []) {
    $sql = "
        SELECT DISTINCT p.*, c.name as category_name,
               (SELECT image_url FROM product_images pi 
                WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                LIMIT 1) as product_image,
               COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id), 0) as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
               (SELECT COUNT(*) FROM order_items oi 
                JOIN orders o ON oi.order_id = o.order_id 
                WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.status = 'active'
    ";
    
    $params = [];
    $conditions = [];
    
    // Xử lý đặc biệt cho từ khóa "điện thoại" - tìm trong các danh mục điện thoại
    $phone_categories = ['iPhone', 'Samsung', 'Xiaomi'];
    $is_phone_search = false;
    
    if (!empty($keywords)) {
        foreach ($keywords as $keyword) {
            if (in_array($keyword, ['điện', 'thoại', 'phone', 'mobile', 'smartphone'])) {
                $is_phone_search = true;
                break;
            }
        }
    }
    
    // Tìm kiếm theo từ khóa
    if (!empty($keywords)) {
        $keyword_conditions = [];
        foreach ($keywords as $keyword) {
            $keyword_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        
        // Nếu là tìm kiếm điện thoại, thêm điều kiện tìm trong các danh mục điện thoại
        if ($is_phone_search) {
            $phone_category_conditions = [];
            foreach ($phone_categories as $cat) {
                $phone_category_conditions[] = "c.name LIKE ?";
                $params[] = "%{$cat}%";
            }
            if (!empty($phone_category_conditions)) {
                $keyword_conditions[] = "(" . implode(" OR ", $phone_category_conditions) . ")";
            }
        }
        
        if (!empty($keyword_conditions)) {
            $conditions[] = "(" . implode(" OR ", $keyword_conditions) . ")";
        }
    }
    
    // Lọc theo danh mục cụ thể
    if ($category && $category !== 'điện thoại') {
        $conditions[] = "c.name LIKE ?";
        $params[] = "%{$category}%";
    } elseif ($category === 'điện thoại') {
        // Tìm trong tất cả danh mục điện thoại
        $phone_conditions = [];
        foreach ($phone_categories as $cat) {
            $phone_conditions[] = "c.name LIKE ?";
            $params[] = "%{$cat}%";
        }
        if (!empty($phone_conditions)) {
            $conditions[] = "(" . implode(" OR ", $phone_conditions) . ")";
        }
    }
    
    // Lọc theo khoảng giá
    if ($price_range) {
        if (isset($price_range['min']) && $price_range['min'] > 0) {
            $conditions[] = "p.price >= ?";
            $params[] = $price_range['min'];
        }
        if (isset($price_range['max']) && $price_range['max'] < 999999999) {
            $conditions[] = "p.price <= ?";
            $params[] = $price_range['max'];
        }
    }
    
    // Lọc theo màu sắc
    if (!empty($colors)) {
        $color_conditions = [];
        foreach ($colors as $color) {
            $color_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$color}%";
            $params[] = "%{$color}%";
        }
        if (!empty($color_conditions)) {
            $conditions[] = "(" . implode(" OR ", $color_conditions) . ")";
        }
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY 
        CASE 
            WHEN p.name LIKE ? THEN 1
            WHEN p.description LIKE ? THEN 2
            WHEN c.name LIKE ? THEN 3
            ELSE 4
        END,
        sold_count DESC,
        avg_rating DESC,
        p.created_at DESC
        LIMIT 20
    ";
    
    // Thêm params cho ORDER BY
    $first_keyword = !empty($keywords) ? $keywords[0] : '';
    $params[] = "%{$first_keyword}%";
    $params[] = "%{$first_keyword}%";
    $params[] = "%{$first_keyword}%";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchProductsFulltext($pdo, $keywords, $category = null, $price_range = null, $colors = [], $brands = []) {
    $search_term = implode(' ', $keywords);
    
    $sql = "
        SELECT DISTINCT p.*, c.name as category_name,
               (SELECT image_url FROM product_images pi 
                WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                LIMIT 1) as product_image,
               (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count,
               (SELECT COUNT(*) FROM order_items oi 
                JOIN orders o ON oi.order_id = o.order_id 
                WHERE oi.product_id = p.product_id AND o.status = 'delivered') as sold_count,
               MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.status = 'active'
        AND (
            MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE) > 0
            OR MATCH(c.name) AGAINST(? IN NATURAL LANGUAGE MODE) > 0
        )
    ";
    
    $params = [$search_term, $search_term, $search_term];
    $conditions = [];
    
    // Các điều kiện lọc khác (giống như hàm cũ)
    if ($category) {
        $conditions[] = "c.name LIKE ?";
        $params[] = "%{$category}%";
    }
    
    if ($price_range) {
        if (isset($price_range['min']) && $price_range['min'] > 0) {
            $conditions[] = "p.price >= ?";
            $params[] = $price_range['min'];
        }
        if (isset($price_range['max']) && $price_range['max'] < 999999999) {
            $conditions[] = "p.price <= ?";
            $params[] = $price_range['max'];
        }
    }
    
    if (!empty($colors)) {
        $color_conditions = [];
        foreach ($colors as $color) {
            $color_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$color}%";
            $params[] = "%{$color}%";
        }
        if (!empty($color_conditions)) {
            $conditions[] = "(" . implode(" OR ", $color_conditions) . ")";
        }
    }
    
    if (!empty($brands)) {
        $brand_conditions = [];
        foreach ($brands as $brand) {
            $brand_conditions[] = "p.brand LIKE ?";
            $params[] = "%{$brand}%";
        }
        if (!empty($brand_conditions)) {
            $conditions[] = "(" . implode(" OR ", $brand_conditions) . ")";
        }
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY relevance_score DESC, sold_count DESC, avg_rating DESC LIMIT 20";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback to regular search if fulltext fails
        error_log("Fulltext search failed: " . $e->getMessage());
        return searchProducts($pdo, $keywords, $category, $price_range, $colors, $brands);
    }
}

function getPopularCategories($pdo) {
    $sql = "
        SELECT c.*, COUNT(p.product_id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.category_id = p.category_id
        WHERE p.status = 'active'
        GROUP BY c.category_id
        HAVING product_count > 0
        ORDER BY product_count DESC
        LIMIT 8
    ";
    
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function saveChatHistory($pdo, $user_id, $user_message, $bot_response) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_history (user_id, user_message, bot_response, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $user_message, $bot_response]);
        
        // Cập nhật thống kê
        updateChatbotAnalytics($pdo);
    } catch (Exception $e) {
        // Log error but don't stop execution
        error_log("Failed to save chat history: " . $e->getMessage());
    }
}

function generateSmartSuggestions($pdo, $intent, $products) {
    $suggestions = [];
    
    // Gợi ý dựa trên sản phẩm tìm được
    if (!empty($products)) {
        $categories = array_unique(array_column($products, 'category_name'));
        $brands = array_filter(array_unique(array_column($products, 'brand')));
        
        // Gợi ý danh mục khác
        foreach (array_slice($categories, 0, 2) as $category) {
            if ($category !== $intent['category']) {
                $suggestions[] = "Xem thêm " . strtolower($category);
            }
        }
        
        // Gợi ý thương hiệu
        foreach (array_slice($brands, 0, 1) as $brand) {
            if (!in_array($brand, $intent['brands'] ?? [])) {
                $suggestions[] = "Sản phẩm " . $brand;
            }
        }
        
        // Phân tích giá để gợi ý
        $prices = array_column($products, 'price');
        $min_price = min($prices);
        $max_price = max($prices);
        
        if (!$intent['price_range']) {
            if ($min_price < 500000) {
                $suggestions[] = "Sản phẩm dưới 500k";
            }
            if ($max_price > 1000000) {
                $suggestions[] = "Sản phẩm trên 1 triệu";
            }
        }
    }
    
    // Thêm gợi ý chung
    $general_suggestions = [
        "Sản phẩm mới nhất",
        "Sản phẩm bán chạy", 
        "Sản phẩm giảm giá",
        "Hàng cao cấp"
    ];
    
    // Thêm gợi ý chung nếu chưa đủ
    while (count($suggestions) < 4 && !empty($general_suggestions)) {
        $suggestions[] = array_shift($general_suggestions);
    }
    
    return array_slice(array_unique($suggestions), 0, 5);
}

/**
 * Lấy sản phẩm mới nhất theo danh mục
 */
function getCategoryNewestProducts($pdo, $category) {
    try {
        $sql = "SELECT p.product_id, p.name as product_name, p.price, p.sale_price, p.category_id,
                       pi.image_url, c.name as category_name
                FROM products p
                LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE c.name LIKE :category
                AND p.status = 'active'
                ORDER BY p.created_at DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':category' => '%' . $category . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCategoryNewestProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy sản phẩm bán chạy nhất theo danh mục
 */
function getCategoryBestsellerProducts($pdo, $category) {
    try {
        $sql = "SELECT p.product_id, p.name as product_name, p.price, p.sale_price, p.category_id,
                       pi.image_url, c.name as category_name,
                       COUNT(oi.product_id) as total_sold
                FROM products p
                LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN order_items oi ON p.product_id = oi.product_id
                WHERE c.name LIKE :category
                AND p.status = 'active'
                GROUP BY p.product_id, p.name, p.price, p.sale_price, 
                         p.category_id, pi.image_url, c.name
                ORDER BY total_sold DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':category' => '%' . $category . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCategoryBestsellerProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy sản phẩm khuyến mãi theo danh mục
 */
function getCategoryPromotionProducts($pdo, $category) {
    try {
        $sql = "SELECT p.product_id, p.name as product_name, p.price, p.sale_price, p.category_id,
                       pi.image_url, c.name as category_name
                FROM products p
                LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE c.name LIKE :category
                AND p.status = 'active'
                AND p.sale_price IS NOT NULL 
                AND p.sale_price > 0 
                AND p.sale_price < p.price
                ORDER BY ((p.price - p.sale_price) / p.price) DESC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':category' => '%' . $category . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCategoryPromotionProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Lấy sản phẩm theo danh mục và khoảng giá
 */
function getCategoryProductsByPrice($pdo, $category, $priceRange) {
    try {
        $sql = "SELECT p.product_id, p.name as product_name, p.price, p.sale_price, p.category_id,
                       pi.image_url, c.name as category_name,
                       COALESCE(p.sale_price, p.price) as final_price
                FROM products p
                LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = 1
                LEFT JOIN categories c ON p.category_id = c.category_id
                WHERE c.name LIKE :category
                AND p.status = 'active'
                AND COALESCE(p.sale_price, p.price) >= :min_price
                AND COALESCE(p.sale_price, p.price) <= :max_price
                ORDER BY final_price ASC
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':category' => '%' . $category . '%',
            ':min_price' => $priceRange['min'],
            ':max_price' => $priceRange['max']
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in getCategoryProductsByPrice: " . $e->getMessage());
        return [];
    }
}

function generateAlternativeSuggestions($pdo, $intent) {
    $suggestions = [];
    
    // Lấy danh mục phổ biến
    $categories = getPopularCategories($pdo);
    foreach (array_slice($categories, 0, 3) as $category) {
        $suggestions[] = "Xem " . $category['name'];
    }
    
    // Gợi ý từ khóa tương tự
    if (!empty($intent['keywords'])) {
        $similar_keywords = getSimilarKeywords($pdo, $intent['keywords']);
        foreach (array_slice($similar_keywords, 0, 2) as $keyword) {
            $suggestions[] = "Tìm " . $keyword;
        }
    }
    
    return array_slice($suggestions, 0, 4);
}

function getSimilarKeywords($pdo, $keywords) {
    $similar = [];
    
    try {
        // Tìm sản phẩm có từ khóa tương tự
        $keyword_str = implode(' ', $keywords);
        $stmt = $pdo->prepare("
            SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(p.name, ' ', numbers.n), ' ', -1) as word,
                   COUNT(*) as frequency
            FROM products p
            CROSS JOIN (
                SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
            ) numbers
            WHERE CHAR_LENGTH(p.name) - CHAR_LENGTH(REPLACE(p.name, ' ', '')) >= numbers.n - 1
            AND p.status = 'active'
            AND (p.name LIKE ? OR p.description LIKE ?)
            GROUP BY word
            HAVING LENGTH(word) > 2 AND frequency > 1
            ORDER BY frequency DESC
            LIMIT 10
        ");
        
        $search_term = "%{$keyword_str}%";
        $stmt->execute([$search_term, $search_term]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            if (!in_array(strtolower($result['word']), $keywords)) {
                $similar[] = $result['word'];
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get similar keywords: " . $e->getMessage());
    }
    
    return $similar;
}

function updateChatbotAnalytics($pdo) {
    try {
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_analytics (date, total_messages, total_users, successful_searches)
            VALUES (?, 1, 1, 1)
            ON DUPLICATE KEY UPDATE
                total_messages = total_messages + 1,
                total_users = total_users + 1,
                successful_searches = successful_searches + 1,
                updated_at = NOW()
        ");
        
        $stmt->execute([$today]);
    } catch (Exception $e) {
        error_log("Failed to update analytics: " . $e->getMessage());
    }
}
?>
