<?php
/**
 * Scheduler Handler
 */

class AI_SEO_Scheduler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Background processing hooks
        add_action('ai_seo_process_queue', array($this, 'process_scheduled_items'));
        add_action('ai_seo_process_single_item', array($this, 'process_item_background'), 10, 1);
        add_action('init', array($this, 'maybe_process_queue'));
        add_action('init', array($this, 'schedule_cron'));
    }
    
    /**
     * Schedule cron job if not already scheduled
     */
    public function schedule_cron() {
        if (!wp_next_scheduled('ai_seo_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'ai_seo_process_queue');
        }
    }
    
    /**
     * Add item to queue
     */
    public function add_to_queue($keyword, $scheduled_at = null, $sheets_row = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        // Check if table exists, create if not
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_queue_table();
        }
        
        // Trim and normalize keyword
        $keyword = trim($keyword);
        $keyword_lower = strtolower($keyword);
        
        // Check for duplicate - don't add if same keyword already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE LOWER(TRIM(keywords)) = %s AND status IN ('pending', 'processing')",
            $keyword_lower
        ));
        
        if ($existing) {
            return new WP_Error('duplicate', __('Keyword already in queue', 'ai-seo-generator'));
        }
        
        $data = array(
            'keywords' => $keyword,
            'status' => 'pending',
            'scheduled_at' => $scheduled_at,
            'sheets_row' => $sheets_row,
        );
        
        $formats = array('%s', '%s', '%s', '%d');
        
        // Remove null sheets_row to avoid format issues
        if ($sheets_row === null) {
            unset($data['sheets_row']);
            array_pop($formats);
        }
        
        $result = $wpdb->insert($table_name, $data, $formats);
        
        if ($result === false) {
            error_log('AI SEO Queue Insert Error: ' . $wpdb->last_error);
            return new WP_Error('db_error', __('Failed to add to queue: ', 'ai-seo-generator') . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create queue table if it doesn't exist
     */
    private function create_queue_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            // Table exists - check for missing columns and add them
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
            
            if (!in_array('scheduled_at', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN scheduled_at datetime DEFAULT NULL AFTER status");
            }
            if (!in_array('post_id', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN post_id bigint(20) DEFAULT NULL AFTER scheduled_at");
            }
            if (!in_array('sheets_row', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN sheets_row int(11) DEFAULT NULL AFTER post_id");
            }
            if (!in_array('created_at', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP AFTER sheets_row");
            }
            if (!in_array('updated_at', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
            }
            if (!in_array('error_message', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN error_message text DEFAULT NULL AFTER updated_at");
            }
            return;
        }
        
        // Create new table
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            keywords text NOT NULL,
            status varchar(50) DEFAULT 'pending',
            scheduled_at datetime DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            sheets_row int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            error_message text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get queue items
     */
    public function get_queue($status = null, $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        $query = "SELECT * FROM {$table_name}";
        
        if ($status) {
            $query .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        
        $query .= " ORDER BY scheduled_at ASC, created_at ASC";
        $query .= $wpdb->prepare(" LIMIT %d", $limit);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Check and process due items (called by "Process Queue Now" button)
     */
    public function check_and_process() {
        // First clean up any stuck processing items
        $this->cleanup_stuck_processing_items();
        
        $this->process_all_due_items();
    }
    
    /**
     * Process scheduled items (automatic cron processing)
     * Only processes items with a specific scheduled time that is due
     * Items with NULL scheduled_at require manual "Process Queue Now" action
     */
    public function process_scheduled_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        // First, clean up stuck processing items (processing for more than 10 minutes)
        $this->cleanup_stuck_processing_items();
        
        $current_time = current_time('mysql');
        
        // Only process items that have a specific scheduled time that is now due
        // Items with NULL scheduled_at will NOT be auto-processed by cron
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE status = 'pending' 
            AND scheduled_at IS NOT NULL 
            AND scheduled_at <= %s
            ORDER BY scheduled_at ASC 
            LIMIT 5",
            $current_time
        ));
        
        foreach ($items as $item) {
            $this->process_item($item->id);
        }
    }
    
    /**
     * Process all due queue items (manual trigger via "Process Queue Now" button)
     * This processes both scheduled items and items with NULL scheduled_at
     */
    public function process_all_due_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        $current_time = current_time('mysql');
        
        // Process all pending items: those with NULL scheduled_at OR those with due scheduled_at
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
            WHERE status = 'pending' 
            AND (scheduled_at IS NULL OR scheduled_at <= %s)
            ORDER BY scheduled_at ASC 
            LIMIT 5",
            $current_time
        ));
        
        foreach ($items as $item) {
            $this->process_item($item->id);
        }
    }
    
    /**
     * Schedule a single item for background processing
     */
    public function schedule_item($item_id) {
        wp_schedule_single_event(time(), 'ai_seo_process_single_item', array($item_id));
    }
    
    /**
     * Process item in background (called by cron)
     */
    public function process_item_background($item_id) {
        $this->process_item($item_id);
    }
    
    /**
     * Process single queue item
     */
    public function process_item($item_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        // Get item
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $item_id
        ));
        
        if (!$item) {
            return new WP_Error('item_not_found', __('Queue item not found', 'ai-seo-generator'));
        }
        
        // Update status to processing
        $wpdb->update(
            $table_name,
            array('status' => 'processing'),
            array('id' => $item_id),
            array('%s'),
            array('%d')
        );
        
        // Generate article
        $generator = AI_SEO_Article_Generator::get_instance();
        $result = $generator->generate_and_publish($item->keywords);
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            
            // Log the error for debugging
            error_log('AI SEO: Article generation failed for "' . $item->keywords . '": ' . $error_message);
            
            // Update status to failed and store error message
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $error_message
                ),
                array('id' => $item_id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Check if queue batch is complete (no more pending items)
            $this->maybe_send_batch_complete_notification();
            
            return $result;
        }
        
        // Update status to completed and store post_id
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'post_id' => $result['post_id']
            ),
            array('id' => $item_id),
            array('%s', '%d'),
            array('%d')
        );
        
        // Update Google Sheets if this item came from sheets
        if (!empty($item->sheets_row)) {
            $sheets = AI_SEO_Google_Sheets::get_instance();
            $post_url = get_permalink($result['post_id']);
            $sheets->mark_as_used($item->sheets_row, $post_url);
        }
        
        // Check if queue batch is complete (no more pending items)
        $this->maybe_send_batch_complete_notification();
        
        return $result;
    }
    
    /**
     * Clean up stuck processing items
     * Items that have been in 'processing' status for more than 10 minutes are likely stuck
     * This can happen if the process was interrupted or timed out
     */
    private function cleanup_stuck_processing_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        // Find items that have been processing for more than 10 minutes
        // Check if they actually have a completed post
        $stuck_items = $wpdb->get_results(
            "SELECT q.*, p.ID as found_post_id 
            FROM {$table_name} q
            LEFT JOIN {$wpdb->posts} p ON p.ID = q.post_id
            WHERE q.status = 'processing' 
            AND q.updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
        
        foreach ($stuck_items as $item) {
            // Check if a post was actually created for this keyword
            $existing_post = $wpdb->get_row($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = '_ai_seo_keywords' 
                AND pm.meta_value = %s
                AND p.post_status IN ('publish', 'draft', 'pending')
                ORDER BY p.ID DESC
                LIMIT 1",
                $item->keywords
            ));
            
            if ($existing_post) {
                // Post exists - delete from queue since it's done
                $wpdb->delete(
                    $table_name,
                    array('id' => $item->id),
                    array('%d')
                );
                error_log('AI SEO: Cleaned up stuck item #' . $item->id . ' - removed from queue (post ' . $existing_post->ID . ' exists)');
            } else {
                // No post found - reset to pending so it can be retried
                $wpdb->update(
                    $table_name,
                    array('status' => 'pending'),
                    array('id' => $item->id),
                    array('%s'),
                    array('%d')
                );
                error_log('AI SEO: Cleaned up stuck item #' . $item->id . ' - reset to pending (no post found)');
            }
        }
    }
    
    /**
     * Update queue item status
     */
    public function update_status($item_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        return $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $item_id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Delete queue item
     */
    public function delete_item($item_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        $result = $wpdb->delete($table_name, array('id' => $item_id), array('%d'));
        
        // Log for debugging
        if ($result === false) {
            error_log('AI SEO Delete Error: ' . $wpdb->last_error . ' for ID: ' . $item_id);
        }
        
        return $result;
    }
    
    /**
     * Maybe process queue on manual trigger
     */
    public function maybe_process_queue() {
        if (isset($_GET['ai_seo_process_queue']) && current_user_can('manage_options')) {
            check_admin_referer('ai_seo_process_queue');
            $this->process_all_due_items();
            wp_redirect(admin_url('admin.php?page=ai-seo-generator-queue&processed=1'));
            exit;
        }
    }
    
    /**
     * Check if queue is complete and send batch notification, then auto-pull next batch
     */
    private function maybe_send_batch_complete_notification() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
        // Check if there are any pending or processing items left
        $pending_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status IN ('pending', 'processing')"
        );
        
        // Log for debugging
        error_log('AI SEO: Checking batch completion - Pending items: ' . $pending_count);
        
        // If no pending items, the batch is complete
        if ($pending_count === 0) {
            // Get stats for completed batch (items processed in last 24 hours)
            $stats = $wpdb->get_row(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$table_name}
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            
            // Log stats for debugging
            error_log('AI SEO: Batch stats - Total: ' . ($stats ? $stats->total : 0) .
                     ', Successful: ' . ($stats ? $stats->successful : 0) .
                     ', Failed: ' . ($stats ? $stats->failed : 0));
            
            $email_enabled = get_option('AI_SEO_email_notifications', true);
            error_log('AI SEO: Email notifications enabled: ' . ($email_enabled ? 'yes' : 'no'));
            
            // Only send notification if there were items processed
            if ($stats && $stats->total > 0 && $email_enabled) {
                // Get failed items with their error messages
                $failed_items = $wpdb->get_results(
                    "SELECT keywords, error_message
                    FROM {$table_name}
                    WHERE status = 'failed'
                    AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY updated_at DESC
                    LIMIT 10"
                );
                
                error_log('AI SEO: Sending batch complete notification for ' . $stats->total . ' items');
                
                $notifications = AI_SEO_Email_Notifications::get_instance();
                $notifications->send_batch_complete(
                    (int) $stats->total,
                    (int) $stats->successful,
                    (int) $stats->failed,
                    $failed_items
                );
            } else {
                if (!$stats) {
                    error_log('AI SEO: No stats found for batch completion');
                } elseif ($stats->total === 0) {
                    error_log('AI SEO: Stats total is 0, no email will be sent');
                } elseif (!$email_enabled) {
                    error_log('AI SEO: Email notifications are disabled');
                }
            }
            
            // Auto-pull next batch from Google Sheets
            $this->auto_pull_next_batch();
        } else {
            error_log('AI SEO: Batch not complete yet, ' . $pending_count . ' items pending');
        }
    }
    
    /**
     * Auto-pull next batch of keywords from Google Sheets
     */
    private function auto_pull_next_batch() {
        $settings = get_option('ai_seo_schedule_settings');
        
        if (empty($settings)) {
            return; // No schedule settings saved, can't auto-pull
        }
        
        $frequency = isset($settings['frequency']) ? $settings['frequency'] : 'weekly';
        $articles_per_interval = isset($settings['articles_per_interval']) ? (int) $settings['articles_per_interval'] : 1;
        $only_unused = isset($settings['only_unused']) ? $settings['only_unused'] : true;
        
        // Calculate next scheduled time based on frequency
        $interval_seconds = $this->get_frequency_interval($frequency);
        $next_scheduled_at = date('Y-m-d H:i:s', time() + $interval_seconds);
        
        // If immediate, set to now
        if ($frequency === 'immediately') {
            $next_scheduled_at = null;
        }
        
        // Fetch keywords from Google Sheets
        $sheets = AI_SEO_Google_Sheets::get_instance();
        $keywords = $sheets->fetch_keywords($only_unused, $articles_per_interval);
        
        if (is_wp_error($keywords) || empty($keywords)) {
            // No more keywords - send notification
            if (get_option('AI_SEO_email_notifications', true)) {
                $notifications = AI_SEO_Email_Notifications::get_instance();
                $notifications->send_sheet_empty();
            }
            return;
        }
        
        // Add keywords to queue
        foreach ($keywords as $item) {
            $keyword = trim($item['keyword']);
            $sheets_row = $item['row'];
            
            if (!empty($keyword)) {
                $this->add_to_queue($keyword, $next_scheduled_at, $sheets_row);
            }
        }
        
        error_log('AI SEO: Auto-pulled ' . count($keywords) . ' keywords for next batch, scheduled at: ' . ($next_scheduled_at ?: 'immediately'));
    }
    
    /**
     * Get interval in seconds for frequency
     */
    private function get_frequency_interval($frequency) {
        switch ($frequency) {
            case 'immediately':
                return 0;
            case 'daily':
                return DAY_IN_SECONDS;
            case 'weekly':
                return WEEK_IN_SECONDS;
            case 'monthly':
                return 30 * DAY_IN_SECONDS;
            default:
                return DAY_IN_SECONDS;
        }
    }
}
