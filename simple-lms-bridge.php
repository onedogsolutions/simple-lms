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
// Integer schema version. Bump when adding an Upgrade step (see class-upgrade.php).
define('SLMS_DB_VERSION', 2);
define('SLMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SLMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SLMS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/* ─── Autoload includes ─────────────────────────────────────────────── */
require_once SLMS_PLUGIN_DIR . 'includes/class-cpt.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-rest.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-metaboxes.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-pmpro.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-expiration.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-course-history.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-certificates.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-migration.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-user-meta.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-relationships.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-analytics.php';
require_once SLMS_PLUGIN_DIR . 'includes/class-upgrade.php';
// The legacy [simple_lms_account] shortcode (formerly class-account-dashboard.php)
// has been removed. The native lms-account-dashboard Beaver Builder module renders
// the account dashboard; shortcode-based rendering of BB module content is not used.


/* ─── Boot ───────────────────────────────────────────────────────────── */

/**
 * Initialize the plugin.
 *
 * @return void
 */
function slms_init()
{
    load_plugin_textdomain('simple-lms-bridge', false, dirname(SLMS_PLUGIN_BASENAME) . '/languages');

    CPT::init();
    REST::init();
    MetaBoxes::init();
    Expiration::init();
    Certificates::init();
    Migration::init();
    Relationships::init();
    Analytics::init();
    Upgrade::init();

    // Conditionally boot PMPro integration.
    if (function_exists('pmpro_getMembershipLevelForUser')) {
        PMPro::init();
    }

    UserMeta::init();

    // Admin Menus
    add_action('admin_menu', __NAMESPACE__ . '\\slms_admin_menu');

    // Handle log download action
    add_action( 'admin_post_slms_download_log', array(__NAMESPACE__ . '\\REST', 'handle_log_download') );

    // Handle analytics CSV export.
    add_action( 'admin_post_slms_analytics_export', array(__NAMESPACE__ . '\\REST', 'handle_analytics_export') );
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
        __('Analytics', 'simple-lms-bridge'),
        __('Analytics', 'simple-lms-bridge'),
        'manage_options',
        'slms-analytics',
        function () {
        echo '<div class="wrap slms-admin-wrap tw-preflight"><div id="slms-admin-root"></div></div>';
    }
    );

    add_submenu_page(
        'simple-lms',
        __('Migration Tool', 'simple-lms-bridge'),
        __('Migration Tool', 'simple-lms-bridge'),
        'manage_options',
        'slms-migration',
        function () {
        echo '<div class="wrap slms-admin-wrap tw-preflight"><div id="slms-migration-root"></div></div>';
    }
    );

    add_submenu_page(
        'simple-lms',
        __('Debug Log', 'simple-lms-bridge'),
        __('Debug Log', 'simple-lms-bridge'),
        'manage_options',
        'slms-debug-log',
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
    // Run pending schema steps (creates/updates custom tables). Fresh installs and
    // in-place updates both converge here rather than in activation-only DDL.
    Upgrade::run();
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
    $screen_id = (string)($screen->id ?? '');
    $is_migration_page = ($hook_suffix === 'simple-lms_page_slms-migration' || (isset($_GET['page']) && $_GET['page'] === 'slms-migration'));
    $is_slms_page = ($is_migration_page || strpos($screen_id ?? '', 'slms-students') !== false || strpos($screen_id ?? '', 'slms-migration') !== false || strpos($screen_id ?? '', 'slms-debug-log') !== false || strpos($screen_id ?? '', 'simple-lms') !== false);

    if (!$is_lms_cpt && !$is_slms_page) {
        return;
    }

    $asset_file = SLMS_PLUGIN_DIR . 'build/admin/index.asset.php';

    if (!file_exists($asset_file)) {
        return;
    }

    $asset = require $asset_file;

    if ($is_slms_page) {
        wp_enqueue_style(
            'slms-tailwind',
            SLMS_PLUGIN_URL . 'build/admin/tailwind.css',
            array(),
            $asset['version']
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
        'downloadUrl' => add_query_arg(
            array(
                'action' => 'slms_download_log',
                '_wpnonce' => wp_create_nonce('slms_download_log'),
            ),
            admin_url('admin-post.php')
        ),
        'analyticsExportUrl' => add_query_arg(
            array(
                'action' => 'slms_analytics_export',
                '_wpnonce' => wp_create_nonce('slms_analytics_export'),
            ),
            admin_url('admin-post.php')
        ),
        'studentsUrl' => admin_url('admin.php?page=slms-students'),
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