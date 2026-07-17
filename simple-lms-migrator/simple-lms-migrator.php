<?php
/**
 * Plugin Name: SimpleLMS Migrator
 * Plugin URI:  https://onedog.solutions
 * Description: One-time data migration tooling for SimpleLMS Bridge (WP Complete / Pods / GF→PMPro imports). Deactivate and delete after go-live.
 * Version:     1.0.0
 * Author:      Ryan D. Waterbury
 * Author URI:  https://onedog.solutions
 * Text Domain: simple-lms-migrator
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: simple-lms-bridge
 *
 * @package SimpleLMS\Migrator
 */

namespace SimpleLMS\Migrator;

if (!defined('ABSPATH')) {
    exit;
}

/* ─── Constants ─────────────────────────────────────────────────────── */
define('SLMS_MIGRATOR_VERSION', '1.0.0');
define('SLMS_MIGRATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SLMS_MIGRATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Bail with an admin notice if the core SimpleLMS Bridge plugin isn't active.
 *
 * @return void
 */
function slms_migrator_init()
{
    if (!class_exists('\SimpleLMS\Relationships')) {
        add_action('admin_notices', __NAMESPACE__ . '\\slms_migrator_missing_core_notice');
        return;
    }

    require_once SLMS_MIGRATOR_PLUGIN_DIR . 'includes/class-migration.php';
    require_once SLMS_MIGRATOR_PLUGIN_DIR . 'includes/class-rest.php';

    \SimpleLMS\Migration::init();
    REST::init();

    add_action('admin_menu', __NAMESPACE__ . '\\slms_migrator_admin_menu', 20);
    add_action('admin_post_slms_download_log', array(__NAMESPACE__ . '\\REST', 'handle_log_download'));
}
add_action('init', __NAMESPACE__ . '\\slms_migrator_init');

/**
 * Admin notice shown when SimpleLMS Bridge (core) is not active.
 *
 * @return void
 */
function slms_migrator_missing_core_notice()
{
    echo '<div class="notice notice-error"><p>' .
        esc_html__('SimpleLMS Migrator requires the SimpleLMS Bridge plugin to be active.', 'simple-lms-migrator') .
        '</p></div>';
}

/**
 * Register the Migration Tool and Debug Log submenus under the SimpleLMS
 * top-level menu registered by the core plugin.
 *
 * @return void
 */
function slms_migrator_admin_menu()
{
    global $menu;

    $parent_exists = false;
    foreach ((array) $menu as $item) {
        if (isset($item[2]) && 'simple-lms' === $item[2]) {
            $parent_exists = true;
            break;
        }
    }

    if (!$parent_exists) {
        return;
    }

    add_submenu_page(
        'simple-lms',
        __('Migration Tool', 'simple-lms-migrator'),
        __('Migration Tool', 'simple-lms-migrator'),
        'manage_options',
        'slms-migration',
        function () {
            echo '<div class="wrap slms-admin-wrap tw-preflight"><div id="slms-migration-root"></div></div>';
        }
    );

    add_submenu_page(
        'simple-lms',
        __('Debug Log', 'simple-lms-migrator'),
        __('Debug Log', 'simple-lms-migrator'),
        'manage_options',
        'slms-debug-log',
        function () {
            echo '<div class="wrap slms-admin-wrap tw-preflight"><div id="slms-debug-log-root"></div></div>';
        }
    );
}

/* ─── Admin Assets ───────────────────────────────────────────────────── */

/**
 * Enqueue the React admin bundle on the Migration Tool / Debug Log screens.
 *
 * @return void
 */
function slms_migrator_enqueue_admin_assets()
{
    $screen = get_current_screen();

    if (!$screen) {
        return;
    }

    $screen_id = (string) ($screen->id ?? '');
    $is_migrator_page = (strpos($screen_id, 'slms-migration') !== false || strpos($screen_id, 'slms-debug-log') !== false);

    if (!$is_migrator_page) {
        return;
    }

    $asset_file = SLMS_MIGRATOR_PLUGIN_DIR . 'build/admin/index.asset.php';

    if (!file_exists($asset_file)) {
        return;
    }

    $asset = require $asset_file;

    wp_enqueue_style(
        'slms-migrator-tailwind',
        SLMS_MIGRATOR_PLUGIN_URL . 'build/admin/tailwind.css',
        array(),
        $asset['version']
    );

    wp_enqueue_script(
        'slms-migrator-admin',
        SLMS_MIGRATOR_PLUGIN_URL . 'build/admin/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_enqueue_style(
        'slms-migrator-admin',
        SLMS_MIGRATOR_PLUGIN_URL . 'build/admin/index.css',
        array('wp-components'),
        $asset['version']
    );

    wp_localize_script('slms-migrator-admin', 'slmsMigrator', array(
        'restUrl' => esc_url_raw(rest_url('simple-lms/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'page' => isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '',
        'downloadUrl' => add_query_arg(
            array(
                'action' => 'slms_download_log',
                '_wpnonce' => wp_create_nonce('slms_download_log'),
            ),
            admin_url('admin-post.php')
        ),
    ));
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\slms_migrator_enqueue_admin_assets');
