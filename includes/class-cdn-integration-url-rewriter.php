<?php
/**
 * Class for rewriting URLs to use the CDN.
 *
 * @since 1.0.0
 */
class CDN_Integration_URL_Rewriter {

    /**
     * The helper instance.
     *
     * @since 1.0.0
     * @access protected
     * @var CDN_Integration_Helper $helper
     */
    protected $helper;

    /**
     * URL cache for better performance.
     *
     * @since 1.0.0
     * @access protected
     * @var array $url_cache
     */
    protected $url_cache = array();

    /**
     * Initialize the URL rewriter.
     *
     * @since 1.0.0
     * @param CDN_Integration_Helper $helper The helper instance.
     */
    public function __construct($helper) {
        $this->helper = $helper;
    }

/**
 * Add CDN configuration script to the head.
 *
 * @since 1.0.0
 */
public function add_cdn_config_script() {
    // Do not add to admin area
    if (is_admin()) {
        return;
    }
    
    if (!$this->helper->is_enabled()) {
        return;
    }
    
    $cdn_base_url = $this->helper->get_cdn_base_url();
    if (empty($cdn_base_url)) {
        return;
    }
    
    // Get the list of custom URLs
    $custom_urls = $this->helper->get_custom_urls();
    
    // If no custom URLs, no need to continue
    if (empty($custom_urls)) {
        return;
    }
    
    $config = array(
        'baseUrl' => site_url(),
        'cdnBaseUrl' => $cdn_base_url,
        'customUrls' => $custom_urls,
        'excludedPaths' => $this->helper->get_excluded_paths()
    );
    
    ?>
    <script type="text/javascript">
    /* WordPress CDN Integration Config */
    window.wpCdnConfig = <?php echo json_encode($config); ?>;
    
    /* Dynamic URL rewriting for late-loaded resources */
    (function() {
        var cdnBaseUrl = "<?php echo esc_js($cdn_base_url); ?>";
        if (!cdnBaseUrl) return;
        
        // Helper function to check if a URL should be rewritten
        function shouldRewriteUrl(url) {
            if (!url) return false;
            
            // Skip if not from our domain
            if (url.indexOf('http') === 0 && url.indexOf(window.wpCdnConfig.baseUrl) !== 0) {
                return false;
            }
            
            // Skip data URLs
            if (url.indexOf('data:') === 0) {
                return false;
            }
            
            // Skip admin URLs
            if (url.indexOf('/wp-admin') !== -1 || url.indexOf('/wp-login') !== -1) {
                return false;
            }
            
            // Get the path part of the URL
            var path = url;
            if (url.indexOf('http') === 0) {
                var parser = document.createElement('a');
                parser.href = url;
                path = parser.pathname;
            }
            
            // Make sure path starts with a slash
            if (path.indexOf('/') !== 0) {
                path = '/' + path;
            }
            
            // Remove query string for comparison
            path = path.replace(/\?.*$/, '');
            
            // Check if path is excluded
            for (var i = 0; i < window.wpCdnConfig.excludedPaths.length; i++) {
                var excludedPath = window.wpCdnConfig.excludedPaths[i];
                
                // Check for wildcard at the end
                if (excludedPath.slice(-1) === '*') {
                    var basePath = excludedPath.slice(0, -1);
                    if (path.indexOf(basePath) === 0) {
                        return false;
                    }
                } else if (path === excludedPath) {
                    return false;
                }
            }
            
            // Check if path matches any custom URL
            for (var j = 0; j < window.wpCdnConfig.customUrls.length; j++) {
                var customUrl = window.wpCdnConfig.customUrls[j].trim();
                
                // Make sure custom URL starts with a slash
                if (customUrl.indexOf('/') !== 0) {
                    customUrl = '/' + customUrl;
                }
                
                // Remove query string from custom URL for comparison
                customUrl = customUrl.replace(/\?.*$/, '');
                
                // Check for exact match
                if (customUrl === path) {
                    return true;
                }
                // Check for wildcard match
                else if (customUrl.slice(-1) === '*') {
                    var wildcardBase = customUrl.slice(0, -1);
                    if (path.indexOf(wildcardBase) === 0) {
                        return true;
                    }
                }
            }
            
            return false;
        }
        
        // Helper function to rewrite a URL
        function rewriteUrl(url) {
            if (!shouldRewriteUrl(url)) return url;
            
            var path = url;
            if (url.indexOf('http') === 0) {
                var parser = document.createElement('a');
                parser.href = url;
                path = parser.pathname;
            }
            
            // Remove leading slash for jsDelivr
            path = path.replace(/^\//, '');
            
            return cdnBaseUrl + path;
        }
            
            // Patch for dynamically added script/link elements
            var originalCreateElement = document.createElement;
            document.createElement = function(tagName) {
                var element = originalCreateElement.apply(document, arguments);
                
                if (tagName.toLowerCase() === 'script' || tagName.toLowerCase() === 'link') {
                    var originalSetAttribute = element.setAttribute;
                    element.setAttribute = function(name, value) {
                        if ((name === 'src' || name === 'href') && value && shouldRewriteUrl(value)) {
                            value = rewriteUrl(value);
                        }
                        return originalSetAttribute.call(this, name, value);
                    };
                }
                
                return element;
            };
            
            // Also observe DOM changes to rewrite URLs in dynamically added elements
            if (window.MutationObserver) {
                new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(node) {
                                if (node.tagName) {
                                    // For script and link tags
                                    if (node.tagName.toLowerCase() === 'script' && node.src) {
                                        if (shouldRewriteUrl(node.src)) {
                                            node.src = rewriteUrl(node.src);
                                        }
                                    } else if (node.tagName.toLowerCase() === 'link' && node.href) {
                                        if (shouldRewriteUrl(node.href)) {
                                            node.href = rewriteUrl(node.href);
                                        }
                                    }
                                    
                                    // For img tags
                                    if (node.tagName.toLowerCase() === 'img' && node.src) {
                                        if (shouldRewriteUrl(node.src)) {
                                            node.src = rewriteUrl(node.src);
                                        }
                                    }
                                }
                            });
                        }
                    });
                }).observe(document, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
    }

