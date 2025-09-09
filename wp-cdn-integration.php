<?php
/**
 * Plugin Name: WordPress Free Unlimited CDN
 * Plugin URI: https://mago-ar.com
 * Description: Professional WordPress CDN integration plugin that serves static assets through jsDelivr CDN using GitHub as storage. Features automatic file analysis, batch uploads, progress tracking, and comprehensive error handling.
 * Version: 2.0.0
 * Author: MagoArab
 * Author URI: https://mago-ar.com
 * Text Domain: wp-cdn-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 * 
 * @package WP_CDN_Integration
 * @version 2.0.0
 * @author MagoArab
 * @link https://mago-ar.com
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin constants and configuration.
 * 
 * @since 2.0.0
 */
define('WP_CDN_INTEGRATION_VERSION', '2.0.0');
define('WP_CDN_INTEGRATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_CDN_INTEGRATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_CDN_INTEGRATION_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WP_CDN_INTEGRATION_LOG_FILE', WP_CONTENT_DIR . '/wp-cdn-integration-log.log');

/**
 * Plugin configuration constants.
 * 
 * @since 2.0.0
 */
define('WP_CDN_INTEGRATION_MAX_UPLOAD_BATCH_SIZE', 5);
define('WP_CDN_INTEGRATION_MAX_ANALYZE_PAGES', 20);
define('WP_CDN_INTEGRATION_API_TIMEOUT', 30);
define('WP_CDN_INTEGRATION_SUPPORTED_FILE_TYPES', 'css,js,png,jpg,jpeg,gif,svg,webp,ico,woff,woff2,ttf,eot,otf');

// Include required files
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-loader.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-logger.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-helper.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-config.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-github-oauth.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-github-api.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-jsdelivr-api.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-url-analyzer.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-url-rewriter.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'admin/class-cdn-integration-admin.php';

/**
 * Initialize and run the WordPress CDN Integration plugin.
 * 
 * This function sets up all the necessary components for the plugin to function,
 * including loading classes, initializing hooks, and setting up admin functionality.
 *
 * @since 1.0.0
 * @since 2.0.0 Enhanced with better error handling and professional architecture
 * 
 * @return void
 */
function run_wp_cdn_integration() {
    // Initialize the loader
    $plugin_loader = new CDN_Integration_Loader();
    
    // Initialize the logger
    $plugin_logger = new CDN_Integration_Logger();
    
    // Initialize the helper
    $plugin_helper = new CDN_Integration_Helper($plugin_logger);
    
    // Initialize GitHub API
    $github_api = new CDN_Integration_Github_API($plugin_helper);
    
    // Initialize jsDelivr API
    $jsdelivr_api = new CDN_Integration_Jsdelivr_API($plugin_helper);
    
    // Initialize URL analyzer
    $url_analyzer = new CDN_Integration_URL_Analyzer($plugin_helper);
    
    // Initialize URL rewriter
    $url_rewriter = new CDN_Integration_URL_Rewriter($plugin_helper);
    
    // Initialize admin functions if in admin
    if (is_admin()) {
        $plugin_admin = new CDN_Integration_Admin(
            $plugin_helper,
            $github_api,
            $jsdelivr_api,
            $url_analyzer
        );
        
        // Hook the admin init
        $plugin_loader->add_action('admin_init', $plugin_admin, 'admin_init');
        $plugin_loader->add_action('admin_menu', $plugin_admin, 'add_menu_pages');
        $plugin_loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_admin_assets');
        
        // Ajax hooks
        $plugin_loader->add_action('wp_ajax_cdn_test_connection', $plugin_admin, 'ajax_test_connection');
$plugin_loader->add_action('wp_ajax_cdn_purge_cache', $plugin_admin, 'ajax_purge_cache');
$plugin_loader->add_action('wp_ajax_cdn_analyze_urls', $plugin_admin, 'ajax_analyze_urls');
$plugin_loader->add_action('wp_ajax_cdn_upload_to_github', $plugin_admin, 'ajax_upload_to_github');
$plugin_loader->add_action('wp_ajax_cdn_validate_urls', $plugin_admin, 'ajax_validate_urls');
$plugin_loader->add_action('wp_ajax_cdn_direct_analyze_urls', $plugin_admin, 'ajax_direct_analyze_urls');
$plugin_loader->add_action('wp_ajax_cdn_view_log', $plugin_admin, 'ajax_view_log');
$plugin_loader->add_action('wp_ajax_cdn_update_custom_urls', $plugin_admin, 'ajax_update_custom_urls');
$plugin_loader->add_action('wp_ajax_cdn_auto_connect_github', $plugin_admin, 'ajax_auto_connect_github');
$plugin_loader->add_action('wp_ajax_cdn_disconnect_github', $plugin_admin, 'ajax_disconnect_github');
$plugin_loader->add_action('wp_ajax_cdn_github_callback', $plugin_admin, 'ajax_github_callback');
        
        
        // Settings API hooks
        $plugin_loader->add_action('admin_init', $plugin_admin, 'register_settings');
    }
    
    // Front-end hooks - only apply when not in admin
    if ($plugin_helper->is_enabled() && !is_admin()) {
        $plugin_loader->add_action('wp_head', $url_rewriter, 'add_cdn_config_script', 5);
        
        // Low priority to catch all URLs
        $plugin_loader->add_filter('style_loader_src', $url_rewriter, 'rewrite_url', 9999);
        $plugin_loader->add_filter('script_loader_src', $url_rewriter, 'rewrite_url', 9999);
        $plugin_loader->add_filter('the_content', $url_rewriter, 'rewrite_content_urls', 9999);
        
        // Images and other media
        $plugin_loader->add_filter('wp_get_attachment_url', $url_rewriter, 'rewrite_url', 9999);
        $plugin_loader->add_filter('wp_get_attachment_image_src', $url_rewriter, 'rewrite_image_src', 9999);
        $plugin_loader->add_filter('wp_calculate_image_srcset', $url_rewriter, 'rewrite_image_srcset', 9999);
    }
    
    // Run all the hooks
    $plugin_loader->run();
}

