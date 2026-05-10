-- =====================================================
-- STORE DATABASE SCHEMA & SAMPLE DATA
-- =====================================================
-- Created for kongnguyeen store project
-- Database: store
-- =====================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = utf8mb4_unicode_ci;

-- =====================================================
-- DROP EXISTING TABLES (if any)
-- =====================================================
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `shipments`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `product_attributes`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `feedbacks`;
DROP TABLE IF EXISTS `promotions`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `user_logs`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- =====================================================
-- 1. ROLES TABLE
-- =====================================================
CREATE TABLE `roles` (
  `role_id` INT PRIMARY KEY AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'admin', 'Quản trị viên hệ thống'),
(2, 'user', 'Người dùng thông thường'),
(3, 'vendor', 'Nhà cung cấp');

-- =====================================================
-- 2. USERS TABLE
-- =====================================================
CREATE TABLE `users` (
  `user_id` INT PRIMARY KEY AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20),
  `password` VARCHAR(255) NOT NULL,
  `password_plaintext` VARCHAR(255),
  `profile_image` VARCHAR(255),
  `role_id` INT NOT NULL DEFAULT 2,
  `is_active` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password`, `password_plaintext`, `role_id`, `is_active`) VALUES
(1, 'Admin User', 'admin@store.com', '0123456789', 'admin123', 'admin123', 1, 1),
(2, 'Công Nguyễn', 'congnguyen@gmail.com', '0987654321', 'user123', 'user123', 2, 1),
(3, 'Ngô Văn A', 'ngovana@gmail.com', '0912345678', 'user123', 'user123', 2, 1),
(4, 'Trần Thị B', 'tranthib@gmail.com', '0918765432', 'user123', 'user123', 2, 1),
(5, 'Vendor Test', 'vendor@store.com', '0901234567', 'vendor123', 'vendor123', 3, 1);

-- =====================================================
-- 3. CATEGORIES TABLE
-- =====================================================
CREATE TABLE `categories` (
  `category_id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`category_id`, `name`, `description`, `status`) VALUES
(1, 'Điện thoại', 'Các sản phẩm điện thoại thông minh', 'active'),
(2, 'Máy tính', 'Máy tính xách tay, desktop, tablet', 'active'),
(3, 'Phụ kiện', 'Phụ kiện điện tử, sạc, cáp', 'active'),
(4, 'Âm thanh', 'Loa, tai nghe, micro', 'active'),
(5, 'Đồ gia dụng', 'Thiết bị gia dụng thông minh', 'active');

