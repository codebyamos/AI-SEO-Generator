<?php
/**
 * Google Sheets Integration
 * 
 * Column Structure (matching user's sheet):
 * A = Keyword
 * B = Intent
 * C = Difficulty (KD%)
 * D = Volume
 * E = CPC ($)
 * F = Used in URL? (Yes/no)
 * G = URL (full article URL)
 */

class AI_SEO_Google_Sheets {
    
    private static $instance = null;
    private $sheets_id;
    private $service;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->sheets_id = get_option('AI_SEO_google_sheets_id', '');
        $this->init_service();
    }
    
    /**
     * Initialize Google Sheets service
     */
    private function init_service() {
        if (!class_exists('Google_Client')) {
            return;
        }
        
        // Also check for Google_Service_Sheets class
        if (!class_exists('Google_Service_Sheets')) {
            return;
        }
        
        $credentials_path = get_option('AI_SEO_google_credentials_path', '');
        if (empty($credentials_path) || !file_exists($credentials_path)) {
            return;
        }
        
        try {
            $client = new Google_Client();
            $client->setApplicationName('AI SEO Generator');
            // Use string instead of class constant to avoid parse-time errors
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
            $client->setAuthConfig($credentials_path);
            $client->setAccessType('offline');
            
            $this->service = new Google_Service_Sheets($client);
        } catch (Exception $e) {
            error_log('Google Sheets initialization error: ' . $e->getMessage());
            $this->service = null;
        }
    }
    
    /**
     * Fetch keywords from Google Sheets (only unused ones)
     * Returns array with keyword and row number for updating later
     */
    public function fetch_keywords($only_unused = true, $limit = 10) {
        if (!$this->service || empty($this->sheets_id)) {
            return new WP_Error('sheets_not_configured', __('Google Sheets not properly configured', 'ai-seo-generator'));
        }
        
        try {
            // Fetch columns A through G starting from row 3 (rows 1-2 are headers)
            $response = $this->service->spreadsheets_values->get($this->sheets_id, 'A3:G');
            $values = $response->getValues();
            
            if (empty($values)) {
                return array();
            }
            
            $keywords = array();
            $seen_keywords = array();
            $row_number = 3;
            
            foreach ($values as $row) {
                $keyword = isset($row[0]) ? trim($row[0]) : '';
                // Column F (index 5) - check for any variation of "yes"
                $used = isset($row[5]) ? strtolower(trim($row[5])) : '';
                
                // Skip empty keywords
                if (empty($keyword)) {
                    $row_number++;
                    continue;
                }
                
                // If only_unused is true, skip rows where Used column contains "yes"
                if ($only_unused && strpos($used, 'yes') !== false) {
                    $row_number++;
                    continue;
                }
                
                // Skip duplicate keywords within the same fetch (case-insensitive)
                $keyword_lower = strtolower($keyword);
                if (in_array($keyword_lower, $seen_keywords)) {
                    $row_number++;
                    continue;
                }
                $seen_keywords[] = $keyword_lower;
                
                $keywords[] = array(
                    'keyword' => $keyword,
                    'row' => $row_number
                );
                
                // Apply limit
                if (count($keywords) >= $limit) {
                    break;
                }
                
                $row_number++;
            }
            
            return $keywords;
        } catch (Exception $e) {
            error_log('Google Sheets fetch error: ' . $e->getMessage());
            return new WP_Error('sheets_fetch_error', $e->getMessage());
        }
    }
    
    /**
     * Mark keyword as used and add the article URL
     * Sets column B to "yes" and column C to full URL
     * Also colors the row green
     */
    public function mark_as_used($row, $post_url) {
        if (!$this->service || empty($this->sheets_id)) {
            return false;
        }
        
        // Check if required class exists
        if (!class_exists('Google_Service_Sheets_ValueRange')) {
            error_log('Google Sheets update error: Google_Service_Sheets_ValueRange class not found');
            return false;
        }
        
        try {
            // Update values (F = "Yes", G = URL)
            $range = "F{$row}:G{$row}";
            $values = array(
                array('Yes', $post_url)
            );
            
            $body = new Google_Service_Sheets_ValueRange(array(
                'values' => $values
            ));
            
            $params = array(
                'valueInputOption' => 'RAW'
            );
            
            $this->service->spreadsheets_values->update(
                $this->sheets_id,
                $range,
                $body,
                $params
            );
            
            // Set background color to #b6d7a8 (light green)
            $this->set_row_color($row, 0.714, 0.843, 0.659); // #b6d7a8
            
            return true;
        } catch (Exception $e) {
            error_log('Google Sheets update error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set background color for a row
     */
    private function set_row_color($row, $red, $green, $blue) {
        if (!$this->service || empty($this->sheets_id)) {
            return false;
        }
        
        // Check if required classes exist
        if (!class_exists('Google_Service_Sheets_Request') || !class_exists('Google_Service_Sheets_BatchUpdateSpreadsheetRequest')) {
            error_log('Google Sheets color update error: Required Google Sheets classes not found');
            return false;
        }
        
        try {
            // Get sheet ID (first sheet)
            $spreadsheet = $this->service->spreadsheets->get($this->sheets_id);
            $sheets = $spreadsheet->getSheets();
            $sheet_id = $sheets[0]->getProperties()->getSheetId();
            
            $requests = array(
                new Google_Service_Sheets_Request(array(
                    'repeatCell' => array(
                        'range' => array(
                            'sheetId' => $sheet_id,
                            'startRowIndex' => $row - 1, // 0-indexed
                            'endRowIndex' => $row,
                            'startColumnIndex' => 0,
                            'endColumnIndex' => 7 // Columns A through G
                        ),
                        'cell' => array(
                            'userEnteredFormat' => array(
                                'backgroundColor' => array(
                                    'red' => $red,
                                    'green' => $green,
                                    'blue' => $blue
                                )
                            )
                        ),
                        'fields' => 'userEnteredFormat.backgroundColor'
                    )
                ))
            );
            
            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(array(
                'requests' => $requests
            ));
            
            $this->service->spreadsheets->batchUpdate($this->sheets_id, $batchUpdateRequest);
            
            return true;
        } catch (Exception $e) {
            error_log('Google Sheets color update error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Legacy method - Update status in Google Sheets
     * Kept for backward compatibility
     */
    public function update_status($row, $status, $post_url = '') {
        if ($status === 'completed' || $status === 'yes') {
            return $this->mark_as_used($row, $post_url);
        }
        
        if (!$this->service || empty($this->sheets_id)) {
            return false;
        }
        
        try {
            $range = "B{$row}:C{$row}";
            $values = array(
                array($status === 'yes' ? 'yes' : 'no', $post_url)
            );
            
            $body = new Google_Service_Sheets_ValueRange(array(
                'values' => $values
            ));
            
            $params = array(
                'valueInputOption' => 'RAW'
            );
            
            $this->service->spreadsheets_values->update(
                $this->sheets_id,
                $range,
                $body,
                $params
            );
            
            return true;
        } catch (Exception $e) {
            error_log('Google Sheets update error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Append keywords to sheet
     */
    public function append_keywords($keywords) {
        if (!$this->service || empty($this->sheets_id)) {
            return false;
        }
        
        try {
            $values = array();
            foreach ($keywords as $keyword) {
                $values[] = array($keyword, 'no', ''); // Keyword, Used=no, URL empty
            }
            
            $body = new Google_Service_Sheets_ValueRange(array(
                'values' => $values
            ));
            
            $params = array(
                'valueInputOption' => 'RAW'
            );
            
            $this->service->spreadsheets_values->append(
                $this->sheets_id,
                'A:C',
                $body,
                $params
            );
            
            return true;
        } catch (Exception $e) {
            error_log('Google Sheets append error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test connection to Google Sheets
     */
    public function test_connection() {
        $debug = array();
        
        // Check if Google Client library exists
        if (!class_exists('Google_Client')) {
            return array(
                'success' => false,
                'message' => __('Google Client library not installed. Run: composer require google/apiclient', 'ai-seo-generator'),
                'debug' => 'Google_Client class not found'
            );
        }
        $debug[] = 'Google_Client class exists';
        
        // Check credentials path
        $credentials_path = get_option('AI_SEO_google_credentials_path', '');
        $debug[] = 'Credentials path from settings: ' . $credentials_path;
        
        if (empty($credentials_path)) {
            return array(
                'success' => false,
                'message' => __('Credentials file path is empty', 'ai-seo-generator'),
                'debug' => implode("\n", $debug)
            );
        }
        
        // Check if it's a URL (not allowed)
        if (filter_var($credentials_path, FILTER_VALIDATE_URL)) {
            return array(
                'success' => false,
                'message' => __('Credentials must be a server file path, not a URL. Download the JSON file and upload it to your server.', 'ai-seo-generator'),
                'debug' => implode("\n", $debug)
            );
        }
        
        // Check if file exists
        if (!file_exists($credentials_path)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Credentials file not found at: %s', 'ai-seo-generator'), $credentials_path),
                'debug' => implode("\n", $debug)
            );
        }
        $debug[] = 'Credentials file exists';
        
        // Check if file is readable
        if (!is_readable($credentials_path)) {
            return array(
                'success' => false,
                'message' => __('Credentials file exists but is not readable. Check file permissions.', 'ai-seo-generator'),
                'debug' => implode("\n", $debug)
            );
        }
        $debug[] = 'Credentials file is readable';
        
        // Check if it's valid JSON
        $json_content = file_get_contents($credentials_path);
        $json_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => __('Credentials file is not valid JSON: ', 'ai-seo-generator') . json_last_error_msg(),
                'debug' => implode("\n", $debug)
            );
        }
        $debug[] = 'Credentials file is valid JSON';
        
        // Check for required fields in the JSON
        if (!isset($json_data['client_email'])) {
            return array(
                'success' => false,
                'message' => __('Credentials file missing client_email field. Make sure this is a service account JSON file.', 'ai-seo-generator'),
                'debug' => implode("\n", $debug)
            );
        }
        $debug[] = 'Service account email: ' . $json_data['client_email'];
        
        // Check sheets ID
        $sheets_id = get_option('AI_SEO_google_sheets_id', '');
        $debug[] = 'Sheets ID from settings: ' . $sheets_id;
        
        if (empty($sheets_id)) {
            return array(
                'success' => false,
                'message' => __('Spreadsheet ID is empty', 'ai-seo-generator'),
                'debug' => implode("\n", $debug)
            );
        }
        
        // Extract ID if full URL was provided
        if (strpos($sheets_id, 'docs.google.com') !== false) {
            preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $sheets_id, $matches);
            if (isset($matches[1])) {
                $sheets_id = $matches[1];
                $debug[] = 'Extracted Sheets ID from URL: ' . $sheets_id;
            } else {
                return array(
                    'success' => false,
                    'message' => __('Could not extract Spreadsheet ID from URL. Please enter just the ID.', 'ai-seo-generator'),
                    'debug' => implode("\n", $debug)
                );
            }
        }
        
        // Try to initialize the client
        try {
            $client = new Google_Client();
            $client->setApplicationName('AI SEO Generator');
            // Use string instead of class constant to avoid parse-time errors
            $client->setScopes(['https://www.googleapis.com/auth/spreadsheets']);
            $client->setAuthConfig($credentials_path);
            $client->setAccessType('offline');
            $debug[] = 'Google Client initialized';
            
            $service = new Google_Service_Sheets($client);
            $debug[] = 'Google Sheets service created';
            
            // Try to access the spreadsheet
            $response = $service->spreadsheets->get($sheets_id);
            $title = $response->getProperties()->getTitle();
            $debug[] = 'Successfully connected to spreadsheet: ' . $title;
            
            return array(
                'success' => true,
                'message' => sprintf(__('✓ Connected to: %s', 'ai-seo-generator'), $title),
                'title' => $title,
                'debug' => implode("\n", $debug),
                'service_account' => $json_data['client_email']
            );
            
        } catch (Exception $e) {
            $debug[] = 'Exception: ' . $e->getMessage();
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => implode("\n", $debug)
            );
        }
    }
    
    /**
     * Get service account email from credentials file
     */
    public function get_service_account_email() {
        $credentials_path = get_option('AI_SEO_google_credentials_path', '');
        
        if (empty($credentials_path) || !file_exists($credentials_path)) {
            return null;
        }
        
        $json_content = file_get_contents($credentials_path);
        if ($json_content === false) {
            return null;
        }
        
        $json_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($json_data['client_email'])) {
            return null;
        }
        
        return $json_data['client_email'];
    }
    
    /**
     * Get keywords preview without marking as used
     */
    public function preview_keywords($only_unused = true, $limit = 10) {
        return $this->fetch_keywords($only_unused, $limit);
    }
    
    /**
     * Test marking a row - marks a specific row as used with test URL
     * Used for testing the sheet update functionality
     */
    public function test_mark_row($row_number = null) {
        // Check if required classes exist
        if (!class_exists('Google_Service_Sheets_ValueRange')) {
            return array(
                'success' => false,
                'message' => __('Google API classes not found. Please run "composer install" in the plugin directory.', 'ai-seo-generator')
            );
        }
        
        if (!$this->service || empty($this->sheets_id)) {
            return array(
                'success' => false,
                'message' => __('Google Sheets not configured. Please check your Spreadsheet ID and credentials file path.', 'ai-seo-generator')
            );
        }
        
        try {
            // If no row specified, find the first unused row
            if ($row_number === null) {
                $keywords = $this->fetch_keywords(true, 1);
                if (is_wp_error($keywords)) {
                    return array(
                        'success' => false,
                        'message' => $keywords->get_error_message()
                    );
                }
                if (empty($keywords)) {
                    return array(
                        'success' => false,
                        'message' => __('No unused keywords found in the sheet', 'ai-seo-generator')
                    );
                }
                $row_number = $keywords[0]['row'];
                $keyword = $keywords[0]['keyword'];
            } else {
                $keyword = 'Row ' . $row_number;
            }
            
            // Test URL
            $test_url = home_url('/test-article-' . time() . '/');
            
            // Try to mark the row
            $result = $this->mark_as_used($row_number, $test_url);
            
            if ($result) {
                return array(
                    'success' => true,
                    'message' => sprintf(
                        __('✓ Successfully updated row %d ("%s"). Check your Google Sheet - the row should now be green (#b6d7a8) with "Yes" in column F and a test URL in column G.', 'ai-seo-generator'),
                        $row_number,
                        $keyword
                    ),
                    'row' => $row_number,
                    'keyword' => $keyword,
                    'test_url' => $test_url
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to update the row. Check error logs for details.', 'ai-seo-generator')
                );
            }
        } catch (Exception $e) {
            error_log('Google Sheets test_mark_row error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => sprintf(__('Error: %s', 'ai-seo-generator'), $e->getMessage())
            );
        }
    }
}
