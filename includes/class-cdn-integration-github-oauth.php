<?php
/**
 * WordPress CDN Integration GitHub OAuth Handler
 *
 * @package WP_CDN_Integration
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub OAuth Integration Class
 *
 * @since 2.0.0
 */
class CDN_Integration_GitHub_OAuth {
    
    /**
     * GitHub OAuth App Client ID
     * 
     * @var string
     */
    private $client_id;
    
    /**
     * GitHub OAuth App Client Secret
     * 
     * @var string
     */
    private $client_secret;
    
    /**
     * Redirect URI
     * 
     * @var string
     */
    private $redirect_uri;
    
    /**
     * Constructor
     * 
     * @since 2.0.0
     */
    public function __construct() {
        $this->client_id = $this->get_client_id();
        $this->client_secret = $this->get_client_secret();
        $this->redirect_uri = WP_CDN_INTEGRATION_PLUGIN_URL . 'github-callback.php';
    }
    
    /**
     * Get GitHub OAuth Client ID
     * 
     * @since 2.0.0
     * @return string
     */
    private function get_client_id() {
        // Try to get from settings first
        $settings = get_option('wp_cdn_integration_settings', array());
        if (!empty($settings['github_oauth_client_id'])) {
            return $settings['github_oauth_client_id'];
        }
        
        // Fallback to config
        return CDN_Integration_Config::GITHUB_CLIENT_ID;
    }
    
    /**
     * Get GitHub OAuth Client Secret
     * 
     * @since 2.0.0
     * @return string
     */
    private function get_client_secret() {
        // Try to get from settings first
        $settings = get_option('wp_cdn_integration_settings', array());
        if (!empty($settings['github_oauth_client_secret'])) {
            return $settings['github_oauth_client_secret'];
        }
        
        // Fallback to config
        return CDN_Integration_Config::GITHUB_CLIENT_SECRET;
    }
    
    /**
     * Check if OAuth is properly configured
     * 
     * @since 2.0.0
     * @return bool
     */
    public function is_configured() {
        // Always return true since we're using a pre-configured public OAuth App
        return true;
    }
    
    /**
     * Get OAuth authorization URL
     * 
     * @since 2.0.0
     * @param string $state State parameter for security
     * @return string
     */
    public function get_authorization_url($state) {
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'repo,user',
            'state' => $state,
            'allow_signup' => 'true'
        );
        
