<?php

/**
 * @link              https://onedog.solutions
 * @since             1.0.0
 * @package           SimpleLMS
 *
 * @wordpress-plugin
 * Plugin Name:       SimpleLMS
 * Description:       SimpleLMS is a system to connect WordPress Custom Post Types to be a completable set of lessons. A lightweight Learning Management System.
 * Version:           1.0.0
 * Author:            Ryan Waterbury
 * Author URI:        https://onedog.solutions/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       simple-lms
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

// Fix for 5.3. This variable wasn't added until 5.4.
if (!defined('JSON_UNESCAPED_UNICODE')) {
  define('JSON_UNESCAPED_UNICODE', 256);
}

// Define some variables we will use throughout the plugin:
define('SIMPLE_LMS_PRODUCT_NAME', 'SimpleLMS');
define('SIMPLE_LMS_PREFIX', 'simple-lms');
define('SIMPLE_LMS_VERSION', '1.0.0');
define('SIMPLE_LMS_IS_ACTIVATED', true);

function simple_lms_is_production()
{
  if (defined('SimpleLMSOM_IS_VIP_ENV') && (true === SimpleLMSOM_IS_VIP_ENV))
    return true;
  if ($_SERVER['SERVER_NAME'] == 'localhost')
    return false;
  if ($_SERVER['SERVER_NAME'] == '127.0.0.1')
    return false;
  if (substr($_SERVER['SERVER_NAME'], -4) == '.dev')
    return false;
  if (substr($_SERVER['SERVER_NAME'], -5) == '.test')
    return false;
  if (substr($_SERVER['SERVER_NAME'], -6) == '.local')
    return false;
  return true;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-simple-lms-activator.php
 */
function activate_simple_lms()
{
  require_once plugin_dir_path(__FILE__) . 'includes/class-simple-lms-activator.php';
  SimpleLMS_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-simple-lms-deactivator.php
 */
function deactivate_simple_lms()
{
  require_once plugin_dir_path(__FILE__) . 'includes/class-simple-lms-deactivator.php';
  SimpleLMS_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_simple_lms');
register_deactivation_hook(__FILE__, 'deactivate_simple_lms');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-simple-lms.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_simple_lms()
{

  $plugin = new SimpleLMS();
  $plugin->run();

}
run_simple_lms();