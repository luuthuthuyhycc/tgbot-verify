# Military SheerID Verification - PHP Web Application

🎖️ Ứng dụng web PHP để xác minh quân nhân qua SheerID API cho ChatGPT.

## 📁 Cấu trúc thư mục

```
military-web/
├── index.php                  # Trang chủ - Form nhập thông tin
├── verify.php                 # Xử lý xác minh
├── config.php                 # Cấu hình hệ thống
├── includes/
│   ├── NameGenerator.php      # Class tạo thông tin ngẫu nhiên
│   └── SheerIDVerifier.php    # Class gọi SheerID API
├── assets/
│   └── css/
│       └── style.css          # Stylesheet
└── README.md                  # Tài liệu hướng dẫn
```

## 🚀 Cài đặt

### Yêu cầu hệ thống

- PHP 7.4 trở lên
- Extension: `curl`, `json`
- Web server: Apache, Nginx, hoặc PHP built-in server

### Cài đặt nhanh

1. **Clone hoặc copy thư mục:**
```bash
cd /path/to/your/webserver
cp -r military-web /var/www/html/
```

2. **Chạy với PHP built-in server (development):**
```bash
cd military-web
php -S localhost:8080
```

3. **Truy cập:** http://localhost:8080

### Cài đặt với Docker

```bash
cd military-web
docker run -d -p 8080:80 -v $(pwd):/var/www/html php:8.2-apache
```

## 📖 Hướng dẫn sử dụng

### 1. Lấy SheerID URL

1. Truy cập trang đăng ký ưu đãi quân nhân của ChatGPT
2. Chọn xác minh qua SheerID
3. Copy URL từ thanh địa chỉ trình duyệt
4. URL sẽ có dạng: `https://services.sheerid.com/verify/xxx?verificationId=abc123...`

### 2. Điền thông tin

1. Dán URL vào ô "SheerID URL"
2. Chọn trạng thái quân nhân (Veteran, Active Duty, Reservist)
3. Chọn đơn vị quân đội (Army, Navy, Air Force, etc.)
4. Chọn "Tự động tạo thông tin" hoặc nhập thủ công

### 3. Xác minh

1. Click "Bắt đầu xác minh"
2. Đợi hệ thống xử lý (khoảng 5-15 giây)
3. Xem kết quả và liên kết chuyển hướng nếu thành công

## 🔧 API Flow

Quy trình xác minh quân nhân gồm 2 bước:

```
┌─────────────────────────────────────────────────────────────┐
│           BƯỚC 1: collectMilitaryStatus                      │
├─────────────────────────────────────────────────────────────┤
│  POST /rest/v2/verification/{id}/step/collectMilitaryStatus │
│                                                              │
│  Request: { "status": "VETERAN" }                           │
│  Response: { "submissionUrl": "...", ... }                  │
└───────────────────────────┬─────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│     BƯỚC 2: collectInactiveMilitaryPersonalInfo             │
├─────────────────────────────────────────────────────────────┤
│  POST {submissionUrl}                                        │
│                                                              │
│  Request: {                                                  │
│    "firstName": "John",                                      │
│    "lastName": "Smith",                                      │
│    "birthDate": "1985-06-15",                               │
│    "email": "john.smith@gmail.com",                         │
│    "organization": { "id": 4070, "name": "Army" },          │
│    "dischargeDate": "2020-03-01",                           │
│    ...                                                       │
│  }                                                           │
└─────────────────────────────────────────────────────────────┘
```

## 🎖️ Đơn vị quân đội được hỗ trợ

| ID | Tên | Mô tả |
|----|-----|-------|
| 4070 | Army | Lục quân Hoa Kỳ |
| 4073 | Air Force | Không quân Hoa Kỳ |
| 4072 | Navy | Hải quân Hoa Kỳ |
| 4071 | Marine Corps | Thủy quân lục chiến |
| 4074 | Coast Guard | Tuần duyên Hoa Kỳ |
| 4544268 | Space Force | Lực lượng Không gian |

## 📝 Trạng thái quân nhân

- `VETERAN` - Cựu chiến binh (đã xuất ngũ)
- `ACTIVE_DUTY` - Đang tại ngũ
- `RESERVIST` - Lực lượng dự bị

## 🔒 Bảo mật

- Không lưu trữ bất kỳ thông tin cá nhân nào
- Tất cả request được gửi trực tiếp đến SheerID
- Sử dụng HTTPS cho mọi API call

## ⚠️ Lưu ý quan trọng

1. **Chỉ dùng cho mục đích học tập và nghiên cứu**
2. Không sử dụng cho mục đích gian lận hoặc vi phạm điều khoản dịch vụ
3. Người dùng chịu hoàn toàn trách nhiệm về việc sử dụng công cụ này

## 📄 License

MIT License - Xem file [LICENSE](../LICENSE) để biết thêm chi tiết.

## 🔗 Liên kết

- [SheerID Documentation](https://developer.sheerid.com/)
- [Main Project - tgbot-verify](../)
