<?php
/**
 * Admin Menu Handler
 */

class AI_SEO_Admin_Menu {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('AI SEO Generator', 'ai-seo-generator'),
            __('AI SEO Generator', 'ai-seo-generator'),
            'manage_options',
            'ai-seo-generator',
            array($this, 'render_dashboard_page'),
            'dashicons-edit-large',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'ai-seo-generator',
            __('Dashboard', 'ai-seo-generator'),
            __('Dashboard', 'ai-seo-generator'),
            'manage_options',
            'ai-seo-generator',
            array($this, 'render_dashboard_page')
        );
        
        // Queue submenu (includes scheduling functionality)
        add_submenu_page(
            'ai-seo-generator',
            __('Article Queue', 'ai-seo-generator'),
            __('Article Queue', 'ai-seo-generator'),
            'manage_options',
            'ai-seo-generator-queue',
            array($this, 'render_queue_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'ai-seo-generator',
            __('Settings', 'ai-seo-generator'),
            __('Settings', 'ai-seo-generator'),
            'manage_options',
            'ai-seo-generator-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render Dashboard Page
     */
    public function render_dashboard_page() {
        require_once AI_SEO_PLUGIN_DIR . 'dashboard.php';
    }
    
    /**
     * Render Queue Page
     */
    public function render_queue_page() {
        require_once AI_SEO_PLUGIN_DIR . 'queue.php';
    }
    
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        require_once AI_SEO_PLUGIN_DIR . 'settings.php';
    }
}
