<?php

/**
 * @link              https://simplelms.co
 * @since             1.0.0
 * @package           SimpleLMS
 *
 * @wordpress-plugin
 * Plugin Name:       SimpleLMS
 * Description:       A WordPress plugin that helps your students keep track of their progress through your course or membership site.
 * Version:           2.9.8
 * Author:            SimpleLMS
 * Author URI:        https://simplelms.co/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simple-lms
 * Domain Path:       /languages
 * SimpleLMS Package: simple-lms
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
define( 'SIMPLELMS_STORE_URL', 'https://simplelms.co' );
define( 'SIMPLELMS_PRODUCT_NAME', 'SimpleLMS' );
define( 'SIMPLELMS_PREFIX', 'simple-lms' );
define( 'SIMPLELMS_VERSION', '2.9.8' );
define( 'SIMPLELMS_IS_ACTIVATED', true );

/**
 * PREMIUM:
 * The code that runs to determine if a premium license is valid.
 */
function simplelms_license_is_valid() {
  if ( !simplelms_is_production() ) return true;

  $result = get_option( SIMPLELMS_PREFIX . '_license_status' );

  if ( ( false === $result ) || ( $result === 'valid' ) ) {
    $store_url = SIMPLELMS_STORE_URL;
    $item_name = SIMPLELMS_PRODUCT_NAME;
    $license = get_option( SIMPLELMS_PREFIX . '_license_key' );

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
      update_option( SIMPLELMS_PREFIX . '_license_status', $license_data->expires);
      $result = $license_data->expires;
    }
  }

  return ( $result !== false ) && (( $result === 'lifetime') || ( strtotime($result) ));
}

function simplelms_is_production() {
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
    'https://simplelms.co/premium.json',
    __FILE__
  );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-simple-lms-activator.php
 */
function activate_simplelms() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-simple-lms-activator.php';
  SimpleLMS_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-simple-lms-deactivator.php
 */
function deactivate_simplelms() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-simple-lms-deactivator.php';
  SimpleLMS_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_simplelms' );
register_deactivation_hook( __FILE__, 'deactivate_simplelms' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-simple-lms.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_simplelms() {

  $plugin = new SimpleLMS();
  $plugin->run();

}
run_simplelms();



if ( ! function_exists( 'simplelms_repository_name_updater_register' ) ) {
	function simplelms_repository_name_updater_register( $updater ) {
		$updater->register( 'simple-lms', __FILE__ );
	}
	add_action( 'ithemes_updater_register', 'simplelms_repository_name_updater_register' );

	require( __DIR__ . '/lib/updater/load.php' );
}
