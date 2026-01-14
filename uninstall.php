<?php
/**
 * Fired when the plugin is uninstalled.
 */

// Chặn truy cập trực tiếp
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Xóa dữ liệu cấu hình theo Prefix mới
delete_option( 'wpsila_scfp_zone_id' );
delete_option( 'wpsila_scfp_api_token' );