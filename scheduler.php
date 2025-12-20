<?php
/**
 * Scheduler Page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ai-seo-scheduler-page">
        <!-- Batch Schedule -->
        <div class="ai-seo-card">
            <h2><?php _e('Batch Schedule Articles', 'ai-seo-generator'); ?></h2>
            <form id="ai-seo-batch-schedule-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="batch-keywords"><?php _e('Keywords (one per line)', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <textarea id="batch-keywords" 
                                      name="keywords" 
                                      rows="10" 
                                      class="large-text" 
                                      placeholder="<?php esc_attr_e('Enter one keyword per line...', 'ai-seo-generator'); ?>"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="start-date"><?php _e('Start Date', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="start-date" name="start_date" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="frequency"><?php _e('Frequency', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <select id="frequency" name="frequency">
                                <option value="hourly"><?php _e('Every Hour', 'ai-seo-generator'); ?></option>
                                <option value="daily" selected><?php _e('Daily', 'ai-seo-generator'); ?></option>
                                <option value="weekly"><?php _e('Weekly', 'ai-seo-generator'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('How often to publish articles from the batch', 'ai-seo-generator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="submit" class="button button-primary">
                        <?php _e('Schedule Batch', 'ai-seo-generator'); ?>
                    </button>
                </p>
                
                <div id="batch-status" class="ai-seo-status-message" style="display: none;"></div>
            </form>
        </div>
        
        <!-- Schedule Preview -->
        <div class="ai-seo-card">
            <h2><?php _e('Scheduled Items Preview', 'ai-seo-generator'); ?></h2>
            <div id="schedule-preview">
                <p class="description"><?php _e('Enter keywords and dates above to see schedule preview', 'ai-seo-generator'); ?></p>
            </div>
        </div>
        
        <!-- Cron Settings -->
        <div class="ai-seo-card">
            <h2><?php _e('Automatic Processing', 'ai-seo-generator'); ?></h2>
            <p>
                <?php _e('The plugin automatically checks for scheduled items every hour using WordPress cron.', 'ai-seo-generator'); ?>
            </p>
            
            <?php
            $next_run = wp_next_scheduled('AI_SEO_check_schedule');
            if ($next_run):
            ?>
                <p>
                    <strong><?php _e('Next scheduled check:', 'ai-seo-generator'); ?></strong>
                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run); ?>
                </p>
            <?php endif; ?>
            
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ai-seo-generator-scheduler&action=run_now'), 'ai_seo_run_scheduler'); ?>" class="button">
                    <?php _e('Process Scheduled Items Now', 'ai-seo-generator'); ?>
                </a>
            </p>
            
            <div class="notice notice-info inline">
                <p>
                    <strong><?php _e('Note:', 'ai-seo-generator'); ?></strong>
                    <?php _e('For more reliable scheduling on high-traffic sites, consider setting up a real cron job to trigger wp-cron.php', 'ai-seo-generator'); ?>
                </p>
            </div>
        </div>
        
        <!-- Tips -->
        <div class="ai-seo-card">
            <h2><?php _e('Scheduling Tips', 'ai-seo-generator'); ?></h2>
            <ul>
                <li><?php _e('Schedule posts during peak traffic hours for better visibility', 'ai-seo-generator'); ?></li>
                <li><?php _e('Maintain consistent posting frequency for better SEO', 'ai-seo-generator'); ?></li>
                <li><?php _e('Review generated content before publishing (set default status to Draft)', 'ai-seo-generator'); ?></li>
                <li><?php _e('Use Google Sheets integration for managing large batches of keywords', 'ai-seo-generator'); ?></li>
                <li><?php _e('Monitor your Gemini API usage to avoid rate limits', 'ai-seo-generator'); ?></li>
            </ul>
        </div>
    </div>
</div>
