<?php
/**
 * Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$scheduler = AI_SEO_Scheduler::get_instance();

// Get AI article statistics (posts with _ai_seo_generated meta)
$total_ai_articles = count(get_posts(array(
    'meta_key' => '_ai_seo_generated',
    'posts_per_page' => -1,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'fields' => 'ids',
)));

$published_count = count(get_posts(array(
    'meta_key' => '_ai_seo_generated',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'fields' => 'ids',
)));

$drafts_count = count(get_posts(array(
    'meta_key' => '_ai_seo_generated',
    'posts_per_page' => -1,
    'post_status' => array('draft', 'pending'),
    'fields' => 'ids',
)));

// Get queue count
global $wpdb;
$table_name = $wpdb->prefix . 'ai_seo_queue';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
$queue_count = 0;
if ($table_exists) {
    $queue_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'");
}

// Get recent generated posts (AI-generated articles)
$recent_posts = get_posts(array(
    'meta_key' => '_ai_seo_generated',
    'orderby' => 'date',
    'order' => 'DESC',
    'posts_per_page' => 10,
    'post_status' => array('publish', 'draft', 'pending'),
));
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ai-seo-dashboard">
        <!-- Quick Stats -->
        <div class="ai-seo-stats-grid">
            <div class="ai-seo-stat-card total">
                <div class="ai-seo-stat-icon">🤖</div>
                <div class="ai-seo-stat-content">
                    <div class="ai-seo-stat-value" id="stat-total"><?php echo esc_html($total_ai_articles); ?></div>
                    <div class="ai-seo-stat-label"><?php _e('Total AI Articles', 'ai-seo-generator'); ?></div>
                </div>
            </div>
            
            <div class="ai-seo-stat-card success">
                <div class="ai-seo-stat-icon">✓</div>
                <div class="ai-seo-stat-content">
                    <div class="ai-seo-stat-value" id="stat-published"><?php echo esc_html($published_count); ?></div>
                    <div class="ai-seo-stat-label"><?php _e('Published', 'ai-seo-generator'); ?></div>
                </div>
            </div>
            
            <div class="ai-seo-stat-card pending">
                <div class="ai-seo-stat-icon">📝</div>
                <div class="ai-seo-stat-content">
                    <div class="ai-seo-stat-value" id="stat-drafts"><?php echo esc_html($drafts_count); ?></div>
                    <div class="ai-seo-stat-label"><?php _e('Drafts', 'ai-seo-generator'); ?></div>
                </div>
            </div>
            
            <div class="ai-seo-stat-card queue">
                <div class="ai-seo-stat-icon">⏳</div>
                <div class="ai-seo-stat-content">
                    <div class="ai-seo-stat-value" id="stat-queue"><?php echo esc_html($queue_count); ?></div>
                    <div class="ai-seo-stat-label"><?php _e('In Queue', 'ai-seo-generator'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="ai-seo-card">
            <h2><?php _e('Quick Actions', 'ai-seo-generator'); ?></h2>
            <div class="ai-seo-actions">
                <a href="#" id="ai-seo-generate-now" class="button button-primary button-large">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Generate Article Now', 'ai-seo-generator'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ai-seo-generator-queue'); ?>" class="button button-large">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e('View Queue', 'ai-seo-generator'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ai-seo-generator-settings'); ?>" class="button button-large">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Settings', 'ai-seo-generator'); ?>
                </a>
            </div>
        </div>
        
        <!-- Recently Generated Articles -->
        <div class="ai-seo-card">
            <h2><?php _e('Recently Generated Articles', 'ai-seo-generator'); ?> 🤖</h2>
            <div id="recent-articles-container">
            <?php if (!empty($recent_posts)): ?>
                <table class="wp-list-table widefat fixed striped" id="recent-articles-table">
                    <thead>
                        <tr>
                            <th><?php _e('Title', 'ai-seo-generator'); ?></th>
                            <th><?php _e('Status', 'ai-seo-generator'); ?></th>
                            <th><?php _e('Created', 'ai-seo-generator'); ?></th>
                            <th><?php _e('Actions', 'ai-seo-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_posts as $post): ?>
                            <tr data-post-id="<?php echo esc_attr($post->ID); ?>" data-post-status="<?php echo esc_attr($post->post_status); ?>">
                                <td><strong><?php echo esc_html($post->post_title); ?></strong></td>
                                <td>
                                    <span class="ai-seo-status ai-seo-status-<?php echo esc_attr($post->post_status); ?>">
                                        <?php 
                                        $status_labels = array(
                                            'publish' => __('Published', 'ai-seo-generator'),
                                            'draft' => __('Draft', 'ai-seo-generator'),
                                            'pending' => __('Pending', 'ai-seo-generator'),
                                            'private' => __('Private', 'ai-seo-generator'),
                                        );
                                        echo esc_html(isset($status_labels[$post->post_status]) ? $status_labels[$post->post_status] : ucfirst($post->post_status)); 
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post->post_date))); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($post->ID); ?>" class="button button-small"><?php _e('Edit', 'ai-seo-generator'); ?></a>
                                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank" class="button button-small"><?php _e('View', 'ai-seo-generator'); ?></a>
                                    <button type="button" class="button button-small button-link-delete ai-seo-delete-article" data-post-id="<?php echo esc_attr($post->ID); ?>" data-post-status="<?php echo esc_attr($post->post_status); ?>">
                                        <?php _e('Delete', 'ai-seo-generator'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="ai-seo-view-all">
                    <a href="<?php echo admin_url('edit.php?meta_key=_ai_seo_generated'); ?>">
                        <?php _e('View all AI-generated posts →', 'ai-seo-generator'); ?>
                    </a>
                </p>
            <?php else: ?>
                <p id="no-articles-message"><?php _e('No articles generated yet. Use "Generate Article Now" to create your first AI article!', 'ai-seo-generator'); ?></p>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Generate Modal -->
<div id="ai-seo-generate-modal" class="ai-seo-modal" style="display: none;">
    <div class="ai-seo-modal-content">
        <span class="ai-seo-modal-close">&times;</span>
        <h2><?php _e('Generate Article', 'ai-seo-generator'); ?></h2>
        <form id="ai-seo-generate-form">
            <p>
                <label for="ai-seo-keywords">
                    <strong><?php _e('Keywords / Topic', 'ai-seo-generator'); ?></strong>
                </label>
                <input type="text" id="ai-seo-keywords" name="keywords" class="regular-text" required>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="generate_now" value="1" checked>
                    <?php _e('Generate immediately', 'ai-seo-generator'); ?>
                </label>
            </p>
            <p>
                <button type="submit" class="button button-primary"><?php _e('Generate', 'ai-seo-generator'); ?></button>
                <button type="button" class="button ai-seo-modal-cancel"><?php _e('Cancel', 'ai-seo-generator'); ?></button>
            </p>
            <div id="ai-seo-generate-status" class="ai-seo-status-message" style="display: none;"></div>
        </form>
    </div>
</div>

<style>
.ai-seo-stat-card.queue {
    border-left-color: #f0ad4e;
}
.ai-seo-stat-card.total {
    border-left-color: #0073aa;
}
.button-link-delete {
    color: #a00 !important;
    border-color: #a00 !important;
}
.button-link-delete:hover {
    color: #dc3232 !important;
    border-color: #dc3232 !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Delete article handler
    $(document).on('click', '.ai-seo-delete-article', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var postStatus = $btn.data('post-status');
        var $row = $btn.closest('tr');
        
        if (!confirm('<?php _e('Are you sure you want to delete this article?', 'ai-seo-generator'); ?>')) {
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Deleting...', 'ai-seo-generator'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_seo_delete_article',
                nonce: aiSeo.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    // Update stats
                    var totalVal = parseInt($('#stat-total').text()) - 1;
                    $('#stat-total').text(Math.max(0, totalVal));
                    
                    if (postStatus === 'publish') {
                        var pubVal = parseInt($('#stat-published').text()) - 1;
                        $('#stat-published').text(Math.max(0, pubVal));
                    } else {
                        var draftVal = parseInt($('#stat-drafts').text()) - 1;
                        $('#stat-drafts').text(Math.max(0, draftVal));
                    }
                    
                    // Remove row with animation
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('#recent-articles-table tbody tr').length === 0) {
                            $('#recent-articles-table').remove();
                            $('.ai-seo-view-all').remove();
                            $('#recent-articles-container').html('<p id="no-articles-message"><?php _e('No articles generated yet. Use "Generate Article Now" to create your first AI article!', 'ai-seo-generator'); ?></p>');
                        }
                    });
                } else {
                    alert(response.data.message || '<?php _e('Failed to delete article', 'ai-seo-generator'); ?>');
                    $btn.prop('disabled', false).text('<?php _e('Delete', 'ai-seo-generator'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Request failed. Please try again.', 'ai-seo-generator'); ?>');
                $btn.prop('disabled', false).text('<?php _e('Delete', 'ai-seo-generator'); ?>');
            }
        });
    });
});
</script>