// In class-cdn-integration-url-rewriter.php
// Modify the rewrite_url method to only rewrite custom URLs
/**
 * Rewrite URLs to use the CDN.
 *
 * @since 1.0.0
 * @param string $url URL to potentially rewrite.
 * @return string Rewritten URL.
 */
public function rewrite_url($url) {
    // Skip if disabled, empty, or admin URL
    if (!$this->helper->is_enabled() || empty($url) || is_admin()) {
        return $url;
    }
    
    if (strpos($url, '/wp-admin') !== false || strpos($url, '/wp-login') !== false) {
        return $url;
    }
    
    // Check cache
    $cache_key = md5($url);
    if (isset($this->url_cache[$cache_key])) {
        return $this->url_cache[$cache_key];
    }
    
    $cdn_base_url = $this->helper->get_cdn_base_url();
    if (empty($cdn_base_url)) {
        return $url;
    }
    
    // Get custom URLs
    $custom_urls = $this->helper->get_custom_urls();
    if (empty($custom_urls)) {
        $this->url_cache[$cache_key] = $url;
        return $url;
    }
    
    // Normalize the URL for matching
    $normalized_url = $this->normalize_url_for_matching($url);
    if (!$normalized_url) {
        $this->url_cache[$cache_key] = $url;
        return $url;
    }
    
    // Check for matches in custom URLs
    $match_found = false;
    foreach ($custom_urls as $pattern) {
        $pattern = trim($pattern);
        
        // Normalize the pattern
        if (strpos($pattern, '/') !== 0) {
            $pattern = '/' . $pattern;
        }
        
        // Is it a wildcard pattern?
        if (substr($pattern, -1) === '*') {
            $pattern_base = substr($pattern, 0, -1);
            if (strpos($normalized_url, $pattern_base) === 0) {
                $match_found = true;
                break;
            }
        } 
        // Exact match
        else if ($pattern === $normalized_url) {
            $match_found = true;
            break;
        }
    }
    
    if (!$match_found) {
        $this->url_cache[$cache_key] = $url;
        return $url;
    }
    
    // Create CDN URL
    $site_url = site_url();
    $cdn_url = $url;
    
    // Handle absolute URLs
    if (strpos($url, $site_url) === 0) {
        $path = substr($url, strlen($site_url));
        $cdn_url = rtrim($cdn_base_url, '/') . '/' . ltrim($path, '/');
    } 
    // Handle relative URLs
    else if (strpos($url, '/') === 0) {
        $cdn_url = rtrim($cdn_base_url, '/') . $url;
    }
    
    if ($this->helper->is_debug_enabled()) {
        $this->helper->log("Rewrote URL: {$url} to {$cdn_url}", 'debug');
    }
    
    $this->url_cache[$cache_key] = $cdn_url;
    return $cdn_url;
}

