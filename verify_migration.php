<?php
/**
 * Verification script for WP Complete Migration (Mocked WP).
 * 
 * Usage: php verify_migration.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting verification script...\n";

// Define ABSPATH to allow including files.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

try {
    // Mock WordPress functions
    if (!function_exists('add_action')) { function add_action($tag, $callback) {} }
    if (!function_exists('current_user_can')) { function current_user_can($cap) { return true; } }
    if (!function_exists('wp_die')) { function wp_die($message) { echo "WP_DIE: $message\n"; exit; } }
    if (!function_exists('__')) { function __($text, $domain) { return $text; } }
    if (!function_exists('admin_url')) { function admin_url($path) { return "http://example.com/wp-admin/$path"; } }
    if (!function_exists('get_post_status')) { function get_post_status($id) { return 'publish'; } }
    if (!function_exists('esc_url')) { function esc_url($url) { return $url; } } // Added missing mock

    // Mock Data Store
    $mock_users = [
        (object)['ID' => 1, 'user_login' => 'admin']
    ];
    $mock_user_meta = [];

    // Mock WP Users
    if (!function_exists('get_users')) {
        function get_users($args) {
            global $mock_users;
            return $mock_users;
        }
    }

    // Mock Meta Functions
    if (!function_exists('get_user_meta')) {
        function get_user_meta($user_id, $key, $single) {
            global $mock_user_meta;
            if (isset($mock_user_meta[$user_id][$key])) {
                return $mock_user_meta[$user_id][$key];
            }
            return $single ? '' : [];
        }
    }
    if (!function_exists('update_user_meta')) {
        function update_user_meta($user_id, $key, $value) {
            global $mock_user_meta;
            $mock_user_meta[$user_id][$key] = $value;
            // echo "Updated user meta: $key\n";
            return true;
        }
    }
    if (!function_exists('delete_user_meta')) {
        function delete_user_meta($user_id, $key) {
            global $mock_user_meta;
            unset($mock_user_meta[$user_id][$key]);
            return true;
        }
    }
    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key, $single) {
            // Mock LMS order
            if ($key === '_simple_lms_order') {
                return [101]; // Lesson ID 101
            }
            return '';
        }
    }

    // Mock DB
    class MockWPDB {
        public $postmeta = 'wp_postmeta';
        public $posts = 'wp_posts'; // Add table name mock
        
        public function prepare($query, $args) {
            return $query;
        }
        
        public function get_col($query) {
            // Return course ID 50 for lesson 101
            return [50]; 
        }
        
        public function esc_like($text) {
            return $text;
        }
    }
    global $wpdb;
    $wpdb = new MockWPDB();

    // Redirect Mock
    function wp_redirect($location) {
        echo "Redirecting to: $location\n";
        // We don't exit here so we can assert state
    }

    // Include the class to test
    require_once 'includes/class-migration.php';

    // --- TEST SETUP ---
    echo "Setting up test data...\n";
    $user_id = 1;

    // Simulate WP Complete Data: Lesson 101 completed on 2023-01-01
    $wpc_data = [
        '101' => ['completed' => '2023-01-01 10:00:00'],
        '102-button' => ['completed' => '2023-01-02 10:00:00']
    ];
    update_user_meta($user_id, 'wpcomplete', json_encode($wpc_data));

    echo "Running Migration...\n";
    \SimpleLMS\Migration::run_wpc_migration();

    echo "Verifying Results...\n";
    $progress = get_user_meta($user_id, '_lms_progress', true);
    
    // Assert Course 50 has Lesson 101 completed
    if (isset($progress[50][101])) {
        echo "SUCCESS: Course 50, Lesson 101 is marked completed.\n";
        echo "Timestamp: " . date('Y-m-d H:i:s', $progress[50][101]) . "\n";
    } else {
        echo "FAILURE: Course 50, Lesson 101 NOT found in progress.\n";
        print_r($progress);
    }

    // Assert Course 50 has Lesson 102 completed (from button format)
    if (isset($progress[50][102])) {
        echo "SUCCESS: Course 50, Lesson 102 is marked completed.\n";
    } else {
        echo "FAILURE: Course 50, Lesson 102 NOT found in progress.\n";
    }

} catch (Throwable $t) {
    echo "FATAL ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString();
}