        return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
    }
    
    /**
     * Start automatic GitHub connection process
     * 
     * @since 2.0.0
     * @return array Connection result with redirect URL or error
     */
    public function start_auto_connection() {
        // Generate secure state parameter
        $state = wp_generate_password(32, false);
        set_transient('wp_cdn_github_oauth_state', $state, 600); // 10 minutes
        
        // Get authorization URL
        $auth_url = $this->get_authorization_url($state);
        
        return array(
            'success' => true,
            'redirect_url' => $auth_url,
            'message' => __('Redirecting to GitHub for automatic setup...', 'wp-cdn-integration')
        );
    }
    
    /**
     * Complete automatic GitHub connection process
     * 
     * @since 2.0.0
     * @param string $code Authorization code from GitHub
     * @param string $state State parameter for verification
     * @return array Connection result
     */
    public function complete_auto_connection($code, $state) {
        // Verify state parameter
        $stored_state = get_transient('wp_cdn_github_oauth_state');
        if (!$stored_state || $stored_state !== $state) {
            return array(
                'success' => false,
                'message' => __('Security verification failed. Please try again.', 'wp-cdn-integration')
            );
        }
        
        // Clean up state
        delete_transient('wp_cdn_github_oauth_state');
        
        // Exchange code for access token
        $token_result = $this->exchange_code_for_token($code);
        if (!$token_result) {
            return array(
                'success' => false,
                'message' => __('Failed to get access token from GitHub.', 'wp-cdn-integration')
            );
        }
        
        // Get user information
        $user_info = $this->get_user_info($token_result['access_token']);
        if (!$user_info) {
            return array(
                'success' => false,
                'message' => __('Failed to get user information from GitHub.', 'wp-cdn-integration')
            );
        }
        
        // Auto-create repository if needed
        $repo_result = $this->auto_setup_repository($token_result['access_token'], $user_info['login']);
        if (!$repo_result['success']) {
            return $repo_result;
        }
        
        // Save settings automatically
        $settings = get_option('wp_cdn_integration_settings', array());
        $settings['github_username'] = $user_info['login'];
        $settings['github_repository'] = $repo_result['repository_name'];
        $settings['github_branch'] = 'main';
        $settings['github_token'] = $token_result['access_token'];
        update_option('wp_cdn_integration_settings', $settings);
        
        return array(
            'success' => true,
            'message' => sprintf(
                __('Successfully connected to GitHub! Repository "%s/%s" is ready to use.', 'wp-cdn-integration'),
                $user_info['login'],
                $repo_result['repository_name']
            ),
            'user' => $user_info,
            'repository' => $repo_result['repository_name']
        );
    }
    
    /**
     * Auto-setup GitHub repository for CDN
     * 
     * @since 2.0.0
     * @param string $access_token GitHub access token
     * @param string $username GitHub username
     * @return array Setup result
     */
    private function auto_setup_repository($access_token, $username) {
        $site_name = get_bloginfo('name');
        $repo_name = sanitize_title($site_name . '-cdn-assets');
        
        // Check if repository already exists
        $existing_repo = $this->check_repository_exists($access_token, $username, $repo_name);
        if ($existing_repo) {
            return array(
                'success' => true,
                'repository_name' => $repo_name,
                'message' => __('Using existing repository.', 'wp-cdn-integration')
            );
        }
        
        // Create new repository
        $repo_data = array(
            'name' => $repo_name,
            'description' => sprintf(__('CDN assets for %s - Powered by WordPress CDN Integration', 'wp-cdn-integration'), $site_name),
            'private' => false,
            'auto_init' => true,
            'gitignore_template' => 'Node'
        );
        
        $response = wp_remote_post('https://api.github.com/user/repos', array(
            'headers' => array(
                'Authorization' => 'token ' . $access_token,
                'Accept' => 'application/vnd.github.v3+json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-CDN-Integration/' . WP_CDN_INTEGRATION_VERSION
            ),
            'body' => json_encode($repo_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Failed to create GitHub repository.', 'wp-cdn-integration')
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'repository_name' => $repo_name,
                'message' => __('Repository created successfully.', 'wp-cdn-integration')
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to create GitHub repository.', 'wp-cdn-integration')
        );
    }
    
    /**
     * Check if repository exists
     * 
     * @since 2.0.0
     * @param string $access_token GitHub access token
     * @param string $username GitHub username
     * @param string $repo_name Repository name
     * @return bool
     */
    private function check_repository_exists($access_token, $username, $repo_name) {
        $response = wp_remote_get("https://api.github.com/repos/{$username}/{$repo_name}", array(
            'headers' => array(
                'Authorization' => 'token ' . $access_token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress-CDN-Integration/' . WP_CDN_INTEGRATION_VERSION
            ),
            'timeout' => 30
        ));
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @since 2.0.0
     * @param string $code Authorization code
     * @return array|false
     */
    public function exchange_code_for_token($code) {
        $response = wp_remote_post('https://github.com/login/oauth/access_token', array(
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'WordPress-CDN-Integration/' . WP_CDN_INTEGRATION_VERSION
            ),
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('GitHub OAuth Error: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            error_log('GitHub OAuth Error: Status ' . $status_code . ', Response: ' . $body);
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            error_log('GitHub OAuth Error: ' . $data['error_description']);
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get GitHub user information
     * 
     * @since 2.0.0
     * @param string $access_token Access token
     * @return array|false
     */
    public function get_user_info($access_token) {
        $response = wp_remote_get('https://api.github.com/user', array(
            'headers' => array(
                'Authorization' => 'token ' . $access_token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress-CDN-Integration/' . WP_CDN_INTEGRATION_VERSION
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Create Personal Access Token
     * 
     * @since 2.0.0
     * @param string $access_token OAuth access token
     * @return array|false
     */
    public function create_personal_access_token($access_token) {
        $token_data = array(
            'scopes' => array('repo'),
            'note' => 'WordPress CDN Integration - ' . get_site_url(),
            'note_url' => get_site_url()
        );
        
        $response = wp_remote_post('https://api.github.com/authorizations', array(
            'headers' => array(
                'Authorization' => 'token ' . $access_token,
                'Accept' => 'application/vnd.github.v3+json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-CDN-Integration/' . WP_CDN_INTEGRATION_VERSION
            ),
            'body' => json_encode($token_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return $response_data;
        }
        
        return false;
    }
    
    /**
     * Create GitHub repository
     * 
     * @since 2.0.0
     * @param string $access_token OAuth access token
     * @param string $repo_name Repository name
     * @param string $repo_description Repository description
     * @return array|false
     */
    public function create_repository($access_token, $repo_name, $repo_description = '') {
        $repo_data = array(
            'name' => $repo_name,
            'description' => $repo_description ?: 'WordPress CDN Integration Repository',
            'private' => false, // Must be public for jsDelivr CDN
            'auto_init' => true,
            'gitignore_template' => 'Node'
        );
        
        $response = wp_remote_post('https://api.github.com/user/repos', array(
            'headers' => array(
                'Authorization' => 'token ' . $access_token,
                'Accept' => 'application/vnd.github.v3+json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-CDN-Integration/' . WP_CDN_INTEGRATION_VERSION
            ),
            'body' => json_encode($repo_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return $response_data;
        }
        
        return false;
    }
    
    /**
     * Get configuration status message
     * 
     * @since 2.0.0
     * @return string
     */
    public function get_status_message() {
        return __('GitHub integration is ready! No setup required. Click "Connect with GitHub" to get started.', 'wp-cdn-integration');
    }
    
    /**
     * Get setup instructions
     * 
     * @since 2.0.0
     * @return array
     */
    public function get_setup_instructions() {
        return array(
            'title' => __('GitHub OAuth Setup Instructions', 'wp-cdn-integration'),
            'steps' => array(
                array(
                    'title' => __('Create GitHub OAuth App', 'wp-cdn-integration'),
                    'description' => __('Go to GitHub Settings → Developer settings → OAuth Apps → New OAuth App', 'wp-cdn-integration'),
                    'details' => array(
                        __('Application name: WordPress CDN Integration', 'wp-cdn-integration'),
                        __('Homepage URL: ' . home_url(), 'wp-cdn-integration'),
                        __('Authorization callback URL: ' . $this->redirect_uri, 'wp-cdn-integration')
                    )
                ),
                array(
                    'title' => __('Get OAuth Credentials', 'wp-cdn-integration'),
                    'description' => __('Copy the Client ID and Client Secret from your OAuth App', 'wp-cdn-integration')
                ),
                array(
                    'title' => __('Configure Plugin', 'wp-cdn-integration'),
                    'description' => __('Enter the credentials in the plugin settings below', 'wp-cdn-integration')
                )
            )
        );
    }
}