/**
 * Normalize a URL for matching with patterns.
 *
 * @param string $url The URL to normalize.
 * @return string|false The normalized URL or false if invalid.
 */
private function normalize_url_for_matching($url) {
    // Handle absolute URLs
    if (strpos($url, 'http') === 0) {
        $site_url = site_url();
        if (strpos($url, $site_url) !== 0) {
            return false; // External URL
        }
        $url = substr($url, strlen($site_url));
    }
    
    // Ensure URL starts with a slash
    if (strpos($url, '/') !== 0) {
        $url = '/' . $url;
    }
    
    // Remove query string
    $url = preg_replace('/\?.*$/', '', $url);
    
    return $url;
}
/**
 * Rewrite URLs to use the CDN.
 *
 * @since 1.0.0
 * @param string $url URL to potentially rewrite.
 * @return string Rewritten URL.
 */
public function rewrite_ur2l($url) {
    // Skip if in admin area
    if (is_admin()) {
        return $url;
    }

    if (!$this->helper->is_enabled()) {
        return $url;
    }
    
    if (empty($url)) {
        return $url;
    }
    
    // Skip admin URLs
    if (strpos($url, '/wp-admin') !== false || strpos($url, '/wp-login') !== false) {
        return $url;
    }
    
    // Check URL cache first
    $cache_key = md5($url);
    if (isset($this->url_cache[$cache_key])) {
        return $this->url_cache[$cache_key];
    }
    
    $cdn_base_url = $this->helper->get_cdn_base_url();
    if (empty($cdn_base_url)) {
        $this->url_cache[$cache_key] = $url;
        return $url;
    }
    
    // Get custom URLs list
    $custom_urls = $this->helper->get_custom_urls();
    
    // If no custom URLs are defined, return original URL
    if (empty($custom_urls)) {
        $this->url_cache[$cache_key] = $url;
        return $url;
    }
    
    // Normalize URL for comparison
    $normalized_url = $url;
    if (strpos($url, 'http') === 0) {
        $site_url = site_url();
        
        // Skip external URLs
        if (strpos($url, $site_url) !== 0) {
            $this->url_cache[$cache_key] = $url;
            return $url;
        }
        
        // Extract path from URL
        $parsed_url = parse_url($url);
        if (isset($parsed_url['path'])) {
            $normalized_url = $parsed_url['path'];
        } else {
            $this->url_cache[$cache_key] = $url;
            return $url;
        }
    }
    
    // Ensure URL starts with a slash for comparison
    if (strpos($normalized_url, '/') !== 0) {
        $normalized_url = '/' . $normalized_url;
    }
    
    // Remove query string for comparison
    $normalized_url = preg_replace('/\?.*$/', '', $normalized_url);
    
    // Check if URL matches any of the custom URLs
    $match_found = false;
    foreach ($custom_urls as $custom_url) {
        // Normalize custom URL for comparison
        $custom_url = trim($custom_url);
        if (strpos($custom_url, '/') !== 0) {
            $custom_url = '/' . $custom_url;
        }
        $custom_url = preg_replace('/\?.*$/', '', $custom_url);
        
        // Check for exact match or wildcard match
        if ($custom_url === $normalized_url) {
            $match_found = true;
            break;
        } elseif (substr($custom_url, -1) === '*') {
            // Handle wildcard at the end (e.g., /wp-content/themes/mytheme/*)
            $wildcard_base = substr($custom_url, 0, -1);
            if (strpos($normalized_url, $wildcard_base) === 0) {
                $match_found = true;
                break;
            }
        }
    }
    
    // If no match found, return original URL
    if (!$match_found) {
        $this->url_cache[$cache_key] = $url;
        return $url;
    }
    
    // Get remote path for CDN
    $remote_path = $this->helper->get_remote_path_for_url($url);
    
    // Create CDN URL
    $cdn_url = rtrim($cdn_base_url, '/') . '/' . ltrim($remote_path, '/');
    
    // Debug log
    if ($this->helper->is_debug_enabled()) {
        $this->helper->log("Rewrote URL: {$url} to {$cdn_url}", 'debug');
    }
    
    // Cache and return
    $this->url_cache[$cache_key] = $cdn_url;
	
    return $cdn_url;
}

    /**
     * Rewrite URLs in content.
     *
     * @since 1.0.0
     * @param string $content Content to rewrite URLs in.
     * @return string Content with rewritten URLs.
     */
    public function rewrite_content_urls($content) {
        // Skip if in admin area
        if (is_admin()) {
            return $content;
        }

        if (!$this->helper->is_enabled() || empty($content)) {
            return $content;
        }
        
        $cdn_base_url = $this->helper->get_cdn_base_url();
        if (empty($cdn_base_url)) {
            return $content;
        }
        
        // Define patterns to find URLs in content
        $patterns = array(
            // Images
            '/<img[^>]*src=[\'"]([^\'"]+)[\'"][^>]*>/i',
            
            // CSS and JS
            '/<link[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>/i',
            '/<script[^>]*src=[\'"]([^\'"]+)[\'"][^>]*>/i',
            
            // Background in style attributes
            '/style=[\'"][^"\']*background(-image)?:\s*url\([\'"]?([^\'")\s]+)[\'"]?\)[^"\']*[\'"]/',
            
            // CSS url() in style tags
            '/<style[^>]*>(.*?)<\/style>/is'
        );
        
        foreach ($patterns as $pattern) {
            // Special handling for CSS styles
            if (strpos($pattern, '<style') === 0) {
                preg_match_all($pattern, $content, $style_matches);
                if (!empty($style_matches[1])) {
                    foreach ($style_matches[1] as $i => $style_content) {
                        // Find all url() in CSS
                        preg_match_all('/url\([\'"]?([^\'")\s]+)[\'"]?\)/i', $style_content, $url_matches);
                        if (!empty($url_matches[1])) {
                            $new_style = $style_content;
                            foreach ($url_matches[1] as $url) {
                                $new_url = $this->rewrite_url($url);
                                if ($new_url !== $url) {
                                    $new_style = str_replace($url, $new_url, $new_style);
                                }
                            }
                            if ($new_style !== $style_content) {
                                $content = str_replace($style_content, $new_style, $content);
                            }
                        }
                    }
                }
                continue;
            }
            
            // Handle background URLs in style attributes
            if (strpos($pattern, 'background') !== false) {
                preg_match_all($pattern, $content, $matches);
                if (!empty($matches[2])) {
                    foreach ($matches[2] as $i => $url) {
                        $new_url = $this->rewrite_url($url);
                        if ($new_url !== $url) {
                            $old_style = $matches[0][$i];
                            $new_style = str_replace($url, $new_url, $old_style);
                            $content = str_replace($old_style, $new_style, $content);
                        }
                    }
                }
                continue;
            }
            
            // Handle standard URL attributes (src, href)
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $i => $url) {
                    $new_url = $this->rewrite_url($url);
                    if ($new_url !== $url) {
                        $content = str_replace($url, $new_url, $content);
                    }
                }
            }
        }
        
        return $content;
    }

    /**
     * Rewrite image source.
     *
     * @since 1.0.0
     * @param array $image Image data from wp_get_attachment_image_src.
     * @return array Modified image data.
     */
    public function rewrite_image_src($image) {
        // Skip if in admin area
        if (is_admin()) {
            return $image;
        }

        if (!$this->helper->is_enabled() || !is_array($image) || empty($image[0])) {
            return $image;
        }
        
        $image[0] = $this->rewrite_url($image[0]);
        return $image;
    }

    /**
     * Rewrite image srcset.
     *
     * @since 1.0.0
     * @param array $sources Array of image sources.
     * @return array Modified sources.
     */
    public function rewrite_image_srcset($sources) {
        // Skip if in admin area
        if (is_admin()) {
            return $sources;
        }

        if (!$this->helper->is_enabled() || !is_array($sources)) {
            return $sources;
        }
        
        foreach ($sources as &$source) {
            if (isset($source['url'])) {
                $source['url'] = $this->rewrite_url($source['url']);
            }
        }
        
        return $sources;
    }

