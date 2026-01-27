<?php
/**
 * Plugin Name: Simple Cafe Purge
 * Description: Giải pháp xóa cache Cloudflare siêu nhẹ cho Blog. Tự động xóa khi cập nhật bài viết và hỗ trợ nút "Purge Everything".
 * Version: 1.13.4
 * Author: wpsila - Nguyễn Đức Anh
 * Author URI: https://simple-cafe-purge.wpsila.com
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
        'Simple Cafe Purge', 
        'manage_options', 
        'simple-cafe-purge', 
        'wpsila_scfp_options_page'
    );
}

function wpsila_scfp_options_page() {
	// 1. CHẶN ĐẦU: Kiểm tra quyền hạn ngay lập tức
    if (!current_user_can('manage_options')) {
        wp_die(__('Bạn không có quyền truy cập trang này.'));
    }
	
    // --- XỬ LÝ LƯU CẤU HÌNH ---
    if (isset($_POST['wpsila_scfp_save_settings']) && check_admin_referer('wpsila_scfp_save_settings_verify')) {
        $input_zone_id = sanitize_text_field($_POST['wpsila_scfp_zone_id']);
        $input_api_token = sanitize_text_field($_POST['wpsila_scfp_api_token']);

        if (empty($input_zone_id) || empty($input_api_token)) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>Lỗi:</strong> Không được để trống Zone ID và API Token!</p></div>';
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
                echo '<div class="notice notice-success is-dismissible"><p>🚀 <strong>Thành công:</strong> Đã xóa toàn bộ cache website trên Cloudflare.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>Lỗi:</strong> ' . esc_html($result['message']) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>⚠️ Vui lòng nhập thông tin API trước.</p></div>';
        }
    }

    $zone_id = get_option('wpsila_scfp_zone_id', '');
    $api_token = get_option('wpsila_scfp_api_token', '');
    ?>
    
    <style>
        .wpsila-card { background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px; margin-bottom: 20px; }
        .wpsila-card.is-danger { border-left: 4px solid #d63638; }
        .wpsila-full-width { width: 100%; }
        .wpsila-pwd-wrapper { position: relative; max-width: 100%; }
        .wpsila-pwd-input { width: 100%; padding-right: 40px; }
        .wpsila-eye-icon { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #50575e; }
        .wpsila-btn-purge { font-weight: bold !important; border: 1px solid #d63638 !important; padding: 5px 15px !important; background: #fbeaea !important; color: #d63638 !important; transition: all 0.2s; cursor: pointer; }
        .wpsila-btn-purge:hover { background: #d63638 !important; color: #fff !important; }
        .wpsila-hint { margin-top: 15px; font-size: 13px; color: #646970; font-style: italic; line-height: 1.5; border-top: 1px dashed #ddd; padding-top: 10px; }
        .wpsila-hint strong { color: #d63638; }
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
                    <tr>
                        <th scope="row">Zone ID <span style="color:red">*</span></th>
                        <td>
                            <input type="text" name="wpsila_scfp_zone_id" value="<?php echo esc_attr($zone_id); ?>" class="regular-text wpsila-full-width" placeholder="Ví dụ: a1b2c3..." required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Token <span style="color:red">*</span></th>
                        <td>
                            <div class="wpsila-pwd-wrapper">
                                <input type="password" id="wpsila_scfp_api_token" name="wpsila_scfp_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text wpsila-pwd-input" required autocomplete="new-password" />
                                <span id="wpsila_toggle_token" class="dashicons dashicons-visibility wpsila-eye-icon" title="Hiện/Ẩn Token"></span>
                            </div>
                            <p class="description">Quyền cần có: <strong>Zone > Cache Purge > Purge</strong></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Lưu cấu hình', 'primary', 'wpsila_scfp_save_settings'); ?>
            </form>
        </div>

        <div class="wpsila-card is-danger">
            <h2>🔥 Xóa toàn bộ Cache</h2>
            <form method="post" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa TOÀN BỘ cache không?');">
                <?php wp_nonce_field('wpsila_scfp_purge_all_verify'); ?>
                <input type="submit" name="wpsila_scfp_purge_everything" class="button wpsila-btn-purge" value="Xóa Sạch Cache Ngay Lập Tức" />
            </form>
            <p class="wpsila-hint">💡 <strong>Mẹo:</strong> Nhấn nút này để kiểm tra kết nối API. Nếu hiện "Thành công" nghĩa là bạn đã cấu hình đúng!</p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggleBtn = document.getElementById('wpsila_toggle_token');
            var inputField = document.getElementById('wpsila_scfp_api_token');
            if (toggleBtn && inputField) {
                toggleBtn.addEventListener('click', function() {
                    var isPwd = inputField.type === 'password';
                    inputField.type = isPwd ? 'text' : 'password';
                    toggleBtn.classList.toggle('dashicons-visibility', !isPwd);
                    toggleBtn.classList.toggle('dashicons-hidden', isPwd);
                });
            }
        });
    </script>
    <?php
}

// =========================================================================
// 2. LOGIC TỰ ĐỘNG (AUTO PURGE CHO BLOG)
// =========================================================================

// Helper mở rộng biến thể URL (có gạch chéo và không gạch chéo)
function wpsila_expand_urls($urls) {
    $expanded = [];
    foreach ($urls as $url) {
        $expanded[] = $url;
        // Thêm bản có / ở cuối
        $expanded[] = trailingslashit($url); 
        // Thêm bản không có / ở cuối
        $expanded[] = untrailingslashit($url);
    }
    // Lọc trùng lặp và lấy lại danh sách sạch
    return array_values(array_unique($expanded));
}

add_action('transition_post_status', 'wpsila_scfp_handle_post_transition', 10, 3);

function wpsila_scfp_handle_post_transition($new_status, $old_status, $post) {
    if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
    if ($new_status !== 'publish' && $old_status !== 'publish') return;
    
    $zone_id = get_option('wpsila_scfp_zone_id');
    $api_token = get_option('wpsila_scfp_api_token');
    if (!$zone_id || !$api_token) return;

    $urls = [get_permalink($post->ID), home_url('/'), home_url()];

    if ($post->post_type === 'post') {
        $urls[] = get_bloginfo('rss2_url');
        // Lấy link Categories & Tags
        foreach (['category', 'post_tag'] as $tax) {
            $terms = get_the_terms($post->ID, $tax);
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $link = get_term_link($term);
                    if ($link && !is_wp_error($link)) $urls[] = $link;
                }
            }
        }
    }
    
    // 1. Mở rộng biến thể (có / và không /)
    $urls = wpsila_expand_urls($urls);

    // 2. Cắt giới hạn (Cloudflare cho phép 100 URL, ta để 90 cho an toàn sau khi đã nhân bản)
    $urls = array_slice($urls, 0, 90); 
    
    // 3. Gửi request
    wpsila_scfp_send_purge_request($zone_id, $api_token, $urls);
}


// =========================================================================
// 3. CÁC HÀM API
// =========================================================================

function wpsila_scfp_send_purge_request($zone_id, $token, $urls) {
    wp_remote_post("https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache", [
        'body' => json_encode(['files' => $urls]),
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'method' => 'POST', 'blocking' => false, 'timeout' => 10,
    ]);
}

function wpsila_scfp_execute_purge_everything($zone_id, $token) {
    $response = wp_remote_post("https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache", [
        'body' => json_encode(['purge_everything' => true]),
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        'method' => 'POST', 'blocking' => true, 'timeout' => 15,
    ]);

    if (is_wp_error($response)) return ['success' => false, 'message' => $response->get_error_message()];
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (wp_remote_retrieve_response_code($response) === 200 && !empty($body['success'])) return ['success' => true];
    return ['success' => false, 'message' => $body['errors'][0]['message'] ?? 'Lỗi không xác định'];
}

// Link Cài đặt nhanh
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    array_unshift($links, '<a href="options-general.php?page=simple-cafe-purge">Cài đặt</a>');
    return $links;
});

// Thêm link "Hướng dẫn sử dụng" vào dòng thông tin plugin
add_filter('plugin_row_meta', 'wpsila_scfp_add_plugin_meta_links', 10, 2);
function wpsila_scfp_add_plugin_meta_links($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $new_links = [
            // Link hướng dẫn
            'docs' => '<a href="https://blog.wpsila.com/rtd-cafe-va-plugin-simple-cafe-purge/" target="_blank" style="color: #d63638; font-weight: bold;">📚 Hướng dẫn sử dụng</a>',
        ];
        return array_merge($links, $new_links);
    }
    return $links;
}

// =========================================================================
// 4. TÍNH NĂNG: NÚT "PURGE THIS URL" TRÊN ADMIN BAR
// =========================================================================

// Thêm nút vào Admin Bar (Chỉ hiển thị ngoài Frontend và với Admin)
add_action('admin_bar_menu', 'wpsila_scfp_admin_bar_node', 99);
function wpsila_scfp_admin_bar_node($wp_admin_bar) {
    // Chỉ hiện cho Admin và khi đang xem ngoài giao diện (Frontend)
    if (!current_user_can('manage_options') || is_admin()) return;

    // Chỉ hiện khi đã cấu hình API
    if (!get_option('wpsila_scfp_zone_id')) return;

    // Tạo link có kèm nonce để bảo mật
    $href = wp_nonce_url(add_query_arg('wpsila_action', 'purge_current'), 'wpsila_scfp_purge_current_verify');

    $wp_admin_bar->add_node([
        'id'    => 'wpsila_purge_current',
        'title' => '<span class="ab-icon dashicons dashicons-cloud"></span> Purge Cloudflare Cache This URL',
        'href'  => $href,
        'meta'  => ['title' => 'Xóa cache Cloudflare cho trang bạn đang xem']
    ]);
}

// Xử lý logic khi bấm nút
add_action('init', 'wpsila_scfp_process_admin_bar_purge');
function wpsila_scfp_process_admin_bar_purge() {
    // Kiểm tra tham số và Nonce bảo mật
    if (isset($_GET['wpsila_action']) && $_GET['wpsila_action'] == 'purge_current' && check_admin_referer('wpsila_scfp_purge_current_verify')) {
        
        // Kiểm tra quyền lần nữa
        if (!current_user_can('manage_options')) return;

        $zone_id = get_option('wpsila_scfp_zone_id');
        $api_token = get_option('wpsila_scfp_api_token');
        
        if ($zone_id && $api_token) {
            // 1. Lấy đường dẫn thô (Domain + Path) từ Server
            $raw_path = $_SERVER['HTTP_HOST'] . remove_query_arg(['wpsila_action', '_wpnonce']);
            
            // 2. Tạo thủ công cả 2 biến thể HTTP và HTTPS
            // Chỉ làm việc này ở đây để xử lý lỗi SSL/Proxy cho nút bấm tay
            $urls_to_purge = [
                'http://' . $raw_path,
                'https://' . $raw_path
            ];

            // 3. Mở rộng thêm biến thể có/không dấu gạch chéo
            // Tổng cộng sẽ có tối đa 4 URLs (Http có/không /, Https có/không /)
            $urls = wpsila_expand_urls($urls_to_purge);

            // 4. Gửi request
            $response = wp_remote_post("https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache", [
                'body'    => json_encode(['files' => $urls]), 
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_token, 
                    'Content-Type'  => 'application/json'
                ],
                'method'   => 'POST', 
                'blocking' => true, 
                'timeout'  => 10,
            ]);
            
            // 5. Redirect
            wp_redirect(add_query_arg('wpsila_purged', '1', remove_query_arg(['wpsila_action', '_wpnonce'])));
            exit;
        }
    }
}

// Hiển thị thông báo nhỏ bằng JS sau khi reload
add_action('wp_footer', 'wpsila_scfp_purge_success_script');
function wpsila_scfp_purge_success_script() {
    if (isset($_GET['wpsila_purged']) && $_GET['wpsila_purged'] == '1') {
        ?>
        <script>
            // Xóa tham số query trên thanh địa chỉ cho đẹp
            if(history.replaceState) history.replaceState(null, null, window.location.href.split("?")[0]);
            // Thông báo đơn giản (hoặc bạn có thể dùng alert nếu muốn)
            console.log('🚀 Simple Cafe Purge: Đã xóa cache trang này!');
            alert('✅ Đã xóa cache Cloudflare cho URL này thành công!');
        </script>
        <?php
    }
}