<?php
/**
 * Plugin Name: SimpleLMS Bridge
 * Plugin URI:  https://onedog.solutions
 * Description: A lightweight, CPT-based LMS with React admin UI and Beaver Builder integration.
 * Version:     1.0.0
 * Author:      Ryan D. Waterbury
 * Author URI:  https://onedog.solutions
 * Text Domain: simple-lms-bridge
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if (!defined('ABSPATH')) {
	exit;
}

/* ─── Constants ─────────────────────────────────────────────────────── */
define('SLMS_VERSION', '1.0.0');
define('SLMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SLMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SLMS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/* ─── Autoload includes ─────────────────────────────────────────────── */
require_once SLMS_PLUGIN_DIR . 'includes/class-cpt.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-rest.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-metaboxes.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-pmpro.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-expiration.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-certificates.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-migration.php';


/* ─── Boot ───────────────────────────────────────────────────────────── */

/**
 * Initialize the plugin.
 *
 * @return void
 */
function slms_init()
{
	CPT::init();
	REST::init();
	MetaBoxes::init();
	Expiration::init();
	Certificates::init();
	Migration::init();

	// Conditionally boot PMPro integration.
	if (function_exists('pmpro_getMembershipLevelForUser')) {
		PMPro::init();
	}
}
add_action('init', __NAMESPACE__ . '\\slms_init');

/* ─── Activation ─────────────────────────────────────────────────────── */

/**
 * Flush rewrite rules on activation so CPT permalinks work immediately.
 *
 * @return void
 */
function slms_activate()
{
	CPT::register_post_types();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\slms_activate');

/* ─── Deactivation ───────────────────────────────────────────────────── */

/**
 * Clean up rewrite rules on deactivation.
 *
 * @return void
 */
function slms_deactivate()
{
	flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\slms_deactivate');

/* ─── Admin Assets ───────────────────────────────────────────────────── */

/**
 * Enqueue the React admin bundle on Course / Lesson edit screens.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 * @return void
 */
function slms_enqueue_admin_assets($hook_suffix)
{
	$screen = get_current_screen();

	if (!$screen) {
		return;
	}

	// Load on our CPT edit screens and the Student Manager page.
	$is_lms_cpt = in_array($screen->post_type, array('lms_course', 'lms_lesson'), true);
	$is_students_page = ('toplevel_page_slms-students' === $screen->id);

	if (!$is_lms_cpt && !$is_students_page) {
		return;
	}

	$asset_file = SLMS_PLUGIN_DIR . 'build/admin/index.asset.php';

	if (!file_exists($asset_file)) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'slms-admin',
		SLMS_PLUGIN_URL . 'build/admin/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);

	wp_enqueue_style(
		'slms-admin',
		SLMS_PLUGIN_URL . 'build/admin/index.css',
		array('wp-components'),
		$asset['version']
	);

	wp_localize_script('slms-admin', 'slmsAdmin', array(
		'restUrl' => esc_url_raw(rest_url('simple-lms/v1/')),
		'nonce' => wp_create_nonce('wp_rest'),
		'postId' => get_the_ID(),
		'postType' => $screen->post_type,
		'page' => isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '',
	));
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\slms_enqueue_admin_assets');

/* ─── Beaver Builder Integration ─────────────────────────────────────── */

/**
 * Load Beaver Builder modules.
 *
 * @return void
 */
function slms_load_bb_modules()
{
	if (class_exists('FLBuilder')) {
		require_once SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-content/lms-content.php';
		require_once SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-outline/lms-outline.php';
		require_once SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-complete-button/lms-complete-button.php';
	}
}
add_action('init', __NAMESPACE__ . '\\slms_load_bb_modules');

/**
 * Enqueue frontend assets for LMS modules.
 *
 * @return void
 */
function slms_enqueue_frontend_assets()
{
	wp_enqueue_style(
		'slms-frontend',
		SLMS_PLUGIN_URL . 'assets/css/frontend.css',
		array(),
		SLMS_VERSION
	);
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\slms_enqueue_frontend_assets');

<?php
/**
 * Register Custom Post Types for Simple LMS
 */
function slms_register_post_types() {

    // 1. Register Courses
    $course_labels = array(
        'name'               => 'Courses',
        'singular_name'      => 'Course',
        'menu_name'          => 'LMS Courses',
        'add_new'            => 'Add New Course',
        'add_new_item'       => 'Add New Course',
        'edit_item'          => 'Edit Course',
        'all_items'          => 'All Courses',
    );

    $course_args = array(
        'labels'             => $course_labels,
        'public'             => true,
        'has_archive'        => true,
        'show_in_menu'       => true, // Ensures it appears in the sidebar
        'show_in_rest'       => true, // Enables the Block Editor
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'rewrite'            => array( 'slug' => 'courses' ),
        'menu_icon'          => 'dashicons-welcome-learn-more',
    );

    register_post_type( 'slms_course', $course_args );

    // 2. Register Lessons
    $lesson_labels = array(
        'name'               => 'Lessons',
        'singular_name'      => 'Lesson',
        'menu_name'          => 'LMS Lessons',
        'add_new'            => 'Add New Lesson',
        'add_new_item'       => 'Add New Lesson',
        'edit_item'          => 'Edit Lesson',
        'all_items'          => 'All Lessons',
    );

    $lesson_args = array(
        'labels'             => $lesson_labels,
        'public'             => true,
        'has_archive'        => true,
        'show_in_menu'       => true, 
        'show_in_rest'       => true,
        'supports'           => array( 'title', 'editor', 'revisions' ),
        'rewrite'            => array( 'slug' => 'lessons' ),
        'menu_icon'          => 'dashicons-media-text',
    );

    register_post_type( 'slms_lesson', $lesson_args );
}

add_action( 'init', 'slms_register_post_types' );

/**
 * Register Course Category Taxonomy and Lesson Relationships
 */
function slms_register_taxonomies() {
    
    // 1. Create a "Course Category" Taxonomy
    $labels = array(
        'name'              => 'Course Categories',
        'singular_name'     => 'Course Category',
        'search_items'      => 'Search Categories',
        'all_items'         => 'All Categories',
        'parent_item'       => 'Parent Category',
        'edit_item'         => 'Edit Category',
        'update_item'       => 'Update Category',
        'add_new_item'      => 'Add New Category',
        'menu_name'         => 'Course Categories',
    );

    $args = array(
        'hierarchical'      => true, // Makes it behaves like Categories, not Tags
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'show_in_rest'      => true, // Required for the Block Editor
        'rewrite'           => array( 'slug' => 'course-category' ),
    );

    // Register taxonomy for both post types so they can share categories
    register_taxonomy( 'slms_course_cat', array( 'slms_course', 'slms_lesson' ), $args );
}

add_action( 'init', 'slms_register_taxonomies' );