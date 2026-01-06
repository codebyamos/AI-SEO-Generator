<?php
/**
 * GitHub Updater Class
 * 
 * Enables automatic updates from GitHub releases
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_GitHub_Updater {
    
    private static $instance = null;
    
    private $slug;
    private $plugin_file;
    private $plugin_data;
    private $github_repo = 'codebyamos/AI-SEO-Generator';
    private $github_api_url = 'https://api.github.com/repos/';
    private $transient_name = 'ai_seo_github_update';
    private $transient_expiration = 604800; // 7 days (weekly check)
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->slug = plugin_basename(AI_SEO_PLUGIN_FILE);
        $this->plugin_file = AI_SEO_PLUGIN_FILE;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_directory_name'), 10, 4);
        add_filter('plugin_action_links_' . $this->slug, array($this, 'add_action_links'));
        
        // Handle manual update check
        add_action('admin_init', array($this, 'handle_manual_check'));
    }
    
    /**
     * Add plugin action links
     */
    public function add_action_links($links) {
        $check_url = wp_nonce_url(
            add_query_arg(
                array(
                    'ai_seo_check_update' => '1',
                ),
                admin_url('plugins.php')
            ),
            'ai_seo_check_update'
        );
        
        $new_links = array(
            '<a href="' . esc_url($check_url) . '">' . __('Check for updates', 'ai-seo-generator') . '</a>',
        );
        
        return array_merge($new_links, $links);
    }
    
    /**
     * Handle manual update check
     */
    public function handle_manual_check() {
        // Display any stored update check message
        if (get_transient('ai_seo_update_message')) {
            add_action('admin_notices', array($this, 'display_update_message'));
        }
        
        if (!isset($_GET['ai_seo_check_update']) || !current_user_can('update_plugins')) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'ai_seo_check_update')) {
            return;
        }
        
        // Clear the transient to force a fresh check
        delete_transient($this->transient_name);
        delete_site_transient('update_plugins');
        
        // Force WordPress to check for plugin updates
        wp_update_plugins();
        
        // Check if update is available
        $update_info = $this->get_github_release_info();
        
        if ($update_info && isset($update_info['tag_name'])) {
            $latest_version = ltrim($update_info['tag_name'], 'v');
            $current_version = AI_SEO_VERSION;
            
            if (version_compare($latest_version, $current_version, '>')) {
                set_transient('ai_seo_update_message', array(
                    'type' => 'info',
                    'message' => sprintf(
                        __('<strong>AI SEO Generator:</strong> A new version (%s) is available! <a href="%s">Update now</a>.', 'ai-seo-generator'),
                        esc_html($latest_version),
                        esc_url(admin_url('plugins.php'))
                    )
                ), 30);
            } else {
                set_transient('ai_seo_update_message', array(
                    'type' => 'success',
                    'message' => '<strong>AI SEO Generator:</strong> ' . __('You are running the latest version.', 'ai-seo-generator')
                ), 30);
            }
        } else {
            set_transient('ai_seo_update_message', array(
                'type' => 'warning',
                'message' => '<strong>AI SEO Generator:</strong> ' . __('Unable to check for updates. Please try again later.', 'ai-seo-generator')
            ), 30);
        }
        
        // Redirect to remove query args
        wp_redirect(admin_url('plugins.php'));
        exit;
    }
    
    /**
     * Display stored update message
     */
    public function display_update_message() {
        $message_data = get_transient('ai_seo_update_message');
        if ($message_data) {
            delete_transient('ai_seo_update_message');
            echo '<div class="notice notice-' . esc_attr($message_data['type']) . ' is-dismissible"><p>';
            echo wp_kses_post($message_data['message']);
            echo '</p></div>';
        }
    }
    
    /**
     * Get plugin data
     */
    private function get_plugin_data() {
        if (empty($this->plugin_data)) {
            $this->plugin_data = get_plugin_data($this->plugin_file);
        }
        return $this->plugin_data;
    }
    
    /**
     * Get release info from GitHub
     */
    private function get_github_release_info() {
        $cached = get_transient($this->transient_name);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = $this->github_api_url . $this->github_repo . '/releases/latest';
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
            'timeout' => 10,
        ));
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $release_info = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($release_info)) {
            set_transient($this->transient_name, $release_info, $this->transient_expiration);
        }
        
        return $release_info;
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $release_info = $this->get_github_release_info();
        
        if (!$release_info || !isset($release_info['tag_name'])) {
            return $transient;
        }
        
        $latest_version = ltrim($release_info['tag_name'], 'v');
        $current_version = isset($transient->checked[$this->slug]) ? $transient->checked[$this->slug] : AI_SEO_VERSION;
        
        if (version_compare($latest_version, $current_version, '>')) {
            // Find the zip download URL
            $download_url = $release_info['zipball_url'];
            
            // Check for uploaded asset (preferred)
            if (!empty($release_info['assets'])) {
                foreach ($release_info['assets'] as $asset) {
                    if (strpos($asset['name'], '.zip') !== false) {
                        $download_url = $asset['browser_download_url'];
                        break;
                    }
                }
            }
            
            $plugin_data = $this->get_plugin_data();
            
            $transient->response[$this->slug] = (object) array(
                'slug' => dirname($this->slug),
                'plugin' => $this->slug,
                'new_version' => $latest_version,
                'url' => 'https://github.com/' . $this->github_repo,
                'package' => $download_url,
                'icons' => array(),
                'banners' => array(),
                'tested' => '',
                'requires_php' => '7.4',
            );
        }
        
        return $transient;
    }
    
    /**
     * Get plugin info for the WordPress plugin details popup
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }
        
        $release_info = $this->get_github_release_info();
        
        if (!$release_info) {
            return $result;
        }
        
        $plugin_data = $this->get_plugin_data();
        
        $result = (object) array(
            'name' => $plugin_data['Name'],
            'slug' => dirname($this->slug),
            'version' => ltrim($release_info['tag_name'], 'v'),
            'author' => $plugin_data['AuthorName'],
            'author_profile' => $plugin_data['AuthorURI'],
            'homepage' => 'https://github.com/' . $this->github_repo,
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'downloaded' => 0,
            'last_updated' => $release_info['published_at'],
            'sections' => array(
                'description' => $plugin_data['Description'],
                'changelog' => nl2br(esc_html($release_info['body'])),
            ),
            'download_link' => $release_info['zipball_url'],
        );
        
        // Check for uploaded asset
        if (!empty($release_info['assets'])) {
            foreach ($release_info['assets'] as $asset) {
                if (strpos($asset['name'], '.zip') !== false) {
                    $result->download_link = $asset['browser_download_url'];
                    break;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Fix the directory name after extraction
     * GitHub zipball extracts to owner-repo-hash format, we need to rename it
     */
    public function fix_directory_name($source, $remote_source, $upgrader, $hook_extra = array()) {
        global $wp_filesystem;
        
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->slug) {
            return $source;
        }
        
        $correct_name = dirname($this->slug);
        $new_source = trailingslashit($remote_source) . $correct_name;
        
        if ($source !== $new_source) {
            if ($wp_filesystem->move($source, $new_source)) {
                return $new_source;
            }
        }
        
        return $source;
    }
}
