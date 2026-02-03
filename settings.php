<?php
/**
 * Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Save settings
if (isset($_POST['ai_seo_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'ai_seo_settings')) {
    // API Keys
    update_option('AI_SEO_gemini_api_key', sanitize_text_field(isset($_POST['gemini_api_key']) ? $_POST['gemini_api_key'] : ''));
    update_option('AI_SEO_wordpress_api_key', sanitize_text_field(isset($_POST['wordpress_api_key']) ? $_POST['wordpress_api_key'] : ''));
    update_option('AI_SEO_openai_api_key', sanitize_text_field(isset($_POST['openai_api_key']) ? $_POST['openai_api_key'] : ''));
    update_option('AI_SEO_anthropic_api_key', sanitize_text_field(isset($_POST['anthropic_api_key']) ? $_POST['anthropic_api_key'] : ''));
    
    // Integrations
    update_option('AI_SEO_google_sheets_enabled', isset($_POST['google_sheets_enabled']));
    update_option('AI_SEO_google_sheets_id', sanitize_text_field(isset($_POST['google_sheets_id']) ? $_POST['google_sheets_id'] : ''));
    update_option('AI_SEO_google_credentials_path', sanitize_text_field(isset($_POST['google_credentials_path']) ? $_POST['google_credentials_path'] : ''));
    
    // Notifications
    update_option('AI_SEO_email_notifications', isset($_POST['email_notifications']));
    update_option('AI_SEO_notification_email', sanitize_email(isset($_POST['notification_email']) ? $_POST['notification_email'] : ''));
    
    // Content Settings
    update_option('AI_SEO_default_post_status', sanitize_text_field(isset($_POST['default_post_status']) ? $_POST['default_post_status'] : 'draft'));
    update_option('AI_SEO_include_tables', isset($_POST['include_tables']));
    update_option('AI_SEO_internal_links', absint(isset($_POST['internal_links']) ? $_POST['internal_links'] : 2));
    update_option('AI_SEO_external_links', absint(isset($_POST['external_links']) ? $_POST['external_links'] : 2));
    update_option('AI_SEO_business_description', wp_kses_post(stripslashes(isset($_POST['business_description']) ? $_POST['business_description'] : '')));
    
    add_settings_error('ai_seo', 'settings_saved', __('Settings saved successfully', 'ai-seo-generator'), 'success');
}

$settings = AI_SEO_Settings::get_instance()->get_all_settings();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('ai_seo'); ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="#api-keys" class="nav-tab nav-tab-active"><?php _e('API Keys', 'ai-seo-generator'); ?></a>
        <a href="#integrations" class="nav-tab"><?php _e('Integrations', 'ai-seo-generator'); ?></a>
        <a href="#content" class="nav-tab"><?php _e('Content Settings', 'ai-seo-generator'); ?></a>
        <a href="#notifications" class="nav-tab"><?php _e('Notifications', 'ai-seo-generator'); ?></a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('ai_seo_settings'); ?>
        
        <div class="ai-seo-settings-grid">
            <!-- API Keys Section -->
            <div id="api-keys-section" class="ai-seo-settings-section">
                <div class="ai-seo-card">
                    <h2><?php _e('API Keys & Credentials', 'ai-seo-generator'); ?></h2>
                    <p class="description" style="margin-bottom: 20px;">
                        <?php _e('Configure all API keys and authentication credentials needed for the plugin to function.', 'ai-seo-generator'); ?>
                    </p>
                    
                    <table class="form-table">
                        <!-- Gemini API Key -->
                        <tr>
                            <th scope="row">
                                <label for="gemini_api_key">
                                    <?php _e('Google Gemini API Key', 'ai-seo-generator'); ?>
                                    <span class="required" style="color: #dc3232;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="gemini_api_key" 
                                       name="gemini_api_key" 
                                       value="<?php echo esc_attr($settings['gemini_api_key']); ?>" 
                                       class="large-text"
                                       placeholder="AIzaSy...">
                                <p class="description">
                                    <?php _e('Required for content and image generation.', 'ai-seo-generator'); ?>
                                    <a href="https://aistudio.google.com/app/apikey" target="_blank"><?php _e('Get API Key →', 'ai-seo-generator'); ?></a>
                                </p>
                                <p>
                                    <button type="button" id="test-gemini" class="button"><?php _e('Test Connection', 'ai-seo-generator'); ?></button>
                                    <span id="gemini-test-result"></span>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2"><hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;"></td>
                        </tr>
                        
                        <!-- WordPress API (if needed for external access) -->
                        <tr>
                            <th scope="row">
                                <label for="wordpress_api_key"><?php _e('WordPress API Key', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="wordpress_api_key" 
                                       name="wordpress_api_key" 
                                       value="<?php echo esc_attr(get_option('AI_SEO_wordpress_api_key', '')); ?>" 
                                       class="large-text"
                                       placeholder="Optional - for external API access">
                                <p class="description">
                                    <?php _e('Optional: Use this for external API access to trigger content generation.', 'ai-seo-generator'); ?>
                                </p>
                                <p>
                                    <button type="button" id="generate-api-key" class="button"><?php _e('Generate New Key', 'ai-seo-generator'); ?></button>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2"><hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;"></td>
                        </tr>
                        
                        <!-- OpenAI API Key (for future expansion) -->
                        <tr>
                            <th scope="row">
                                <label for="openai_api_key"><?php _e('OpenAI API Key', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="openai_api_key" 
                                       name="openai_api_key" 
                                       value="<?php echo esc_attr(get_option('AI_SEO_openai_api_key', '')); ?>" 
                                       class="large-text"
                                       placeholder="sk-...">
                                <p class="description">
                                    <?php _e('Optional: Alternative AI provider for content generation.', 'ai-seo-generator'); ?>
                                    <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('Get API Key →', 'ai-seo-generator'); ?></a>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2"><hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;"></td>
                        </tr>
                        
                        <!-- Anthropic API Key (for future expansion) -->
                        <tr>
                            <th scope="row">
                                <label for="anthropic_api_key"><?php _e('Anthropic API Key', 'ai-seo-generator'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="anthropic_api_key" 
                                       name="anthropic_api_key" 
                                       value="<?php echo esc_attr(get_option('AI_SEO_anthropic_api_key', '')); ?>" 
                                       class="large-text"
                                       placeholder="sk-ant-...">
                                <p class="description">
                                    <?php _e('Optional: Use Claude for content generation.', 'ai-seo-generator'); ?>
                                    <a href="https://console.anthropic.com/settings/keys" target="_blank"><?php _e('Get API Key →', 'ai-seo-generator'); ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="notice notice-info inline" style="margin-top: 20px;">
                        <p>
                            <strong><?php _e('API Key Security:', 'ai-seo-generator'); ?></strong>
                            <?php _e('All API keys are stored securely in your WordPress database. Never share these keys publicly.', 'ai-seo-generator'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Integrations Section -->
            <div id="integrations-section" class="ai-seo-settings-section" style="display: none;">
            
                <!-- Google Sheets Settings -->
                <div class="ai-seo-card">
                    <h2><?php _e('Google Sheets Integration', 'ai-seo-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="google_sheets_enabled"><?php _e('Enable Google Sheets', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="google_sheets_enabled" 
                                       name="google_sheets_enabled" 
                                       value="1" 
                                       <?php checked($settings['google_sheets_enabled']); ?>>
                                <?php _e('Enable Google Sheets integration', 'ai-seo-generator'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="google_sheets_id"><?php _e('Spreadsheet ID', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="google_sheets_id" 
                                   name="google_sheets_id" 
                                   value="<?php echo esc_attr($settings['google_sheets_id']); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Found in the spreadsheet URL: docs.google.com/spreadsheets/d/[SPREADSHEET_ID]', 'ai-seo-generator'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="google_credentials_path">
                                <?php _e('Credentials File Path', 'ai-seo-generator'); ?>
                                <span class="dashicons dashicons-editor-help" id="credentials-help-icon" style="cursor: pointer; color: #0073aa;" title="<?php esc_attr_e('Click for instructions', 'ai-seo-generator'); ?>"></span>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="google_credentials_path" 
                                   name="google_credentials_path" 
                                   value="<?php echo esc_attr($settings['google_credentials_path']); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php _e('Full server path to your Google service account JSON file', 'ai-seo-generator'); ?>
                            </p>
                            <?php
                            // Try to get and display the service account email
                            $sheets = AI_SEO_Google_Sheets::get_instance();
                            $service_email = $sheets->get_service_account_email();
                            if ($service_email):
                            ?>
                            <div class="notice notice-info inline" style="margin: 10px 0; padding: 10px;">
                                <p>
                                    <strong><?php _e('Important:', 'ai-seo-generator'); ?></strong>
                                    <?php _e('You must share your Google Sheet with the service account email:', 'ai-seo-generator'); ?>
                                    <code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; user-select: all;"><?php echo esc_html($service_email); ?></code>
                                    — <?php _e('Give it "Editor" access.', 'ai-seo-generator'); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            <p>
                                <button type="button" id="test-sheets" class="button"><?php _e('Test Connection', 'ai-seo-generator'); ?></button>
                                <button type="button" id="test-sheet-update" class="button" style="margin-left: 10px;"><?php _e('Test Sheet Update', 'ai-seo-generator'); ?></button>
                                <span id="sheets-test-result"></span>
                            </p>
                            <p class="description" style="margin-top: 5px;">
                                <?php _e('<strong>Test Sheet Update</strong> will mark the first unused keyword row with "Yes" and a test URL, and color it green (#b6d7a8).', 'ai-seo-generator'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                </div>
                
                <!-- Credentials Help Modal -->
                <div id="credentials-help-modal" class="ai-seo-modal" style="display: none;">
                    <div class="ai-seo-modal-content" style="max-width: 700px;">
                        <span class="ai-seo-modal-close">&times;</span>
                        <h2><?php _e('How to Get Google Service Account Credentials', 'ai-seo-generator'); ?></h2>
                        <ol style="line-height: 1.8;">
                            <li>
                                <strong><?php _e('Go to Google Cloud Console', 'ai-seo-generator'); ?></strong><br>
                                <a href="https://console.cloud.google.com/" target="_blank">https://console.cloud.google.com/</a>
                            </li>
                            <li>
                                <strong><?php _e('Create or select a project', 'ai-seo-generator'); ?></strong><br>
                                <?php _e('Click the project dropdown at the top and create a new project or select an existing one.', 'ai-seo-generator'); ?>
                            </li>
                            <li>
                                <strong><?php _e('Enable the Google Sheets API', 'ai-seo-generator'); ?></strong><br>
                                <?php _e('Go to "APIs & Services" → "Library" → Search for "Google Sheets API" → Click "Enable"', 'ai-seo-generator'); ?>
                            </li>
                            <li>
                                <strong><?php _e('Create a Service Account', 'ai-seo-generator'); ?></strong><br>
                                <?php _e('Go to "APIs & Services" → "Credentials" → "Create Credentials" → "Service Account"', 'ai-seo-generator'); ?><br>
                                <?php _e('Give it a name and click "Create and Continue" → Skip optional steps → Click "Done"', 'ai-seo-generator'); ?>
                            </li>
                            <li>
                                <strong><?php _e('Download the JSON Key', 'ai-seo-generator'); ?></strong><br>
                                <?php _e('Click on the service account you just created → Go to "Keys" tab → "Add Key" → "Create new key" → Select "JSON" → Click "Create"', 'ai-seo-generator'); ?><br>
                                <?php _e('A JSON file will be downloaded to your computer.', 'ai-seo-generator'); ?>
                            </li>
                            <li>
                                <strong><?php _e('Upload to Your Server', 'ai-seo-generator'); ?></strong><br>
                                <?php _e('Upload the JSON file to your server (preferably outside the public web directory for security).', 'ai-seo-generator'); ?><br>
                                <?php _e('Enter the full server path in the "Credentials File Path" field above.', 'ai-seo-generator'); ?><br>
                                <?php printf(__('Example: %s', 'ai-seo-generator'), '<code>/home/username/credentials/google-service-account.json</code>'); ?>
                            </li>
                            <li>
                                <strong><?php _e('Share Your Google Sheet', 'ai-seo-generator'); ?></strong><br>
                                <?php _e('Open your Google Sheet → Click "Share" button → Add the service account email (found in the JSON file as "client_email") → Give it "Editor" access.', 'ai-seo-generator'); ?>
                            </li>
                        </ol>
                        <p style="margin-top: 20px;">
                            <button type="button" class="button ai-seo-modal-close"><?php _e('Close', 'ai-seo-generator'); ?></button>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Section -->
            <div id="notifications-section" class="ai-seo-settings-section" style="display: none;">
                <!-- Email Settings -->
                <div class="ai-seo-card">
                <h2><?php _e('Email Notifications', 'ai-seo-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email_notifications"><?php _e('Enable Notifications', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="email_notifications" 
                                       name="email_notifications" 
                                       value="1" 
                                       <?php checked($settings['email_notifications']); ?>>
                                <?php _e('Send email notifications', 'ai-seo-generator'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="notification_email"><?php _e('Notification Email', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="notification_email" 
                                   name="notification_email" 
                                   value="<?php echo esc_attr($settings['notification_email']); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Email address where notifications will be sent.', 'ai-seo-generator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e('Test Email', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <button type="button" id="test-email-btn" class="button">
                                <span class="dashicons dashicons-email" style="vertical-align: middle;"></span>
                                <?php _e('Send Test Email', 'ai-seo-generator'); ?>
                            </button>
                            <span id="test-email-result" style="margin-left: 10px;"></span>
                            <p class="description"><?php _e('Send a test email to verify your email configuration is working correctly.', 'ai-seo-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                </div>
            </div>
            
            <!-- Content Section -->
            <div id="content-section" class="ai-seo-settings-section" style="display: none;">
                <!-- Content Settings -->
                <div class="ai-seo-card">
            
            <!-- Content Settings -->
            <div class="ai-seo-card">
                <h2><?php _e('Content Generation Settings', 'ai-seo-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="business_description"><?php _e('Business Description', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <textarea id="business_description" 
                                      name="business_description" 
                                      rows="6" 
                                      class="large-text" 
                                      placeholder="<?php esc_attr_e('Describe your business, expertise, products, services, and unique value proposition...', 'ai-seo-generator'); ?>"><?php echo esc_textarea(get_option('AI_SEO_business_description', '')); ?></textarea>
                            <p class="description">
                                <?php _e('Provide detailed information about your business. This will be used to generate content that demonstrates E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) and creates articles with specific, relevant insights rather than generic content.', 'ai-seo-generator'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;"></td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_post_status"><?php _e('Default Post Status', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <select id="default_post_status" name="default_post_status">
                                <option value="draft" <?php selected($settings['default_post_status'], 'draft'); ?>><?php _e('Draft', 'ai-seo-generator'); ?></option>
                                <option value="pending" <?php selected($settings['default_post_status'], 'pending'); ?>><?php _e('Pending Review', 'ai-seo-generator'); ?></option>
                                <option value="publish" <?php selected($settings['default_post_status'], 'publish'); ?>><?php _e('Published', 'ai-seo-generator'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="include_tables"><?php _e('Include Tables', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="include_tables" 
                                       name="include_tables" 
                                       value="1" 
                                       <?php checked($settings['include_tables']); ?>>
                                <?php _e('Include HTML tables in generated content', 'ai-seo-generator'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="internal_links"><?php _e('Internal Links', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="internal_links" 
                                   name="internal_links" 
                                   value="<?php echo esc_attr($settings['internal_links']); ?>" 
                                   min="0" 
                                   max="10" 
                                   class="small-text">
                            <p class="description"><?php _e('Number of internal links to add', 'ai-seo-generator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="external_links"><?php _e('External Links', 'ai-seo-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="external_links" 
                                   name="external_links" 
                                   value="<?php echo esc_attr($settings['external_links']); ?>" 
                                   min="0" 
                                   max="10" 
                                   class="small-text">
                            <p class="description"><?php _e('Number of external links to authoritative sources', 'ai-seo-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                </div>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="ai_seo_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-seo-generator'); ?>">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding section
        var target = $(this).attr('href');
        $('.ai-seo-settings-section').hide();
        $(target + '-section').show();
    });
    
    // Generate WordPress API key
    $('#generate-api-key').on('click', function() {
        var newKey = 'plx_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        $('#wordpress_api_key').val(newKey);
    });
    
    // Test Sheet Update button
    $('#test-sheet-update').on('click', function() {
        var $button = $(this);
        var $result = $('#sheets-test-result');
        
        if (!confirm('This will mark the first unused keyword row in your Google Sheet as "Yes" with a test URL and color it green (#b6d7a8). Continue?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('<span class="spinner is-active" style="float:none;"></span> Updating sheet...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_seo_test_sheet_update',
                nonce: '<?php echo wp_create_nonce('ai_seo_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var msg = response.data.message;
                    if (response.data.keyword) {
                        msg += '<br><small>Keyword: "' + response.data.keyword + '" (Row ' + response.data.row + ')</small>';
                    }
                    $result.html(msg).css('color', 'green');
                } else {
                    $result.html('✗ ' + response.data.message).css('color', 'red');
                }
                $button.prop('disabled', false).text('Test Sheet Update');
            },
            error: function(xhr, status, error) {
                $result.html('✗ Request failed: ' + error).css('color', 'red');
                $button.prop('disabled', false).text('Test Sheet Update');
            }
        });
    });
    
    // Test Email button
    $('#test-email-btn').on('click', function() {
        var $button = $(this);
        var $result = $('#test-email-result');
        var email = $('#notification_email').val();
        
        if (!email) {
            $result.html('<span style="color: red;">✗ Please enter an email address first</span>');
            return;
        }
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-email" style="vertical-align: middle;"></span> Sending...');
        $result.html('<span class="spinner is-active" style="float:none; margin: 0;"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_seo_test_email',
                nonce: '<?php echo wp_create_nonce('ai_seo_nonce'); ?>',
                email: email
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                }
                $button.prop('disabled', false).html('<span class="dashicons dashicons-email" style="vertical-align: middle;"></span> Send Test Email');
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color: red;">✗ Request failed: ' + error + '</span>');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-email" style="vertical-align: middle;"></span> Send Test Email');
            }
        });
    });
});
</script>