-- =====================================================
-- 4. SUPPLIERS TABLE
-- =====================================================
CREATE TABLE `suppliers` (
  `supplier_id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `contact_email` VARCHAR(100),
  `contact_phone` VARCHAR(20),
  `address` TEXT,
  `city` VARCHAR(50),
  `country` VARCHAR(50),
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`supplier_id`, `name`, `contact_email`, `contact_phone`, `address`, `city`, `country`, `status`) VALUES
(1, 'Samsung Vietnam', 'samsung@vn.com', '02439746000', '123 Đường Samsung, Hà Nội', 'Hà Nội', 'Việt Nam', 'active'),
(2, 'Apple Vietnam', 'apple@vn.com', '0243778886', '456 Đường Apple, Hà Nội', 'Hà Nội', 'Việt Nam', 'active'),
(3, 'Sony Vietnam', 'sony@vn.com', '0243898888', '789 Đường Sony, TP.HCM', 'TP.HCM', 'Việt Nam', 'active'),
(4, 'LG Vietnam', 'lg@vn.com', '0243899999', '321 Đường LG, Đà Nẵng', 'Đà Nẵng', 'Việt Nam', 'active'),
(5, 'Dell Vietnam', 'dell@vn.com', '0243877777', '654 Đường Dell, TP.HCM', 'TP.HCM', 'Việt Nam', 'active');

-- =====================================================
-- 5. PRODUCTS TABLE
-- =====================================================
CREATE TABLE `products` (
  `product_id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(12, 2) NOT NULL,
  `discount_price` DECIMAL(12, 2),
  `stock` INT DEFAULT 0,
  `category_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `status` ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `discount_price`, `stock`, `category_id`, `supplier_id`, `status`) VALUES
(1, 'Samsung Galaxy S24', 'Smartphone Samsung cao cấp, màn hình 6.2 inch, camera 50MP', 19990000, 18990000, 50, 1, 1, 'active'),
(2, 'iPhone 15 Pro', 'Điện thoại Apple, chip A17 Pro, màn hình 6.1 inch', 29990000, 28990000, 30, 1, 2, 'active'),
(3, 'MacBook Pro M3', 'Laptop chuyên nghiệp, CPU M3, SSD 512GB', 35990000, NULL, 20, 2, 2, 'active'),
(4, 'Dell XPS 15', 'Laptop mạnh mẽ, Intel i7, RTX 4060', 25990000, 24990000, 15, 2, 5, 'active'),
(5, 'Sony WH-1000XM5', 'Tai nghe chống ồn tuyệt vời, pin 30 giờ', 8990000, 8490000, 100, 4, 3, 'active'),
(6, 'USB-C Cable', 'Cáp sạc nhanh, hỗ trợ 65W', 250000, NULL, 500, 3, 1, 'active'),
(7, 'Smart Watch', 'Đồng hồ thông minh, theo dõi sức khỏe', 4990000, 4490000, 80, 3, 4, 'active'),
(8, 'Smart Speaker', 'Loa thông minh với AI trợ lý', 2990000, NULL, 60, 4, 4, 'active'),
(9, 'Robot Hút Bụi', 'Máy hút bụi thông minh tự động', 12990000, 11990000, 25, 5, 4, 'active'),
(10, 'Webcam 4K', 'Webcam độ phân giải cao, auto focus', 1990000, NULL, 40, 3, 3, 'active');

-- =====================================================
-- 6. PRODUCT IMAGES TABLE
-- =====================================================
CREATE TABLE `product_images` (
  `image_id` INT PRIMARY KEY AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `product_images` (`product_id`, `image_url`, `is_primary`) VALUES
(1, 'assets/uploads/galaxy-s24.jpg', 1),
(2, 'assets/uploads/iphone15-pro.jpg', 1),
(3, 'assets/uploads/macbook-pro-m3.jpg', 1),
(4, 'assets/uploads/dell-xps-15.jpg', 1),
(5, 'assets/uploads/sony-wh-1000xm5.jpg', 1),
(6, 'assets/uploads/usb-c-cable.jpg', 1),
(7, 'assets/uploads/smart-watch.jpg', 1),
(8, 'assets/uploads/smart-speaker.jpg', 1),
(9, 'assets/uploads/robot-vacuum.jpg', 1),
(10, 'assets/uploads/webcam-4k.jpg', 1);

-- =====================================================
-- 7. PRODUCT ATTRIBUTES TABLE
-- =====================================================
CREATE TABLE `product_attributes` (
  `attribute_id` INT PRIMARY KEY AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `attribute_name` VARCHAR(100) NOT NULL,
  `attribute_value` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `product_attributes` (`product_id`, `attribute_name`, `attribute_value`) VALUES
(1, 'Màn hình', '6.2 inch AMOLED'),
(1, 'CPU', 'Snapdragon 8 Gen 3'),
(1, 'RAM', '12GB'),
(1, 'Bộ nhớ', '256GB'),
(2, 'Màn hình', '6.1 inch Super Retina'),
(2, 'CPU', 'A17 Pro'),
(2, 'RAM', '8GB'),
(3, 'CPU', 'Apple M3'),
(3, 'RAM', '8GB'),
(3, 'Màn hình', '14 inch Liquid Retina Pro'),
(4, 'CPU', 'Intel Core i7'),
(4, 'GPU', 'NVIDIA RTX 4060'),
(4, 'RAM', '16GB'),
(5, 'Tần số', '20-20000Hz'),
(5, 'Thời lượng pin', '30 giờ'),
(6, 'Chuẩn', 'USB-C'),
(6, 'Công suất', '65W'),
(7, 'Kết nối', 'Bluetooth 5.2'),
(7, 'Pin', '7-10 ngày'),
(8, 'AI Assistant', 'Google Assistant / Alexa'),
(8, 'Công suất', '10W'),
(9, 'Diện tích làm sạch', 'Tối đa 150m2'),
(9, 'Thời lượng pin', '120 phút'),
(10, 'Độ phân giải', '4K (3840x2160)'),
(10, 'Tốc độ khung hình', '30fps');

-- =====================================================
-- 8. ADDRESSES TABLE
-- =====================================================
CREATE TABLE `addresses` (
  `address_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `full_name` VARCHAR(100),
  `phone` VARCHAR(20),
  `province_city` VARCHAR(100),
  `district` VARCHAR(100),
  `ward` VARCHAR(100),
  `address_detail` TEXT,
  `is_default` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `addresses` (`address_id`, `user_id`, `full_name`, `phone`, `province_city`, `district`, `ward`, `address_detail`, `is_default`) VALUES
(1, 2, 'Công Nguyễn', '0987654321', 'Hà Nội', 'Hoàn Kiếm', 'Tây Hồ', '123 Đường Láng', 1),
(2, 2, 'Công Nguyễn', '0987654321', 'TP.HCM', 'Quận 1', 'Bến Nghé', '456 Nguyễn Huệ', 0),
(3, 3, 'Ngô Văn A', '0912345678', 'Hà Nội', 'Ba Đình', 'Phúc Tân', '789 Kim Mã', 1),
(4, 4, 'Trần Thị B', '0918765432', 'Đà Nẵng', 'Hải Châu', 'Thạch Thang', '321 Trần Phú', 1);

-- =====================================================
-- 9. CARTS TABLE
-- =====================================================
CREATE TABLE `carts` (
  `cart_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `carts` (`cart_id`, `user_id`) VALUES
(1, 2),
(2, 3),
(3, 4);

-- =====================================================
-- 10. CART ITEMS TABLE
-- =====================================================
CREATE TABLE `cart_items` (
  `cart_item_id` INT PRIMARY KEY AUTO_INCREMENT,
  `cart_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cart_items` (`cart_id`, `product_id`, `quantity`) VALUES
(1, 1, 1),
(1, 6, 2),
(2, 5, 1),
(3, 7, 1);

-- =====================================================
-- 11. ORDERS TABLE
-- =====================================================
CREATE TABLE `orders` (
  `order_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `address_id` INT NOT NULL,
  `total_amount` DECIMAL(12, 2) NOT NULL,
  `discount_amount` DECIMAL(12, 2) DEFAULT 0,
  `final_amount` DECIMAL(12, 2) NOT NULL,
  `payment_method` VARCHAR(50),
  `shipping_method` VARCHAR(50),
  `status` ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'paid') DEFAULT 'pending',
  `momo_order_id` VARCHAR(100),
  `momo_trans_id` VARCHAR(100),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `total_amount`, `discount_amount`, `final_amount`, `payment_method`, `shipping_method`, `status`, `created_at`) VALUES
