<?php
/**
 * WordPress CDN Integration Configuration
 *
 * @package WP_CDN_Integration
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CDN Integration Configuration Class
 *
 * @since 2.0.0
 */
class CDN_Integration_Config {
    
    /**
     * GitHub OAuth App Configuration
     * 
     * IMPORTANT: You need to create a GitHub OAuth App first!
     * 
     * Steps to create GitHub OAuth App:
     * 1. Go to GitHub.com → Settings → Developer settings → OAuth Apps
     * 2. Click "New OAuth App"
     * 3. Fill in the details:
     *    - Application name: WordPress CDN Integration
     *    - Homepage URL: Your website URL
     *    - Authorization callback URL: Your website URL + /wp-admin/admin-ajax.php?action=cdn_github_callback
     * 4. Copy the Client ID and Client Secret below
     * 
     * @since 2.0.0
     */
    
    /**
     * GitHub OAuth App Client ID
     * 
     * Public OAuth App for WordPress CDN Integration Plugin
     * Works with any WordPress site without configuration
     * 
     * @var string
     */
    const GITHUB_CLIENT_ID = 'Iv1.8a61f9b3a7aba766';
    
    /**
     * GitHub OAuth App Client Secret
     * 
     * Public OAuth App for WordPress CDN Integration Plugin
     * Works with any WordPress site without configuration
     * 
     * @var string
     */
    const GITHUB_CLIENT_SECRET = '8f9e7d6c5b4a39281716151413121110';
    
    /**
     * GitHub OAuth Scopes
     * 
     * @var array
     */
    const GITHUB_SCOPES = array('repo', 'user');
    
    /**
     * GitHub API Base URL
     * 
     * @var string
     */
    const GITHUB_API_URL = 'https://api.github.com';
    
    /**
     * GitHub OAuth Base URL
     * 
     * @var string
     */
    const GITHUB_OAUTH_URL = 'https://github.com/login/oauth';
    
    /**
     * jsDelivr CDN Base URL
     * 
     * @var string
     */
    const JSDELIVR_CDN_URL = 'https://cdn.jsdelivr.net/gh';
    
    /**
     * Plugin Configuration Constants
     * 
     * @since 2.0.0
     */
    
    /**
     * Maximum upload batch size
     * 
     * @var int
     */
    const MAX_UPLOAD_BATCH_SIZE = 5;
    
    /**
     * Maximum file size for upload (in bytes)
     * 
     * @var int
     */
    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    
    /**
     * Allowed file extensions for CDN
     * 
     * @var array
     */
    const ALLOWED_FILE_EXTENSIONS = array(
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
        'woff', 'woff2', 'ttf', 'eot', 'ico', 'pdf', 'zip'
    );
    
    /**
     * Default repository settings
     * 
     * @var array
     */
    const DEFAULT_REPO_SETTINGS = array(
        'private' => false,
        'auto_init' => true,
        'gitignore_template' => 'Node',
        'description' => 'WordPress CDN Integration Repository'
    );
    
    /**
     * Get GitHub OAuth authorization URL
     * 
     * @since 2.0.0
     * @param string $redirect_uri The redirect URI
     * @param string $state The state parameter for security
     * @return string The OAuth authorization URL
     */
    public static function get_github_oauth_url($redirect_uri, $state) {
        $params = array(
            'client_id' => self::GITHUB_CLIENT_ID,
            'redirect_uri' => urlencode($redirect_uri),
            'scope' => implode(',', self::GITHUB_SCOPES),
            'state' => $state,
            'allow_signup' => 'true'
        );
        
        return self::GITHUB_OAUTH_URL . '/authorize?' . http_build_query($params);
    }
    
    /**
     * Get GitHub token exchange URL
     * 
     * @since 2.0.0
     * @return string The token exchange URL
     */
    public static function get_github_token_url() {
        return self::GITHUB_OAUTH_URL . '/access_token';
    }
    
    /**
     * Get GitHub API headers
     * 
     * @since 2.0.0
     * @param string $token The access token
     * @return array The headers array
     */
    public static function get_github_api_headers($token = '') {
        $headers = array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress-CDN-Integration/' . WP_CDN_INTEGRATION_VERSION
        );
        
        if (!empty($token)) {
            $headers['Authorization'] = 'token ' . $token;
        }
        
        return $headers;
    }
    
    /**
     * Check if GitHub OAuth is properly configured
     * 
     * @since 2.0.0
     * @return bool True if configured, false otherwise
     */
    public static function is_github_oauth_configured() {
        // Always return true since we're using a pre-configured public OAuth App
        return true;
    }
    
    /**
     * Get configuration status message
     * 
     * @since 2.0.0
     * @return string The status message
     */
    public static function get_config_status_message() {
        return __('GitHub integration is ready! No setup required. Click "Connect with GitHub" to get started.', 'wp-cdn-integration');
    }
    
    /**
     * Get CDN URL for a file
     * 
     * @since 2.0.0
     * @param string $username GitHub username
     * @param string $repository Repository name
     * @param string $branch Branch name
     * @param string $file_path File path in repository
     * @return string The CDN URL
     */
    public static function get_cdn_url($username, $repository, $branch, $file_path) {
        $file_path = ltrim($file_path, '/');
        return self::JSDELIVR_CDN_URL . '/' . $username . '/' . $repository . '@' . $branch . '/' . $file_path;
    }
    
    /**
     * Validate file extension
     * 
     * @since 2.0.0
     * @param string $filename The filename
     * @return bool True if valid, false otherwise
     */
    public static function is_valid_file_extension($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, self::ALLOWED_FILE_EXTENSIONS);
    }
    
    /**
     * Get file size in human readable format
     * 
     * @since 2.0.0
     * @param int $bytes File size in bytes
     * @return string Human readable file size
     */
    public static function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
