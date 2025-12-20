<?php
/**
 * Gemini API Handler
 */

class AI_SEO_Gemini_API {
    
    private static $instance = null;
    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('AI_SEO_gemini_api_key', '');
    }
    
    /**
     * Generate article content
     */
    public function generate_article($keywords, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'ai-seo-generator'));
        }
        
        $defaults = array(
            'include_tables' => get_option('AI_SEO_include_tables', false),
            'internal_links' => get_option('AI_SEO_internal_links', 2),
            'external_links' => get_option('AI_SEO_external_links', 2),
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $prompt = $this->build_prompt($keywords, $options);
        
        // Use Gemini 2.5 Pro - best available model for content generation
        $response = $this->make_request('gemini-2.5-pro:generateContent', array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->parse_response($response);
    }
    
    /**
     * Search for a real URL using Gemini with Google Search grounding
     * This uses the Gemini API with Google Search to find actual, working URLs
     */
    public function search_for_url($search_query, $exclude_domains = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'ai-seo-generator'));
        }
        
        // Build exclusion list for the prompt
        $exclude_text = '';
        if (!empty($exclude_domains)) {
            $exclude_text = "\n\nDO NOT use URLs from these domains (already used): " . implode(', ', $exclude_domains);
        }
        
        $prompt = "I need you to find ONE real, currently working URL for the following topic: \"{$search_query}\"\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- The URL must be from an authoritative source (government site, university, major organization, well-known company)\n";
        $prompt .= "- The URL must be a specific page about this topic, NOT a homepage\n";
        $prompt .= "- The URL must be currently active and accessible (not a 404 or dead link)\n";
        $prompt .= "- Prefer .gov, .edu, .org domains, or major established websites\n";
        $prompt .= "- The page must be in English\n";
        $prompt .= $exclude_text;
        $prompt .= "\n\nRespond with ONLY the URL, nothing else. Just the complete URL starting with https:// or http://";
        
        // Use Gemini with Google Search grounding enabled
        $response = $this->make_request('gemini-2.0-flash:generateContent', array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'tools' => array(
                array(
                    'google_search' => new stdClass() // Enable Google Search grounding
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.1, // Low temperature for factual responses
                'maxOutputTokens' => 256,
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('AI SEO: Google Search grounding failed: ' . $response->get_error_message());
            return $response;
        }
        
        // Extract URL from response
        $url = $this->extract_url_from_response($response);
        
        if (empty($url)) {
            error_log('AI SEO: No URL found in Gemini response for query: ' . $search_query);
            return null;
        }
        
        return $url;
    }
    
    /**
     * Extract URL from Gemini response
     */
    private function extract_url_from_response($response) {
        if (!isset($response['candidates'][0]['content']['parts'])) {
            return null;
        }
        
        $text = '';
        foreach ($response['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        
        // Clean up the response - extract just the URL
        $text = trim($text);
        
        // Try to find a URL in the response
        if (preg_match('/https?:\/\/[^\s<>"\']+/i', $text, $matches)) {
            $url = $matches[0];
            // Clean up any trailing punctuation
            $url = rtrim($url, '.,;:!?)\'">');
            return $url;
        }
        
        return null;
    }
    
    /**
     * Generate image using Vertex AI Imagen 3 for highest quality
     */
    public function generate_image($prompt, $aspect_ratio = null, $retry_count = 0, &$debug_log = array()) {
        // Check if Google Client library is available
        if (!class_exists('Google_Client')) {
            return new WP_Error('missing_dependency', __('Google API Client library not installed. Please run composer install.', 'ai-seo-generator'));
        }
        
        $credentials_path = get_option('AI_SEO_google_credentials_path', '');
        
        if (empty($credentials_path) || !file_exists($credentials_path)) {
            return new WP_Error('no_credentials', __('Google credentials file not configured', 'ai-seo-generator'));
        }
        
        // Get aspect ratio from settings if not provided
        if (empty($aspect_ratio)) {
            $image_orientation = get_option('AI_SEO_image_orientation', 'landscape');
            $aspect_ratio = ($image_orientation === 'portrait') ? '9:16' : '16:9';
        } else {
            $aspect_ratio = ($aspect_ratio === 'portrait') ? '9:16' : '16:9';
        }
        
        $debug_log[] = 'Vertex AI Imagen 3 Request - Attempt ' . ($retry_count + 1);
        
        try {
            // Load credentials and get project ID
            $credentials_json = file_get_contents($credentials_path);
            $credentials = json_decode($credentials_json, true);
            $project_id = $credentials['project_id'] ?? '';
            
            // Get access token using service account
            $client = new Google_Client();
            $client->setAuthConfig($credentials_path);
            $client->addScope('https://www.googleapis.com/auth/cloud-platform');
            $client->fetchAccessTokenWithAssertion();
            $token = $client->getAccessToken();
            
            if (empty($token['access_token'])) {
                if ($retry_count < 2) {
                    sleep(2);
                    return $this->generate_image($prompt, $aspect_ratio, $retry_count + 1, $debug_log);
                }
                return new WP_Error('auth_error', __('Failed to get Vertex AI access token', 'ai-seo-generator'));
            }
            
            // Vertex AI Imagen 3 endpoint
            $location = 'us-central1';
            $endpoint = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$project_id}/locations/{$location}/publishers/google/models/imagen-3.0-generate-001:predict";
            
            $image_prompt = "Professional ultra-high-resolution photograph: " . $prompt . ". 

REQUIREMENTS:
- 8K ultra-high-definition, extremely sharp and detailed
- Professional DSLR quality with perfect focus
- Studio-grade lighting, realistic shadows and highlights  
- Rich vibrant colors, professional color grading
- Photorealistic, indistinguishable from real photography
- NO text, NO watermarks, NO logos
- NO blur, NO noise, NO AI artifacts
- Realistic human anatomy if people included

Style: High-end editorial photography, luxury magazine quality.";
            
            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token['access_token'],
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'instances' => array(
                        array('prompt' => $image_prompt)
                    ),
                    'parameters' => array(
                        'sampleCount' => 1,
                        'aspectRatio' => $aspect_ratio,
                        'personGeneration' => 'allow_adult',
                        'safetySetting' => 'block_few'
                    )
                )),
                'timeout' => 120,
            ));
            
            if (is_wp_error($response)) {
                $debug_log[] = 'Vertex AI WP Error: ' . $response->get_error_message();
                if ($retry_count < 2) {
                    sleep(2);
                    return $this->generate_image($prompt, $aspect_ratio, $retry_count + 1, $debug_log);
                }
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $response_code = wp_remote_retrieve_response_code($response);
            
            $debug_log[] = 'Vertex AI Response Code: ' . $response_code;
            
            if (isset($data['error'])) {
                $debug_log[] = 'Vertex AI API Error: ' . json_encode($data['error']);
                if ($retry_count < 2) {
                    sleep(2);
                    return $this->generate_image($prompt, $aspect_ratio, $retry_count + 1, $debug_log);
                }
                return new WP_Error('vertex_error', 'Vertex AI Error: ' . ($data['error']['message'] ?? json_encode($data['error'])), array('debug' => $debug_log));
            }
            
            // Check for image in response
            if (isset($data['predictions'][0]['bytesBase64Encoded'])) {
                $debug_log[] = 'Vertex AI Imagen 3 Success: High-res image generated';
                $image_data = base64_decode($data['predictions'][0]['bytesBase64Encoded']);
                return $this->save_image_data($image_data, $aspect_ratio, 'png', $prompt);
            }
            
            $debug_log[] = 'Vertex AI: No image in response - ' . substr($body, 0, 500);
            
            if ($retry_count < 2) {
                sleep(2);
                return $this->generate_image($prompt, $aspect_ratio, $retry_count + 1, $debug_log);
            }
            
            return new WP_Error('vertex_error', 'Vertex AI did not return an image after 3 attempts.', array('debug' => $debug_log));
            
        } catch (Exception $e) {
            $debug_log[] = 'Vertex AI Exception: ' . $e->getMessage();
            if ($retry_count < 2) {
                sleep(2);
                return $this->generate_image($prompt, $aspect_ratio, $retry_count + 1, $debug_log);
            }
            return new WP_Error('vertex_error', 'Vertex AI Exception: ' . $e->getMessage(), array('debug' => $debug_log));
        }
    }
    
    /**
     * Build content generation prompt with E-E-A-T standards
     */
    private function build_prompt($keywords, $options) {
        $business_description = get_option('AI_SEO_business_description', '');
        
        $prompt = "Write a comprehensive SEO-optimized article about: {$keywords}\n\n";
        
        if (!empty($business_description)) {
            $prompt .= "Business Context: {$business_description}\n\n";
        }
        
        // E-E-A-T STANDARDS
        $prompt .= "=== E-E-A-T CONTENT STANDARDS (MANDATORY) ===\n\n";
        
        $prompt .= "EXPERIENCE - First-Hand Proof:\n";
        $prompt .= "- Include personal anecdotes, real-world examples, or specific case studies\n";
        $prompt .= "- Share step-by-step methods that demonstrate actual hands-on experience\n";
        $prompt .= "- Provide practical insights that only come from doing the work, not just theory\n";
        $prompt .= "- Show proof of real-world knowledge through specific details and results\n\n";
        
        $prompt .= "EXPERTISE - Deep Subject Knowledge:\n";
        $prompt .= "- Cover the topic comprehensively, addressing all related sub-topics\n";
        $prompt .= "- Answer common user questions naturally (like 'People Also Ask' queries)\n";
        $prompt .= "- Use niche terminology naturally and accurately\n";
        $prompt .= "- Demonstrate subject matter expertise through depth and completeness\n\n";
        
        $prompt .= "AUTHORITATIVENESS - Credible Information:\n";
        $prompt .= "- Back up claims with references to authoritative sources (studies, official data)\n";
        $prompt .= "- Present information as coming from a recognized expert perspective\n";
        $prompt .= "- Include specific, verifiable facts and statistics where relevant\n\n";
        
        $prompt .= "TRUSTWORTHINESS - Accuracy & Transparency:\n";
        $prompt .= "- Ensure all facts and statistics are accurate and verifiable\n";
        $prompt .= "- Be transparent about limitations or when professional consultation is needed\n";
        $prompt .= "- Provide balanced, honest information without exaggeration\n\n";
        
        // AI-OPTIMIZED STRUCTURE
        $prompt .= "=== AI-OPTIMIZED ARTICLE STRUCTURE (MANDATORY) ===\n\n";
        
        $prompt .= "ANSWER-FIRST FORMAT (Inverted Pyramid):\n";
        $prompt .= "- Begin the article with a clear, direct answer to the main query\n";
        $prompt .= "- Start each major section with a concise answer before elaborating\n";
        $prompt .= "- Put the most important information first in every section\n\n";
        
        $prompt .= "QUESTION-BASED HEADINGS:\n";
        $prompt .= "- Frame subheadings as natural-language questions when appropriate\n";
        $prompt .= "- Example: 'How Does [Topic] Work?' or 'What Are the Benefits of [Topic]?'\n";
        $prompt .= "- This format directly serves AI's conversational nature for featured snippets\n\n";
        
        $prompt .= "SCANNABLE ELEMENTS (for AI extraction):\n";
        $prompt .= "- Use bulleted lists for features, benefits, or key points\n";
        $prompt .= "- Use numbered lists for steps, processes, or rankings\n";
        $prompt .= "- Include comparison tables when comparing options or features\n";
        $prompt .= "- These elements are easily extracted by AI overviews and Google SGE\n\n";
        
        $prompt .= "FAQ SECTION (REQUIRED):\n";
        $prompt .= "- Include a 'Frequently Asked Questions' section near the end\n";
        $prompt .= "- Format: Use <h3> for each question, followed by a <p> with the direct answer\n";
        $prompt .= "- Include 3-5 relevant questions that users commonly ask about this topic\n";
        $prompt .= "- Answers should be concise but complete (2-4 sentences each)\n\n";
        
        // CONTENT REQUIREMENTS
        $prompt .= "=== CONTENT REQUIREMENTS ===\n\n";
        $prompt .= "- Provide ONLY a title (do NOT wrap it in <h1> tags or any other HTML tags)\n";
        $prompt .= "- Write the content in HTML format with proper heading hierarchy starting from <h2> (not h1)\n";
        $prompt .= "- Include at least 1500 words of high-quality, insightful content\n";
        $prompt .= "- Use proper paragraphs and formatting\n";
        $prompt .= "- Include bullet points or numbered lists where appropriate\n";
        $prompt .= "- Make content natural, engaging, and avoid generic statements\n";
        
        if ($options['include_tables']) {
            $prompt .= "- MUST include at least one HTML comparison/data table with relevant, specific data. Use proper <thead> and <tbody> tags.\n";
        }
        
        if ($options['internal_links'] > 0) {
            $prompt .= "- IMPORTANT: You MUST include exactly {$options['internal_links']} internal link placeholders in the content. Use this EXACT format (do not modify): <a href=\"[INTERNAL_LINK]\" data-context=\"topic or keyword\">descriptive anchor text</a>. Place them naturally within paragraphs. Example: <a href=\"[INTERNAL_LINK]\" data-context=\"seo best practices\">learn more about SEO</a>\n";
        }
        
        if ($options['external_links'] > 0) {
            $prompt .= "- IMPORTANT: You MUST include exactly {$options['external_links']} external link placeholders in the content. Use this EXACT format (do not modify): <a href=\"[EXTERNAL_LINK]\" data-search=\"specific search query for finding a real authoritative source\" target=\"_blank\" rel=\"noopener noreferrer\">descriptive anchor text</a>\n";
            $prompt .= "  * The data-search attribute should contain a specific Google search query to find a real, authoritative page about that exact topic\n";
            $prompt .= "  * Make search queries specific - e.g., 'OSHA workplace safety guidelines official' not just 'safety'\n";
            $prompt .= "  * Each placeholder should target DIFFERENT authoritative sources (government sites, universities, industry associations, major companies)\n";
            $prompt .= "  * Spread the {$options['external_links']} links throughout different sections\n";
            $prompt .= "  * Example: <a href=\"[EXTERNAL_LINK]\" data-search=\"EPA air quality standards official guidelines\" target=\"_blank\" rel=\"noopener noreferrer\">EPA air quality standards</a>\n";
        }
        
        $prompt .= "\nSEO REQUIREMENTS (STRICT CHARACTER LIMITS - COUNT CAREFULLY!):\n";
        $prompt .= "- seo_title: SEO title, MAXIMUM 50 characters (count each character!)\n";
        $prompt .= "- meta_description: MAXIMUM 120 characters ONLY - this is critical, count every character before submitting!\n";
        $prompt .= "- slug: URL-friendly slug using lowercase letters and hyphens only, 3-5 words, include main keyword\n";
        $prompt .= "- focus_keyphrase: The primary keyword/phrase this article targets (2-4 words)\n";
        
        $prompt .= "\nFormat the response as JSON with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= '  "title": "Article Title (plain text, no HTML tags)",'; 
        $prompt .= "\n";
        $prompt .= '  "seo_title": "SEO Title max 60 chars",'; 
        $prompt .= "\n";
        $prompt .= '  "meta_description": "Meta description max 155 chars",'; 
        $prompt .= "\n";
        $prompt .= '  "slug": "url-friendly-slug",'; 
        $prompt .= "\n";
        $prompt .= '  "focus_keyphrase": "main target keyword",'; 
        $prompt .= "\n";
        $prompt .= '  "content": "Full HTML content starting with <h2> tags (do not include h1 or title in content)",'; 
        $prompt .= "\n";
        $prompt .= '  "image_prompts": ["prompt1", "prompt2", "prompt3"]'; 
        $prompt .= "\n}\n";
        
        return $prompt;
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $data) {
        $url = $this->api_url . $endpoint . '?key=' . $this->api_key;
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed';
            return new WP_Error('api_error', $error_message, array('status' => $code));
        }
        
        return json_decode($body, true);
    }
    
    /**
     * Parse API response
     */
    private function parse_response($response) {
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('invalid_response', __('Invalid API response', 'ai-seo-generator'));
        }
        
        $text = $response['candidates'][0]['content']['parts'][0]['text'];
        
        // Extract JSON from response (may be wrapped in markdown code block)
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);
        
        $data = json_decode($text, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', __('Failed to parse response JSON', 'ai-seo-generator'));
        }
        
        return $data;
    }
    
    /**
     * Download and save generated image from Gemini 2.0
     */
    private function download_image_from_gemini($response, $aspect_ratio = '16:9') {
        // Check for inline data (Gemini 2.0 style)
        if (isset($response['candidates'][0]['content']['parts'])) {
            foreach ($response['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']) && isset($part['inlineData']['data'])) {
                    $image_data = base64_decode($part['inlineData']['data']);
                    return $this->save_image_data($image_data, $aspect_ratio);
                }
                // Sometimes it might return a text description if it refused to generate
                if (isset($part['text'])) {
                    // If we only got text, it might be a refusal or description
                    // We can't use this as an image
                }
            }
        }
        
        // Fallback/Error
        return new WP_Error('no_image_data', __('No image data found in response. The model may have refused the prompt.', 'ai-seo-generator'));
    }

    /**
     * Convert image to WebP format with high quality
     * Target ~300KB for excellent quality balance
     */
    private function convert_to_webp($source_path, $target_size_kb = 300) {
        // Check if GD library supports WebP
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
            return false;
        }
        
        $image_data = file_get_contents($source_path);
        if ($image_data === false) {
            return false;
        }
        
        $image = @imagecreatefromstring($image_data);
        if ($image === false) {
            return false;
        }
        
        // Preserve transparency for PNG images
        imagealphablending($image, true);
        imagesavealpha($image, true);
        
        // Generate WebP filename
        $webp_path = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $source_path);
        
        // Start with maximum quality (100) and reduce gradually
        // Minimum quality of 75 to balance quality and file size
        $quality = 100;
        $min_quality = 75;
        $target_size = $target_size_kb * 1024; // Convert to bytes
        
        do {
            imagewebp($image, $webp_path, $quality);
            clearstatcache(true, $webp_path);
            $file_size = filesize($webp_path);
            
            if ($file_size <= $target_size || $quality <= $min_quality) {
                break;
            }
            
            // Reduce quality by 5 for finer control
            $quality -= 5;
        } while ($quality >= $min_quality);
        
        imagedestroy($image);
        
        // If still too large at min quality, resize the image instead of degrading quality further
        if ($file_size > $target_size * 1.5) { // Only resize if significantly over target
            $image = @imagecreatefromstring($image_data);
            if ($image !== false) {
                $orig_width = imagesx($image);
                $orig_height = imagesy($image);
                
                // Scale down to 80% and use higher quality
                $new_width = (int)($orig_width * 0.8);
                $new_height = (int)($orig_height * 0.8);
                
                $resized = imagecreatetruecolor($new_width, $new_height);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                
                // Use high quality resampling
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
                
                // Save with quality 90 for resized image
                imagewebp($resized, $webp_path, 90);
                
                imagedestroy($image);
                imagedestroy($resized);
            }
        }
        
        return $webp_path;
    }
    
    /**
     * Save image data to media library
     */
    private function save_image_data($image_data, $aspect_ratio, $extension = 'png', $prompt = '') {
        $upload_dir = wp_upload_dir();
        // Create a slug from the prompt or use generic name
        $slug = !empty($prompt) ? sanitize_title(substr($prompt, 0, 50)) : 'ai-generated';
        $filename = $slug . '-' . time() . '.' . $extension;
        $file_path = $upload_dir['path'] . '/' . $filename;
        $file_url = $upload_dir['url'] . '/' . $filename;
        
        // Ensure upload directory exists
        if (!file_exists($upload_dir['path'])) {
            wp_mkdir_p($upload_dir['path']);
        }
        
        if (file_put_contents($file_path, $image_data) === false) {
            return new WP_Error('image_save_error', __('Failed to save image', 'ai-seo-generator'));
        }
        
        // Check if file was actually written
        if (!file_exists($file_path) || filesize($file_path) < 100) {
            return new WP_Error('image_save_error', __('Image file is empty or invalid', 'ai-seo-generator'));
        }
        
        // Convert to WebP for better performance (target 100-180KB)
        $webp_path = $this->convert_to_webp($file_path, 150);
        if ($webp_path && file_exists($webp_path)) {
            // Delete original file
            @unlink($file_path);
            // Use WebP file instead
            $file_path = $webp_path;
            $filename = basename($webp_path);
            $file_url = $upload_dir['url'] . '/' . $filename;
            $extension = 'webp';
        }
        
        // Determine mime type
        $mime_type = 'image/webp';
        if ($extension === 'jpg' || $extension === 'jpeg') {
            $mime_type = 'image/jpeg';
        } elseif ($extension === 'png') {
            $mime_type = 'image/png';
        }
        
        // Create attachment with proper guid
        $attachment = array(
            'guid' => $file_url,
            'post_mime_type' => $mime_type,
            'post_title' => !empty($prompt) ? ucfirst($prompt) : 'AI Generated Image',
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $file_path);
        
        if (is_wp_error($attach_id)) {
            return $attach_id;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Update the attached file path
        update_attached_file($attach_id, $file_path);
        
        // Store aspect ratio and source in metadata
        update_post_meta($attach_id, '_ai_seo_aspect_ratio', $aspect_ratio);
        update_post_meta($attach_id, '_ai_seo_generated', true);
        
        return $attach_id;
    }
    
    /**
     * Download and save generated image (Legacy Imagen)
     */
    private function download_image($response, $aspect_ratio = '16:9') {
        if (!isset($response['predictions'][0]['bytesBase64Encoded'])) {
            return new WP_Error('invalid_image_response', __('Invalid image response', 'ai-seo-generator'));
        }
        
        $image_data = base64_decode($response['predictions'][0]['bytesBase64Encoded']);
        
        $upload_dir = wp_upload_dir();
        $filename = 'gemini-image-' . time() . '.png';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        if (file_put_contents($file_path, $image_data) === false) {
            return new WP_Error('image_save_error', __('Failed to save image', 'ai-seo-generator'));
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => 'image/png',
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $file_path);
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Store aspect ratio in metadata
        update_post_meta($attach_id, '_ai_seo_aspect_ratio', $aspect_ratio);
        
        return $attach_id;
    }
    
    /**
     * Regenerate title for article
     */
    public function regenerate_title($keywords, $current_title = '') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'ai-seo-generator'));
        }
        
        $business_description = get_option('AI_SEO_business_description', '');
        
        $prompt = "Generate a COMPLETELY DIFFERENT, creative, SEO-optimized title for an article about: {$keywords}\n\n";
        
        if (!empty($business_description)) {
            $prompt .= "Business Context: {$business_description}\n\n";
        }
        
        if (!empty($current_title)) {
            $prompt .= "Current title (DO NOT use similar wording): {$current_title}\n\n";
            $prompt .= "IMPORTANT: Create a title that uses a completely different angle, structure, and vocabulary. Do NOT just rephrase the current title.\n\n";
        }
        
        $prompt .= "Title styles to consider (pick one randomly):\n";
        $prompt .= "- Question format (How to...? Why does...? What is...?)\n";
        $prompt .= "- Number/List format (7 Ways to..., Top 10...)\n";
        $prompt .= "- Bold statement or claim\n";
        $prompt .= "- Problem-solution format\n";
        $prompt .= "- Curiosity gap (The Secret to..., What Nobody Tells You About...)\n";
        $prompt .= "- Direct benefit (Get More..., Save Time on...)\n\n";
        
        $prompt .= "Requirements:\n";
        $prompt .= "- Create a compelling, click-worthy title using a DIFFERENT approach than the current one\n";
        $prompt .= "- Keep it under 60 characters for SEO\n";
        $prompt .= "- Make it specific and relevant\n";
        $prompt .= "- Return ONLY the title text, no quotes or extra formatting\n";
        
        // Use gemini-2.0-flash for faster, more reliable title generation
        $response = $this->make_request('gemini-2.0-flash:generateContent', array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.9,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 100,
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Check for content filtering or empty response
        if (isset($response['candidates'][0]['finishReason']) && $response['candidates'][0]['finishReason'] === 'SAFETY') {
            return new WP_Error('content_filtered', __('Content was filtered. Please try again.', 'ai-seo-generator'));
        }
        
        // Try to get the text from the response
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $title = trim($response['candidates'][0]['content']['parts'][0]['text']);
            $title = trim($title, '"\' ');
            return $title;
        }
        
        // If no candidates, check for prompt feedback
        if (isset($response['promptFeedback']['blockReason'])) {
            return new WP_Error('blocked', __('Request blocked: ', 'ai-seo-generator') . $response['promptFeedback']['blockReason']);
        }
        
        // Log the actual response for debugging
        error_log('AI SEO Title Regen - Response: ' . print_r($response, true));
        
        return new WP_Error('invalid_response', __('Invalid API response. Check error log for details.', 'ai-seo-generator'));
    }
}
