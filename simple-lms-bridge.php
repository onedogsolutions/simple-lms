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
require_once SLMS_PLUGIN_DIR . 'includes/class-user-meta.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-relationships.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-course-history.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-account-dashboard.php';


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
    Relationships::init();

    // Conditionally boot PMPro integration.
    if (function_exists('pmpro_getMembershipLevelForUser')) {
        PMPro::init();
    }

    UserMeta::init();
    AccountDashboard::init();

    // Admin Menus
    add_action('admin_menu', __NAMESPACE__ . '\\slms_admin_menu');
}
add_action('init', __NAMESPACE__ . '\\slms_init');

/**
 * Register the top-level SimpleLMS menu and its submenus.
 *
 * @return void
 */
function slms_admin_menu()
{
    add_menu_page(
        __('SimpleLMS', 'simple-lms-bridge'),
        __('SimpleLMS', 'simple-lms-bridge'),
        'manage_options',
        'simple-lms',
        function () {
        // Dashboard or overview could go here. For now, redirect to courses.
        echo '<div class="wrap"><h1>' . esc_html__('SimpleLMS Overview', 'simple-lms-bridge') . '</h1><p>' . esc_html__('Welcome to SimpleLMS. Use the side menu to manage your courses, lessons, and students.', 'simple-lms-bridge') . '</p></div>';
    },
        'dashicons-welcome-learn-more',
        6
    );

    add_submenu_page(
        'simple-lms',
        __('Student Manager', 'simple-lms-bridge'),
        __('Student Manager', 'simple-lms-bridge'),
        'manage_options',
        'slms-students',
        array(__NAMESPACE__ . '\\MetaBoxes', 'render_students_page')
    );

    add_submenu_page(
        'simple-lms',
        __('Migration Tool', 'simple-lms-bridge'),
        __('Migration Tool', 'simple-lms-bridge'),
        'manage_options',
        'slms-migration',
        function () {
        echo '<div class="wrap slms-admin-wrap tw-preflight"><div id="slms-admin-root"></div></div>';
    }
    );
}

/* ─── Activation ─────────────────────────────────────────────────────── */

/**
 * Flush rewrite rules on activation so CPT permalinks work immediately.
 *
 * @return void
 */
function slms_activate()
{
    CPT::register_post_types();
    Relationships::create_table();
    CourseHistory::create_table();
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

    // Load on our CPT edit screens and the Student Manager / Migration Tool pages.
    $is_lms_cpt = in_array($screen->post_type, array('slms_course', 'slms_lesson'), true);
    $is_slms_page = (strpos($screen->id, 'slms-students') !== false || strpos($screen->id, 'slms-migration') !== false || strpos($screen->id, 'simple-lms') !== false);

    if (!$is_lms_cpt && !$is_slms_page) {
        return;
    }

    $asset_file = SLMS_PLUGIN_DIR . 'build/admin/index.asset.php';

    if (!file_exists($asset_file)) {
        return;
    }

    $asset = require $asset_file;

    if ($is_slms_page) {
        // Emergency Tailwind CDN — local build is failing
        wp_enqueue_script(
            'tailwind-cdn',
            'https://cdn.tailwindcss.com',
            array(),
            null,
            false
        );
    }

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
        require_once SLMS_PLUGIN_DIR . 'includes/bb-modules/lms-account-dashboard/lms-account-dashboard.php';
        require_once SLMS_PLUGIN_DIR . 'includes/bb-modules/slms-student-dashboard/slms-student-dashboard.php';
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


/* ─── Shortcodes (Legacy) ────────────────────────────────────────────── */

// If we need any legacy shortcodes, they would go here.