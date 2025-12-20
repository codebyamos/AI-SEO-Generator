<?php
/**
 * Article Queue Page (Merged with Scheduler)
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'ai_seo_queue';

// Ensure table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
if (!$table_exists) {
    // Trigger table creation
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        keywords text NOT NULL,
        status varchar(50) DEFAULT 'pending',
        scheduled_at datetime DEFAULT NULL,
        post_id bigint(20) DEFAULT NULL,
        sheets_row int(11) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY scheduled_at (scheduled_at)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Clean up any stuck processing items (but keep completed items for stats)
// Items with status = 'processing' that are stuck should be cleaned up
$wpdb->query("DELETE FROM {$table_name} WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

// Handle delete single item via GET (fallback for when AJAX fails)
if (isset($_GET['action']) && $_GET['action'] === 'delete_item' && isset($_GET['item_id']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'ai_seo_delete_item')) {
    $item_id = absint($_GET['item_id']);
    $wpdb->delete($table_name, array('id' => $item_id), array('%d'));
    echo '<div class="notice notice-success"><p>' . __('Item removed from queue!', 'ai-seo-generator') . '</p></div>';
}

$queue_items = $wpdb->get_results("SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY scheduled_at ASC");

// Handle run now action
if (isset($_GET['action']) && $_GET['action'] === 'run_now' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'ai_seo_run_scheduler')) {
    $scheduler = AI_SEO_Scheduler::get_instance();
    $scheduler->check_and_process();
    echo '<div class="notice notice-success"><p>' . __('Scheduler ran successfully!', 'ai-seo-generator') . '</p></div>';
    // Refresh queue items after processing
    $queue_items = $wpdb->get_results("SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY scheduled_at ASC");
}

// Handle clear completed action
if (isset($_GET['action']) && $_GET['action'] === 'clear_completed' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'ai_seo_clear_completed')) {
    $wpdb->delete($table_name, array('status' => 'completed'));
    $wpdb->delete($table_name, array('status' => 'failed'));
    echo '<div class="notice notice-success"><p>' . __('Cleared completed and failed items!', 'ai-seo-generator') . '</p></div>';
    $queue_items = $wpdb->get_results("SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY scheduled_at ASC");
}

// Handle clear pending action
if (isset($_GET['action']) && $_GET['action'] === 'clear_pending' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'ai_seo_clear_pending')) {
    $wpdb->delete($table_name, array('status' => 'pending'));
    echo '<div class="notice notice-success"><p>' . __('Cleared all pending items!', 'ai-seo-generator') . '</p></div>';
    $queue_items = $wpdb->get_results("SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY scheduled_at ASC");
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ai-seo-queue-page">
        
        <!-- Add to Queue Section -->
        <div class="ai-seo-card">
            <h2><?php _e('Add Keywords to Queue', 'ai-seo-generator'); ?></h2>
            
            <div class="ai-seo-queue-tabs">
                <button type="button" class="ai-seo-tab-btn active" data-tab="manual"><?php _e('Manual Entry', 'ai-seo-generator'); ?></button>
                <button type="button" class="ai-seo-tab-btn" data-tab="sheets"><?php _e('From Google Sheets', 'ai-seo-generator'); ?></button>
            </div>
            
            <!-- Manual Entry Tab -->
            <div id="manual-tab" class="ai-seo-tab-content active">
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
                                <label for="start-date"><?php _e('Start Date/Time', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="datetime-local" id="start-date" name="start_date" class="regular-text" value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="articles-per-interval"><?php _e('Articles Per Interval', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="articles-per-interval" name="articles_per" min="1" max="10" value="2" style="width: 60px;">
                                <span><?php _e('per', 'ai-seo-generator'); ?></span>
                                <select id="frequency" name="frequency">
                                    <option value="immediately"><?php _e('Immediately', 'ai-seo-generator'); ?></option>
                                    <option value="daily"><?php _e('Day', 'ai-seo-generator'); ?></option>
                                    <option value="weekly" selected><?php _e('Week', 'ai-seo-generator'); ?></option>
                                    <option value="monthly"><?php _e('Month', 'ai-seo-generator'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                            <?php _e('Add to Queue', 'ai-seo-generator'); ?>
                        </button>
                    </p>
                    
                    <div id="batch-status" class="ai-seo-status-message" style="display: none;"></div>
                </form>
            </div>
            
            <!-- Google Sheets Tab -->
            <div id="sheets-tab" class="ai-seo-tab-content" style="display: none;">
                <form id="ai-seo-sheets-import-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php _e('Keywords Range', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="sheets-range" name="range" class="regular-text" value="A3:A" placeholder="A3:A">
                                <p class="description"><?php _e('Sheet range to fetch keywords from (column A by default)', 'ai-seo-generator'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php _e('Only Unused Keywords', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="only-unused" name="only_unused" value="1" checked>
                                    <?php _e('Only import keywords where "Used" column is empty or "no"', 'ai-seo-generator'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="sheets-start-date"><?php _e('Start Date/Time', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="datetime-local" id="sheets-start-date" name="start_date" class="regular-text" value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="sheets-articles-per-interval"><?php _e('Articles Per Interval', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="sheets-articles-per-interval" name="articles_per" min="1" max="10" value="2" style="width: 60px;">
                                <span><?php _e('per', 'ai-seo-generator'); ?></span>
                                <select id="sheets-frequency" name="frequency">
                                    <option value="immediately"><?php _e('Immediately', 'ai-seo-generator'); ?></option>
                                    <option value="daily"><?php _e('Day', 'ai-seo-generator'); ?></option>
                                    <option value="weekly" selected><?php _e('Week', 'ai-seo-generator'); ?></option>
                                    <option value="monthly"><?php _e('Month', 'ai-seo-generator'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="button" id="preview-sheets-btn" class="button">
                            <span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span>
                            <?php _e('Preview Keywords', 'ai-seo-generator'); ?>
                        </button>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php _e('Import & Add to Queue', 'ai-seo-generator'); ?>
                        </button>
                    </p>
                    
                    <div id="sheets-preview" style="display: none; margin-top: 15px;">
                        <h4><?php _e('Preview Keywords:', 'ai-seo-generator'); ?></h4>
                        <div id="sheets-preview-list"></div>
                    </div>
                    
                    <div id="sheets-status" class="ai-seo-status-message" style="display: none;"></div>
                </form>
            </div>
        </div>
        
        <!-- Queue Status & Actions -->
        <div class="ai-seo-card">
            <h2><?php _e('Queue Status', 'ai-seo-generator'); ?></h2>
            
            <div class="ai-seo-queue-stats">
                <?php
                $pending = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'");
                ?>
                <div class="stat-box pending">
                    <span class="stat-number"><?php echo intval($pending); ?></span>
                    <span class="stat-label"><?php _e('In Queue', 'ai-seo-generator'); ?></span>
                </div>
            </div>
            
            <div class="ai-seo-queue-actions" style="margin-top: 20px;">
                <?php
                $next_run = wp_next_scheduled('AI_SEO_check_schedule');
                if ($next_run):
                ?>
                    <p>
                        <strong><?php _e('Next automatic check:', 'ai-seo-generator'); ?></strong>
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run); ?>
                    </p>
                <?php endif; ?>
                
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ai-seo-generator-queue&action=run_now'), 'ai_seo_run_scheduler'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span>
                        <?php _e('Process Queue Now', 'ai-seo-generator'); ?>
                    </a>
                    
                    <?php if ($pending > 0): ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ai-seo-generator-queue&action=clear_pending'), 'ai_seo_clear_pending'); ?>" class="button">
                        <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                        <?php _e('Clear Queue', 'ai-seo-generator'); ?>
                    </a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Queue Items Table -->
        <div class="ai-seo-card">
            <h2><?php _e('Queue Items', 'ai-seo-generator'); ?></h2>
            
            <?php if (empty($queue_items)): ?>
                <p class="description"><?php _e('No items in the queue. Add keywords above to get started.', 'ai-seo-generator'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Keyword', 'ai-seo-generator'); ?></th>
                            <th><?php _e('Scheduled For', 'ai-seo-generator'); ?></th>
                            <th><?php _e('Actions', 'ai-seo-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue_items as $item): ?>
                            <tr data-id="<?php echo esc_attr($item->id); ?>">
                                <td><?php echo esc_html($item->keywords); ?></td>
                                <td>
                                    <?php 
                                    if ($item->scheduled_at) {
                                        echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->scheduled_at));
                                    } else {
                                        _e('ASAP', 'ai-seo-generator');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small ai-seo-remove-btn" data-id="<?php echo $item->id; ?>">
                                        <?php _e('Remove', 'ai-seo-generator'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Tips -->
        <div class="ai-seo-card">
            <h2><?php _e('Tips', 'ai-seo-generator'); ?></h2>
            <ul>
                <li><?php _e('The queue automatically processes items every hour via WordPress cron.', 'ai-seo-generator'); ?></li>
                <li><?php _e('Use "Process Queue Now" to immediately generate articles for due items.', 'ai-seo-generator'); ?></li>
                <li><?php _e('Google Sheets integration updates the "Used" column and adds the article URL when complete.', 'ai-seo-generator'); ?></li>
                <li><?php _e('You will receive one email notification when the entire batch is completed.', 'ai-seo-generator'); ?></li>
            </ul>
        </div>
    </div>
</div>

<style>
.ai-seo-queue-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    border-bottom: 1px solid #ccc;
}
.ai-seo-tab-btn {
    padding: 10px 20px;
    background: #f1f1f1;
    border: 1px solid #ccc;
    border-bottom: none;
    cursor: pointer;
    margin-bottom: -1px;
}
.ai-seo-tab-btn.active {
    background: #fff;
    border-bottom: 1px solid #fff;
}
.ai-seo-tab-content {
    padding-top: 10px;
}
.ai-seo-queue-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.stat-box {
    padding: 20px 30px;
    background: #f9f9f9;
    border-radius: 8px;
    text-align: center;
    min-width: 100px;
}
.stat-box .stat-number {
    display: block;
    font-size: 32px;
    font-weight: bold;
}
.stat-box .stat-label {
    display: block;
    color: #666;
    margin-top: 5px;
}
.stat-box.pending { border-left: 4px solid #f0ad4e; }
.stat-box.processing { border-left: 4px solid #5bc0de; }
.stat-box.completed { border-left: 4px solid #5cb85c; }
.stat-box.failed { border-left: 4px solid #d9534f; }
.ai-seo-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}
.ai-seo-status-pending { background: #fcf8e3; color: #8a6d3b; }
.ai-seo-status-processing { background: #d9edf7; color: #31708f; }
.ai-seo-status-completed { background: #dff0d8; color: #3c763d; }
.ai-seo-status-failed { background: #f2dede; color: #a94442; }
</style>

<script>
jQuery(document).ready(function($) {
    // Ensure nonce is available
    var aiSeoNonce = (typeof aiSeo !== 'undefined' && aiSeo.nonce) ? aiSeo.nonce : '<?php echo wp_create_nonce("ai_seo_nonce"); ?>';
    
    // Tab switching
    $('.ai-seo-tab-btn').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        
        // Update tab buttons
        $('.ai-seo-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        // Show corresponding content
        $('.ai-seo-tab-content').hide();
        $('#' + tab + '-tab').show();
    });
    
    // Manual batch schedule form
    $('#ai-seo-batch-schedule-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $status = $('#batch-status');
        
        var keywordsText = $('#batch-keywords').val().trim();
        var startDate = $('#start-date').val();
        var frequency = $('#frequency').val();
        
        if (!keywordsText) {
            $status.html('<div class="notice notice-error"><p>Please enter keywords</p></div>').show();
            return;
        }
        
        var keywords = keywordsText.split('\n').filter(function(k) { return k.trim().length > 0; });
        
        if (keywords.length === 0) {
            $status.html('<div class="notice notice-error"><p>No valid keywords found</p></div>').show();
            return;
        }
        
        $btn.prop('disabled', true).text('Adding to queue...');
        $status.html('<div class="notice notice-info"><p>Adding ' + keywords.length + ' keywords to queue...</p></div>').show();
        
        var articlesPerInterval = parseInt($('#articles-per-interval').val()) || 1;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_seo_batch_schedule',
                nonce: aiSeoNonce,
                keywords: keywords,
                start_date: startDate,
                frequency: frequency,
                articles_per_interval: articlesPerInterval
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $status.html('<div class="notice notice-error"><p>' + (response.data.message || 'Failed to add to queue') + '</p></div>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> Add to Queue');
                }
            },
            error: function() {
                $status.html('<div class="notice notice-error"><p>Request failed</p></div>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> Add to Queue');
            }
        });
    });
    
    // Google Sheets import form
    $('#ai-seo-sheets-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $status = $('#sheets-status');
        
        var onlyUnused = $('#only-unused').is(':checked');
        var startDate = $('#sheets-start-date').val();
        var frequency = $('#sheets-frequency').val();
        var articlesPerInterval = parseInt($('#sheets-articles-per-interval').val()) || 1;
        
        $btn.prop('disabled', true).text('Importing...');
        $status.html('<div class="notice notice-info"><p>Importing ' + articlesPerInterval + ' keywords from Google Sheets...</p></div>').show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_seo_import_sheets_to_queue',
                nonce: aiSeoNonce,
                only_unused: onlyUnused ? '1' : '0',
                start_date: startDate,
                frequency: frequency,
                articles_per_interval: articlesPerInterval
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() { 
                        location.reload(); 
                    }, 1000);
                } else {
                    // Don't show errors during page unload/reload
                    if (document.hidden || !document.hasFocus()) {
                        return;
                    }
                    $status.html('<div class="notice notice-error"><p>' + (response.data.message || 'Import failed') + '</p></div>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Import & Add to Queue');
                }
            },
            error: function(xhr, status, error) {
                // Don't show errors during page unload/reload or if aborted
                if (status === 'abort' || document.hidden || !document.hasFocus()) {
                    return;
                }
                $status.html('<div class="notice notice-error"><p>Request failed</p></div>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Import & Add to Queue');
            }
        });
    });
    
    // Preview sheets keywords
    $('#preview-sheets-btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $preview = $('#sheets-preview');
        var $previewList = $('#sheets-preview-list');
        var $status = $('#sheets-status');
        
        var onlyUnused = $('#only-unused').is(':checked');
        var articlesPerInterval = parseInt($('#sheets-articles-per-interval').val()) || 1;
        
        $btn.prop('disabled', true).text('Fetching...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_seo_fetch_sheets_keywords',
                nonce: aiSeoNonce,
                only_unused: onlyUnused ? '1' : '0',
                limit: articlesPerInterval
            },
            success: function(response) {
                if (response.success && response.data.keywords) {
                    var keywords = response.data.keywords;
                    var html = '<ul>';
                    for (var i = 0; i < keywords.length; i++) {
                        html += '<li><strong>' + keywords[i].keyword + '</strong> (Row ' + keywords[i].row + ')</li>';
                    }
                    html += '</ul>';
                    html += '<p><strong>Will import ' + keywords.length + ' keywords for this batch</strong></p>';
                    
                    $previewList.html(html);
                    $preview.show();
                    $status.hide();
                } else {
                    $status.html('<div class="notice notice-error"><p>' + (response.data.message || 'No keywords found') + '</p></div>').show();
                    $preview.hide();
                }
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span> Preview Keywords');
            },
            error: function() {
                $status.html('<div class="notice notice-error"><p>Request failed</p></div>').show();
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span> Preview Keywords');
            }
        });
    });
    
    // Remove from queue - instant, no confirmation
    $(document).on('click', '.ai-seo-remove-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var itemId = $btn.data('id');
        var $row = $btn.closest('tr');
        
        $btn.prop('disabled', true).text('...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_seo_remove_from_queue',
                nonce: aiSeoNonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(200, function() { 
                        $(this).remove();
                        // Update pending count
                        var $pendingCount = $('.stat-box.pending .stat-number');
                        var count = parseInt($pendingCount.text()) - 1;
                        $pendingCount.text(Math.max(0, count));
                    });
                } else {
                    $btn.prop('disabled', false).text('Remove');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Remove');
            }
        });
    });
});
</script>
