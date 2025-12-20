<?php
/**
 * Google Sheets Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$sheets = AI_SEO_Google_Sheets::get_instance();
$connection_test = $sheets->test_connection();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ai-seo-sheets-page">
        <!-- Connection Status -->
        <div class="ai-seo-card">
            <h2><?php _e('Connection Status', 'ai-seo-generator'); ?></h2>
            <?php if ($connection_test['success']): ?>
                <div class="notice notice-success inline">
                    <p>
                        <strong><?php _e('Connected:', 'ai-seo-generator'); ?></strong>
                        <?php echo esc_html($connection_test['message']); ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="notice notice-error inline">
                    <p>
                        <strong><?php _e('Not Connected:', 'ai-seo-generator'); ?></strong>
                        <?php echo esc_html($connection_test['message']); ?>
                    </p>
                </div>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=ai-seo-generator-settings'); ?>" class="button button-primary">
                        <?php _e('Configure Settings', 'ai-seo-generator'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        
        <?php if ($connection_test['success']): ?>
            <!-- Fetch Keywords -->
            <div class="ai-seo-card">
                <h2><?php _e('Fetch Keywords from Sheet', 'ai-seo-generator'); ?></h2>
                <form id="ai-seo-fetch-keywords-form">
                    <p>
                        <label for="sheet-range">
                            <strong><?php _e('Range (e.g., A3:A)', 'ai-seo-generator'); ?></strong>
                        </label>
                        <input type="text" id="sheet-range" name="range" value="A3:A" class="regular-text">
                    </p>
                    <p>
                        <button type="submit" class="button button-primary">
                            <?php _e('Fetch Keywords', 'ai-seo-generator'); ?>
                        </button>
                    </p>
                    <div id="fetch-status" class="ai-seo-status-message" style="display: none;"></div>
                </form>
                
                <div id="keywords-preview" style="display: none; margin-top: 20px;">
                    <h3><?php _e('Preview', 'ai-seo-generator'); ?></h3>
                    <div id="keywords-list"></div>
                    <p>
                        <button id="add-to-queue-btn" class="button button-primary">
                            <?php _e('Add to Queue', 'ai-seo-generator'); ?>
                        </button>
                    </p>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="ai-seo-card">
                <h2><?php _e('Setup Instructions', 'ai-seo-generator'); ?></h2>
                <ol>
                    <li><?php _e('Create a Google Sheet with your keywords in column A', 'ai-seo-generator'); ?></li>
                    <li><?php _e('Add a header row (e.g., "Keywords", "Status", "URL") in row 1', 'ai-seo-generator'); ?></li>
                    <li><?php _e('Enter your keywords starting from row 2', 'ai-seo-generator'); ?></li>
                    <li><?php _e('Share the sheet with your service account email', 'ai-seo-generator'); ?></li>
                    <li><?php _e('The plugin will update columns B and C with status and post URLs', 'ai-seo-generator'); ?></li>
                </ol>
                
                <h3><?php _e('Expected Sheet Format:', 'ai-seo-generator'); ?></h3>
                <table class="wp-list-table widefat fixed">
                    <thead>
                        <tr>
                            <th>A: Keywords</th>
                            <th>B: Status</th>
                            <th>C: URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>best seo practices 2024</td>
                            <td>completed</td>
                            <td>https://yoursite.com/post-url</td>
                        </tr>
                        <tr>
                            <td>content marketing tips</td>
                            <td>pending</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>social media strategy</td>
                            <td>pending</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
