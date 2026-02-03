<?php
/**
 * AJAX Handlers
 */

class AI_SEO_AJAX {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_ai_seo_generate_article', array($this, 'generate_article'));
        add_action('wp_ajax_ai_seo_add_to_queue', array($this, 'add_to_queue'));
        add_action('wp_ajax_ai_seo_batch_add_to_queue', array($this, 'batch_add_to_queue'));
        add_action('wp_ajax_ai_seo_fetch_sheets_keywords', array($this, 'fetch_sheets_keywords'));
        add_action('wp_ajax_ai_seo_batch_schedule', array($this, 'batch_schedule'));
        add_action('wp_ajax_ai_seo_regenerate_title', array($this, 'regenerate_title'));
        add_action('wp_ajax_ai_seo_regenerate_image', array($this, 'regenerate_image'));
        add_action('wp_ajax_ai_seo_generate_custom_image', array($this, 'generate_custom_image'));
        add_action('wp_ajax_ai_seo_set_featured_image', array($this, 'set_featured_image'));
        add_action('wp_ajax_ai_seo_import_sheets_to_queue', array($this, 'import_sheets_to_queue'));
        add_action('wp_ajax_ai_seo_remove_from_queue', array($this, 'remove_from_queue'));
        add_action('wp_ajax_ai_seo_test_sheet_update', array($this, 'test_sheet_update'));
        add_action('wp_ajax_ai_seo_test_email', array($this, 'test_email'));
        add_action('wp_ajax_ai_seo_delete_article', array($this, 'delete_article'));
    }
    
    /**
     * Generate article immediately
     */
    public function generate_article() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        $generate_now = isset($_POST['generate_now']) && $_POST['generate_now'] == 1;
        
        if (empty($keywords)) {
            wp_send_json_error(array('message' => __('Keywords are required', 'ai-seo-generator')));
        }
        
        if ($generate_now) {
            // Generate immediately - ONLY ONE ARTICLE
            $generator = AI_SEO_Article_Generator::get_instance();
            $result = $generator->generate_and_publish($keywords);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            wp_send_json_success(array(
                'message' => __('Article generated successfully!', 'ai-seo-generator'),
                'post_id' => $result['post_id'],
                'post_url' => $result['post_url'],
                'edit_url' => get_edit_post_link($result['post_id'], 'raw'),
            ));
        } else {
            // Add to queue
            $scheduler = AI_SEO_Scheduler::get_instance();
            $queue_id = $scheduler->add_to_queue($keywords);
            
            if (is_wp_error($queue_id)) {
                wp_send_json_error(array('message' => $queue_id->get_error_message()));
            }
            
            wp_send_json_success(array(
                'message' => __('Added to queue successfully!', 'ai-seo-generator'),
                'queue_id' => $queue_id,
            ));
        }
    }
    
    /**
     * Add item to queue
     */
    public function add_to_queue() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        $scheduled_date = isset($_POST['scheduled_date']) ? sanitize_text_field($_POST['scheduled_date']) : null;
        
        if (empty($keywords)) {
            wp_send_json_error(array('message' => __('Keywords are required', 'ai-seo-generator')));
        }
        
        // Convert datetime-local format to MySQL datetime
        if ($scheduled_date) {
            $scheduled_date = date('Y-m-d H:i:s', strtotime($scheduled_date));
        }
        
        $scheduler = AI_SEO_Scheduler::get_instance();
        $queue_id = $scheduler->add_to_queue($keywords, $scheduled_date);
        
        if (is_wp_error($queue_id)) {
            wp_send_json_error(array('message' => $queue_id->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Added to queue successfully!', 'ai-seo-generator'),
            'queue_id' => $queue_id,
        ));
    }
    
    /**
     * Batch add to queue
     */
    public function batch_add_to_queue() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $keywords = isset($_POST['keywords']) ? $_POST['keywords'] : array();
        
        if (!is_array($keywords) || empty($keywords)) {
            wp_send_json_error(array('message' => __('No keywords provided', 'ai-seo-generator')));
        }
        
        $scheduler = AI_SEO_Scheduler::get_instance();
        $added = 0;
        $failed = 0;
        
        foreach ($keywords as $keyword) {
            $keyword = sanitize_text_field($keyword);
            if (empty($keyword)) {
                continue;
            }
            
            $result = $scheduler->add_to_queue($keyword);
            if (is_wp_error($result)) {
                $failed++;
            } else {
                $added++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Added %d items to queue (%d failed)', 'ai-seo-generator'), $added, $failed),
            'added' => $added,
            'failed' => $failed,
        ));
    }
    
    /**
     * Fetch keywords from Google Sheets
     */
    public function fetch_sheets_keywords() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $only_unused = isset($_POST['only_unused']) && $_POST['only_unused'] == '1';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;
        $preview_only = isset($_POST['preview']) && $_POST['preview'] == '1';
        
        $sheets = AI_SEO_Google_Sheets::get_instance();
        $keywords = $sheets->fetch_keywords($only_unused, $limit);
        
        if (is_wp_error($keywords)) {
            wp_send_json_error(array('message' => $keywords->get_error_message()));
        }
        
        if (empty($keywords)) {
            wp_send_json_error(array('message' => __('No unused keywords found in the sheet', 'ai-seo-generator')));
        }
        
        wp_send_json_success(array(
            'keywords' => $keywords,
            'count' => count($keywords),
        ));
    }
    
    /**
     * Batch schedule articles
     */
    public function batch_schedule() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $keywords = isset($_POST['keywords']) ? $_POST['keywords'] : array();
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily';
        $articles_per_interval = isset($_POST['articles_per_interval']) ? absint($_POST['articles_per_interval']) : 1;
        
        if (!is_array($keywords) || empty($keywords)) {
            wp_send_json_error(array('message' => __('No keywords provided', 'ai-seo-generator')));
        }
        
        if (empty($start_date)) {
            wp_send_json_error(array('message' => __('Start date is required', 'ai-seo-generator')));
        }
        
        // Ensure at least 1 article per interval
        if ($articles_per_interval < 1) {
            $articles_per_interval = 1;
        }
        
        // Calculate schedule intervals
        $interval_seconds = $this->get_frequency_interval($frequency);
        $current_timestamp = strtotime($start_date);
        
        $scheduler = AI_SEO_Scheduler::get_instance();
        $added = 0;
        $articles_in_current_interval = 0;
        
        foreach ($keywords as $keyword) {
            $keyword = sanitize_text_field($keyword);
            if (empty($keyword)) {
                continue;
            }
            
            $scheduled_date = date('Y-m-d H:i:s', $current_timestamp);
            $result = $scheduler->add_to_queue($keyword, $scheduled_date);
            
            if (!is_wp_error($result)) {
                $added++;
                $articles_in_current_interval++;
                // Move to next interval after reaching articles_per_interval
                if ($articles_in_current_interval >= $articles_per_interval) {
                    $current_timestamp += $interval_seconds;
                    $articles_in_current_interval = 0;
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Scheduled %d articles successfully!', 'ai-seo-generator'), $added),
            'scheduled' => $added,
        ));
    }
    
    /**
     * Get frequency interval in seconds
     */
    private function get_frequency_interval($frequency) {
        switch ($frequency) {
            case 'hourly':
                return 3600; // 1 hour
            case 'daily':
                return 86400; // 24 hours
            case 'weekly':
                return 604800; // 7 days
            case 'monthly':
                return 2592000; // 30 days
            default:
                return 86400; // Default to daily
        }
    }
    
    /**
     * Regenerate title for post
     */
    public function regenerate_title() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';
        $current_title = isset($_POST['current_title']) ? sanitize_text_field($_POST['current_title']) : '';
        
        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'ai-seo-generator')));
        }
        
        // Get keywords from post meta if not provided
        if (empty($keywords)) {
            $keywords = get_post_meta($post_id, '_ai_seo_keywords', true);
        }
        
        if (empty($keywords)) {
            wp_send_json_error(array('message' => __('Keywords not found', 'ai-seo-generator')));
        }
        
        $gemini = AI_SEO_Gemini_API::get_instance();
        $new_title = $gemini->regenerate_title($keywords, $current_title);
        
        if (is_wp_error($new_title)) {
            wp_send_json_error(array('message' => $new_title->get_error_message()));
        }
        
        // Update the post title
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $new_title,
        ));
        
        wp_send_json_success(array(
            'title' => $new_title,
            'message' => __('Title regenerated successfully!', 'ai-seo-generator'),
        ));
    }
    
    /**
     * Regenerate featured image for post
     */
    public function regenerate_image() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
        $orientation = isset($_POST['orientation']) ? sanitize_text_field($_POST['orientation']) : 'landscape';
        
        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'ai-seo-generator')));
        }
        
        // ALWAYS use the article title for featured image - it must match the article topic
        $title = get_the_title($post_id);
        $keywords = get_post_meta($post_id, '_ai_seo_keywords', true);
        
        // Build a descriptive prompt from title and keywords
        if (!empty($keywords)) {
            $prompt = $title . ' - ' . $keywords;
        } else {
            $prompt = $title;
        }
        
        $debug_log = array();
        $debug_log[] = 'Generating featured image for: ' . $prompt;
        
        $gemini = AI_SEO_Gemini_API::get_instance();
        $image_id = $gemini->generate_image($prompt, $orientation, 0, $debug_log);
        
        if (is_wp_error($image_id)) {
            $error_data = $image_id->get_error_data();
            wp_send_json_error(array(
                'message' => $image_id->get_error_message(),
                'debug' => isset($error_data['debug']) ? $error_data['debug'] : $debug_log
            ));
        }
        
        // Set as featured image
        set_post_thumbnail($post_id, $image_id);
        update_post_meta($post_id, '_thumbnail_id', $image_id);
        
        // Attach image to post
        wp_update_post(array(
            'ID' => $image_id,
            'post_parent' => $post_id,
        ));
        
        // Get various image sizes for UI update
        $image_url = wp_get_attachment_url($image_id);
        $image_thumb = wp_get_attachment_image_src($image_id, 'thumbnail');
        $image_medium = wp_get_attachment_image_src($image_id, 'medium');
        $image_full = wp_get_attachment_image_src($image_id, 'full');
        
        wp_send_json_success(array(
            'image_id' => $image_id,
            'image_url' => $image_url,
            'thumbnail_url' => $image_thumb ? $image_thumb[0] : $image_url,
            'medium_url' => $image_medium ? $image_medium[0] : $image_url,
            'full_url' => $image_full ? $image_full[0] : $image_url,
            'message' => __('Image regenerated successfully!', 'ai-seo-generator'),
            'debug' => $debug_log,
            'prompt_used' => $prompt
        ));
    }
    
    /**
     * Generate custom AI image with user prompt
     */
    public function generate_custom_image() {
        try {
            check_ajax_referer('ai_seo_nonce', 'nonce');
            
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => 'Permission denied', 'debug' => array('User lacks edit_posts capability')));
                return;
            }
            
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
            $orientation = isset($_POST['orientation']) ? sanitize_text_field($_POST['orientation']) : 'landscape';
            
            if (empty($prompt)) {
                wp_send_json_error(array('message' => 'Please provide an image description'));
                return;
            }
            
            $debug_log = array();
            $debug_log[] = 'Custom image prompt: ' . $prompt;
            $debug_log[] = 'Orientation: ' . $orientation;
            
            $gemini = AI_SEO_Gemini_API::get_instance();
            
            // Generate image with custom prompt - pass debug log
            $image_id = $gemini->generate_image($prompt, $orientation, 0, $debug_log);
            
            if (is_wp_error($image_id)) {
                $error_data = $image_id->get_error_data();
                wp_send_json_error(array(
                    'message' => $image_id->get_error_message(),
                    'debug' => isset($error_data['debug']) ? $error_data['debug'] : $debug_log
                ));
                return;
            }
            
            if (!is_numeric($image_id) || $image_id <= 0) {
                wp_send_json_error(array('message' => 'Invalid image ID returned', 'debug' => $debug_log));
                return;
            }
            
            // Attach image to post if post_id is provided
            if ($post_id > 0) {
                wp_update_post(array(
                    'ID' => $image_id,
                    'post_parent' => $post_id,
                ));
            }
            
            // Get image URLs
            $image_url = wp_get_attachment_url($image_id);
            $image_medium = wp_get_attachment_image_src($image_id, 'medium');
            
            $debug_log[] = 'Image generated successfully, ID: ' . $image_id;
            
            wp_send_json_success(array(
                'image_id' => $image_id,
                'image_url' => $image_url,
                'medium_url' => $image_medium ? $image_medium[0] : $image_url,
                'message' => 'Image generated successfully!',
                'debug' => $debug_log,
                'prompt_used' => $prompt
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'PHP Exception: ' . $e->getMessage(),
                'debug' => 'File: ' . $e->getFile() . ' Line: ' . $e->getLine()
            ));
        } catch (Error $e) {
            wp_send_json_error(array(
                'message' => 'PHP Error: ' . $e->getMessage(),
                'debug' => 'File: ' . $e->getFile() . ' Line: ' . $e->getLine()
            ));
        }
    }
    
    /**
     * Set an existing image as featured image
     */
    public function set_featured_image() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $image_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;
        
        if (empty($post_id) || empty($image_id)) {
            wp_send_json_error(array('message' => 'Post ID and Image ID are required'));
        }
        
        // Verify the image exists
        if (!wp_attachment_is_image($image_id)) {
            wp_send_json_error(array('message' => 'Invalid image ID'));
        }
        
        // Set as featured image
        $result = set_post_thumbnail($post_id, $image_id);
        
        if ($result) {
            // Attach image to post
            wp_update_post(array(
                'ID' => $image_id,
                'post_parent' => $post_id,
            ));
            
            wp_send_json_success(array(
                'message' => 'Featured image set successfully',
                'image_id' => $image_id,
                'image_url' => wp_get_attachment_url($image_id)
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to set featured image'));
        }
    }
    
    /**
     * Import keywords from Google Sheets and add to queue
     */
    public function import_sheets_to_queue() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        // Prevent double-submission with a transient lock - silent fail
        $lock_key = 'ai_seo_import_lock_' . get_current_user_id();
        if (get_transient($lock_key)) {
            // Silently return success to avoid confusing error messages during page reload
            wp_send_json_success(array('message' => __('Import in progress...', 'ai-seo-generator'), 'silent' => true));
        }
        set_transient($lock_key, true, 30); // Lock for 30 seconds
        
        $only_unused = isset($_POST['only_unused']) && $_POST['only_unused'] == '1';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : 'daily';
        $articles_per_interval = isset($_POST['articles_per_interval']) ? absint($_POST['articles_per_interval']) : 1;
        
        // Ensure at least 1 article per interval
        if ($articles_per_interval < 1) {
            $articles_per_interval = 1;
        }
        
        // Save schedule settings for auto-pull after batch completion
        update_option('ai_seo_schedule_settings', array(
            'frequency' => $frequency,
            'articles_per_interval' => $articles_per_interval,
            'only_unused' => $only_unused,
        ));
        
        $sheets = AI_SEO_Google_Sheets::get_instance();
        // Use articles_per_interval as the limit - only pull what we need for this batch
        $keywords = $sheets->fetch_keywords($only_unused, $articles_per_interval);
        
        if (is_wp_error($keywords)) {
            delete_transient($lock_key);
            wp_send_json_error(array('message' => $keywords->get_error_message()));
        }
        
        // Check how many we actually got
        $fetched_count = is_array($keywords) ? count($keywords) : 0;
        
        if (empty($keywords)) {
            delete_transient($lock_key);
            // No keywords left - send notification that sheet is empty
            $notifications = AI_SEO_Email_Notifications::get_instance();
            $notifications->send_sheet_empty();
            wp_send_json_error(array('message' => __('No unused keywords found in the sheet. You have been notified by email.', 'ai-seo-generator')));
        }
        
        // All keywords in this batch get the SAME scheduled time
        // Since we only pulled articles_per_interval keywords, they all belong to this interval
        $scheduled_at = null;
        if ($frequency !== 'immediately' && !empty($start_date)) {
            $scheduled_at = date('Y-m-d H:i:s', strtotime($start_date));
        } elseif ($frequency !== 'immediately') {
            $scheduled_at = date('Y-m-d H:i:s', time());
        }
        
        $scheduler = AI_SEO_Scheduler::get_instance();
        $added = 0;
        $skipped = 0;
        $errors = array();
        
        // Add all keywords with the same scheduled time
        foreach ($keywords as $item) {
            $keyword = sanitize_text_field($item['keyword']);
            $sheets_row = $item['row'];
            
            if (empty($keyword)) {
                continue;
            }
            
            $result = $scheduler->add_to_queue($keyword, $scheduled_at, $sheets_row);
            
            if (is_wp_error($result)) {
                $skipped++;
                $errors[] = $keyword . ': ' . $result->get_error_message();
            } else if ($result) {
                $added++;
            }
        }
        
        if ($added === 0 && !empty($errors)) {
            delete_transient($lock_key);
            wp_send_json_error(array('message' => 'Failed to add keywords: ' . implode(', ', array_unique($errors))));
        }
        
        // Release lock
        delete_transient($lock_key);
        
        $msg = sprintf(__('Added %d of %d keywords to queue.', 'ai-seo-generator'), $added, $fetched_count);
        if ($skipped > 0) {
            $msg .= sprintf(' (%d skipped - already in queue)', $skipped);
        }
        if ($fetched_count < $articles_per_interval) {
            $msg .= sprintf(' Note: Only %d unused keywords found in sheet (requested %d).', $fetched_count, $articles_per_interval);
        }
        
        wp_send_json_success(array(
            'message' => $msg,
            'added' => $added,
            'skipped' => $skipped,
            'fetched' => $fetched_count,
            'requested' => $articles_per_interval
        ));
    }
    
    /**
     * Remove item from queue
     */
    public function remove_from_queue() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        
        if (empty($item_id)) {
            wp_send_json_error(array('message' => __('Item ID is required', 'ai-seo-generator')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        // Delete directly to ensure it works
        $result = $wpdb->delete($table_name, array('id' => $item_id), array('%d'));
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Database error: ', 'ai-seo-generator') . $wpdb->last_error));
        }
        
        if ($result === 0) {
            wp_send_json_error(array('message' => __('Item not found in queue', 'ai-seo-generator')));
        }
        
        wp_send_json_success(array(
            'message' => __('Item removed from queue', 'ai-seo-generator'),
        ));
    }
    
    /**
     * Test Google Sheets update - marks first unused row with test data
     */
    public function test_sheet_update() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        try {
            $sheets = AI_SEO_Google_Sheets::get_instance();
            $result = $sheets->test_mark_row();
            
            if (isset($result['success']) && $result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            error_log('AI SEO test_sheet_update error: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Test email configuration
     */
    public function test_email() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email)) {
            wp_send_json_error(array('message' => __('Please provide an email address', 'ai-seo-generator')));
        }
        
        $notifications = AI_SEO_Email_Notifications::get_instance();
        $result = $notifications->send_test_email($email);
        
        if ($result) {
            wp_send_json_success(array('message' => sprintf(__('Test email sent to %s', 'ai-seo-generator'), $email)));
        } else {
            wp_send_json_error(array('message' => __('Failed to send email. Check your WordPress mail configuration.', 'ai-seo-generator')));
        }
    }
    
    /**
     * Delete an AI-generated article
     */
    public function delete_article() {
        check_ajax_referer('ai_seo_nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-seo-generator')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (empty($post_id)) {
            wp_send_json_error(array('message' => __('Post ID is required', 'ai-seo-generator')));
        }
        
        // Verify this is an AI-generated post
        $is_ai_generated = get_post_meta($post_id, '_ai_seo_generated', true);
        if (!$is_ai_generated) {
            wp_send_json_error(array('message' => __('This post was not generated by AI SEO Generator', 'ai-seo-generator')));
        }
        
        // Delete the post (move to trash)
        $result = wp_trash_post($post_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Article deleted successfully', 'ai-seo-generator')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete article', 'ai-seo-generator')));
        }
    }
}
