<?php
/**
 * Email Notifications Handler
 */

class AI_SEO_Email_Notifications {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Send success notification
     */
    public function send_success($keywords, $post_id) {
        $to = get_option('AI_SEO_notification_email', get_option('admin_email'));
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(
            __('[%s] Article Generated Successfully: %s', 'ai-seo-generator'),
            $site_name,
            $keywords
        );
        
        $message = $this->get_email_template('success', array(
            'keywords' => $keywords,
            'post_title' => $post->post_title,
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id),
            'post_status' => $post->post_status,
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send failure notification
     */
    public function send_failure($keywords, $error_message) {
        $to = get_option('AI_SEO_notification_email', get_option('admin_email'));
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(
            __('[%s] Article Generation Failed: %s', 'ai-seo-generator'),
            $site_name,
            $keywords
        );
        
        $message = $this->get_email_template('failure', array(
            'keywords' => $keywords,
            'error_message' => $error_message,
            'queue_url' => admin_url('admin.php?page=ai-seo-generator-queue'),
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send batch completion notification
     */
    public function send_batch_complete($total, $successful, $failed, $failed_items = array()) {
        $to = get_option('AI_SEO_notification_email', get_option('admin_email'));
        
        // Log for debugging
        error_log('AI SEO: Sending batch complete notification to ' . $to . ' - Total: ' . $total . ', Successful: ' . $successful . ', Failed: ' . $failed);
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(
            __('[%s] Batch Complete: %d/%d Successful', 'ai-seo-generator'),
            $site_name,
            $successful,
            $total
        );
        
        $message = $this->get_email_template('batch', array(
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'failed_items' => $failed_items,
            'dashboard_url' => admin_url('admin.php?page=ai-seo-generator'),
            'queue_url' => admin_url('admin.php?page=ai-seo-generator-queue'),
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        if ($result) {
            error_log('AI SEO: Batch complete email sent successfully');
        } else {
            error_log('AI SEO: Failed to send batch complete email');
        }
        
        return $result;
    }
    
    /**
     * Send notification when Google Sheet runs out of keywords
     */
    public function send_sheet_empty() {
        $to = get_option('AI_SEO_notification_email', get_option('admin_email'));
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Google Sheet Keywords Exhausted', 'ai-seo-generator'), $site_name);
        
        $message = $this->get_email_template('sheet_empty', array(
            'settings_url' => admin_url('admin.php?page=ai-seo-generator-settings'),
            'sheets_url' => admin_url('admin.php?page=ai-seo-generator-sheets'),
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($type, $data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #fff; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { padding: 20px 0; border-bottom: 1px solid #eee; }
                .header h1 { margin: 0; color: #333; font-size: 24px; }
                .content { padding: 20px 0; }
                .button { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .success { color: #28a745; }
                .error { color: #dc3545; }
                .info { background: #f8f9fa; padding: 15px; border-left: 4px solid #0073aa; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                        <a href="<?php echo esc_url(home_url()); ?>" style="color: #0073aa;"><?php echo esc_url(home_url()); ?></a>
                    </p>
                </div>
                <div class="content">
                    <?php
                    switch ($type) {
                        case 'success':
                            ?>
                            <h2 class="success">✓ Article Generated Successfully</h2>
                            <div class="info">
                                <p><strong>Keywords:</strong> <?php echo esc_html($data['keywords']); ?></p>
                                <p><strong>Title:</strong> <?php echo esc_html($data['post_title']); ?></p>
                                <p><strong>Status:</strong> <?php echo esc_html(ucfirst($data['post_status'])); ?></p>
                            </div>
                            <p>Your article has been generated and is ready for review.</p>
                            <p>
                                <a href="<?php echo esc_url($data['edit_url']); ?>" class="button">Edit Article</a>
                                <a href="<?php echo esc_url($data['post_url']); ?>" class="button">View Article</a>
                            </p>
                            <?php
                            break;
                            
                        case 'failure':
                            ?>
                            <h2 class="error">✗ Article Generation Failed</h2>
                            <div class="info">
                                <p><strong>Keywords:</strong> <?php echo esc_html($data['keywords']); ?></p>
                                <p><strong>Error:</strong> <?php echo esc_html($data['error_message']); ?></p>
                            </div>
                            <p>Please check your settings and try again.</p>
                            <p>
                                <a href="<?php echo esc_url($data['queue_url']); ?>" class="button">View Queue</a>
                            </p>
                            <?php
                            break;
                            
                        case 'batch':
                            ?>
                            <h2>Batch Processing Complete</h2>
                            <div class="info">
                                <p><strong>Total Items:</strong> <?php echo esc_html($data['total']); ?></p>
                                <p><strong class="success">Successful:</strong> <?php echo esc_html($data['successful']); ?></p>
                                <p><strong class="error">Failed:</strong> <?php echo esc_html($data['failed']); ?></p>
                            </div>
                            <?php if (!empty($data['failed_items']) && $data['failed'] > 0): ?>
                            <div style="margin: 20px 0;">
                                <h3 class="error" style="margin-bottom: 10px;">Failed Items Details:</h3>
                                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                                    <thead>
                                        <tr style="background: #f8f9fa;">
                                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Keywords</th>
                                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Error</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['failed_items'] as $item): ?>
                                        <tr>
                                            <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($item->keywords); ?></td>
                                            <td style="padding: 10px; border: 1px solid #ddd; color: #dc3545;"><?php echo esc_html($item->error_message ?: 'Unknown error'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="margin-top: 15px;">
                                    <a href="<?php echo esc_url($data['queue_url']); ?>" class="button" style="background: #dc3545;">View Failed Items in Queue</a>
                                </p>
                            </div>
                            <?php endif; ?>
                            <p>Your articles are ready for review. The next batch will be automatically pulled from Google Sheets.</p>
                            <p>
                                <a href="<?php echo esc_url($data['dashboard_url']); ?>" class="button">View Dashboard</a>
                            </p>
                            <?php
                            break;
                            
                        case 'sheet_empty':
                            ?>
                            <h2 class="error">⚠ Google Sheet Keywords Exhausted</h2>
                            <div class="info">
                                <p>Your Google Sheet has run out of unused keywords.</p>
                                <p>The automatic article generation has been paused until you add more keywords to the sheet.</p>
                            </div>
                            <p>To continue generating articles:</p>
                            <ol>
                                <li>Add more keywords to your Google Sheet</li>
                                <li>Make sure the "Used" column (Column F) is empty or set to "no" for new keywords</li>
                                <li>Import new keywords from the Queue page</li>
                            </ol>
                            <p>
                                <a href="<?php echo esc_url($data['sheets_url']); ?>" class="button">Google Sheets Settings</a>
                            </p>
                            <?php
                            break;
                            
                        case 'test':
                            ?>
                            <h2 class="success">✓ Test Email Successful</h2>
                            <div class="info">
                                <p>This is a test email from the AI SEO Generator plugin.</p>
                                <p><strong>Sent at:</strong> <?php echo esc_html($data['test_time']); ?></p>
                            </div>
                            <p>If you received this email, your email configuration is working correctly!</p>
                            <p>You will receive notifications for:</p>
                            <ul>
                                <li>Batch processing completion (articles ready for review)</li>
                                <li>Google Sheet keyword exhaustion</li>
                            </ul>
                            <?php
                            break;
                    }
                    ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Test email configuration
     */
    public function send_test_email($to) {
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] AI SEO Generator Test Email', 'ai-seo-generator'), $site_name);
        
        $message = $this->get_email_template('test', array(
            'test_time' => current_time('mysql'),
        ));
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to, $subject, $message, $headers);
    }
}
