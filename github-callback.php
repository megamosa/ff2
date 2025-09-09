<?php
// Ensure WordPress environment is loaded
if (!defined('ABSPATH')) {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
}

// Prevent direct access if ABSPATH is not defined (should be defined by wp-load.php)
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary plugin files
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-github-oauth.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-admin.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-helper.php';
require_once WP_CDN_INTEGRATION_PLUGIN_DIR . 'includes/class-cdn-integration-logger.php';

// Initialize helper and logger for potential use
$logger = new CDN_Integration_Logger();
$helper = new CDN_Integration_Helper($logger);

// Get parameters from GitHub callback
$code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';
$state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

// Prepare redirect URL back to the admin page
$admin_redirect_url = admin_url('admin.php?page=wp-cdn-integration');

// Handle OAuth errors
if ($error) {
    $error_description = isset($_GET['error_description']) ? sanitize_text_field($_GET['error_description']) : $error;
    // Use JavaScript to close the popup and pass error to opener
    echo "<script type='text/javascript'>
        if (window.opener) {
            window.opener.location.href = '{$admin_redirect_url}&github_error=" . urlencode($error_description) . "';
            window.close();
        } else {
            window.location.href = '{$admin_redirect_url}&github_error=" . urlencode($error_description) . "';
        }
    </script>";
    exit;
}

// Validate required parameters
if (empty($code) || empty($state)) {
    echo "<script type='text/javascript'>
        if (window.opener) {
            window.opener.location.href = '{$admin_redirect_url}&github_error=" . urlencode('Invalid callback parameters') . "';
            window.close();
        } else {
            window.location.href = '{$admin_redirect_url}&github_error=" . urlencode('Invalid callback parameters') . "';
        }
    </script>";
    exit;
}

try {
    $github_oauth = new CDN_Integration_GitHub_OAuth();
    $result = $github_oauth->complete_auto_connection($code, $state);

    if ($result && isset($result['success']) && $result['success']) {
        // Success: Use JavaScript to close the popup and refresh the opener
        echo "<script type='text/javascript'>
            if (window.opener) {
                window.opener.location.href = '{$admin_redirect_url}&github_success=1';
                window.close();
            } else {
                window.location.href = '{$admin_redirect_url}&github_success=1';
            }
        </script>";
        exit;
    } else {
        $error_message = isset($result['message']) ? $result['message'] : __('Connection failed', 'wp-cdn-integration');
        echo "<script type='text/javascript'>
            if (window.opener) {
                window.opener.location.href = '{$admin_redirect_url}&github_error=" . urlencode($error_message) . "';
                window.close();
            } else {
                window.location.href = '{$admin_redirect_url}&github_error=" . urlencode($error_message) . "';
            }
        </script>";
        exit;
    }
} catch (Exception $e) {
    echo "<script type='text/javascript'>
        if (window.opener) {
            window.opener.location.href = '{$admin_redirect_url}&github_error=" . urlencode($e->getMessage()) . "';
            window.close();
        } else {
            window.location.href = '{$admin_redirect_url}&github_error=" . urlencode($e->getMessage()) . "';
        }
    </script>";
    exit;
}
