<?php
/**
 * Verification script for LMS Content Migration.
 * 
 * Usage: php verify_content_migration.php
 */

// Load WordPress.
define('WP_USE_THEMES', false);
// Try to find wp-load.php in common locations if not in root.
$wp_load = __DIR__ . '/wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = dirname(dirname(dirname(__DIR__))) . '/wp-load.php'; // Assuming plugin directory depth.
}

if (file_exists($wp_load)) {
    require_once($wp_load);
}
else {
    echo "Error: wp-load.php not found. Please run this script from a WordPress environment.\n";
    exit(1);
}

// Ensure SimpleLMS namespace is available.
if (!class_exists('SimpleLMS\Migration')) {
    echo "Error: SimpleLMS\Migration class not found. Ensure the plugin is active.\n";
    exit(1);
}

use SimpleLMS\Migration;

echo "--- LMS Content Migration Verification ---\n";

// 1. Check pending content.
$pending_count = Migration::get_pending_content_count();
echo "Pending courses in legacy Pods: $pending_count\n";

if ($pending_count === 0) {
    echo "No pending courses found. Check if legacy 'course' CPT exists and has posts without _slms_migrated meta.\n";
    exit(0);
}

// 2. Run a batch migration.
echo "Running migration batch (limit 5)...\n";
$result = Migration::migrate_cpt_batch(5);

echo "Migration result:\n";
print_r($result);

// 3. Verify Imported Courses.
$new_courses = get_posts(array(
    'post_type' => 'slms_course',
    'post_status' => 'publish',
    'numberposts' => -1,
));

echo "\n--- Imported Courses (" . count($new_courses) . ") ---\n";
foreach ($new_courses as $course) {
    $lesson_order = get_post_meta($course->ID, '_simple_lms_order', true);
    $price = get_post_meta($course->ID, '_slms_course_price', true);
    $terms = wp_get_post_terms($course->ID, 'slms_course_cat');
    $term_names = wp_list_pluck($terms, 'name');

    echo "Course: {$course->post_title} (ID: {$course->ID})\n";
    echo "  - Lessons: " . (is_array($lesson_order) ? count($lesson_order) : 0) . " found.\n";
    echo "  - Price: $price\n";
    echo "  - Categories: " . implode(', ', $term_names) . "\n";
}

// 4. Verify Imported Lessons.
$new_lessons = get_posts(array(
    'post_type' => 'slms_lesson',
    'post_status' => 'publish',
    'numberposts' => -1,
));

echo "\n--- Imported Lessons (" . count($new_lessons) . ") ---\n";
foreach ($new_lessons as $lesson) {
    echo "Lesson: {$lesson->post_title} (ID: {$lesson->ID})\n";
}

echo "\nVerification complete.\n";