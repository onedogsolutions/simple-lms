<?php

/**
 * @link              https://wpcomplete.co
 * @since             1.0.0
 * @package           WPComplete
 *
 * @wordpress-plugin
 * Plugin Name:       WPComplete
 * Description:       A WordPress plugin that helps your students keep track of their progress through your course or membership site.
 * Version:           2.9.8
 * Author:            iThemes
 * Author URI:        https://ithemes.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpcomplete
 * Domain Path:       /languages
 * iThemes Package:   wpcomplete
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}

// Fix for 5.3. This variable wasn't added until 5.4.
if ( ! defined( 'JSON_UNESCAPED_UNICODE' ) ) {
  define( 'JSON_UNESCAPED_UNICODE', 256 );
}

// Define some variables we will use throughout the plugin:
define( 'WPCOMPLETE_STORE_URL', 'https://wpcomplete.co' );
define( 'WPCOMPLETE_PRODUCT_NAME', 'WPComplete' );
define( 'WPCOMPLETE_PREFIX', 'wpcomplete' );
define( 'WPCOMPLETE_VERSION', '2.9.8' );
define( 'WPCOMPLETE_IS_ACTIVATED', true );

/**
 * PREMIUM:
 * The code that runs to determine if a premium license is valid.
 */
function wpcomplete_license_is_valid() {
  if ( !wpcomplete_is_production() ) return true;

  $result = get_option( WPCOMPLETE_PREFIX . '_license_status' );

  if ( ( false === $result ) || ( $result === 'valid' ) ) {
    $store_url = WPCOMPLETE_STORE_URL;
    $item_name = WPCOMPLETE_PRODUCT_NAME;
    $license = get_option( WPCOMPLETE_PREFIX . '_license_key' );

    if ( !$license || empty( $license ) )
      return false;

    $api_params = array(
      'edd_action' => 'check_license',
      'license' => $license,
      'item_name' => urlencode( $item_name )
    );

    $response = wp_remote_get( add_query_arg( $api_params, $store_url ), array( 'timeout' => 15, 'sslverify' => false ) );

    if ( is_wp_error( $response ) )
      return false;

    $license_data = json_decode( wp_remote_retrieve_body( $response ) );
    $result = false;

    if ( ( $license_data->license == 'valid') || $license_data->success ) {
      update_option( WPCOMPLETE_PREFIX . '_license_status', $license_data->expires);
      $result = $license_data->expires;
    }
  }

  return ( $result !== false ) && (( $result === 'lifetime') || ( strtotime($result) ));
}

function wpcomplete_is_production() {
  if ( defined( 'WPCOM_IS_VIP_ENV' ) && ( true === WPCOM_IS_VIP_ENV ) ) return true;
  if ( $_SERVER['SERVER_NAME'] == 'localhost' ) return false;
  if ( $_SERVER['SERVER_NAME'] == '127.0.0.1' ) return false;
  if ( substr( $_SERVER['SERVER_NAME'], -4 ) == '.dev' ) return false;
  if ( substr( $_SERVER['SERVER_NAME'], -5 ) == '.test' ) return false;
  if ( substr( $_SERVER['SERVER_NAME'], -6 ) == '.local' ) return false;
  return true;
}

/**
 * The code that checks for plugin updates.
 * Borrowed from: https://github.com/YahnisElsts/plugin-update-checker
 */
if (@include plugin_dir_path( __FILE__ ) . 'includes/plugin-update-checker-3.1.php') {
  $myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://wpcomplete.co/premium.json',
    __FILE__
  );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wpcomplete-activator.php
 */
function activate_wpcomplete() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcomplete-activator.php';
  WPComplete_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wpcomplete-deactivator.php
 */
function deactivate_wpcomplete() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcomplete-deactivator.php';
  WPComplete_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wpcomplete' );
register_deactivation_hook( __FILE__, 'deactivate_wpcomplete' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wpcomplete.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wpcomplete() {

  $plugin = new WPComplete();
  $plugin->run();

}
run_wpcomplete();



if ( ! function_exists( 'ithemes_repository_name_updater_register' ) ) {
	function ithemes_repository_name_updater_register( $updater ) {
		$updater->register( 'wpcomplete', __FILE__ );
	}
	add_action( 'ithemes_updater_register', 'ithemes_repository_name_updater_register' );

	require( __DIR__ . '/lib/updater/load.php' );
}
