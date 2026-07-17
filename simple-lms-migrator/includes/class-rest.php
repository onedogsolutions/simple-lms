<?php
/**
 * REST API endpoints for the SimpleLMS Migrator companion plugin.
 *
 * Registered under the same `simple-lms/v1` route namespace as core so the
 * existing React frontends keep working unmodified.
 *
 * @package SimpleLMS\Migrator
 */

namespace SimpleLMS\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class REST
 *
 * Registers migration/debug-log REST routes under the simple-lms/v1 namespace.
 */
class REST {


	const NAMESPACE = 'simple-lms/v1';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all migrator REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		/* ── Migration ──────────────────────────────────────────────── */

		// Status endpoint
		register_rest_route(
			self::NAMESPACE,
			'/migration/status',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return rest_ensure_response(
						array(
							'progress' => array( 'pending' => \SimpleLMS\Migration::get_pending_migration_count() ),
							'content'  => array( 'pending' => \SimpleLMS\Migration::get_pending_content_count() ),
							'history'  => array( 'pending' => \SimpleLMS\Migration::get_pending_history_count() ),
							'pmpro'    => array( 'pending' => \SimpleLMS\Migration::get_pending_pmpro_count() ),
						)
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// CPT Migration endpoint
		register_rest_route(
			self::NAMESPACE,
			'/migration/cpts',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$limit = $request->get_param( 'limit' ) ?? 5;
					return rest_ensure_response( \SimpleLMS\Migration::migrate_cpt_batch( $limit ) );
				},
				'args'                => array(
					'limit' => array(
						'sanitize_callback' => 'absint',
						'default'           => 5,
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Progress Migration endpoint
		register_rest_route(
			self::NAMESPACE,
			'/migration/progress',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$limit = $request->get_param( 'limit' ) ?? 10;
					return rest_ensure_response( \SimpleLMS\Migration::migrate_progress_batch( $limit ) );
				},
				'args'                => array(
					'limit' => array(
						'sanitize_callback' => 'absint',
						'default'           => 10,
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// History Migration endpoint
		register_rest_route(
			self::NAMESPACE,
			'/migration/history',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$limit = $request->get_param( 'limit' ) ?? 10;
					$offset = $request->get_param( 'offset' ) ?? 0;
					return rest_ensure_response( \SimpleLMS\Migration::migrate_history_batch( $limit, $offset ) );
				},
				'args'                => array(
					'limit'  => array(
						'sanitize_callback' => 'absint',
						'default'           => 10,
					),
					'offset' => array(
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// PMPro Registration Sync endpoint (Phase 2)
		register_rest_route(
			self::NAMESPACE,
			'/migration/pmpro',
			array(
				'methods'             => 'POST',
				'callback'            => function ( $request ) {
					$limit = $request->get_param( 'limit' ) ?? 10;
					$offset = $request->get_param( 'offset' ) ?? 0;
					return rest_ensure_response( \SimpleLMS\Migration::migrate_pmpro_batch( $limit, $offset ) );
				},
				'args'                => array(
					'limit'  => array(
						'sanitize_callback' => 'absint',
						'default'           => 10,
					),
					'offset' => array(
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Reset Phase 2 migration meta so entries can be re-processed.
		register_rest_route(
			self::NAMESPACE,
			'/migration/pmpro/reset',
			array(
				'methods'             => 'POST',
				'callback'            => function () {
					return rest_ensure_response( \SimpleLMS\Migration::reset_pmpro_migration() );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		/* ── Debug Log ──────────────────────────────────────────────── */

		register_rest_route(
			self::NAMESPACE,
			'/debug-log',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return rest_ensure_response(
						array(
							'log' => \SimpleLMS\Migration::read_log( 500 ),
						)
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/debug-log',
			array(
				'methods'             => 'DELETE',
				'callback'            => function () {
					\SimpleLMS\Migration::clear_log();
					return rest_ensure_response( array( 'success' => true ) );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Handle log download via admin-post.php.
	 *
	 * @return void
	 */
	public static function handle_log_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		check_admin_referer( 'slms_download_log' );

		$log_file = \SimpleLMS\Migration::get_log_file_path();

		if ( ! file_exists( $log_file ) || filesize( $log_file ) === 0 ) {
			wp_die( 'No log file found.', 404 );
		}

		$filename = 'slms-migration-' . gmdate( 'Y-m-d_H-i-s' ) . '.log';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $log_file ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		readfile( $log_file );
		exit;
	}
}
