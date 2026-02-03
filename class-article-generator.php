<?php
/**
 * Article Generator
 */

class AI_SEO_Article_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor - meta boxes registered in main plugin file
    }
    
    /**
     * Generate and publish article
     */
    public function generate_and_publish($keywords, $options = array()) {
        error_log('AI SEO: Starting article generation for keywords: ' . $keywords);
        
        // Generate content
        $gemini = AI_SEO_Gemini_API::get_instance();
        $article_data = $gemini->generate_article($keywords, $options);
        
        if (is_wp_error($article_data)) {
            error_log('AI SEO: Gemini API error for "' . $keywords . '": ' . $article_data->get_error_message());
            return $article_data;
        }
        
        $title = wp_strip_all_tags($article_data['title']);
        
        // Process internal links with real pages
        if (!empty($article_data['content'])) {
            $article_data['content'] = $this->process_internal_links($article_data['content']);
        }
        
        // Process external links - find real URLs using Google Search
        if (!empty($article_data['content'])) {
            $result = $this->process_external_links($article_data['content']);
            if (is_wp_error($result)) {
                error_log('AI SEO: External links processing failed for "' . $keywords . '": ' . $result->get_error_message());
                return $result; // Fail the entire article generation
            }
            $article_data['content'] = $result;
        }
        
        // Convert HTML to Gutenberg blocks
        $gutenberg_content = $this->convert_html_to_blocks($article_data['content']);
        
        // Generate and attach images
        $image_ids = array();
        $image_count = get_option('AI_SEO_image_count', 1);
        
        if (!empty($article_data['image_prompts']) && $image_count > 0) {
            $prompts = array_slice($article_data['image_prompts'], 0, $image_count);
            
            foreach ($prompts as $prompt) {
                $image_id = $gemini->generate_image($prompt);
                if (!is_wp_error($image_id)) {
                    $image_ids[] = $image_id;
                }
            }
        }
        
        // Create post
        $post_status = get_option('AI_SEO_default_post_status', 'draft');
        
        // Generate slug from AI or fallback to title
        $post_slug = '';
        if (!empty($article_data['slug'])) {
            $post_slug = sanitize_title($article_data['slug']);
        }
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => $gutenberg_content,
            'post_status' => $post_status,
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
            'post_name' => $post_slug,
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            error_log('AI SEO: WordPress post creation failed for "' . $keywords . '": ' . $post_id->get_error_message());
            return $post_id;
        }
        
        error_log('AI SEO: Successfully created post #' . $post_id . ' for keywords: ' . $keywords);
        
        // Set Yoast SEO fields
        // SEO Title (max 50 characters)
        if (!empty($article_data['seo_title'])) {
            $seo_title = substr($article_data['seo_title'], 0, 50);
            update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
        }
        
        // Meta Description (max 120 characters - strict limit)
        if (!empty($article_data['meta_description'])) {
            $meta_desc = substr($article_data['meta_description'], 0, 120);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
            update_post_meta($post_id, '_ai_seo_meta_description', $meta_desc);
        }
        
        // Focus Keyphrase
        if (!empty($article_data['focus_keyphrase'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $article_data['focus_keyphrase']);
        }
        
        // DO NOT add tags - removed per user request
        
        // Set featured image - IMPORTANT: Must have at least one image
        if (!empty($image_ids)) {
            $featured_image_id = $image_ids[0];
            set_post_thumbnail($post_id, $featured_image_id);
            // Force update meta just in case
            update_post_meta($post_id, '_thumbnail_id', $featured_image_id);
            
            // Attach remaining images to post
            foreach ($image_ids as $image_id) {
                wp_update_post(array(
                    'ID' => $image_id,
                    'post_parent' => $post_id,
                ));
            }
        }
        
        // Store generation metadata
        update_post_meta($post_id, '_ai_seo_keywords', $keywords);
        update_post_meta($post_id, '_ai_seo_generated', current_time('mysql'));
        update_post_meta($post_id, '_ai_seo_image_ids', $image_ids);
        
        return array(
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'image_ids' => $image_ids,
            'data' => $article_data,
        );
    }
    
    /**
     * Convert HTML to Gutenberg blocks
     */
    private function convert_html_to_blocks($html) {
        // Clean up HTML encoding issues
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove any wrapping divs or body tags
        $html = strip_tags($html, '<h2><h3><h4><h5><h6><p><ul><ol><li><a><strong><em><b><i><table><thead><tbody><tr><th><td><br>');
        
        $blocks = '';
        
        // Use simple conversion instead of DOM to avoid encoding issues
        return $this->html_to_blocks_simple($html);
    }
    
    /**
     * Simple HTML to blocks conversion fallback
     */
    private function html_to_blocks_simple($html) {
        // Ensure proper UTF-8 encoding
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        
        // Remove any null bytes or invalid characters
        $html = str_replace(chr(0), '', $html);
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html);
        
        // Convert headings - strip any attributes, Gutenberg expects clean tags
        $html = preg_replace('/<h2[^>]*>/i', "<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">", $html);
        $html = preg_replace('/<\/h2>/i', "</h2>\n<!-- /wp:heading -->\n\n", $html);
        
        $html = preg_replace('/<h3[^>]*>/i', "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">", $html);
        $html = preg_replace('/<\/h3>/i', "</h3>\n<!-- /wp:heading -->\n\n", $html);
        
        $html = preg_replace('/<h4[^>]*>/i', "<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">", $html);
        $html = preg_replace('/<\/h4>/i', "</h4>\n<!-- /wp:heading -->\n\n", $html);
        
        // Convert paragraphs - Gutenberg paragraph blocks must have clean <p> tags with no attributes
        $html = preg_replace('/<p[^>]*>/i', "<!-- wp:paragraph -->\n<p>", $html);
        $html = preg_replace('/<\/p>/i', "</p>\n<!-- /wp:paragraph -->\n\n", $html);
        
        // Convert lists - strip attributes
        $html = preg_replace('/<ul[^>]*>/i', "<!-- wp:list -->\n<ul>", $html);
        $html = preg_replace('/<\/ul>/i', "</ul>\n<!-- /wp:list -->\n\n", $html);
        
        $html = preg_replace('/<ol[^>]*>/i', "<!-- wp:list {\"ordered\":true} -->\n<ol>", $html);
        $html = preg_replace('/<\/ol>/i', "</ol>\n<!-- /wp:list -->\n\n", $html);
        
        // Handle tables - need proper structure with tbody for Gutenberg
        // First, ensure tables have tbody if they don't
        $html = preg_replace_callback('/<table([^>]*)>(.*?)<\/table>/is', function($matches) {
            $table_attrs = $matches[1];
            $table_content = $matches[2];
            
            // Clean up any weird characters in table content
            $table_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $table_content);
            
            // Remove any existing class attributes as we'll add our own
            $table_attrs = preg_replace('/\s*class=["\'][^"\']*["\']/i', '', $table_attrs);
            
            // Ensure proper thead/tbody structure
            $thead = '';
            $tbody_content = $table_content;
            
            // Extract thead if present
            if (preg_match('/<thead[^>]*>(.*?)<\/thead>/is', $table_content, $thead_match)) {
                $thead = '<thead>' . $thead_match[1] . '</thead>';
                $tbody_content = preg_replace('/<thead[^>]*>.*?<\/thead>/is', '', $table_content);
            }
            
            // Check if tbody exists, if not wrap rows in tbody
            if (stripos($tbody_content, '<tbody') === false) {
                // Clean up the remaining content and wrap in tbody
                $tbody_content = trim($tbody_content);
                if (!empty($tbody_content)) {
                    $tbody_content = '<tbody>' . $tbody_content . '</tbody>';
                }
            }
            
            // Build the complete table with proper Gutenberg block structure
            $table_html = $thead . $tbody_content;
            
            return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table>" . $table_html . "</table></figure>\n<!-- /wp:table -->\n\n";
        }, $html);
        
        return $html;
    }
    
    /**
     * Process internal links placeholders
     */
    public function process_internal_links($content) {
        // Match internal link placeholders - handle various formats the AI might use
        // Pattern 1: <a href='[INTERNAL_LINK]' data-context='keyword'>text</a>
        // Pattern 2: <a href="[INTERNAL_LINK]" data-context="keyword">text</a>
        // Pattern 3: <a data-context='keyword' href='[INTERNAL_LINK]'>text</a>
        
        // First pattern: href first, then data-context
        $pattern1 = '/<a\s+[^>]*href=["\']?\[INTERNAL_LINK\]["\']?\s+[^>]*data-context=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i';
        
        // Second pattern: data-context first, then href
        $pattern2 = '/<a\s+[^>]*data-context=["\']([^"\']+)["\'][^>]*href=["\']?\[INTERNAL_LINK\]["\']?[^>]*>([^<]+)<\/a>/i';
        
        // Try first pattern
        preg_match_all($pattern1, $content, $matches1, PREG_SET_ORDER);
        
        // Try second pattern
        preg_match_all($pattern2, $content, $matches2, PREG_SET_ORDER);
        
        // Combine matches
        $matches = array_merge($matches1, $matches2);
        
        // Also try to find any [INTERNAL_LINK] that wasn't matched by the patterns above
        if (empty($matches) && strpos($content, '[INTERNAL_LINK]') !== false) {
            // Generic pattern to catch any link with [INTERNAL_LINK]
            preg_match_all('/<a\s+[^>]*href=["\']?\[INTERNAL_LINK\]["\']?[^>]*>([^<]+)<\/a>/i', $content, $generic_matches, PREG_SET_ORDER);
            foreach ($generic_matches as $match) {
                $full_tag = $match[0];
                $anchor_text = $match[1];
                
                // Extract data-context if present
                $context = '';
                if (preg_match('/data-context=["\']([^"\']+)["\']/i', $full_tag, $ctx_match)) {
                    $context = $ctx_match[1];
                } else {
                    // Use anchor text as context
                    $context = $anchor_text;
                }
                
                $matches[] = array($full_tag, $context, $anchor_text);
            }
        }
        
        if (empty($matches)) {
            return $content;
        }
        
        // Track used post IDs to avoid duplicates
        $used_post_ids = array();
        
        foreach ($matches as $match) {
            $full_tag = $match[0];
            $context = $match[1]; // The keyword/context for finding relevant page
            $anchor_text = $match[2];
            
            // Search for relevant pages based on context, excluding already used posts
            $relevant_post = $this->find_relevant_post($context, $used_post_ids);
            
            if ($relevant_post) {
                // Add to used list to prevent duplicates
                $used_post_ids[] = $relevant_post->ID;
                
                $replacement = sprintf(
                    '<a href="%s">%s</a>',
                    get_permalink($relevant_post->ID),
                    esc_html($anchor_text)
                );
                $content = str_replace($full_tag, $replacement, $content);
            } else {
                // If no relevant post found, just use the anchor text without link
                $content = str_replace($full_tag, esc_html($anchor_text), $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Find relevant post for internal linking
     * @param string $context The search context/keyword
     * @param array $exclude_ids Post IDs to exclude (already used)
     */
    private function find_relevant_post($context, $exclude_ids = array()) {
        // First try to find by title match
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5, // Get more to filter out used ones
            's' => $context,
            'orderby' => 'relevance',
        );
        
        // Exclude already used posts
        if (!empty($exclude_ids)) {
            $args['post__not_in'] = $exclude_ids;
        }
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $post = $query->posts[0];
            wp_reset_postdata();
            return $post;
        }
        
        // Fallback: Get any recent post that hasn't been used
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Exclude already used posts in fallback too
        if (!empty($exclude_ids)) {
            $args['post__not_in'] = $exclude_ids;
        }
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $post = $query->posts[0];
            wp_reset_postdata();
            return $post;
        }
        
        return null;
    }
    
    /**
     * Process external link placeholders - find real URLs using Google Search
     * Returns WP_Error if any external link cannot be found
     */
    public function process_external_links($content) {
        // Match external link placeholders with data-search attribute
        // Pattern: <a href="[EXTERNAL_LINK]" data-search="search query" target="_blank" rel="noopener noreferrer">anchor text</a>
        $pattern = '/<a\s+[^>]*href=["\']?\[EXTERNAL_LINK\]["\']?[^>]*>([^<]+)<\/a>/i';
        
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        
        if (empty($matches)) {
            return $content;
        }
        
        // Track used domains to avoid duplicates
        $used_domains = array();
        
        foreach ($matches as $match) {
            $full_tag = $match[0];
            $anchor_text = $match[1];
            
            // Extract data-search if present
            $search_query = '';
            if (preg_match('/data-search=["\']([^"\']+)["\']/i', $full_tag, $search_match)) {
                $search_query = $search_match[1];
            } else {
                // Use anchor text as search query
                $search_query = $anchor_text;
            }
            
            // Search for a real URL using Gemini with Google Search grounding
            // This will retry up to 5 times with different search strategies
            $real_url = $this->find_real_external_url($search_query, $used_domains);
            
            if ($real_url) {
                // Extract domain and add to used list
                $parsed = parse_url($real_url);
                if (isset($parsed['host'])) {
                    $used_domains[] = $parsed['host'];
                }
                
                $replacement = sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url($real_url),
                    esc_html($anchor_text)
                );
                $content = str_replace($full_tag, $replacement, $content);
            } else {
                // All retries exhausted - fail the article generation
                return new WP_Error(
                    'external_link_failed',
                    sprintf(
                        __('Failed to find a valid external link for: "%s" (search query: "%s"). All 5 retry attempts exhausted.', 'ai-seo-generator'),
                        $anchor_text,
                        $search_query
                    )
                );
            }
        }
        
        return $content;
    }
    
    /**
     * Find a real external URL using Gemini with Google Search grounding
     * Retries multiple times with different search strategies until a valid URL is found
     */
    private function find_real_external_url($search_query, $used_domains = array(), $attempt = 1) {
        $max_attempts = 5;
        $gemini = AI_SEO_Gemini_API::get_instance();
        
        // Modify search query on retries to get different results
        $modified_query = $search_query;
        if ($attempt === 2) {
            $modified_query = $search_query . ' official website';
        } elseif ($attempt === 3) {
            $modified_query = $search_query . ' .gov OR .edu OR .org';
        } elseif ($attempt === 4) {
            $modified_query = $search_query . ' guide resource';
        } elseif ($attempt === 5) {
            $modified_query = $search_query . ' information page 2024';
        }
        
        // Use Gemini with Google Search grounding to find real URLs
        $url = $gemini->search_for_url($modified_query, $used_domains);
        
        if (empty($url) || is_wp_error($url)) {
            if ($attempt < $max_attempts) {
                error_log('AI SEO: Retrying URL search (attempt ' . ($attempt + 1) . ') for: ' . $search_query);
                return $this->find_real_external_url($search_query, $used_domains, $attempt + 1);
            }
            error_log('AI SEO: All attempts exhausted finding URL for: ' . $search_query);
            return null;
        }
        
        // Validate the URL is actually working
        if ($this->validate_external_url($url)) {
            return $url;
        }
        
        // URL failed validation, add its domain to exclusions and retry
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            $used_domains[] = $parsed['host'];
        }
        
        if ($attempt < $max_attempts) {
            error_log('AI SEO: URL validation failed, retrying (attempt ' . ($attempt + 1) . ') for: ' . $search_query);
            return $this->find_real_external_url($search_query, $used_domains, $attempt + 1);
        }
        
        error_log('AI SEO: All attempts exhausted (validation failures) for: ' . $search_query);
        return null;
    }
    
    /**
     * Validate external URL - check both HTTP status AND page content
     */
    private function validate_external_url($url) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Make a HEAD request first to check status
        $response = wp_remote_head($url, array(
            'timeout' => 10,
            'redirection' => 3,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ));
        
        if (is_wp_error($response)) {
            error_log('AI SEO: URL validation failed (request error): ' . $url . ' - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Check for obvious error codes
        if ($status_code >= 400) {
            error_log('AI SEO: URL validation failed (status ' . $status_code . '): ' . $url);
            return false;
        }
        
        // For 200 responses, we need to check the page content for soft 404s
        if ($status_code === 200) {
            // Make a GET request to read page content
            $get_response = wp_remote_get($url, array(
                'timeout' => 15,
                'redirection' => 3,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ));
            
            if (is_wp_error($get_response)) {
                // If we can't read content but HEAD worked, accept it
                return true;
            }
            
            $body = wp_remote_retrieve_body($get_response);
            
            // Check for soft 404 patterns in the page content
            if ($this->is_soft_404($body, $url)) {
                error_log('AI SEO: URL validation failed (soft 404 detected): ' . $url);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Detect soft 404 pages - pages that return 200 but show "not found" content
     */
    private function is_soft_404($html, $url) {
        if (empty($html)) {
            return true; // Empty page is suspicious
        }
        
        // Convert to lowercase for easier matching
        $html_lower = strtolower($html);
        
        // Common "not found" patterns in page content
        $not_found_patterns = array(
            // Title patterns
            '/<title[^>]*>.*?(404|not found|page not found|error|oops|sorry|doesn\'t exist|does not exist|no longer available|couldn\'t find|could not find|unavailable|missing page|dead link).*?<\/title>/is',
            
            // H1/H2 patterns
            '/<h1[^>]*>.*?(404|not found|page not found|oops|sorry|we couldn\'t find|couldn\'t find|page doesn\'t exist|page does not exist|no longer exists|no longer available).*?<\/h1>/is',
            '/<h2[^>]*>.*?(404|not found|page not found|oops|sorry).*?<\/h2>/is',
            
            // Common error page text
            '/page\s*(you\'re|you are)?\s*(looking for)?\s*(is)?\s*(not|no longer)\s*(found|available|exists?)/i',
            '/this\s*page\s*(doesn\'t|does not|can\'t be|cannot be)\s*(exist|found)/i',
            '/(we\'re sorry|sorry),?\s*(the page|this page|that page).*?(not found|doesn\'t exist|does not exist|no longer)/i',
            '/requested\s*(page|url|resource)\s*(was|is|could)\s*not\s*(found|be found|located)/i',
            '/(error|oops)[:\s]*(404|page not found)/i',
            '/the\s*link\s*(you|that)\s*(clicked|followed).*?(broken|outdated|expired|no longer works)/i',
        );
        
        foreach ($not_found_patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }
        
        // Check for very short pages (often error pages)
        $text_content = strip_tags($html);
        $text_content = preg_replace('/\s+/', ' ', $text_content);
        $word_count = str_word_count($text_content);
        
        // If page has very few words and contains error-like terms, it's likely a soft 404
        if ($word_count < 100) {
            $error_terms = array('404', 'not found', 'error', 'oops', 'sorry', 'doesn\'t exist', 'no longer');
            foreach ($error_terms as $term) {
                if (stripos($text_content, $term) !== false) {
                    return true;
                }
            }
        }
        
        // Check for common 404 page CSS classes or IDs
        $error_indicators = array(
            'class="error-404"',
            'class="not-found"',
            'class="page-not-found"',
            'id="error-404"',
            'id="not-found"',
            'class="error-page"',
            'data-error="404"',
        );
        
        foreach ($error_indicators as $indicator) {
            if (stripos($html, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate article data
     */
    private function validate_article_data($data) {
        $required = array('title', 'content');
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'ai-seo-generator'), $field));
            }
        }
        
        return true;
    }
    
    /**
     * Add meta boxes for regenerate buttons
     */
    public function add_regenerate_meta_boxes() {
        $screens = array('post');
        
        foreach ($screens as $screen) {
            // Add meta box for title regeneration - position it high
            add_meta_box(
                'ai_seo_regenerate_title',
                __('Regenerate Title', 'ai-seo-generator'),
                array($this, 'render_title_meta_box'),
                $screen,
                'side',
                'high'
            );
            
            // Add meta box for image regeneration - position below featured image
            add_meta_box(
                'ai_seo_regenerate_image',
                __('Regenerate Image', 'ai-seo-generator'),
                array($this, 'render_image_meta_box'),
                $screen,
                'side',
                'low'
            );
        }
    }
    
    /**
     * Render title regeneration meta box
     */
    public function render_title_meta_box($post) {
        $keywords = get_post_meta($post->ID, '_ai_seo_keywords', true);
        
        if (empty($keywords)) {
            echo '<p>' . __('This post was not generated using AI.', 'ai-seo-generator') . '</p>';
            return;
        }
        
        ?>
        <div class="ai-seo-meta-box">
            <p><?php _e('Generate a new title for this article using AI.', 'ai-seo-generator'); ?></p>
            <p><strong><?php _e('Keywords:', 'ai-seo-generator'); ?></strong> <?php echo esc_html($keywords); ?></p>
            <button type="button" class="button button-large button-primary ai-seo-regenerate-title" data-post-id="<?php echo esc_attr($post->ID); ?>" style="width: 100%; height: auto; padding: 8px; margin-top: 10px;">
                <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                <?php _e('Regenerate Title', 'ai-seo-generator'); ?>
            </button>
            <p class="description" style="margin-top: 10px;"><?php _e('This will generate a new title based on the article keywords. The current title will be replaced.', 'ai-seo-generator'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Render image regeneration meta box
     */
    public function render_image_meta_box($post) {
        $keywords = get_post_meta($post->ID, '_ai_seo_keywords', true);
        $has_thumbnail = has_post_thumbnail($post->ID);
        
        if (empty($keywords)) {
            echo '<p>' . __('This post was not generated using AI.', 'ai-seo-generator') . '</p>';
            return;
        }
        
        $image_orientation = get_option('AI_SEO_image_orientation', 'landscape');
        ?>
        <div class="ai-seo-meta-box">
            <p><?php _e('Generate a new featured image for this article using AI.', 'ai-seo-generator'); ?></p>
            <p><strong><?php _e('Image Orientation:', 'ai-seo-generator'); ?></strong> <?php echo esc_html(ucfirst($image_orientation)); ?></p>
            
            <button type="button" class="button button-large button-primary ai-seo-regenerate-image" data-post-id="<?php echo esc_attr($post->ID); ?>" style="width: 100%; height: auto; padding: 8px; margin-top: 10px;">
                <span class="dashicons dashicons-format-image" style="margin-top: 4px;"></span>
                <?php _e('Regenerate Image', 'ai-seo-generator'); ?>
            </button>
            <p class="description" style="margin-top: 10px;"><?php _e('This will generate a new AI image and set it as the featured image. You can change the orientation in plugin settings.', 'ai-seo-generator'); ?></p>
            
            <p style="margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=ai-seo-generator-settings#content'); ?>" target="_blank">
                    <?php _e('Change Image Orientation', 'ai-seo-generator'); ?>
                </a>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Move this meta box right after the featured image box
            var featuredImageBox = $('#postimagediv');
            var regenImageBox = $('#ai_seo_regenerate_image');
            if (featuredImageBox.length && regenImageBox.length) {
                regenImageBox.insertAfter(featuredImageBox);
            }
        });
        </script>
        <?php
    }
}
