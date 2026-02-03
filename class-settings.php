<?php
/**
 * Settings Handler
 */

class AI_SEO_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_AI_SEO_test_gemini', array($this, 'test_gemini_connection'));
        add_action('wp_ajax_AI_SEO_test_sheets', array($this, 'test_sheets_connection'));
        add_action('wp_ajax_AI_SEO_test_email', array($this, 'test_email'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Gemini API settings
        register_setting('AI_SEO_settings', 'AI_SEO_gemini_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        // Google Sheets settings
        register_setting('AI_SEO_settings', 'AI_SEO_google_sheets_enabled', array(
            'type' => 'boolean',
            'default' => false,
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_google_sheets_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_google_credentials_path', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        // Email settings
        register_setting('AI_SEO_settings', 'AI_SEO_email_notifications', array(
            'type' => 'boolean',
            'default' => true,
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_notification_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
        ));
        
        // Content settings
        register_setting('AI_SEO_settings', 'AI_SEO_default_post_status', array(
            'type' => 'string',
            'default' => 'draft',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_include_tables', array(
            'type' => 'boolean',
            'default' => false,
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_image_count', array(
            'type' => 'integer',
            'default' => 1,
            'sanitize_callback' => 'absint',
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_image_orientation', array(
            'type' => 'string',
            'default' => 'landscape',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_internal_links', array(
            'type' => 'integer',
            'default' => 2,
            'sanitize_callback' => 'absint',
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_external_links', array(
            'type' => 'integer',
            'default' => 2,
            'sanitize_callback' => 'absint',
        ));
        
        register_setting('AI_SEO_settings', 'AI_SEO_business_description', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_business_description'),
        ));
    }
    
    /**
     * Sanitize business description - strip slashes and allow apostrophes
     */
    public function sanitize_business_description($value) {
        // Remove excessive slashes that may have been added
        $value = stripslashes($value);
        // Allow safe HTML
        return wp_kses_post($value);
    }
    
    /**
     * Test Gemini API connection
     */
    public function test_gemini_connection() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'ai-seo-generator')));
        }
        
        // Temporarily set API key for testing
        $original_key = get_option('AI_SEO_gemini_api_key');
        update_option('AI_SEO_gemini_api_key', $api_key);
        
        $gemini = AI_SEO_Gemini_API::get_instance();
        $test_result = $gemini->generate_article('test connection', array());
        
        // Restore original key
        update_option('AI_SEO_gemini_api_key', $original_key);
        
        if (is_wp_error($test_result)) {
            wp_send_json_error(array('message' => $test_result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('Connection successful!', 'ai-seo-generator')));
    }
    
    /**
     * Test Google Sheets connection
     */
    public function test_sheets_connection() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        try {
            $sheets = AI_SEO_Google_Sheets::get_instance();
            $result = $sheets->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            error_log('AI SEO test_sheets_connection error: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Send test email
     */
    public function test_email() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'ai-seo-generator')));
        }
        
        $notifications = AI_SEO_Email_Notifications::get_instance();
        $result = $notifications->send_test_email($email);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Test email sent successfully!', 'ai-seo-generator')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send test email', 'ai-seo-generator')));
        }
    }
    
    /**
     * Get all settings
     */
    public function get_all_settings() {
        return array(
            'gemini_api_key' => get_option('AI_SEO_gemini_api_key', ''),
            'google_sheets_enabled' => get_option('AI_SEO_google_sheets_enabled', false),
            'google_sheets_id' => get_option('AI_SEO_google_sheets_id', ''),
            'google_credentials_path' => get_option('AI_SEO_google_credentials_path', ''),
            'email_notifications' => get_option('AI_SEO_email_notifications', true),
            'notification_email' => get_option('AI_SEO_notification_email', get_option('admin_email')),
            'default_post_status' => get_option('AI_SEO_default_post_status', 'draft'),
            'include_tables' => get_option('AI_SEO_include_tables', false),
            'image_count' => get_option('AI_SEO_image_count', 1),
            'image_orientation' => get_option('AI_SEO_image_orientation', 'landscape'),
            'internal_links' => get_option('AI_SEO_internal_links', 2),
            'external_links' => get_option('AI_SEO_external_links', 2),
            'business_description' => get_option('AI_SEO_business_description', ''),
        );
    }
}
