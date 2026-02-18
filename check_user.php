<?php
/**
 * Script to check user rwaterbury.
 * 
 * Usage: php check_user.php
 */

require_once( __DIR__ . '/../../../wp-load.php' );

$user = get_user_by('login', 'rwaterbury');

if ($user) {
    echo "User found: " . $user->user_login . " (ID: " . $user->ID . ")\n";
    echo "Roles: " . implode(', ', $user->roles) . "\n";
    echo "Email: " . $user->user_email . "\n";
    
    // Check if user has admin capabilities
    if (user_can($user->ID, 'manage_options')) {
        echo "User has 'manage_options' capability (Admin).\n";
    } else {
        echo "User does NOT have 'manage_options' capability.\n";
    }
} else {
    echo "User 'rwaterbury' not found.\n";
    
    // List all users to see who exists
    $users = get_users(['number' => 5]);
    echo "Existing users:\n";
    foreach ($users as $u) {
        echo "- " . $u->user_login . " (" . implode(', ', $u->roles) . ")\n";
    }
}