// Fire up the plugin
run_wp_cdn_integration();

// Activation hook
register_activation_hook(__FILE__, 'wp_cdn_integration_activate');
function wp_cdn_integration_activate() {
    // Add default options if not exist
    if (!get_option('wp_cdn_integration_settings')) {
        add_option('wp_cdn_integration_settings', array(
            'enabled' => '0',            // Disabled by default to prevent issues
            'debug_mode' => '0',
            'github_username' => '',
            'github_repository' => '',
            'github_branch' => 'main',
            'github_token' => '',
            'file_types' => 'js,css,png,jpg,jpeg,gif,svg,woff,woff2,ttf,eot',  // More file types by default
            'excluded_paths' => '/wp-admin/*, /wp-login.php', // Exclude admin by default
            'custom_urls' => ''
        ));
    }
}
// Add to wp-cdn-integration.php - around line 120
function wp_cdn_restore_github_settings() {
    $github_settings = get_option('wp_cdn_integration_github_settings', array());
    $cdn_settings = get_option('wp_cdn_integration_settings', array());
    
    if (!empty($github_settings) && (empty($cdn_settings['github_username']) || empty($cdn_settings['github_repository']))) {
        $cdn_settings['github_username'] = $github_settings['username'];
        $cdn_settings['github_repository'] = $github_settings['repository'];
        $cdn_settings['github_branch'] = $github_settings['branch'];
        $cdn_settings['github_token'] = $github_settings['token'];
        update_option('wp_cdn_integration_settings', $cdn_settings);
    }
}
add_action('admin_init', 'wp_cdn_restore_github_settings');
// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_cdn_integration_deactivate');
function wp_cdn_integration_deactivate() {
    // Clean up if needed
}

/**
 * Helper function to check if we're in an admin page.
 * More comprehensive than the built-in is_admin() which 
 * returns true for AJAX requests.
 *
 * @since 1.0.0
 * @return boolean True if in an admin page, false otherwise.
 */
function wp_cdn_is_admin_page() {
    // Check if it's the admin area
    if (!is_admin()) {
        return false;
    }
    
    // Check if this is an AJAX request from the frontend
    if (defined('DOING_AJAX') && DOING_AJAX) {
        $referer = wp_get_referer();
        if ($referer) {
            $referer_path = parse_url($referer, PHP_URL_PATH);
            if (strpos($referer_path, '/wp-admin/') === false) {
                return false;
            }
        }
    }
    
    return true;
}
// Add a diagnostic check to display CDN status
function wp_cdn_diagnostic() {
    if (!is_admin() && isset($_GET['cdn_debug']) && current_user_can('manage_options')) {
        $helper = new CDN_Integration_Helper(new CDN_Integration_Logger());
        $enabled = $helper->is_enabled() ? 'Yes' : 'No';
        $cdn_base = $helper->get_cdn_base_url();
        $custom_urls = $helper->get_custom_urls();
        
        echo '<div style="background:#fff; padding:15px; margin:15px; border:1px solid #ddd;">';
        echo '<h3>Free Unlimited CDN Diagnostic</h3>';
        echo '<p>CDN Enabled: ' . $enabled . '</p>';
        echo '<p>CDN Base URL: ' . $cdn_base . '</p>';
        echo '<p>Custom URLs Count: ' . count($custom_urls) . '</p>';
        echo '<ul>';
        foreach ($custom_urls as $url) {
            echo '<li>' . $url . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
}
add_action('wp_footer', 'wp_cdn_diagnostic');
// إضافة رؤوس CORS لملفات الخطوط
function wp_cdn_add_cors_headers() {
    // تحقق مما إذا كان CDN مفعل
    $helper = new CDN_Integration_Helper(new CDN_Integration_Logger());
    if (!$helper->is_enabled()) {
        return;
    }
    
    // تحقق من نوع الملف المطلوب
    $file_path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
    
    // قائمة امتدادات ملفات الخطوط
    $font_extensions = array('ttf', 'ttc', 'otf', 'eot', 'woff', 'woff2');
    
    // إضافة رؤوس CORS إذا كان الملف خطًا
    if (in_array(strtolower($file_ext), $font_extensions)) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
    }
}

// تشغيل الدالة قبل إرسال أي محتوى
add_action('init', 'wp_cdn_add_cors_headers', 1);
// وظيفة لمعالجة روابط ملفات الخطوط بشكل خاص
function wp_cdn_handle_font_urls($url, $original_url) {
    if (empty($url)) {
        return $url;
    }
    
    // تحقق من امتداد الملف
    $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    $font_extensions = array('ttf', 'ttc', 'otf', 'eot', 'woff', 'woff2');
    
    // إذا كان خطًا، أضف قيمة عشوائية للقضاء على مشكلة التخزين المؤقت
    if (in_array($ext, $font_extensions)) {
        $url = add_query_arg('cdnver', mt_rand(1000, 9999), $url);
    }
    
    return $url;
}

// إضافة مرشح لمعالجة روابط ملفات الخطوط
add_filter('wp_cdn_integration_rewritten_url', 'wp_cdn_handle_font_urls', 10, 2);