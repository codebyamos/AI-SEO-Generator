<?php
/**
 * Plugin Name: AI SEO Generator
 * Plugin URI: https://github.com/codebyamos/AI-SEO-Generator
 * Description: Automated SEO content generation with Google Gemini AI, Google Sheets integration, and scheduling
 * Version: 1.0.1
 * Author: Plixail
 * Author URI: https://www.plixail.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-seo-generator
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Debug: Log plugin load
error_log('AI SEO Generator: Plugin loaded - Version 1.0.1');

// Define plugin constants
define('AI_SEO_VERSION', '1.0.1');
define('AI_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_SEO_PLUGIN_FILE', __FILE__);

// Require Composer autoloader if exists
if (file_exists(AI_SEO_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once AI_SEO_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main Plugin Class
 */
class AI_SEO_Generator {
    
    private static $instance = null;
    
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
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once AI_SEO_PLUGIN_DIR . 'class-admin-menu.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-gemini-api.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-google-sheets.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-scheduler.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-article-generator.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-email-notifications.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-settings.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-ajax.php';
        require_once AI_SEO_PLUGIN_DIR . 'class-github-updater.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(AI_SEO_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(AI_SEO_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Show admin notice if Composer dependencies are missing
        add_action('admin_notices', array($this, 'check_dependencies'));
    }
    
    /**
     * Check for missing dependencies and show admin notice
     */
    public function check_dependencies() {
        if (!file_exists(AI_SEO_PLUGIN_DIR . 'vendor/autoload.php')) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>AI SEO Generator:</strong> ';
            echo __('Composer dependencies are missing. Please run <code>composer install</code> in the plugin directory, or upload the <code>vendor</code> folder. Google Sheets and Image Generation features will not work until this is resolved.', 'ai-seo-generator');
            echo '</p></div>';
        }
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize components
        AI_SEO_Admin_Menu::get_instance();
        AI_SEO_Scheduler::get_instance();
        AI_SEO_Settings::get_instance();
        AI_SEO_AJAX::get_instance();
        AI_SEO_GitHub_Updater::get_instance();
        
        // Add custom admin footer for regenerate buttons
        add_action('admin_footer-post.php', array($this, 'add_regenerate_buttons_ui'));
        add_action('admin_footer-post-new.php', array($this, 'add_regenerate_buttons_ui'));
        
        // Add AI indicator column to posts list
        add_filter('manage_posts_columns', array($this, 'add_ai_column'));
        add_action('manage_posts_custom_column', array($this, 'display_ai_column'), 10, 2);
        add_action('admin_head', array($this, 'ai_column_styles'));
        
        // Load text domain for translations
        load_plugin_textdomain('ai-seo-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Add AI column to posts list
     */
    public function add_ai_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns['ai_generated'] = '<span class="dashicons dashicons-superhero-alt" title="AI Generated"></span>';
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }
    
    /**
     * Display AI indicator in column
     */
    public function display_ai_column($column, $post_id) {
        if ($column === 'ai_generated') {
            $is_ai = get_post_meta($post_id, '_ai_seo_generated', true);
            if ($is_ai) {
                echo '<span class="ai-seo-badge" title="Generated by AI SEO Generator">🤖</span>';
            } else {
                echo '<span class="ai-seo-badge-empty">—</span>';
            }
        }
    }
    
    /**
     * Styles for AI column
     */
    public function ai_column_styles() {
        $screen = get_current_screen();
        if ($screen && $screen->base === 'edit' && $screen->post_type === 'post') {
            echo '<style>
                .column-ai_generated { width: 30px; text-align: center; }
                .ai-seo-badge { 
                    font-size: 18px; 
                    cursor: help;
                }
                .ai-seo-badge-empty {
                    color: #ccc;
                }
            </style>';
        }
    }
    
    /**
     * Add regenerate buttons to UI
     */
    public function add_regenerate_buttons_ui() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Show for all posts
        ?>
        <style>
        .ai-seo-regen-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 12px 20px;
            margin: 0 0 20px 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        .ai-seo-regen-bar .ai-seo-logo {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        .ai-seo-regen-bar button {
            background: white !important;
            color: #667eea !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 4px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .ai-seo-regen-bar button:hover {
            background: #f0f0f0 !important;
        }
        </style>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('AI SEO Generator: Initializing button injection...');
            
            var injectButtons = function() {
                // Skip if already added
                if ($('.ai-seo-regen-bar').length) {
                    return;
                }
                
                var barHtml = '<div class="ai-seo-regen-bar">' +
                    '<button type="button" class="ai-seo-regenerate-title-inline" data-post-id="<?php echo esc_attr($post->ID); ?>">' +
                    '<span class="dashicons dashicons-update"></span> Regenerate Title</button>' +
                    '</div>';
                
                // Try multiple insertion points
                var inserted = false;
                
                // 1. Gutenberg: After post title wrapper
                var titleWrapper = $('.edit-post-visual-editor__post-title-wrapper');
                if (titleWrapper.length && !inserted) {
                    titleWrapper.after(barHtml);
                    inserted = true;
                    console.log('AI SEO: Inserted after post-title-wrapper');
                }
                
                // 2. Gutenberg: After editor-post-title
                if (!inserted && $('.editor-post-title').length) {
                    $('.editor-post-title').after(barHtml);
                    inserted = true;
                    console.log('AI SEO: Inserted after editor-post-title');
                }
                
                // 3. Gutenberg: After any h1 in the editor
                if (!inserted && $('.editor-styles-wrapper h1').length) {
                    $('.editor-styles-wrapper h1').first().after(barHtml);
                    inserted = true;
                    console.log('AI SEO: Inserted after h1');
                }
                
                // 4. Classic Editor
                if (!inserted && $('#title').length) {
                    $('#titlewrap').after(barHtml);
                    inserted = true;
                    console.log('AI SEO: Inserted in classic editor');
                }
                
                // 5. Absolute fallback: Prepend to editor header
                if (!inserted && $('.edit-post-header').length) {
                    var headerBar = '<div class="ai-seo-regen-bar" style="margin: 10px; position: absolute; top: 60px; left: 10px; z-index: 9999;">' +
                        '<button type="button" class="ai-seo-regenerate-title-inline" data-post-id="<?php echo esc_attr($post->ID); ?>">' +
                        '<span class="dashicons dashicons-update"></span> Regen Title</button>' +
                        '</div>';
                    $('body').append(headerBar);
                    inserted = true;
                    console.log('AI SEO: Inserted as floating bar');
                }
                
                // UNIFIED AI Image Generator Widget - combines custom prompt and featured image
                if (!$('#ai-image-widget').length) {
                    var unifiedWidget = '<div id="ai-image-widget" style="position: fixed; bottom: 20px; right: 20px; z-index: 99999; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">' +
                        '<div id="ai-widget-toggle" style="width: 56px; height: 56px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(102,126,234,0.4); transition: transform 0.2s;" title="AI Image Generator">' +
                        '<span style="color: white; font-size: 26px;">🎨</span></div>' +
                        '<div id="ai-widget-panel" style="display: none; position: absolute; bottom: 65px; right: 0; width: 340px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); padding: 20px; border: 1px solid #e0e0e0;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">' +
                        '<h4 style="margin: 0; font-size: 16px; color: #1e1e1e;">🎨 AI Image Generator</h4>' +
                        '<span id="ai-widget-close" style="cursor: pointer; font-size: 20px; color: #666; line-height: 1;">&times;</span></div>' +
                        '<textarea id="ai-image-prompt" placeholder="Describe the image you want to generate...\n\nLeave empty to use article title for featured image" style="width: 100%; height: 70px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; resize: none; font-size: 14px; box-sizing: border-box;"></textarea>' +
                        '<div style="display: flex; gap: 10px; margin-top: 12px;">' +
                        '<select id="ai-widget-orientation" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">' +
                        '<option value="landscape">Landscape</option>' +
                        '<option value="portrait">Portrait</option></select>' +
                        '<button type="button" id="ai-generate-image" data-post-id="<?php echo esc_attr($post->ID); ?>" style="flex: 1; padding: 10px 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px;">Generate</button></div>' +
                        '<div id="ai-image-result" style="margin-top: 15px; display: none;">' +
                        '<img id="ai-generated-image" src="" style="width: 100%; border-radius: 8px; margin-bottom: 10px;">' +
                        '<div style="display: flex; flex-direction: column; gap: 8px;">' +
                        '<button type="button" id="ai-use-featured" style="width: 100%; padding: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">⭐ Use as Featured Image</button>' +
                        '<div style="display: flex; gap: 8px;">' +
                        '<button type="button" id="ai-copy-url" style="flex: 1; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; font-size: 13px;">📋 Copy URL</button>' +
                        '<button type="button" id="ai-insert-block" style="flex: 1; padding: 8px; background: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">➕ Insert Block</button></div></div></div>' +
                        '</div></div>';
                    $('body').append(unifiedWidget);
                    console.log('AI SEO: Added unified AI image widget');
                    
                    // Toggle widget panel
                    $('#ai-widget-toggle').on('click', function() {
                        $('#ai-widget-panel').slideToggle(200);
                    });
                    $('#ai-widget-close').on('click', function() {
                        $('#ai-widget-panel').slideUp(200);
                    });
                    
                    // Generate image
                    $(document).on('click', '#ai-generate-image', function() {
                        var $btn = $(this);
                        var prompt = $('#ai-image-prompt').val().trim();
                        var orientation = $('#ai-widget-orientation').val();
                        var postId = $btn.data('post-id');
                        
                        // If no prompt, use article title
                        var useArticleTitle = !prompt;
                        
                        $btn.prop('disabled', true).text('Generating...');
                        
                        var ajaxUrl = (typeof aiSeo !== 'undefined') ? aiSeo.ajaxUrl : '<?php echo admin_url('admin-ajax.php'); ?>';
                        var nonce = (typeof aiSeo !== 'undefined') ? aiSeo.nonce : '<?php echo wp_create_nonce('ai_seo_nonce'); ?>';
                        
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: {
                                action: useArticleTitle ? 'ai_seo_regenerate_image' : 'ai_seo_generate_custom_image',
                                nonce: nonce,
                                post_id: postId,
                                prompt: prompt,
                                orientation: orientation
                            },
                            success: function(response) {
                                console.log('AI Image Response:', response);
                                if (response.data && response.data.debug) {
                                    console.log('Debug Log:', response.data.debug);
                                }
                                if (response.data && response.data.prompt_used) {
                                    console.log('Prompt used:', response.data.prompt_used);
                                }
                                if (response.success) {
                                    var imageUrl = response.data.image_url || response.data.full_url;
                                    $('#ai-generated-image').attr('src', imageUrl);
                                    $('#ai-image-result')
                                        .data('image-url', imageUrl)
                                        .data('image-id', response.data.image_id)
                                        .slideDown(200);
                                    
                                    // If used article title, it's already set as featured
                                    if (useArticleTitle) {
                                        $('#ai-use-featured').html('✓ Already Set as Featured!').prop('disabled', true);
                                        setTimeout(function() {
                                            $('#ai-use-featured').html('⭐ Use as Featured Image').prop('disabled', false);
                                        }, 3000);
                                    }
                                } else {
                                    console.error('Image Error:', response.data);
                                    if (response.data && response.data.debug) {
                                        console.error('Debug Log:', response.data.debug);
                                    }
                                    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to generate image'));
                                }
                                $btn.prop('disabled', false).text('Generate');
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', { status: status, error: error, response: xhr.responseText });
                                alert('Request failed: ' + error);
                                $btn.prop('disabled', false).text('Generate');
                            }
                        });
                    });
                    
                    // Use as Featured Image
                    $(document).on('click', '#ai-use-featured', function() {
                        var $btn = $(this);
                        
                        // Prevent duplicate clicks
                        if ($btn.prop('disabled') || $btn.data('processing')) {
                            return;
                        }
                        
                        var imageId = $('#ai-image-result').data('image-id');
                        var imageUrl = $('#ai-image-result').data('image-url');
                        var postId = $('#ai-generate-image').data('post-id');
                        
                        if (!imageId) {
                            alert('Please generate an image first');
                            return;
                        }
                        
                        // Check if already set as featured
                        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                            var currentFeatured = wp.data.select('core/editor').getEditedPostAttribute('featured_media');
                            if (currentFeatured === parseInt(imageId)) {
                                $btn.html('✓ Already Set as Featured!');
                                return;
                            }
                        }
                        
                        $btn.prop('disabled', true).data('processing', true).html('Setting...');
                        
                        var ajaxUrl = (typeof aiSeo !== 'undefined') ? aiSeo.ajaxUrl : '<?php echo admin_url('admin-ajax.php'); ?>';
                        var nonce = (typeof aiSeo !== 'undefined') ? aiSeo.nonce : '<?php echo wp_create_nonce('ai_seo_nonce'); ?>';
                        
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'ai_seo_set_featured_image',
                                nonce: nonce,
                                post_id: postId,
                                image_id: imageId
                            },
                            success: function(response) {
                                console.log('Set Featured Response:', response);
                                $btn.data('processing', false);
                                if (response.success) {
                                    $btn.html('✓ Featured Image Set!').prop('disabled', true);
                                    
                                    // Update Gutenberg if available
                                    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                                        wp.data.dispatch('core/editor').editPost({ featured_media: parseInt(imageId) });
                                    }
                                    
                                    // Keep it disabled since it's already set
                                    setTimeout(function() {
                                        $btn.html('✓ Featured Image Set!');
                                    }, 2000);
                                } else {
                                    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to set featured image'));
                                    $btn.html('⭐ Use as Featured Image').prop('disabled', false);
                                }
                            },
                            error: function() {
                                $btn.data('processing', false);
                                alert('Request failed');
                                $btn.html('⭐ Use as Featured Image').prop('disabled', false);
                            }
                        });
                    });
                    
                    // Copy URL
                    $(document).on('click', '#ai-copy-url', function() {
                        var $btn = $(this);
                        var url = $('#ai-image-result').data('image-url');
                        navigator.clipboard.writeText(url).then(function() {
                            $btn.html('✓ Copied!');
                            setTimeout(function() { $btn.html('📋 Copy URL'); }, 2000);
                        });
                    });
                    
                    // Insert Block
                    $(document).on('click', '#ai-insert-block', function() {
                        var $btn = $(this);
                        var url = $('#ai-image-result').data('image-url');
                        var imageId = $('#ai-image-result').data('image-id');
                        
                        if (typeof wp !== 'undefined' && wp.blocks && wp.data) {
                            try {
                                var imageBlock = wp.blocks.createBlock('core/image', {
                                    url: url,
                                    id: parseInt(imageId),
                                    alt: 'AI Generated Image',
                                    sizeSlug: 'large',
                                    linkDestination: 'none'
                                });
                                wp.data.dispatch('core/block-editor').insertBlock(imageBlock);
                                $btn.html('✓ Inserted!');
                                setTimeout(function() {
                                    $btn.html('➕ Insert Block');
                                    $('#ai-widget-panel').slideUp(200);
                                }, 1500);
                            } catch(e) {
                                console.error('Insert error:', e);
                                navigator.clipboard.writeText(url);
                                $btn.html('📋 URL Copied!');
                                setTimeout(function() { $btn.html('➕ Insert Block'); }, 2000);
                            }
                        } else {
                            navigator.clipboard.writeText(url);
                            $btn.html('📋 URL Copied!');
                            setTimeout(function() { $btn.html('➕ Insert Block'); }, 2000);
                        }
                    });
                }
            };
            
            // Run immediately and then every 500ms
            injectButtons();
            setInterval(injectButtons, 500);
        });
        </script>
        <?php
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Load on plugin pages and post editor
        if (strpos($hook, 'ai-seo-generator') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_style('ai-seo-admin', AI_SEO_PLUGIN_URL . 'admin.css', array(), AI_SEO_VERSION);
        wp_enqueue_script('ai-seo-admin', AI_SEO_PLUGIN_URL . 'admin.js', array('jquery'), AI_SEO_VERSION, true);
        
        wp_localize_script('ai-seo-admin', 'aiSeo', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_seo_nonce'),
            'pluginUrl' => admin_url(),
        ));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('ai_seo_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'ai_seo_process_queue');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('ai_seo_process_queue');
        wp_clear_scheduled_hook('ai_seo_process_single_item');
        
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'ai_seo_queue';
        
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
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'gemini_api_key' => '',
            'google_sheets_enabled' => false,
            'google_sheets_id' => '',
            'email_notifications' => true,
            'notification_email' => get_option('admin_email'),
            'default_post_status' => 'draft',
            'include_tables' => false,
            'image_count' => 1,
            'internal_links' => 2,
            'external_links' => 2,
        );
        
        foreach ($defaults as $key => $value) {
            $option_name = 'ai_seo_' . $key;
            if (false === get_option($option_name)) {
                add_option($option_name, $value);
            }
        }
    }
}

// Initialize the plugin
function ai_seo_generator() {
    return AI_SEO_Generator::get_instance();
}

// Kick off the plugin
ai_seo_generator();
