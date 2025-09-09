<?php
// Cài đặt bảo mật cho development
// CẢNH BÁO: CHỈ SỬ DỤNG CHO TESTING, KHÔNG BAO GIỜ DÙNG TRONG PRODUCTION!

// Cài đặt cách lưu mật khẩu
define('USE_PLAIN_PASSWORD', true); // true = lưu text, false = hash (khuyến khích)

// Nếu bạn muốn an toàn hơn, đặt false
// define('USE_PLAIN_PASSWORD', false);

/*
CẢNH BÁO BẢO MẬT:
- USE_PLAIN_PASSWORD = true: Mật khẩu lưu dạng text (CỰC KỲ KHÔNG AN TOÀN)
- USE_PLAIN_PASSWORD = false: Mật khẩu được hash (AN TOÀN)

CHỈ SỬ DỤNG true CHO TESTING/DEVELOPMENT!
PRODUCTION PHẢI ĐẶT false!
*/