/**
 * Determine if a URL should be served from CDN.
 *
 * @since 1.0.0
 * @param string $url URL to check.
 * @return bool True if the URL should be served from CDN, false otherwise.
 */
protected function should_use_cdn($url) {
    // Skip empty URLs and data URLs
    if (empty($url) || strpos($url, 'data:') === 0) {
        return false;
    }
    
    // Skip admin URLs
    if (strpos($url, '/wp-admin') !== false || strpos($url, '/wp-login') !== false) {
        return false;
    }
    
    // Get custom URLs list
    $custom_urls = $this->helper->get_custom_urls();
    
    // If no custom URLs are defined, return false
    if (empty($custom_urls)) {
        return false;
    }
    
    // Normalize URL
    $normalized_url = $url;
    if (strpos($url, 'http') === 0) {
        $site_url = site_url();
        
        // Skip external URLs
        if (strpos($url, $site_url) !== 0) {
            return false;
        }
        
        // Extract path from URL
        $parsed_url = parse_url($url);
        if (isset($parsed_url['path'])) {
            $normalized_url = $parsed_url['path'];
        } else {
            return false;
        }
    }
    
    // Ensure URL starts with a slash for comparison
    if (strpos($normalized_url, '/') !== 0) {
        $normalized_url = '/' . $normalized_url;
    }
    
    // Remove query string for comparison
    $normalized_url = preg_replace('/\?.*$/', '', $normalized_url);
    
    // Check excluded paths
    if ($this->helper->is_excluded_path($normalized_url)) {
        return false;
    }
    
    // Check if URL matches any of the custom URLs
    foreach ($custom_urls as $custom_url) {
        // Normalize custom URL for comparison
        $custom_url = trim($custom_url);
        if (strpos($custom_url, '/') !== 0) {
            $custom_url = '/' . $custom_url;
        }
        $custom_url = preg_replace('/\?.*$/', '', $custom_url);
        
        // Check for exact match or wildcard match
        if ($custom_url === $normalized_url) {
            return true;
        } elseif (substr($custom_url, -1) === '*') {
            // Handle wildcard at the end (e.g., /wp-content/themes/mytheme/*)
            $wildcard_base = substr($custom_url, 0, -1);
            if (strpos($normalized_url, $wildcard_base) === 0) {
                return true;
            }
        }
    }
    
    return false;
}

}