<?php

/**
 * Fired during plugin activation
 *
 * @link       https://wpcomplete.co
 * @since      1.0.0
 *
 * @package    WPComplete
 * @subpackage wpcomplete/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WPComplete
 * @subpackage wpcomplete/includes
 * @author     Zack Gilbert <zack@zackgilbert.com>
 */
class WPComplete_Activator {

  /**
   * Short Description. (use period)
   *
   * Long Description.
   *
   * @since    1.0.0
   */
  public static function activate() {
    // if install is using OptimizePress, the auto append probably won't work correctly...
    if ( is_plugin_active('optimizePressHelperTools/optimizepress-helper.php') ) {
      if ( ! get_option( 'wpcomplete_auto_append' ) ) {
        update_option( 'wpcomplete_auto_append', 'false' );
      }
    }

  }

}
