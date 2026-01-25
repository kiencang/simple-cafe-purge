# ☕ Simple Cafe Purge

**Simple Cafe Purge** là một plugin WordPress siêu nhẹ (lightweight), được thiết kế để giải quyết vấn đề đồng bộ bộ nhớ đệm (cache) giữa website và Cloudflare. Plugin này đặc biệt tối ưu cho các hệ thống Blog và Trang tin tức, nơi nội dung cần được cập nhật tới người đọc ngay khi vừa xuất bản.

Đây là công cụ hỗ trợ hoàn hảo cho công cụ tối ưu cache và bảo mật [rtd-cafe](https://rtd-cafe.wpsila.com/). Với plugin này bạn sẽ có 2 cải tiến quan trọng cho rtd-cafe:

- Bạn có thể để cache HTML mặc định từ 8 tiếng lên 7 ngày hoặc thậm chí lên đến 30 ngày trên Cloudflare.
- Các bài viết mà bạn thêm sửa xóa sẽ được cập nhật hiển thị ngay lập tức cho người dùng cuối thay vì mặc định phải đợi 8 tiếng để hết thời gian cache.

---

## ✨ Tính năng nổi bật

* **Tự động hóa thông minh (Auto-Purge):** Tự động nhận diện và xóa cache các đường dẫn liên quan ngay khi bạn Đăng mới, Cập nhật hoặc Xóa bài viết.
    * *Các URL được hỗ trợ:* Bài viết gốc, Trang chủ, RSS Feed, Chuyên mục (Categories) và Thẻ (Tags).
* **Xử lý bất đồng bộ (Non-blocking):** Gửi yêu cầu xóa cache ngầm bên dưới. Website của bạn sẽ không bị chậm hay treo khi đang lưu bài viết.
* **Nút "Purge Everything" thủ công:** Xóa sạch toàn bộ cache của toàn bộ website chỉ với một cú click khi bạn có những thay đổi lớn về giao diện.
* **An toàn & Bảo mật:**
    * Sử dụng Cloudflare API Token (chuẩn bảo mật mới nhất).
    * Kiểm tra quyền truy cập và chống giả mạo request (Nonce verification).
    * Làm sạch dữ liệu đầu vào và đầu ra (Sanitization & Escaping).
* **Giao diện Native:** Tận dụng giao diện mặc định của WordPress, không làm nặng trang quản trị.

---

## 🛠️ Hướng dẫn thiết lập

### 1. Lấy thông tin từ Cloudflare
Để plugin hoạt động, bạn cần chuẩn bị 2 thông tin từ trang quản trị Cloudflare:
* **Zone ID:** Tìm thấy tại tab **Overview** của tên miền (nằm ở cột bên phải).
* **API Token:** 1. Truy cập [My Profile > API Tokens](https://dash.cloudflare.com/profile/api-tokens).
    2. Nhấn **Create Token** -> Sử dụng Template **Edit Zone DNS** (hoặc tạo Custom).
    3. Đảm bảo quyền (Permissions) là: `Zone` > `Cache Purge` > `Purge`.
    4. Chỉ định đúng tên miền tại mục **Zone Resources**.

### 2. Cấu hình Plugin
1. Vào menu **Cài đặt (Settings)** -> **Simple Cafe Purge**.
2. Nhập **Zone ID** và **API Token** đã lấy ở bước trên.
3. Nhấn **Lưu cấu hình**.

---



## ⚠️ Lưu ý sử dụng

* **Đối tượng:** Plugin được thiết kế tối ưu nhất cho `post_type = 'post'` (tức là các dạng bài của blog, trang tin tức WordPress). 
* **Giới hạn:** Mỗi lần cập nhật, plugin sẽ tự động lọc ra tối đa **50 URL** quan trọng nhất để gửi lên Cloudflare nhằm tránh quá tải và đảm bảo tốc độ phản hồi nhanh nhất.
* **Gỡ cài đặt:** Khi bạn xóa plugin, toàn bộ cấu hình API sẽ được tự động dọn dẹp khỏi cơ sở dữ liệu để giữ website luôn sạch sẽ.

---

## 📄 Thông tin dự án

* **Tác giả:** Nguyễn Đức Anh (wpsila)
* **Website:** [wpsila.com](https://wpsila.com)
* **Phiên bản:** 1.12
* **Giấy phép:** GPLv2.

---
*Cảm ơn bạn đã sử dụng giải pháp từ wpsila! Nếu thấy hữu ích, hãy giới thiệu cho bạn bè cùng sử dụng.*
