<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://wpcomplete.co
 * @since      1.0.0
 *
 * @package    WPComplete
 * @subpackage wpcomplete/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    WPComplete
 * @subpackage wpcomplete/includes
 * @author     Zack Gilbert <zack@zackgilbert.com>
 */
class WPComplete_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		global $myUpdateChecker;
		
		if ($myUpdateChecker) $myUpdateChecker->clearCachedVersion();
	}

}
