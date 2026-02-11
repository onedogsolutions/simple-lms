<?php
/**
 * Plugin Name: SimpleLMS Bridge
 * Plugin URI:  https://onedog.solutions
 * Description: A lightweight, CPT-based LMS with React admin UI and Beaver Builder integration.
 * Version:     1.0.0
 * Author:      One Dog Solutions
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