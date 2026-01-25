<?php
/**
 * Plugin Name: Simple Cafe Purge
 * Description: Giải pháp xóa cache (cho Cloudflare) siêu nhẹ. Tự động xóa khi cập nhật nội dung và hỗ trợ nút "Purge Everything"
 * Version: 1.12.2
 * Author: wpsila - Nguyễn Đức Anh
 * Author URI: https://wpsila.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// 1. GIAO DIỆN ADMIN & XỬ LÝ FORM
// =========================================================================

// Gắn vào menu ở trang Admin
add_action('admin_menu', 'wpsila_scfp_add_admin_menu');
function wpsila_scfp_add_admin_menu() {
    add_options_page(
        'Simple Cafe Purge', 
        'Simple Cafe Purge', 
        'manage_options', 
        'simple-cafe-purge', 
        'wpsila_scfp_options_page'
    );
}

// Các class notice notice-error is-dismissible,... kế thừa sẵn từ các class mặc định của nhân WordPress
function wpsila_scfp_options_page() {
    // --- XỬ LÝ LƯU CẤU HÌNH ---
	// Kiểm tra xem người dùng có nhấn nút Lưu cấu hình hay không? // $_POST['wpsila_scfp_save_settings']
	// Kiểm tra bảo mật check_admin_referer('wpsila_scfp_save_settings_verify')
    if (isset($_POST['wpsila_scfp_save_settings']) && check_admin_referer('wpsila_scfp_save_settings_verify')) {
        $input_zone_id = sanitize_text_field($_POST['wpsila_scfp_zone_id']); // đảm bảo dữ liệu lưu vào database là văn bản thuần
        $input_api_token = sanitize_text_field($_POST['wpsila_scfp_api_token']); // tương tự nhưng là cho API

        if (empty($input_zone_id) || empty($input_api_token)) { // Một trong hai trường rỗng thì không lưu và báo cho người dùng
            echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>Lỗi:</strong> 2 trường Zone ID và API Token đều không được để trống!</p></div>';
        } else {
            update_option('wpsila_scfp_zone_id', $input_zone_id); // Hàm chuẩn để lưu data vào bảng wp_options
            update_option('wpsila_scfp_api_token', $input_api_token);
            echo '<div class="notice notice-success is-dismissible"><p>✅ Đã lưu cấu hình thành công!</p></div>'; // Thông báo thành công
        }
    }

    // --- XỬ LÝ PURGE EVERYTHING ---
	// Kiểm tra nút bấm và bảo mật
    if (isset($_POST['wpsila_scfp_purge_everything']) && check_admin_referer('wpsila_scfp_purge_all_verify')) {
        $zone_id = get_option('wpsila_scfp_zone_id'); // Lấy thông tin Zone ID từ bảng dữ liệu
        $api_token = get_option('wpsila_scfp_api_token'); // Tương tự nhưng là cho API
        
        if ($zone_id && $api_token) { // Kiểm tra sự tồn tại trước khi thực thi
            $result = wpsila_scfp_execute_purge_everything($zone_id, $api_token); // Gọi hàm xóa toàn bộ cache của trang
            if ($result['success']) {
				// Thông báo thành công
                echo '<div class="notice notice-success is-dismissible"><p>🚀 <strong>Thành công:</strong> Đã xóa toàn bộ cache của website trên Cloudflare.</p></div>';
            } else {
				// Thông báo lỗi. Hàm esc_html dùng để làm sạch thông báo trước khi đưa ra màn hình.
                echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>Lỗi:</strong> ' . esc_html($result['message']) . '</p></div>';
            }
        } else {
			// Thông báo chưa nhập thông tin cần thiết
            echo '<div class="notice notice-warning is-dismissible"><p>⚠️ Vui lòng nhập Zone ID và API Token trước.</p></div>';
        }
    }

    $zone_id = get_option('wpsila_scfp_zone_id', '');
    $api_token = get_option('wpsila_scfp_api_token', '');
    ?>
    
    <style>
        /* Card chứa nội dung: Mô phỏng box của WordPress */
        .wpsila-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            max-width: 800px;
            margin-bottom: 20px; /* Tạo khoảng cách giữa các box */
        }

        /* Box xóa cache có viền đỏ bên trái */
        .wpsila-card.is-danger {
            border-left: 4px solid #d63638;
        }

        /* Input full width */
        .wpsila-full-width {
            width: 100%;
        }

        /* Wrapper cho ô nhập mật khẩu để định vị icon con mắt */
        .wpsila-pwd-wrapper {
            position: relative; 
            max-width: 100%;
        }

        /* Input mật khẩu cần padding bên phải để không đè lên icon */
        .wpsila-pwd-input {
            width: 100%; 
            padding-right: 40px;
        }

        /* Icon con mắt */
        .wpsila-eye-icon {
            position: absolute; 
            right: 10px; 
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer; 
            color: #50575e;
        }

        /* Nút Xóa cache đặc biệt */
        .wpsila-btn-purge {
            font-weight: bold !important; 
            border: 1px solid #d63638 !important; 
            padding: 5px 15px !important; 
            background: #fbeaea !important;
            color: #d63638 !important;
            transition: all 0.2s;
        }
        .wpsila-btn-purge:hover {
            background: #d63638 !important;
            color: #fff !important;
        }

        /* Dòng mẹo nhỏ */
        .wpsila-hint {
            margin-top: 15px;
            font-size: 13px;
            color: #646970;
            font-style: italic;
            line-height: 1.5;
            border-top: 1px dashed #ddd;
            padding-top: 10px;
        }
		
		.wpsila-hint strong {
			color: #d63638; /* Làm nổi bật chữ Mẹo bằng màu đỏ nhạt */
		}
    </style>

    <div class="wrap">
        <h1>☕ Simple Cafe Purge</h1>
        <p>Plugin siêu nhẹ giúp đồng bộ cache giữa WordPress và hệ thống của Cloudflare.</p>
        <hr>
        
        <div class="wpsila-card">
            <h2>🛠️ Cấu hình API</h2>
            <form method="post" action="">
                <?php wp_nonce_field('wpsila_scfp_save_settings_verify'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Zone ID <span style="color:red">*</span></th>
                        <td>
                            <input type="text" name="wpsila_scfp_zone_id" value="<?php echo esc_attr($zone_id); ?>" class="regular-text wpsila-full-width" placeholder="Ví dụ: a1b2c3d4..." required />
                            <p class="description">Tìm thấy ở trang Overview tên miền (cột bên phải).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">API Token <span style="color:red">*</span></th>
                        <td>
                            <div class="wpsila-pwd-wrapper">
                                <input type="password" id="wpsila_scfp_api_token" name="wpsila_scfp_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text wpsila-pwd-input" required autocomplete="new-password" />
                                <span id="wpsila_toggle_token" class="dashicons dashicons-visibility wpsila-eye-icon" title="Hiện/Ẩn Token"></span>
                            </div>
                            <p class="description">Yêu cầu quyền: <strong>Zone > Cache Purge > Purge</strong> (hãy chắc chắn bạn chỉ định đúng tên miền).</p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="wpsila_scfp_save_settings" class="button button-primary" value="Lưu cấu hình" /></p>
            </form>
        </div>

        <div class="wpsila-card is-danger">
            <h2>🔥 Xóa toàn bộ Cache</h2>
            <form method="post" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa TOÀN BỘ cache không?');">
                <?php wp_nonce_field('wpsila_scfp_purge_all_verify'); ?>
                
                <input type="submit" name="wpsila_scfp_purge_everything" class="button wpsila-btn-purge" value="Xóa Sạch Cache Ngay Lập Tức" />
            </form>
            
            <p class="wpsila-hint">
                💡 <strong>Mẹo:</strong> Bạn có thể nhấn nút này để kiểm tra cấu hình API đã chính xác chưa. Nếu thành công nghĩa là mọi thứ đã thông suốt!
            </p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggleBtn = document.getElementById('wpsila_toggle_token');
            var inputField = document.getElementById('wpsila_scfp_api_token');
            
            if (toggleBtn && inputField) {
                toggleBtn.addEventListener('click', function() {
                    if (inputField.type === 'password') {
                        inputField.type = 'text';
                        toggleBtn.classList.remove('dashicons-visibility');
                        toggleBtn.classList.add('dashicons-hidden');
                    } else {
                        inputField.type = 'password';
                        toggleBtn.classList.remove('dashicons-hidden');
                        toggleBtn.classList.add('dashicons-visibility');
                    }
                });
            }
        });
    </script>
    <?php
}

