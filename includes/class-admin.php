<?php
/**
 * React admin shell.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the scoped SimpleLMS admin page.
 */
final class Admin {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'SimpleLMS', 'simple-lms' ),
			__( 'SimpleLMS', 'simple-lms' ),
			'manage_options',
			'simple-lms',
			array( self::class, 'render_page' ),
			'dashicons-welcome-learn-more',
			58
		);
	}

	/**
	 * Enqueue the React bundle only on the SimpleLMS screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_simple-lms' !== $hook_suffix ) {
			return;
		}

		$asset = SIMPLE_LMS_DIR . 'build/admin/admin.js';
		if ( ! file_exists( $asset ) ) {
			return;
		}
		$style_asset = SIMPLE_LMS_DIR . 'build/admin/admin.css';
		$style_ver   = file_exists( $style_asset ) ? (string) filemtime( $style_asset ) : SIMPLE_LMS_VERSION;

		wp_enqueue_script(
			'simple-lms-admin',
			SIMPLE_LMS_URL . 'build/admin/admin.js',
			array(),
			(string) filemtime( $asset ),
			true
		);
		wp_localize_script(
			'simple-lms-admin',
			'simpleLMSAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( Rest_API::NAMESPACE ) ),
				'cssUrl'    => esc_url_raw( SIMPLE_LMS_URL . 'build/admin/admin.css?ver=' . $style_ver ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'courseType' => Post_Types::COURSE,
				'lessonType' => Post_Types::LESSON,
			)
		);
	}

	/**
	 * Render the React mount point.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SimpleLMS', 'simple-lms' ); ?></h1>
			<div id="simple-lms-admin-root"></div>
			<noscript><?php esc_html_e( 'JavaScript is required to manage SimpleLMS settings.', 'simple-lms' ); ?></noscript>
		</div>
		<?php
	}
}
