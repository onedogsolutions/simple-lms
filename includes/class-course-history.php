<?php
/**
 * Compliance handling for SimpleLMS historical records.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CourseHistory
 *
 * Manages the custom table for 9-year compliance retention.
 */
class CourseHistory {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private static $table_name;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'slms_course_history';
	}

	/**
	 * Create the compliance table using dbDelta.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		self::init();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = 'CREATE TABLE ' . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_name varchar(255) NOT NULL,
            completed_date datetime NOT NULL,
            form_id bigint(20) DEFAULT NULL,
            gf_entry_id bigint(20) DEFAULT NULL,
            cert_uuid varchar(36) DEFAULT NULL,
            cert_data longtext DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY gf_entry_id (gf_entry_id),
            KEY form_id (form_id),
            UNIQUE KEY cert_uuid (cert_uuid)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add the cert_uuid column + unique key and backfill UUIDs for every
	 * pre-existing row so legacy certificates remain verifiable by URL.
	 *
	 * Idempotent — guarded ALTERs make it safe to run repeatedly. Invoked as
	 * Upgrade step 2 (see class-upgrade.php); versioning is owned by the
	 * Upgrade framework, not this method.
	 *
	 * @return void
	 */
	public static function add_cert_uuid_column() {
		global $wpdb;
		self::init();
		$table = self::$table_name;

		// Bail if the table doesn't exist yet (create_table() handles fresh installs).
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			self::create_table();
		}

		// 1. Add the cert_uuid column if missing.
		$has_col = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW COLUMNS FROM `' . $table . '` LIKE %s',
				'cert_uuid'
			)
		);
		if ( ! $has_col ) {
			$wpdb->query( 'ALTER TABLE `' . $table . '` ADD COLUMN cert_uuid varchar(36) DEFAULT NULL' );
		}

		// 2. Backfill UUIDs for any row missing one.
		$ids = $wpdb->get_col(
			'SELECT id FROM `' . $table . "` WHERE cert_uuid IS NULL OR cert_uuid = ''"
		);
		foreach ( (array) $ids as $id ) {
			$wpdb->update(
				$table,
				array( 'cert_uuid' => wp_generate_uuid4() ),
				array( 'id' => absint( $id ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// 3. Add the unique key if it isn't there yet.
		$has_index = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW INDEX FROM `' . $table . '` WHERE Key_name = %s',
				'cert_uuid'
			)
		);
		if ( ! $has_index ) {
			// A duplicate key here is non-fatal.
			$wpdb->query( 'ALTER TABLE `' . $table . '` ADD UNIQUE KEY cert_uuid (cert_uuid)' );
		}
	}

	/**
	 * Insert a record into the course history.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $course_name Course Title.
	 * @param string $date        ISO date or Y-m-d H:i:s.
	 * @param int    $entry_id    Gravity Forms entry ID if applicable.
	 * @param int    $form_id     Gravity Forms form ID if applicable.
	 * @param array  $metadata    Any extra metadata.
	 * @param string $cert_uuid   Native certificate UUID if applicable.
	 * @return int|bool Row ID or false on failure.
	 */
	public static function insert( $user_id, $course_name, $date, $entry_id = null, $form_id = null, $metadata = array(), $cert_uuid = null ) {
		global $wpdb;
		self::init();

		// Avoid duplicate entries for the same user + entry_id if provided
		if ( $entry_id ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . self::$table_name . ' WHERE user_id = %d AND gf_entry_id = %d',
					$user_id,
					$entry_id
				)
			);
			if ( $exists ) {
				return (int) $exists;
			}
		}

		$result = $wpdb->insert(
			self::$table_name,
			array(
				'user_id'        => absint( $user_id ),
				'course_name'    => sanitize_text_field( $course_name ),
				'completed_date' => current_time( 'mysql', strtotime( $date ) ),
				'form_id'        => $form_id ? absint( $form_id ) : null,
				'gf_entry_id'    => $entry_id ? absint( $entry_id ) : null,
				'cert_uuid'      => $cert_uuid ? sanitize_text_field( $cert_uuid ) : null,
				'cert_data'      => ! empty( $metadata ) ? maybe_serialize( $metadata ) : null,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Fetch a single history row by its certificate UUID.
	 *
	 * @param string $uuid Certificate UUID.
	 * @return \stdClass|null Row object or null if not found.
	 */
	public static function get_by_uuid( string $uuid ) {
		global $wpdb;
		self::init();

		if ( '' === $uuid ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::$table_name . ' WHERE cert_uuid = %s LIMIT 1',
				$uuid
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Get history for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_for_user( int $user_id ): array {
		global $wpdb;
		self::init();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::$table_name . ' WHERE user_id = %d ORDER BY completed_date DESC',
				$user_id
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Query rows for a compliance export, filtered by course and/or date range.
	 *
	 * @param array $args {
	 *   @type string $course Course name filter (LIKE), optional.
	 *   @type string $from   Start date (Y-m-d), optional.
	 *   @type string $to     End date (Y-m-d), optional.
	 * }
	 * @return array Row objects.
	 */
	public static function query_for_export( array $args = array() ): array {
		global $wpdb;
		self::init();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['course'] ) ) {
			$where[]  = 'course_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['course'] ) . '%';
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'completed_date >= %s';
			$params[] = $args['from'] . ' 00:00:00';
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'completed_date <= %s';
			$params[] = $args['to'] . ' 23:59:59';
		}

		$sql = 'SELECT * FROM ' . self::$table_name . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY completed_date DESC';
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Purge corrupted records from the history table.
	 *
	 * @return int Number of deleted rows.
	 */
	public static function purge_corrupted_records(): int {
		global $wpdb;
		self::init();

		return (int) $wpdb->query(
			'DELETE FROM ' . self::$table_name . ' WHERE form_id IS NULL OR form_id = 0 OR gf_entry_id IS NULL OR gf_entry_id = 0'
		);
	}

	/**
	 * Backfill form_id for rows that have gf_entry_id but NULL form_id.
	 *
	 * @return array { updated: int, skipped: int, failed: int }
	 */
	public static function repair_form_ids(): array {
		global $wpdb;
		self::init();

		$rows = $wpdb->get_results(
			'SELECT id, gf_entry_id FROM ' . self::$table_name .
			' WHERE gf_entry_id IS NOT NULL AND gf_entry_id > 0 AND (form_id IS NULL OR form_id = 0)'
		);

		$updated = 0;
		$skipped = 0;
		$failed  = 0;

		if ( empty( $rows ) || ! class_exists( 'GFAPI' ) ) {
			return compact( 'updated', 'skipped', 'failed' );
		}

		foreach ( $rows as $row ) {
			$entry = \GFAPI::get_entry( (int) $row->gf_entry_id );

			if ( is_wp_error( $entry ) || empty( $entry['form_id'] ) ) {
				++$failed;
				continue;
			}
			$result = $wpdb->update(
				self::$table_name,
				array( 'form_id' => absint( $entry['form_id'] ) ),
				array( 'id' => absint( $row->id ) ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false === $result ) {
				++$failed;
			} else {
				++$updated;
			}
		}
		return compact( 'updated', 'skipped', 'failed' );
	}
}