// =========================================================================
// 2. LOGIC TỰ ĐỘNG (AUTO PURGE)
// =========================================================================

// transition_post_status là sự kiện cần lắng nghe (cập nhật bài viết, xuất bản bài viết, xóa bài viết)
// Khi sự kiện trên xảy ra (transition_post_status) thì gọi hàm này wpsila_scfp_handle_post_transition
// 10 là mức độ ưu tiên trung bình khi xử lý, nếu có nhiều plugin cùng muốn thực hiện
// 3 là chỉ tham số đầu vào cần thiết $new_status, $old_status, $post (3 tham số có ý nghĩa và thứ tự cố định của core theo thứ tự)
add_action('transition_post_status', 'wpsila_scfp_handle_post_transition', 10, 3);

// Hàm này được thực thi khi nó thấy post, page thay đổi trạng thái (cập nhật bài viết, xuất bản bài viết, xóa bài viết)
// Nó chọn lọc chính xác các đường dẫn để xóa cache thay vì xóa toàn bộ cache
function wpsila_scfp_handle_post_transition($new_status, $old_status, $post) {
    // 1. Chặn các trường hợp không cần thiết
	// Lưu nháp tự động thì không cần kích hoạt, tránh xóa cache liên tục
    if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
	
	// Chuyển từ nháp sang chờ duyệt, tóm lại là chưa xuất bản thì cũng không cần xóa cache
    if ($new_status !== 'publish' && $old_status !== 'publish') return;
	
	// Lấy thông tin Zone ID và API Token
    $zone_id = get_option('wpsila_scfp_zone_id');
    $api_token = get_option('wpsila_scfp_api_token');
    
	// Kiểm tra rỗng
    if (empty($zone_id) || empty($api_token)) return;
	
	// Mảng các URL cần xóa cache
    $urls_to_purge = [];
    
    // --- ƯU TIÊN 1: URL BÀI VIẾT (Quan trọng nhất) ---
    // Luôn add đầu tiên để đảm bảo không bao giờ bị cắt
    $permalink = get_permalink($post->ID);
	
	// Đưa link bài viết vào mảng
    if ($permalink) $urls_to_purge[] = $permalink;
	
	// Đưa các trang liên quan mà khả năng cao sẽ thay đổi khi bài viết thay đổi
	// Chỉ phải làm điều này nếu định dạng của nó là post
    if ($post->post_type === 'post') {
        // --- ƯU TIÊN 2: TRANG CHỦ ---
        $urls_to_purge[] = home_url('/');
        $urls_to_purge[] = home_url(); 
        
        // --- ƯU TIÊN 3: FEED (RSS) ---
        // Nên thêm cái này vì Google News hay quét feed
        $urls_to_purge[] = get_bloginfo('rss2_url');
        
        // --- ƯU TIÊN 4: CATEGORY ---
        $categories = get_the_category($post->ID);
        if ($categories) { // Lấy mảng thư mục của bài
            foreach ($categories as $category) {
                $link = get_category_link($category->term_id); // Lấy link các thư mục
                if ($link && !is_wp_error($link)) $urls_to_purge[] = $link; // Đưa vào mảng purge
            }
        }

        // --- ƯU TIÊN 5: TAGS (Ít quan trọng nhất - Sẽ bị cắt đầu tiên nếu quá nhiều) ---
        $tags = get_the_tags($post->ID); // Lấy mảng các thẻ tag
        if ($tags) {
            foreach ($tags as $tag) {
                $link = get_tag_link($tag->term_id); // Lấy link các tag
                if ($link && !is_wp_error($link)) $urls_to_purge[] = $link; // Đưa vào mảng purge
            }
        }
    }
    
    // Loại bỏ URL trùng lặp
    $urls_to_purge = array_unique($urls_to_purge);
    
    // [QUAN TRỌNG] Reset lại key của mảng để hàm slice chạy đúng index 0,1,2...
    // array_values rất quan trọng sau khi dùng array_unique
	// Để đảm bảo mảng đạt chuẩn JSON
    $urls_to_purge = array_values($urls_to_purge);

    // [BỔ SUNG] Giới hạn 100 URL để tránh lỗi API 413 của Cloudflare (họ cho gửi tối đa 100 link)
	// Nhưng cũng chỉ cần lấy 50 đã là rất nhiều
    if (count($urls_to_purge) > 50) {
        $urls_to_purge = array_slice($urls_to_purge, 0, 50); // Cắt bớt chỉ lấy 50 link
    }

    if (!empty($urls_to_purge)) { // Nếu không rỗng thì gọi hàm xóa cache các link
        wpsila_scfp_send_purge_request($zone_id, $api_token, $urls_to_purge); // Hàm này được định nghĩa ngay bên dưới
    }
}