(1, 2, 1, 19990000, 1000000, 18990000, 'vnpay', 'standard', 'delivered', '2025-05-05 10:30:00'),
(2, 3, 3, 8990000, 500000, 8490000, 'momo', 'fast', 'shipped', '2025-05-07 14:20:00'),
(3, 4, 4, 12990000, 1000000, 11990000, 'bank_transfer', 'standard', 'processing', '2025-05-09 11:15:00');

-- =====================================================
-- 12. ORDER ITEMS TABLE
-- =====================================================
CREATE TABLE `order_items` (
  `order_item_id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `price` DECIMAL(12, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 1, 18990000),
(2, 5, 1, 8490000),
(3, 9, 1, 11990000);

-- =====================================================
-- 13. ORDER STATUS HISTORY TABLE
-- =====================================================
CREATE TABLE `order_status_history` (
  `history_id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `old_status` VARCHAR(50),
  `new_status` VARCHAR(50),
  `changed_by` INT,
  `notes` TEXT,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_status_history` (`order_id`, `old_status`, `new_status`, `changed_by`, `notes`) VALUES
(1, 'pending', 'processing', 1, 'Đơn hàng được xác nhận'),
(1, 'processing', 'shipped', 1, 'Đơn hàng đã được gửi đi'),
(1, 'shipped', 'delivered', 1, 'Đơn hàng đã giao thành công'),
(2, 'pending', 'processing', 1, 'Đơn hàng đang xử lý'),
(2, 'processing', 'shipped', 1, 'Đơn hàng đang vận chuyển');

-- =====================================================
-- 14. REVIEWS TABLE
-- =====================================================
CREATE TABLE `reviews` (
  `review_id` INT PRIMARY KEY AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `rating` INT CHECK (rating >= 1 AND rating <= 5),
  `title` VARCHAR(200),
  `comment` TEXT,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `reviews` (`product_id`, `user_id`, `rating`, `title`, `comment`, `status`) VALUES
(1, 2, 5, 'Sản phẩm tuyệt vời', 'Samsung Galaxy S24 là một chiếc điện thoại tuyệt vời, hiệu suất cao và camera rất đẹp', 'approved'),
(1, 3, 4, 'Rất hài lòng', 'Sản phẩm đạt kỳ vọng, giao hàng nhanh', 'approved'),
(5, 4, 5, 'Tai nghe tốt nhất', 'Chất lượng âm thanh tuyệt vời, chống ồn hiệu quả', 'approved'),
(9, 2, 4, 'Máy hút bụi thông minh', 'Hoạt động tốt, pin đủ lâu, giá hợp lý', 'approved');

-- =====================================================
-- 15. SHIPMENTS TABLE
-- =====================================================
CREATE TABLE `shipments` (
  `shipment_id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `carrier` VARCHAR(50),
  `tracking_number` VARCHAR(100),
  `shipped_date` DATETIME,
  `estimated_delivery` DATETIME,
  `actual_delivery` DATETIME,
  `status` ENUM('pending', 'in_transit', 'delivered', 'failed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shipments` (`shipment_id`, `order_id`, `carrier`, `tracking_number`, `shipped_date`, `estimated_delivery`, `actual_delivery`, `status`) VALUES
(1, 1, 'GHN', 'GHN123456789', '2025-05-06 08:00:00', '2025-05-08 18:00:00', '2025-05-08 16:30:00', 'delivered'),
(2, 2, 'Giao hàng nhanh', 'GHN987654321', '2025-05-08 10:00:00', '2025-05-10 18:00:00', NULL, 'in_transit'),
(3, 3, 'Viettel Post', 'VTP111222333', '2025-05-10 09:00:00', '2025-05-12 18:00:00', NULL, 'pending');

-- =====================================================
-- 16. PROMOTIONS TABLE
-- =====================================================
CREATE TABLE `promotions` (
  `promotion_id` INT PRIMARY KEY AUTO_INCREMENT,
  `promotion_code` VARCHAR(50) NOT NULL UNIQUE,
  `discount_type` ENUM('fixed', 'percentage') DEFAULT 'percentage',
  `discount_value` DECIMAL(10, 2) NOT NULL,
  `max_discount` DECIMAL(12, 2),
  `min_order_amount` DECIMAL(12, 2) DEFAULT 0,
  `max_uses` INT,
  `current_uses` INT DEFAULT 0,
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `status` ENUM('active', 'inactive', 'expired') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `promotions` (`promotion_code`, `discount_type`, `discount_value`, `max_discount`, `min_order_amount`, `max_uses`, `start_date`, `end_date`, `status`) VALUES
('SUMMER2024', 'percentage', 20, 5000000, 5000000, 100, '2025-05-01 00:00:00', '2025-06-30 23:59:59', 'active'),
('WELCOME10', 'percentage', 10, 2000000, 1000000, 50, '2025-05-01 00:00:00', '2025-12-31 23:59:59', 'active'),
('FREE100K', 'fixed', 100000, NULL, 2000000, 25, '2025-05-01 00:00:00', '2025-05-31 23:59:59', 'active');

-- =====================================================
-- 17. FEEDBACKS TABLE
-- =====================================================
CREATE TABLE `feedbacks` (
  `feedback_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `subject` VARCHAR(200),
  `message` TEXT NOT NULL,
  `status` ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
  `reply` TEXT,
  `replied_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`replied_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `feedbacks` (`user_id`, `subject`, `message`, `status`, `reply`, `replied_by`) VALUES
(2, 'Hỗ trợ đặt hàng', 'Tôi gặp vấn đề khi thanh toán', 'replied', 'Xin lỗi bạn, chúng tôi sẽ hỗ trợ ngay. Vui lòng liên hệ chúng tôi.', 1),
(3, 'Chất lượng sản phẩm', 'Sản phẩm tôi nhận bị lỗi', 'replied', 'Chúng tôi sẽ gửi sản phẩm mới cho bạn ngay.', 1),
(4, 'Giao hàng chậm', 'Tại sao giao hàng lâu quá?', 'read', NULL, NULL);

-- =====================================================
-- 18. PASSWORD RESETS TABLE
-- =====================================================
CREATE TABLE `password_resets` (
  `reset_id` INT PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 19. USER LOGS TABLE
-- =====================================================
CREATE TABLE `user_logs` (
  `log_id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `action` VARCHAR(50),
  `ip_address` VARCHAR(50),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CREATE INDEXES FOR BETTER PERFORMANCE
-- =====================================================
CREATE INDEX `idx_users_email` ON `users`(`email`);
CREATE INDEX `idx_users_role` ON `users`(`role_id`);
CREATE INDEX `idx_products_category` ON `products`(`category_id`);
CREATE INDEX `idx_products_supplier` ON `products`(`supplier_id`);
CREATE INDEX `idx_orders_user` ON `orders`(`user_id`);
CREATE INDEX `idx_orders_status` ON `orders`(`status`);
CREATE INDEX `idx_order_items_order` ON `order_items`(`order_id`);
CREATE INDEX `idx_reviews_product` ON `reviews`(`product_id`);
CREATE INDEX `idx_reviews_user` ON `reviews`(`user_id`);
CREATE INDEX `idx_cart_user` ON `carts`(`user_id`);
CREATE INDEX `idx_cart_items_cart` ON `cart_items`(`cart_id`);
CREATE INDEX `idx_addresses_user` ON `addresses`(`user_id`);
CREATE INDEX `idx_feedbacks_user` ON `feedbacks`(`user_id`);
CREATE INDEX `idx_shipments_order` ON `shipments`(`order_id`);
CREATE INDEX `idx_promotions_code` ON `promotions`(`promotion_code`);

-- =====================================================
-- DATABASE SETUP COMPLETE
-- =====================================================
-- Total Tables: 19
-- Total Records (Sample Data): 100+
-- =====================================================
