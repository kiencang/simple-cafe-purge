<?php
/**
 * Plugin Name: Simple Cafe Purge
 * Description: Giải pháp xóa cache (cho Cloudflare) siêu nhẹ. Tự động xóa khi cập nhật nội dung và hỗ trợ nút "Purge Everything".
 * Version: 1.9
 * Author: WPSila Optimizer
 * Author URI: https://wpsila.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// 1. GIAO DIỆN ADMIN & XỬ LÝ FORM
// =========================================================================

add_action('admin_menu', 'wpsila_scfp_add_admin_menu');
function wpsila_scfp_add_admin_menu() {
    add_options_page(
        'Simple Cafe Purge', 
        'CF Purge', 
        'manage_options', 
        'simple-cafe-purge', 
        'wpsila_scfp_options_page'
    );
}

function wpsila_scfp_options_page() {
    // --- XỬ LÝ LƯU CẤU HÌNH ---
    if (isset($_POST['wpsila_scfp_save_settings']) && check_admin_referer('wpsila_scfp_save_settings_verify')) {
        $input_zone_id = sanitize_text_field($_POST['wpsila_scfp_zone_id']);
        $input_api_token = sanitize_text_field($_POST['wpsila_scfp_api_token']);

        if (empty($input_zone_id) || empty($input_api_token)) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>Lỗi:</strong> Zone ID và API Token không được để trống!</p></div>';
        } else {
            update_option('wpsila_scfp_zone_id', $input_zone_id);
            update_option('wpsila_scfp_api_token', $input_api_token);
            echo '<div class="notice notice-success is-dismissible"><p>✅ Đã lưu cấu hình thành công!</p></div>';
        }
    }

    // --- XỬ LÝ PURGE EVERYTHING ---
    if (isset($_POST['wpsila_scfp_purge_everything']) && check_admin_referer('wpsila_scfp_purge_all_verify')) {
        $zone_id = get_option('wpsila_scfp_zone_id');
        $api_token = get_option('wpsila_scfp_api_token');
        
        if ($zone_id && $api_token) {
            $result = wpsila_scfp_execute_purge_everything($zone_id, $api_token);
            if ($result['success']) {
                echo '<div class="notice notice-success is-dismissible"><p>🚀 <strong>Thành công:</strong> Đã xóa toàn bộ cache.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>Lỗi:</strong> ' . esc_html($result['message']) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>⚠️ Vui lòng nhập Zone ID và API Token trước.</p></div>';
        }
    }

    $zone_id = get_option('wpsila_scfp_zone_id', '');
    $api_token = get_option('wpsila_scfp_api_token', '');
    ?>
    <div class="wrap">
        <h1>☕ Simple Cafe Purge</h1>
        <p>Plugin siêu nhẹ giúp đồng bộ cache giữa WordPress và hệ thống CDN.</p>
        <hr>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px;">
            <h2>🛠️ Cấu hình API</h2>
            <form method="post" action="">
                <?php wp_nonce_field('wpsila_scfp_save_settings_verify'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Zone ID <span style="color:red">*</span></th>
                        <td>
                            <input type="text" name="wpsila_scfp_zone_id" value="<?php echo esc_attr($zone_id); ?>" class="regular-text" style="width: 100%;" placeholder="Ví dụ: a1b2c3d4..." required />
                            <p class="description">Tìm thấy ở trang Overview tên miền (cột bên phải).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">API Token <span style="color:red">*</span></th>
                        <td>
                            <input type="password" name="wpsila_scfp_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text" style="width: 100%;" required />
                            <p class="description">Yêu cầu quyền: <strong>Zone > Cache Purge > Purge</strong>.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="wpsila_scfp_save_settings" class="button button-primary" value="Lưu cấu hình" /></p>
            </form>
        </div>
        <br>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; border-left: 4px solid #d63638;">
            <h2>🔥 Xóa toàn bộ Cache</h2>
            <form method="post" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa TOÀN BỘ cache không?');">
                <?php wp_nonce_field('wpsila_scfp_purge_all_verify'); ?>
                <input type="submit" name="wpsila_scfp_purge_everything" class="button button-link-delete" value="Xóa Sạch Cache Ngay Lập Tức" style="font-weight: bold; border: 1px solid #d63638; padding: 5px 15px; background: #fbeaea;" />
            </form>
        </div>
    </div>
    <?php
}

// =========================================================================
// 2. LOGIC TỰ ĐỘNG (AUTO PURGE)
// =========================================================================

add_action('transition_post_status', 'wpsila_scfp_handle_post_transition', 10, 3);

function wpsila_scfp_handle_post_transition($new_status, $old_status, $post) {
    // 1. Chặn các trường hợp không cần thiết
    if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
    if ($new_status !== 'publish' && $old_status !== 'publish') return;

    $zone_id = get_option('wpsila_scfp_zone_id');
    $api_token = get_option('wpsila_scfp_api_token');
    
    if (empty($zone_id) || empty($api_token)) return;

    $urls_to_purge = [];
    
    // --- ƯU TIÊN 1: URL BÀI VIẾT (Quan trọng nhất) ---
    // Luôn add đầu tiên để đảm bảo không bao giờ bị cắt
    $permalink = get_permalink($post->ID);
    if ($permalink) $urls_to_purge[] = $permalink;

    if ($post->post_type === 'post') {
        // --- ƯU TIÊN 2: TRANG CHỦ ---
        $urls_to_purge[] = home_url('/');
        $urls_to_purge[] = home_url(); 
        
        // --- ƯU TIÊN 3: FEED (RSS) ---
        // Nên thêm cái này vì Google News hay quét feed
        $urls_to_purge[] = get_bloginfo('rss2_url');
        
        // --- ƯU TIÊN 4: CATEGORY ---
        $categories = get_the_category($post->ID);
        if ($categories) {
            foreach ($categories as $category) {
                $link = get_category_link($category->term_id);
                if ($link && !is_wp_error($link)) $urls_to_purge[] = $link;
            }
        }

        // --- ƯU TIÊN 5: TAGS (Ít quan trọng nhất - Sẽ bị cắt đầu tiên nếu quá nhiều) ---
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $link = get_tag_link($tag->term_id);
                if ($link && !is_wp_error($link)) $urls_to_purge[] = $link;
            }
        }
    }
    
    // Loại bỏ URL trùng lặp
    $urls_to_purge = array_unique($urls_to_purge);
    
    // [QUAN TRỌNG] Reset lại key của mảng để hàm slice chạy đúng index 0,1,2...
    // array_values rất quan trọng sau khi dùng array_unique
    $urls_to_purge = array_values($urls_to_purge);

    // [BỔ SUNG] Giới hạn 100 URL để tránh lỗi API 413 của Cloudflare
    if (count($urls_to_purge) > 90) {
        $urls_to_purge = array_slice($urls_to_purge, 0, 90);
    }

    if (!empty($urls_to_purge)) {
        wpsila_scfp_send_purge_request($zone_id, $api_token, $urls_to_purge);
    }
}

// =========================================================================
// 3. CÁC HÀM API GỬI LÊN CDN
// =========================================================================

function wpsila_scfp_send_purge_request($zone_id, $token, $urls) {
    $endpoint = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    $body = json_encode(['files' => array_values($urls)]);
    wp_remote_post($endpoint, [
        'body' => $body, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'method' => 'POST', 'blocking' => false, 'timeout' => 5,
    ]);
}

function wpsila_scfp_execute_purge_everything($zone_id, $token) {
    $endpoint = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
    $body = json_encode(['purge_everything' => true]);
    $response = wp_remote_post($endpoint, [
        'body' => $body, 'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'method' => 'POST', 'blocking' => true, 'timeout' => 15,
    ]);
    if (is_wp_error($response)) return ['success' => false, 'message' => $response->get_error_message()];
    $code = wp_remote_retrieve_response_code($response);
    $body_res = json_decode(wp_remote_retrieve_body($response), true);
    return ($code === 200 && isset($body_res['success']) && $body_res['success']) ? ['success' => true] : ['success' => false, 'message' => $body_res['errors'][0]['message'] ?? 'Lỗi không xác định'];
}