// =========================================================================
// 3. CÁC HÀM API GỬI LÊN Cloudflare
// =========================================================================

// Hàm xóa cache các link
// 'blocking' => false, nghĩa là không cần đợi kết quả phản hồi từ Cloudflare.
// Mục đích là để tránh để user phải đợi phản hồi lâu. Lệnh xóa cache diễn ra ngầm bên dưới.
// https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache là endpint chuẩn để xóa cache.
// $zone_id là để biết xóa cache của tên miền nào.
function wpsila_scfp_send_purge_request($zone_id, $token, $urls) {
    $endpoint = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    $body = json_encode(['files' => array_values($urls)]); // Đóng gói thành chuỗi JSON để gửi danh sách các URL
	// wp_remote_post là hàm của WP để gửi yêu cầu đến Cloudflare
    wp_remote_post($endpoint, [
        'body' => $body, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'method' => 'POST', 'blocking' => false, 'timeout' => 10,
    ]);
}

// Hàm xóa toàn bộ cache của website
// 'blocking' => true, nghĩa là phải đợi kết quả phản hồi về để biết có xóa thành công hay chưa.
function wpsila_scfp_execute_purge_everything($zone_id, $token) {
    $endpoint = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    $body = json_encode(['purge_everything' => true]);
    $response = wp_remote_post($endpoint, [
        'body' => $body, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'method' => 'POST', 'blocking' => true, 'timeout' => 15,
    ]);
    if (is_wp_error($response)) return ['success' => false, 'message' => $response->get_error_message()]; // Server mất mạng, không phân giải được DNS thì trả ở đây.
    $code = wp_remote_retrieve_response_code($response); // Sai quyền, sai cú pháp, server lỗi thì trả kết quả ở đây.
    $body_res = json_decode(wp_remote_retrieve_body($response), true);
    // 1. Kiểm tra 3 điều kiện
	if ($code === 200 && isset($body_res['success']) && $body_res['success'] == true) {
		// Nếu ĐÚNG: Trả về thành công
		return ['success' => true];
	} 
	else {
		// Nếu SAI: Cần tìm nội dung lỗi để báo cáo
		
		// Kiểm tra xem Cloudflare có gửi kèm tin nhắn lỗi không?
		if (isset($body_res['errors'][0]['message'])) {
			$specific_error = $body_res['errors'][0]['message']; // Thông báo lỗi cụ thể là gì?
		} else {
			// Nếu không có tin nhắn lỗi, dùng câu chung chung
			$specific_error = 'Lỗi không xác định';
		}

		// Trả về kết quả thất bại kèm lý do
		return [
			'success' => false, 
			'message' => $specific_error
		];
	}
}

// Thêm link "Cài đặt" trực tiếp tại trang danh sách Plugin
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpsila_scfp_add_settings_link');

function wpsila_scfp_add_settings_link($links) {
    // Tạo đường dẫn đến trang cấu hình
    $settings_link = '<a href="options-general.php?page=simple-cafe-purge">' . __('Cài đặt') . '</a>';
    
    // Thêm link này vào đầu mảng các liên kết của plugin
    array_unshift($links, $settings_link);
    
    return $links;
}