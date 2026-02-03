/**
 * AI SEO Generator - Admin JavaScript
 */

(function($) {
    'use strict';

    const AiSeoGenerator = {
        init: function() {
            this.bindEvents();
            this.initSchedulePreview();
        },

        bindEvents: function() {
            // Prevent duplicate bindings by using off() first
            
            // Modal handlers
            $('#ai-seo-generate-now, #ai-seo-add-first').off('click').on('click', this.openGenerateModal);
            $('#ai-seo-add-to-queue').off('click').on('click', this.openQueueModal);
            $('.ai-seo-modal-close, .ai-seo-modal-cancel').off('click').on('click', this.closeModal);
            
            // Credentials help popup
            $('#credentials-help-icon').off('click').on('click', function(e) {
                e.preventDefault();
                $('#credentials-help-modal').fadeIn(200);
            });
            
            // Form submissions - use off() to prevent duplicate handlers
            $('#ai-seo-generate-form').off('submit').on('submit', this.handleGenerateForm);
            $('#ai-seo-add-queue-form').on('submit', this.handleAddToQueue);
            $('#ai-seo-fetch-keywords-form').on('submit', this.handleFetchKeywords);
            $('#ai-seo-batch-schedule-form').on('submit', this.handleBatchSchedule);
            $('#ai-seo-sheets-import-form').on('submit', this.handleSheetsImport);
            
            // Test connections
            $('#test-gemini').on('click', this.testGeminiConnection);
            $('#test-sheets').on('click', this.testSheetsConnection);
            $('#test-sheet-update').on('click', this.testSheetUpdate);
            $('#test-email').on('click', this.testEmail);
            
            // Other actions
            $('#add-to-queue-btn').on('click', this.addFetchedKeywordsToQueue);
            
            // Queue page tabs
            $('.ai-seo-tab-btn').on('click', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                
                // Update tab buttons
                $('.ai-seo-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                // Show corresponding content
                $('.ai-seo-tab-content').hide();
                $('#' + tab + '-tab').show();
            });
            
            // Queue page - preview sheets keywords
            $('#preview-sheets-btn').on('click', this.previewSheetsKeywords);
            
            // Queue page - remove from queue
            $(document).on('click', '.ai-seo-remove-queue', this.removeFromQueue);
            
            // Close modal on outside click
            $(window).on('click', function(e) {
                if ($(e.target).hasClass('ai-seo-modal')) {
                    AiSeoGenerator.closeModal();
                }
            });
        },

        openGenerateModal: function(e) {
            e.preventDefault();
            $('#ai-seo-generate-modal').fadeIn(200);
            $('#ai-seo-keywords').focus();
        },

        openQueueModal: function(e) {
            e.preventDefault();
            $('#ai-seo-add-queue-modal').fadeIn(200);
            $('#ai-seo-queue-keywords').focus();
        },

        closeModal: function() {
            $('.ai-seo-modal').fadeOut(200);
            $('.ai-seo-status-message').hide();
            $('form').trigger('reset');
        },

        handleGenerateForm: function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const $form = $(this);
            const $status = $('#ai-seo-generate-status');
            const $button = $form.find('button[type="submit"]');
            
            // Prevent double submission
            if ($button.prop('disabled') || $form.data('submitting')) {
                return false;
            }
            $form.data('submitting', true);
            
            const keywords = $('#ai-seo-keywords').val().trim();
            const generateNow = $('input[name="generate_now"]').is(':checked');
            
            if (!keywords) {
                AiSeoGenerator.showStatus($status, 'error', 'Please enter keywords');
                $form.data('submitting', false);
                return false;
            }
            
            $button.prop('disabled', true).text('Generating...');
            AiSeoGenerator.showStatus($status, 'loading', 'Generating article...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_generate_article',
                    nonce: aiSeo.nonce,
                    keywords: keywords,
                    generate_now: generateNow ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        AiSeoGenerator.showStatus($status, 'success', response.data.message);
                        // Redirect to edit page
                        if (response.data.edit_url) {
                            window.location.href = response.data.edit_url;
                        } else if (response.data.post_url) {
                            window.location.href = response.data.post_url;
                        } else {
                            location.reload();
                        }
                    } else {
                        AiSeoGenerator.showStatus($status, 'error', response.data.message || 'Generation failed');
                        $button.prop('disabled', false).text('Generate');
                        $form.data('submitting', false);
                    }
                },
                error: function() {
                    AiSeoGenerator.showStatus($status, 'error', 'Request failed. Please try again.');
                    $button.prop('disabled', false).text('Generate');
                    $form.data('submitting', false);
                }
            });
            
            return false;
        },

        handleAddToQueue: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $status = $('#ai-seo-add-queue-status');
            const $button = $form.find('button[type="submit"]');
            
            const keywords = $('#ai-seo-queue-keywords').val().trim();
            const scheduledDate = $('#ai-seo-queue-schedule').val();
            
            if (!keywords) {
                AiSeoGenerator.showStatus($status, 'error', 'Please enter keywords');
                return;
            }
            
            $button.prop('disabled', true).text('Adding...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_add_to_queue',
                    nonce: aiSeo.nonce,
                    keywords: keywords,
                    scheduled_date: scheduledDate
                },
                success: function(response) {
                    if (response.success) {
                        AiSeoGenerator.showStatus($status, 'success', 'Added to queue successfully!');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        AiSeoGenerator.showStatus($status, 'error', response.data.message || 'Failed to add to queue');
                        $button.prop('disabled', false).text('Add to Queue');
                    }
                },
                error: function() {
                    AiSeoGenerator.showStatus($status, 'error', 'Request failed. Please try again.');
                    $button.prop('disabled', false).text('Add to Queue');
                }
            });
        },

        handleFetchKeywords: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $status = $('#fetch-status');
            const $button = $form.find('button[type="submit"]');
            const $preview = $('#keywords-preview');
            
            const range = $('#sheet-range').val().trim();
            
            $button.prop('disabled', true).text('Fetching...');
            AiSeoGenerator.showStatus($status, 'loading', 'Fetching keywords from Google Sheets...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_fetch_sheets_keywords',
                    nonce: aiSeo.nonce,
                    range: range
                },
                success: function(response) {
                    if (response.success && response.data.keywords) {
                        const keywords = response.data.keywords;
                        AiSeoGenerator.showStatus($status, 'success', `Found ${keywords.length} keywords`);
                        
                        // Display preview
                        const $list = $('<ul></ul>');
                        keywords.forEach(function(keyword) {
                            $list.append(`<li>${keyword}</li>`);
                        });
                        $('#keywords-list').html($list);
                        $preview.show();
                        
                        // Store keywords for later
                        $preview.data('keywords', keywords);
                    } else {
                        AiSeoGenerator.showStatus($status, 'error', response.data.message || 'No keywords found');
                    }
                    $button.prop('disabled', false).text('Fetch Keywords');
                },
                error: function() {
                    AiSeoGenerator.showStatus($status, 'error', 'Request failed. Please try again.');
                    $button.prop('disabled', false).text('Fetch Keywords');
                }
            });
        },

        addFetchedKeywordsToQueue: function() {
            const keywords = $('#keywords-preview').data('keywords');
            
            if (!keywords || keywords.length === 0) {
                alert('No keywords to add');
                return;
            }
            
            const $button = $(this);
            $button.prop('disabled', true).text('Adding to queue...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_batch_add_to_queue',
                    nonce: aiSeo.nonce,
                    keywords: keywords
                },
                success: function(response) {
                    if (response.success) {
                        alert(`Successfully added ${keywords.length} items to queue!`);
                        window.location.href = aiSeo.pluginUrl + 'admin.php?page=ai-seo-generator-queue';
                    } else {
                        alert('Failed to add keywords: ' + (response.data.message || 'Unknown error'));
                        $button.prop('disabled', false).text('Add to Queue');
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                    $button.prop('disabled', false).text('Add to Queue');
                }
            });
        },

        handleBatchSchedule: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $status = $('#batch-status');
            const $button = $form.find('button[type="submit"]');
            
            const keywordsText = $('#batch-keywords').val().trim();
            const startDate = $('#start-date').val();
            const frequency = $('#frequency').val();
            const articlesPerInterval = parseInt($('#articles-per-interval').val()) || 1;
            
            if (!keywordsText) {
                AiSeoGenerator.showStatus($status, 'error', 'Please enter keywords');
                return;
            }
            
            const keywords = keywordsText.split('\n').filter(k => k.trim().length > 0);
            
            if (keywords.length === 0) {
                AiSeoGenerator.showStatus($status, 'error', 'No valid keywords found');
                return;
            }
            
            $button.prop('disabled', true).text('Scheduling...');
            AiSeoGenerator.showStatus($status, 'loading', `Scheduling ${keywords.length} articles...`);
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_batch_schedule',
                    nonce: aiSeo.nonce,
                    keywords: keywords,
                    start_date: startDate,
                    frequency: frequency,
                    articles_per_interval: articlesPerInterval
                },
                success: function(response) {
                    if (response.success) {
                        AiSeoGenerator.showStatus($status, 'success', response.data.message);
                        setTimeout(function() {
                            window.location.href = aiSeo.pluginUrl + 'admin.php?page=ai-seo-generator-queue';
                        }, 2000);
                    } else {
                        AiSeoGenerator.showStatus($status, 'error', response.data.message || 'Scheduling failed');
                        $button.prop('disabled', false).text('Schedule Batch');
                    }
                },
                error: function() {
                    AiSeoGenerator.showStatus($status, 'error', 'Request failed. Please try again.');
                    $button.prop('disabled', false).text('Schedule Batch');
                }
            });
        },

        testGeminiConnection: function() {
            const apiKey = $('#gemini_api_key').val().trim();
            const $button = $(this);
            const $result = $('#gemini-test-result');
            
            if (!apiKey) {
                $result.text('Please enter an API key').removeClass('success').addClass('error');
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
            $result.html('<span class="ai-seo-spinner"></span>');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'AI_SEO_test_gemini',
                    nonce: aiSeo.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $result.text('✓ ' + response.data.message).removeClass('error').addClass('success');
                    } else {
                        $result.text('✗ ' + response.data.message).removeClass('success').addClass('error');
                    }
                    $button.prop('disabled', false).text('Test Connection');
                },
                error: function() {
                    $result.text('✗ Request failed').removeClass('success').addClass('error');
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        testSheetsConnection: function() {
            const $button = $(this);
            const $result = $('#sheets-test-result');
            
            $button.prop('disabled', true).text('Testing...');
            $result.html('<span class="ai-seo-spinner"></span>');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'AI_SEO_test_sheets',
                    nonce: aiSeo.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = '✓ ' + response.data.message;
                        if (response.data.service_account) {
                            msg += '<br><small>Service Account: ' + response.data.service_account + '</small>';
                        }
                        $result.html(msg).removeClass('error').addClass('success');
                    } else {
                        var errorMsg = '✗ ' + response.data.message;
                        if (response.data.debug) {
                            errorMsg += '<br><br><strong>Debug Info:</strong><pre style="font-size:11px;background:#f5f5f5;padding:10px;margin-top:10px;white-space:pre-wrap;">' + response.data.debug + '</pre>';
                        }
                        $result.html(errorMsg).removeClass('success').addClass('error');
                    }
                    $button.prop('disabled', false).text('Test Connection');
                },
                error: function(xhr, status, error) {
                    $result.html('✗ Request failed: ' + error).removeClass('success').addClass('error');
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        testSheetUpdate: function() {
            const $button = $(this);
            const $result = $('#sheets-test-result');
            
            if (!confirm('This will mark the first unused keyword row in your Google Sheet as "Yes" with a test URL and color it green (#b6d7a8). Continue?')) {
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
            $result.html('<span class="ai-seo-spinner"></span> Updating sheet...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_test_sheet_update',
                    nonce: aiSeo.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = response.data.message;
                        if (response.data.keyword) {
                            msg += '<br><small>Keyword: "' + response.data.keyword + '" (Row ' + response.data.row + ')</small>';
                        }
                        $result.html(msg).removeClass('error').addClass('success');
                    } else {
                        $result.html('✗ ' + response.data.message).removeClass('success').addClass('error');
                    }
                    $button.prop('disabled', false).text('Test Sheet Update');
                },
                error: function(xhr, status, error) {
                    $result.html('✗ Request failed: ' + error).removeClass('success').addClass('error');
                    $button.prop('disabled', false).text('Test Sheet Update');
                }
            });
        },

        testEmail: function() {
            const email = $('#notification_email').val().trim();
            const $button = $(this);
            const $result = $('#email-test-result');
            
            if (!email) {
                $result.text('Please enter an email').removeClass('success').addClass('error');
                return;
            }
            
            $button.prop('disabled', true).text('Sending...');
            $result.html('<span class="ai-seo-spinner"></span>');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'AI_SEO_test_email',
                    nonce: aiSeo.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        $result.text('✓ ' + response.data.message).removeClass('error').addClass('success');
                    } else {
                        $result.text('✗ ' + response.data.message).removeClass('success').addClass('error');
                    }
                    $button.prop('disabled', false).text('Send Test Email');
                },
                error: function() {
                    $result.text('✗ Request failed').removeClass('success').addClass('error');
                    $button.prop('disabled', false).text('Send Test Email');
                }
            });
        },

        handleTabClick: function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            
            // Update tab buttons
            $('.ai-seo-tab-btn').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding content
            $('.ai-seo-tab-content').hide();
            $('#' + tab + '-tab').show();
        },

        previewSheetsKeywords: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $preview = $('#sheets-preview');
            const $previewList = $('#sheets-preview-list');
            const $status = $('#sheets-status');
            
            const onlyUnused = $('#only-unused').is(':checked');
            const limit = parseInt($('#sheets-limit').val()) || 10;
            
            $button.prop('disabled', true).text('Fetching...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_fetch_sheets_keywords',
                    nonce: aiSeo.nonce,
                    only_unused: onlyUnused ? '1' : '0',
                    limit: limit,
                    preview: '1'
                },
                success: function(response) {
                    if (response.success && response.data.keywords) {
                        const keywords = response.data.keywords;
                        
                        let html = '<ul>';
                        keywords.forEach(function(item) {
                            html += '<li><strong>' + item.keyword + '</strong> (Row ' + item.row + ')</li>';
                        });
                        html += '</ul>';
                        html += '<p><strong>Found ' + keywords.length + ' keywords</strong></p>';
                        
                        $previewList.html(html);
                        $preview.show();
                        $status.hide();
                    } else {
                        AiSeoGenerator.showStatus($status, 'error', response.data.message || 'No keywords found');
                        $preview.hide();
                    }
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span> Preview Keywords');
                },
                error: function() {
                    AiSeoGenerator.showStatus($status, 'error', 'Request failed. Please try again.');
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span> Preview Keywords');
                }
            });
        },

        handleSheetsImport: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $status = $('#sheets-status');
            const $button = $form.find('button[type="submit"]');
            
            const onlyUnused = $('#only-unused').is(':checked');
            const limit = parseInt($('#sheets-limit').val()) || 10;
            const startDate = $('#sheets-start-date').val();
            const frequency = $('#sheets-frequency').val();
            const articlesPerInterval = parseInt($('#sheets-articles-per-interval').val()) || 1;
            
            $button.prop('disabled', true).text('Importing...');
            AiSeoGenerator.showStatus($status, 'loading', 'Importing keywords from Google Sheets...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_import_sheets_to_queue',
                    nonce: aiSeo.nonce,
                    only_unused: onlyUnused ? '1' : '0',
                    limit: limit,
                    start_date: startDate,
                    frequency: frequency,
                    articles_per_interval: articlesPerInterval
                },
                success: function(response) {
                    if (response.success) {
                        AiSeoGenerator.showStatus($status, 'success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        AiSeoGenerator.showStatus($status, 'error', response.data.message || 'Import failed');
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Import & Add to Queue');
                    }
                },
                error: function() {
                    AiSeoGenerator.showStatus($status, 'error', 'Request failed. Please try again.');
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Import & Add to Queue');
                }
            });
        },

        removeFromQueue: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to remove this item from the queue?')) {
                return;
            }
            
            const $button = $(this);
            const itemId = $button.data('id');
            const $row = $button.closest('tr');
            
            $button.prop('disabled', true).text('Removing...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_remove_from_queue',
                    nonce: aiSeo.nonce,
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Failed to remove item');
                        $button.prop('disabled', false).text('Remove');
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                    $button.prop('disabled', false).text('Remove');
                }
            });
        },

        initSchedulePreview: function() {
            $('#batch-keywords, #start-date, #frequency').on('input change', function() {
                AiSeoGenerator.updateSchedulePreview();
            });
        },

        updateSchedulePreview: function() {
            const keywordsText = $('#batch-keywords').val().trim();
            const startDate = $('#start-date').val();
            const frequency = $('#frequency').val();
            
            if (!keywordsText || !startDate) {
                return;
            }
            
            const keywords = keywordsText.split('\n').filter(k => k.trim().length > 0);
            const $preview = $('#schedule-preview');
            
            if (keywords.length === 0) {
                return;
            }
            
            // Calculate schedule
            const schedules = AiSeoGenerator.calculateSchedule(keywords, startDate, frequency);
            
            // Build preview table
            let html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            html += '<th>Keywords</th><th>Scheduled Date</th></tr></thead><tbody>';
            
            schedules.forEach(function(item) {
                html += `<tr><td>${item.keyword}</td><td>${item.date}</td></tr>`;
            });
            
            html += '</tbody></table>';
            $preview.html(html);
        },

        calculateSchedule: function(keywords, startDate, frequency) {
            const schedules = [];
            let currentDate = new Date(startDate);
            
            keywords.forEach(function(keyword) {
                schedules.push({
                    keyword: keyword,
                    date: AiSeoGenerator.formatDate(currentDate)
                });
                
                // Calculate next date based on frequency
                switch (frequency) {
                    case 'hourly':
                        currentDate.setHours(currentDate.getHours() + 1);
                        break;
                    case 'daily':
                        currentDate.setDate(currentDate.getDate() + 1);
                        break;
                    case 'weekly':
                        currentDate.setDate(currentDate.getDate() + 7);
                        break;
                }
            });
            
            return schedules;
        },

        formatDate: function(date) {
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        showStatus: function($element, type, message) {
            $element.removeClass('success error loading')
                .addClass(type)
                .html(message)
                .show();
        },
        
        // Regenerate title functionality
        regenerateTitle: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const postId = $button.data('post-id');
            
            // Try to find title in various places
            let currentTitle = '';
            const $titleField = $('#title');
            
            // Check for Gutenberg
            const isGutenberg = typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor');
            
            if ($titleField.length) {
                currentTitle = $titleField.val();
            } else if (isGutenberg) {
                currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
            }
            
            const originalText = $button.html();
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> Regenerating...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_regenerate_title',
                    nonce: aiSeo.nonce,
                    post_id: postId,
                    current_title: currentTitle
                },
                success: function(response) {
                    if (response.success) {
                        const newTitle = response.data.title;
                        
                        // Update title in UI
                        if ($titleField.length) {
                            // Classic Editor
                            $titleField.val(newTitle).trigger('change');
                        } 
                        
                        if (isGutenberg) {
                            // Gutenberg - use wp.data.dispatch to update title
                            wp.data.dispatch('core/editor').editPost({ title: newTitle });
                            
                            // Also update the visible title input/textarea directly
                            setTimeout(function() {
                                // Update any visible title elements
                                $('.editor-post-title__input').val(newTitle).text(newTitle);
                                $('h1.wp-block-post-title').text(newTitle);
                                $('[data-title]').attr('data-title', newTitle);
                                
                                // Try to trigger React state update
                                var titleInput = document.querySelector('.editor-post-title__input');
                                if (titleInput) {
                                    var nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value').set;
                                    nativeInputValueSetter.call(titleInput, newTitle);
                                    titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }, 100);
                        }
                        
                        // Brief success indication
                        $button.html('<span class="dashicons dashicons-yes"></span> Updated!');
                        setTimeout(function() {
                            $button.prop('disabled', false).html(originalText);
                        }, 2000);
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to regenerate title'));
                        $button.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    alert('Request failed. Please try again.');
                    $button.prop('disabled', false).html(originalText);
                }
            });
        },
        
        // Regenerate image functionality
        regenerateImage: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const postId = $button.data('post-id');
            
            // Get orientation from the sibling selector (check both class names)
            let orientation = 'landscape';
            let $selector = $button.closest('div').find('.ai-seo-image-orientation, .ai-image-orientation');
            if ($selector.length) {
                orientation = $selector.val();
            }
            
            // Check for Gutenberg
            const isGutenberg = typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor');
            
            const originalText = $button.html();
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> Generating...');
            
            $.ajax({
                url: aiSeo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_regenerate_image',
                    nonce: aiSeo.nonce,
                    post_id: postId,
                    orientation: orientation
                },
                success: function(response) {
                    // Log debug info to console
                    console.log('Featured Image API Response:', response);
                    if (response.data && response.data.debug) {
                        console.log('Debug Log:', response.data.debug);
                    }
                    if (response.data && response.data.prompt_used) {
                        console.log('Prompt Used:', response.data.prompt_used);
                    }
                    
                    if (response.success) {
                        const imageId = response.data.image_id;
                        const imageUrl = response.data.image_url;
                        const thumbnailUrl = response.data.thumbnail_url || imageUrl;
                        
                        if (isGutenberg) {
                            // Gutenberg - update featured image without page reload
                            
                            // Step 1: Set the featured_media ID
                            wp.data.dispatch('core/editor').editPost({ featured_media: parseInt(imageId) });
                            
                            // Step 2: Fetch the media entity to update the store
                            if (wp.apiFetch) {
                                wp.apiFetch({ path: '/wp/v2/media/' + imageId }).then(function(media) {
                                    // Inject media into core data store
                                    wp.data.dispatch('core').receiveEntityRecords('root', 'media', [media]);
                                }).catch(function(err) {
                                    console.log('Media fetch error:', err);
                                });
                            }
                            
                            // Step 3: Directly update visible image elements after short delay
                            setTimeout(function() {
                                // Find all featured image elements and update them
                                var imgSelectors = [
                                    '.editor-post-featured-image img',
                                    '.editor-post-featured-image__preview',
                                    '.editor-post-featured-image__container img',
                                    '.components-responsive-wrapper img',
                                    '[class*="featured-image"] img'
                                ];
                                
                                imgSelectors.forEach(function(selector) {
                                    $(selector).each(function() {
                                        $(this).attr('src', thumbnailUrl + '?t=' + Date.now());
                                    });
                                });
                            }, 300);
                            
                            $button.html('<span class="dashicons dashicons-yes"></span> Image Updated!');
                            setTimeout(function() {
                                $button.prop('disabled', false).html(originalText);
                            }, 2000);
                            
                        } else {
                            // Classic Editor - update the thumbnail preview
                            $('#postimagediv .inside img').attr('src', thumbnailUrl);
                            $('#_thumbnail_id').val(imageId);
                            
                            // Update the thumbnail display
                            if ($('#set-post-thumbnail').length) {
                                $('#postimagediv .inside').html(
                                    '<img src="' + thumbnailUrl + '" style="max-width:100%;height:auto;" />' +
                                    '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail">' + 'Remove featured image' + '</a></p>' +
                                    '<input type="hidden" id="_thumbnail_id" name="_thumbnail_id" value="' + imageId + '" />'
                                );
                            }
                            
                            $button.html('<span class="dashicons dashicons-yes"></span> Image Updated!');
                            setTimeout(function() {
                                $button.prop('disabled', false).html(originalText);
                            }, 2000);
                        }
                    } else {
                        console.error('Featured Image Error:', response.data);
                        if (response.data && response.data.debug) {
                            console.error('Debug Log:', response.data.debug);
                        }
                        alert('Error: ' + (response.data.message || 'Failed to regenerate image'));
                        $button.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Featured Image AJAX Error:', { xhr: xhr, status: status, error: error });
                    alert('Request failed. Please try again.');
                    $button.prop('disabled', false).html(originalText);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AiSeoGenerator.init();
        
        // Bind regenerate buttons
        $(document).on('click', '.ai-seo-regenerate-title', AiSeoGenerator.regenerateTitle);
        $(document).on('click', '.ai-seo-regenerate-title-inline', AiSeoGenerator.regenerateTitle);
        $(document).on('click', '.ai-seo-regenerate-image', AiSeoGenerator.regenerateImage);
        $(document).on('click', '.ai-seo-regenerate-image-inline', AiSeoGenerator.regenerateImage);
        $(document).on('click', '.ai-regenerate-image-btn', AiSeoGenerator.regenerateImage);
    });

})(jQuery);
