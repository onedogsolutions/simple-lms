<?php

/**
 * Fired during plugin activation
 *
 * @link       https://onedog.solutions
 * @since      1.0.0
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    SimpleLMS
 * @subpackage simple-lms/includes
 * @author     Ryan Waterbury <ryan.waterbury@onedog.solutions>
 */
class SimpleLMS_Activator
{

  /**
   * Short Description. (use period)
   *
   * Long Description.
   *
   * @since    1.0.0
   */
  public static function activate()
  {
    // if install is using OptimizePress, the auto append probably won't work correctly...
    if (is_plugin_active('optimizePressHelperTools/optimizepress-helper.php')) {
      if (!get_option('simple_lms_auto_append')) {
        update_option('simple_lms_auto_append', 'false');
      }
    }

    self::migrate_options();
  }

  /**
   * Migrate options from wpcomplete to simple_lms
   */
  private static function migrate_options()
  {
    // Check if we need to migrate
    $old_version = get_option('wpcomplete_version');
    if ($old_version && !get_option('simple_lms_version_migrated')) {

      global $wpdb;

      // Migrate Options
      // We want to rename all options starting with 'wpcomplete_' to 'simple_lms_'
      $options = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wpcomplete_%'");

      foreach ($options as $option) {
        $old_name = $option->option_name;
        $new_name = str_replace('wpcomplete_', 'simple_lms_', $old_name);

        // Don't overwrite if exists (unless we want to force?)
        if (!get_option($new_name)) {
          // Get value using SQL to avoid serialization issues requiring unserialize/serialize loop if not needed, 
          // but get_option is safer for handling serialized data.
          $val = get_option($old_name);
          update_option($new_name, $val);
        // Optionally delete old option? Let's keep it for safety for now, or delete it?
        // delete_option( $old_name ); 
        }
      }

      // Mark migration as complete
      update_option('simple_lms_version_migrated', 'true');
    }
  }

}