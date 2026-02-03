<?php
/**
 * Direct test file for Google Sheets - bypasses AJAX
 * Access via: yoursite.com/wp-content/plugins/ai-seo-generator/test-sheets.php
 * DELETE THIS FILE AFTER TESTING
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied - you must be logged in as admin');
}

header('Content-Type: text/plain');

echo "=== AI SEO Generator Debug Test ===\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n\n";

// Check if vendor autoload exists
$autoload = AI_SEO_PLUGIN_DIR . 'vendor/autoload.php';
echo "Autoload path: " . $autoload . "\n";
echo "Autoload exists: " . (file_exists($autoload) ? 'YES' : 'NO') . "\n\n";

// Check Google Client class
echo "Google_Client exists: " . (class_exists('Google_Client') ? 'YES' : 'NO') . "\n";
echo "Google_Service_Sheets exists: " . (class_exists('Google_Service_Sheets') ? 'YES' : 'NO') . "\n";
echo "Google_Service_Sheets_ValueRange exists: " . (class_exists('Google_Service_Sheets_ValueRange') ? 'YES' : 'NO') . "\n\n";

// Check credentials file
$creds_path = get_option('AI_SEO_google_credentials_path', '');
echo "Credentials path: " . $creds_path . "\n";
echo "Credentials file exists: " . (file_exists($creds_path) ? 'YES' : 'NO') . "\n";
if (file_exists($creds_path)) {
    echo "Credentials file readable: " . (is_readable($creds_path) ? 'YES' : 'NO') . "\n";
    $json = file_get_contents($creds_path);
    $data = json_decode($json, true);
    echo "Credentials valid JSON: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO - ' . json_last_error_msg()) . "\n";
    if (isset($data['client_email'])) {
        echo "Service account email: " . $data['client_email'] . "\n";
    }
}
echo "\n";

// Check sheets ID
$sheets_id = get_option('AI_SEO_google_sheets_id', '');
echo "Spreadsheet ID: " . $sheets_id . "\n\n";

// Try to get the sheets instance
echo "=== Testing Google Sheets Class ===\n\n";
try {
    $sheets = AI_SEO_Google_Sheets::get_instance();
    echo "AI_SEO_Google_Sheets instance: OK\n";
    
    // Try test_connection
    echo "\n=== Testing Connection ===\n\n";
    $result = $sheets->test_connection();
    echo "Result:\n";
    print_r($result);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n\n=== END OF TEST ===\n";
echo "DELETE THIS FILE AFTER TESTING!\n";
