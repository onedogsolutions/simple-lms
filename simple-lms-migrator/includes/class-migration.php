<?php
/**
 * Migration utility for SimpleLMS.
 *
 * Migrates lesson completion data from WP Complete to SimpleLMS.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migration
 *
 * Handles data conversion from legacy formats.
 */
class Migration {


	/**
	 * In-memory log buffer for the current migration run.
	 *
	 * @var array
	 */
	private static $log = array();

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		// Add migration action handler.
		add_action( 'admin_post_slms_migrate_wpc', array( __CLASS__, 'run_wpc_migration' ) );
	}

	/**
	 * Append a message to the in-memory log, WP debug log, and the plugin's persistent log file.
	 *
	 * @param string $message Log message.
	 * @param string $level   One of 'info', 'warn', 'error', 'debug'.
	 * @return void
	 */
	private static function log( $message, $level = 'info' ) {
		$timestamp   = current_time( 'Y-m-d H:i:s' );
		$entry       = array(
			'time'  => current_time( 'H:i:s' ),
			'level' => $level,
			'msg'   => $message,
		);
		self::$log[] = $entry;
		error_log( '[SimpleLMS][' . strtoupper( $level ) . '] ' . $message );

		// Write to persistent plugin log file.
		$log_file = self::get_log_file_path();
		$line     = '[' . $timestamp . '] [' . strtoupper( $level ) . '] ' . $message . PHP_EOL;
		@file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get the path to the plugin's persistent log file.
	 *
	 * @return string
	 */
	public static function get_log_file_path() {
		$upload_dir = \wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/slms-logs';
		if ( ! is_dir( $log_dir ) ) {
			\wp_mkdir_p( $log_dir );
			// Protect log directory with .htaccess.
			@file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
			@file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
		}
		return $log_dir . '/migration.log';
	}

	/**
	 * Read the last N lines from the persistent log file.
	 *
	 * @param int $lines Number of lines to read.
	 * @return string
	 */
	public static function read_log( $lines = 200 ) {
		$log_file = self::get_log_file_path();
		if ( ! file_exists( $log_file ) ) {
			return '';
		}

		$content = file_get_contents( $log_file );
		if ( empty( $content ) ) {
			return '';
		}

		$all_lines = explode( PHP_EOL, trim( $content ) );
		$total     = count( $all_lines );

		if ( $total <= $lines ) {
			return implode( PHP_EOL, $all_lines );
		}

		return implode( PHP_EOL, array_slice( $all_lines, $total - $lines ) );
	}

	/**
	 * Clear the persistent log file.
	 *
	 * @return bool
	 */
	public static function clear_log() {
		$log_file = self::get_log_file_path();
		return @file_put_contents( $log_file, '' ) !== false;
	}

	/**
	 * Return and reset the in-memory log buffer.
	 *
	 * @return array
	 */
	public static function flush_log() {
		$entries   = self::$log;
		self::$log = array();
		return $entries;
	}


	/**
	 * Run the student progress migration from WP Complete.
	 */
	public static function run_wpc_migration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'simple-lms-bridge' ) );
		}

		$result = self::migrate_progress_batch( 100 ); // Higher limit for manual trigger.

		wp_redirect( admin_url( 'admin.php?page=slms-students&migration_complete=' . $result['count'] ) );
		exit;
	}

	/**
	 * Phase 1: CPT Migration.
	 * Imports legacy Course CPT and their child lessons into the new schema.
	 *
	 * @param int $limit Max courses to migrate in this batch.
	 * @return array Result summary.
	 */
	public static function migrate_cpt_batch( $limit = 5 ) {
		$limit = absint( $limit );
		self::log( 'Phase 1: Starting content migration (limit=' . $limit . ').' );
		$start_time = microtime( true );

		$legacy_courses = get_posts(
			array(
				'post_type'   => 'course',
				'post_status' => 'publish',
				'post_parent' => 0, // Only parents are "Courses"
				'numberposts' => $limit,
				'meta_query'  => array(
					array(
						'key'     => '_slms_migrated',
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		self::log( 'Found ' . count( $legacy_courses ) . ' unmigrated legacy courses.' );
		$count = 0;

		foreach ( $legacy_courses as $legacy_course ) {
			self::log( 'Processing legacy course ID ' . $legacy_course->ID . ' "' . $legacy_course->post_title . '".' );

			// 1. Import or find current slms_course
			$new_course_id = self::import_course( $legacy_course );

			if ( ! $new_course_id ) {
				self::log( 'SKIP: Could not import/find course for legacy ID ' . $legacy_course->ID . '.', 'warn' );
				continue;
			}

			self::log( 'Mapped legacy course ' . $legacy_course->ID . ' -> new course ' . $new_course_id . '.' );

			// Retrieve the course group taxonomy from the legacy course
			$terms = wp_get_post_terms( $legacy_course->ID, 'slms_course_cat', array( 'fields' => 'ids' ) );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				wp_set_post_terms( $new_course_id, $terms, 'slms_course_cat' );
			}

			// 2. Identify child posts (lessons)
			$legacy_lessons = get_posts(
				array(
					'post_type'   => 'course',
					'post_status' => 'publish',
					'post_parent' => $legacy_course->ID,
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
					'numberposts' => -1,
				)
			);

			$new_lesson_ids = array();

			if ( empty( $legacy_lessons ) ) {
				self::log( 'No child lessons found for legacy course ' . $legacy_course->ID . '; importing course content as lesson.', 'debug' );
				$new_lesson_id = self::import_lesson( $legacy_course );
				if ( $new_lesson_id ) {
					$new_lesson_ids[] = (int) $new_lesson_id;
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						wp_set_post_terms( $new_lesson_id, $terms, 'slms_course_cat', true );
					}
				}
			} else {
				self::log( 'Found ' . count( $legacy_lessons ) . ' child lessons for legacy course ' . $legacy_course->ID . '.' );
				foreach ( $legacy_lessons as $legacy_lesson ) {
					$new_lesson_id = self::import_lesson( $legacy_lesson );
					if ( $new_lesson_id ) {
						$new_lesson_ids[] = (int) $new_lesson_id;
						if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
							wp_set_post_terms( $new_lesson_id, $terms, 'slms_course_cat', true );
						}
					} else {
						self::log( 'SKIP: Could not import lesson for legacy ID ' . $legacy_lesson->ID . '.', 'warn' );
					}
				}
			}

			// 4. Link via Many-to-Many bridge
			if ( ! empty( $new_lesson_ids ) ) {
				Relationships::set_lessons_for_course( $new_course_id, $new_lesson_ids );
				self::log( 'Linked ' . count( $new_lesson_ids ) . ' lessons to course ' . $new_course_id . '.' );
			}

			// 5. Create a PMPro membership level for this course.
			$level_id = self::create_pmpro_level_for_course( $new_course_id, $legacy_course );
			if ( $level_id ) {
				self::log( 'PMPro level ' . $level_id . ' mapped to course ' . $new_course_id . '.' );
			}

			// Mark legacy course as migrated
			update_post_meta( $legacy_course->ID, '_slms_migrated', time() );
			++$count;
		}

		$duration = round( microtime( true ) - $start_time, 2 );
		self::log( 'Phase 1 complete: processed=' . $count . ', duration=' . $duration . 's.' );

		$pending = self::get_pending_content_count();

		return array(
			'processed' => $count,
			'pending'   => $pending,
			'total'     => $count + $pending,
			'duration'  => $duration,
			'success'   => true,
			'status'    => ( $pending === 0 || $count === 0 ) ? 'complete' : 'processing',
			'log'       => self::flush_log(),
		);
	}

	/**
	 * Alias for Phase 3 (progress) migration to maintain compatibility with legacy calls.
	 */
	public static function migrate_batch( $limit = 10 ) {
		return self::migrate_progress_batch( $limit );
	}

	/**
	 * Phase 3: Student Progress (WPComplete) Migration.
	 *
	 * @param int $limit Max users to migrate in this batch.
	 * @return array Result summary.
	 */
	public static function migrate_progress_batch( $limit = 10 ) {
		$limit = max( 1, min( absint( $limit ), 100 ) );
		self::log( 'Phase 3: Starting student progress migration (limit=' . $limit . ').' );
		$start_time = microtime( true );

		global $wpdb;

		$sql = "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%'";
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		$user_ids = $wpdb->get_col( $sql );
		self::log( 'Found ' . count( $user_ids ) . ' users with WPComplete meta.' );

		$count = 0;
		$stats = array(
			'lessons_mapped'               => 0,
			'lessons_skipped_no_match'     => 0,
			'lessons_skipped_no_course'    => 0,
			'lessons_skipped_not_enrolled' => 0,
		);

		foreach ( $user_ids as $user_id ) {
			$user_id    = (int) $user_id;
			$user       = get_userdata( $user_id );
			$user_label = $user ? $user->user_email : 'UID:' . $user_id;

			$wpc_metas = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND (meta_key = 'wpcomplete' OR meta_key LIKE 'wpcomplete_%%')",
					$user_id
				)
			);

			if ( empty( $wpc_metas ) ) {
				self::log( 'User ' . $user_label . ': no WPComplete meta rows found, skipping.', 'debug' );
				continue;
			}

			self::log( 'User ' . $user_label . ': processing ' . count( $wpc_metas ) . ' meta row(s).' );

			$current_progress = get_user_meta( $user_id, '_lms_progress', true );
			if ( ! is_array( $current_progress ) ) {
				$current_progress = array();
			}

			// Pre-fetch enrollment and purchase data for ownership validation.
			$user_courses     = Relationships::get_courses_for_user( $user_id );
			$enrolled_ids     = array_map(
				function ( $c ) {
					return (int) $c->id;
				},
				$user_courses
			);
			$user_gf_products = self::get_user_gf_products( $user_id );

			foreach ( $wpc_metas as $meta ) {
				$key   = $meta->meta_key;
				$value = $meta->meta_value;

				// Try JSON first — WPComplete stores data as JSON.
				$data        = json_decode( $value ?? '', true );
				$format_used = 'json';

				if ( $data === null ) {
					// Fallback to maybe_unserialize for older formats.
					$data        = maybe_unserialize( $value ?? '' );
					$format_used = 'serialized';
				}

				// Handle WPComplete booleans/strings/integers.
				if ( ! is_array( $data ) && ! empty( $data ) ) {
					// If it's a valid date string, use that. Otherwise, fallback to 'completed' => time().
					$parsed_ts = is_string( $data ) ? strtotime( $data ) : false;
					$data      = array( 'completed' => ( $parsed_ts ?: time() ) );
				}

				if ( ! is_array( $data ) ) {
					self::log( 'User ' . $user_label . ': could not parse value for key "' . $key . '" as JSON or serialized. Archiving and skipping.', 'warn' );
					// Archive unparseable data to prevent infinite loops while preserving history.
					update_user_meta( $user_id, '_failed_migration_' . $key, $value );
					delete_user_meta( $user_id, $key );
					continue;
				}

				if ( $key === 'wpcomplete' && is_array( $data ) ) {
					$entry_count = count( $data );
					self::log( 'User ' . $user_label . ': bulk key "wpcomplete" has ' . $entry_count . ' entries (' . $format_used . ').', 'debug' );

					foreach ( $data as $post_key => $post_data ) {
						if ( $post_key === '0-site' || strpos( ( $post_key ?? '' ), '0-site' ) !== false ) {
							continue;
						}

						$legacy_lesson_id = self::extract_post_id( $post_key );
						if ( ! $legacy_lesson_id ) {
							self::log( 'User ' . $user_label . ': could not extract post ID from key "' . $post_key . '".', 'warn' );
							continue;
						}

						self::process_legacy_lesson_progress( $user_id, $legacy_lesson_id, $post_data, $current_progress, $stats, $user_label, $enrolled_ids, $user_gf_products );
					}
				} else {
					if ( strpos( ( $key ?? '' ), 'wpcomplete_0-site' ) !== false ) {
						delete_user_meta( $user_id, $key );
						continue;
					}

					$legacy_lesson_id = (int) preg_replace( '/[^0-9]/', '', str_replace( 'wpcomplete_', '', $key ?? '' ) );
					if ( ! $legacy_lesson_id ) {
						self::log( 'User ' . $user_label . ': could not parse lesson ID from meta key "' . $key . '".', 'warn' );
						delete_user_meta( $user_id, $key );
						continue;
					}

					self::process_legacy_lesson_progress( $user_id, $legacy_lesson_id, $data, $current_progress, $stats, $user_label, $enrolled_ids, $user_gf_products );
				}

				delete_user_meta( $user_id, $key );
			}

			update_user_meta( $user_id, '_lms_progress', $current_progress );

			$course_count = count( $current_progress );
			$lesson_count = 0;
			foreach ( $current_progress as $lessons ) {
				$lesson_count += is_array( $lessons ) ? count( $lessons ) : 0;
			}
			self::log( 'User ' . $user_label . ': saved progress — ' . $course_count . ' course(s), ' . $lesson_count . ' lesson(s) total.' );
			++$count;
		}

		$duration = round( microtime( true ) - $start_time, 2 );
		self::log(
			sprintf(
				'Phase 3 complete: users=%d, mapped=%d, skipped_no_match=%d, skipped_no_course=%d, skipped_not_enrolled=%d, duration=%ss.',
				$count,
				$stats['lessons_mapped'],
				$stats['lessons_skipped_no_match'],
				$stats['lessons_skipped_no_course'],
				$stats['lessons_skipped_not_enrolled'],
				$duration
			)
		);

		$pending = self::get_pending_migration_count();

		return array(
			'processed' => $count,
			'pending'   => $pending,
			'total'     => $count + $pending,
			'duration'  => $duration,
			'success'   => true,
			'status'    => ( $pending === 0 || $count === 0 ) ? 'complete' : 'processing',
			'stats'     => $stats,
			'log'       => self::flush_log(),
		);
	}

	/**
	 * Resolve the origin course for a legacy lesson using the post_parent hierarchy.
	 *
	 * In the legacy Pods CPT, lessons were children of courses (post_parent).
	 * This method finds the new slms_course that maps to the legacy parent.
	 *
	 * @param int $legacy_lesson_id The legacy lesson post ID.
	 * @return int|null The slms_course ID if resolved, or null.
	 */
	private static function resolve_origin_course( $legacy_lesson_id ) {
		$legacy_post = get_post( $legacy_lesson_id );
		if ( ! $legacy_post ) {
			return null;
		}

		$parent_id = \wp_get_post_parent_id( $legacy_post );
		if ( ! $parent_id ) {
			return null;
		}

		// Check if the parent itself is an slms_course.
		$parent_post = get_post( $parent_id );
		if ( $parent_post && $parent_post->post_type === 'slms_course' ) {
			return $parent_id;
		}

		// Look up slms_course by _legacy_id meta matching the parent.
		$query = new \WP_Query(
			array(
				'post_type'      => 'slms_course',
				'meta_key'       => '_legacy_id',
				'meta_value'     => $parent_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}

		return null;
	}

	/**
	 * Check if a user has ownership/access evidence for a specific course.
	 *
	 * Uses a three-tier check:
	 *   1. Enrollment table (wp_slms_user_course)
	 *   2. Active PMPro membership level
	 *   3. GF Form 2 purchase history (field 20 legacy course IDs → new course IDs)
	 *
	 * @param int   $user_id          User ID.
	 * @param int   $course_id        Course post ID (new slms_course).
	 * @param array $enrolled_ids     Pre-fetched array of course IDs the user is enrolled in.
	 * @param array $user_gf_products Pre-fetched array of new slms_course IDs from GF purchases.
	 * @return bool
	 */
	private static function user_owns_course( $user_id, $course_id, $enrolled_ids, $user_gf_products ) {
		// Check A: Enrollment table.
		if ( in_array( $course_id, $enrolled_ids, true ) ) {
			return true;
		}

		// Check B: PMPro active membership level.
		if ( class_exists( __NAMESPACE__ . '\\PMPro' ) && PMPro::has_course_access( $user_id, $course_id ) ) {
			return true;
		}

		// Check C: GF Form 2 purchase history — course ID match.
		if ( ! empty( $user_gf_products ) && in_array( $course_id, $user_gf_products, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Pre-fetch GF Form 2 course purchases for a user (for ownership validation).
	 *
	 * Reads checkbox field 20 ("Select Your Courses") which contains legacy
	 * course post IDs. Maps those to new slms_course IDs via _legacy_id.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of new slms_course post IDs the user has purchased.
	 */
	private static function get_user_gf_products( $user_id ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$gf_form_id      = 2;
		$course_field_id = 20;

		$search_criteria = array(
			'status'        => 'active',
			'field_filters' => array(
				array(
					'key'   => 'created_by',
					'value' => $user_id,
				),
			),
		);

		$entries = \GFAPI::get_entries( $gf_form_id, $search_criteria );
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$course_ids = self::extract_legacy_course_ids( $entries, $course_field_id );

		if ( empty( $course_ids ) ) {
			return array();
		}

		// Map legacy course IDs to new slms_course IDs.
		$new_course_ids = array();
		foreach ( array_unique( $course_ids ) as $legacy_id ) {
			$query = new \WP_Query(
				array(
					'post_type'      => 'slms_course',
					'meta_key'       => '_legacy_id',
					'meta_value'     => $legacy_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( $query->have_posts() ) {
				$new_course_ids[] = (int) $query->posts[0];
			}
		}

		return array_unique( $new_course_ids );
	}

	/**
	 * Parse and sanitize legacy course IDs from an array of Gravity Forms entries.
	 * Splits mistakenly concatenated IDs (e.g., 546630) to retrieve valid course assignments.
	 *
	 * @param array $entries
	 * @param int   $course_field_id
	 * @return array array of unique int course IDs
	 */
	private static function extract_legacy_course_ids( $entries, $course_field_id ) {
		$course_ids = array();

		foreach ( $entries as $entry ) {
			// GF checkboxes store each choice in sub-fields: 20.1, 20.2, etc.
			for ( $i = 1; $i <= 20; $i++ ) {
				$val = rgar( $entry, $course_field_id . '.' . $i );
				if ( ! empty( $val ) && is_numeric( $val ) ) {
					$raw_id = (string) $val;
					$len    = strlen( $raw_id );

					// Detect mistakenly concatenated IDs (e.g., from a badly configured form export/import)
					if ( $len === 6 ) {
						// 3-digit + 3-digit
						$course_ids[] = (int) substr( $raw_id, 0, 3 );
						$course_ids[] = (int) substr( $raw_id, 3 );
					} elseif ( $len === 5 ) {
						// 2-digit + 3-digit OR 3-digit + 2-digit
						$course_ids[] = (int) substr( $raw_id, 0, 2 );
						$course_ids[] = (int) substr( $raw_id, 2 );
						$course_ids[] = (int) substr( $raw_id, 0, 3 );
						$course_ids[] = (int) substr( $raw_id, 3 );
					} else {
						// Standard valid ID
						$course_ids[] = (int) $raw_id;
					}
				}
			}
		}

		return array_values( array_unique( $course_ids ) );
	}

	/**
	 * Helper to process legacy lesson completions.
	 *
	 * Validates course ownership before recording progress for shared lessons.
	 * Uses a tiered approach: legacy parent match, then enrollment/purchase checks.
	 */
	private static function process_legacy_lesson_progress( $user_id, $legacy_lesson_id, $data, &$current_progress, &$stats = null, $user_label = '', $enrolled_ids = array(), $user_gf_products = array() ) {
		// 1. Look up new lesson by _legacy_id meta.
		$new_lesson_query = new \WP_Query(
			array(
				'post_type'      => 'slms_lesson',
				'meta_key'       => '_legacy_id',
				'meta_value'     => $legacy_lesson_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		// 2. Fallback: try matching by post ID directly (legacy ID IS the slms_lesson ID).
		if ( ! $new_lesson_query->have_posts() ) {
			$direct_post = get_post( $legacy_lesson_id );
			if ( $direct_post instanceof \WP_Post && $direct_post->post_type === 'slms_lesson' && $direct_post->post_status === 'publish' ) {
				$new_lesson_id = $direct_post->ID;
				self::log( $user_label . ': legacy lesson ' . $legacy_lesson_id . ' matched directly as slms_lesson ' . $new_lesson_id . '.', 'debug' );
			} else {
				// 3. Fallback: try matching by title.
				$legacy_post = get_post( $legacy_lesson_id );
				if ( $legacy_post ) {
					$title_query = new \WP_Query(
						array(
							'post_type'      => 'slms_lesson',
							'title'          => $legacy_post->post_title,
							'posts_per_page' => 1,
							'fields'         => 'ids',
							'no_found_rows'  => true,
							'post_status'    => 'publish',
						)
					);
					if ( $title_query->have_posts() ) {
						$new_lesson_id = $title_query->posts[0];
						update_post_meta( $new_lesson_id, '_legacy_id', $legacy_lesson_id );
						self::log( $user_label . ': legacy lesson ' . $legacy_lesson_id . ' (' . $legacy_post->post_title . ') matched by title to slms_lesson ' . $new_lesson_id . '.', 'debug' );
					} else {
						self::log( $user_label . ': legacy lesson ' . $legacy_lesson_id . ' (' . $legacy_post->post_title . ') has no matching slms_lesson — _legacy_id lookup failed, direct ID lookup failed (post_type=' . $legacy_post->post_type . '), title lookup failed.', 'warn' );
						if ( $stats !== null ) {
							++$stats['lessons_skipped_no_match'];
						}
						return;
					}
				} else {
					self::log( $user_label . ': legacy lesson ' . $legacy_lesson_id . ' has no matching slms_lesson — _legacy_id lookup failed, post ID ' . $legacy_lesson_id . ' does not exist in wp_posts.', 'warn' );
					if ( $stats !== null ) {
						++$stats['lessons_skipped_no_match'];
					}
					return;
				}
			}
		} else {
			$new_lesson_id = $new_lesson_query->posts[0];
		}

		// Parse completion timestamp from WPComplete data.
		$timestamp = time();
		$ts_source = 'fallback(now)';

		if ( is_array( $data ) && ! empty( $data['completed'] ) ) {
			$completed_val = (string) ( $data['completed'] ?? '' );
			$parsed        = $completed_val !== '' ? strtotime( $completed_val ) : false;
			if ( $parsed ) {
				$timestamp = $parsed;
				$ts_source = 'array[completed]=' . $completed_val;
			}
		} elseif ( is_string( $data ) && $data !== '' && strtotime( $data ) ) {
			$timestamp = strtotime( $data );
			$ts_source = 'string=' . $data;
		} elseif ( is_numeric( $data ) ) {
			$timestamp = (int) $data;
			$ts_source = 'numeric=' . $data;
		}

		// Find linked courses for this lesson.
		$linked_courses = Relationships::get_courses_for_lesson( $new_lesson_id );
		if ( empty( $linked_courses ) ) {
			self::log( $user_label . ': new lesson ' . $new_lesson_id . ' (legacy ' . $legacy_lesson_id . ') is not linked to any course in wp_slms_course_lesson table. Attempting course lookup via _simple_lms_order meta.', 'warn' );

			// Fallback: search all courses for this lesson in their _simple_lms_order.
			global $wpdb;
			$course_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_simple_lms_order' AND meta_value LIKE %s",
					'%' . $wpdb->esc_like( '"' . $new_lesson_id . '"' ) . '%'
				)
			);

			if ( ! empty( $course_ids ) ) {
				self::log( $user_label . ': found lesson ' . $new_lesson_id . ' in _simple_lms_order of course(s): ' . implode( ', ', $course_ids ) . '.', 'debug' );
				$linked_courses = array();
				foreach ( $course_ids as $cid ) {
					$cpost = get_post( $cid );
					if ( $cpost && $cpost->post_type === 'slms_course' ) {
						$obj              = new \stdClass();
						$obj->id          = (int) $cid;
						$obj->title       = $cpost->post_title;
						$linked_courses[] = $obj;
					}
				}
			}

			if ( empty( $linked_courses ) ) {
				self::log( $user_label . ': lesson ' . $new_lesson_id . ' (legacy ' . $legacy_lesson_id . ') still not linked to any course after fallback search. Skipping.', 'warn' );
				if ( $stats !== null ) {
					++$stats['lessons_skipped_no_course'];
				}
				return;
			}
		}

		// Determine which course(s) to record progress for.
		// Tier 1: Resolve origin course from legacy post_parent hierarchy.
		$origin_course_id = self::resolve_origin_course( $legacy_lesson_id );

		if ( $origin_course_id ) {
			// Verify the origin course is among the linked courses.
			$origin_is_linked = false;
			$origin_title     = '';
			foreach ( $linked_courses as $course_obj ) {
				if ( (int) $course_obj->id === $origin_course_id ) {
					$origin_is_linked = true;
					$origin_title     = $course_obj->title;
					break;
				}
			}

			if ( $origin_is_linked ) {
				Relationships::enroll_user( $user_id, $origin_course_id, 'migration' );
				if ( ! isset( $current_progress[ $origin_course_id ] ) ) {
					$current_progress[ $origin_course_id ] = array();
				}
				$current_progress[ $origin_course_id ][ $new_lesson_id ] = $timestamp;
				self::log( $user_label . ': mapped legacy ' . $legacy_lesson_id . ' -> lesson ' . $new_lesson_id . ' in origin course ' . $origin_course_id . ' (' . $origin_title . ') via post_parent (ts: ' . $ts_source . ').', 'debug' );
				if ( $stats !== null ) {
					++$stats['lessons_mapped'];
				}
				return;
			}
			// Origin course not in linked courses — fall through to tiered validation.
			self::log( $user_label . ': legacy lesson ' . $legacy_lesson_id . ' post_parent resolved to course ' . $origin_course_id . ' but it is not in the linked courses list. Falling back to ownership validation.', 'debug' );
		}

		// Tier 3: Single-course passthrough — no ambiguity, skip validation.
		if ( count( $linked_courses ) === 1 ) {
			$course_obj = $linked_courses[0];
			$course_id  = (int) $course_obj->id;
			Relationships::enroll_user( $user_id, $course_id, 'migration' );
			if ( ! isset( $current_progress[ $course_id ] ) ) {
				$current_progress[ $course_id ] = array();
			}
			$current_progress[ $course_id ][ $new_lesson_id ] = $timestamp;
			self::log( $user_label . ': mapped legacy ' . $legacy_lesson_id . ' -> lesson ' . $new_lesson_id . ' in course ' . $course_id . ' (' . $course_obj->title . ') (single course, no ambiguity) (ts: ' . $ts_source . ').', 'debug' );
			if ( $stats !== null ) {
				++$stats['lessons_mapped'];

			}
			return;
		}

		// Tier 2: Multiple courses — validate ownership for each candidate.
		$mapped_any = false;
		foreach ( $linked_courses as $course_obj ) {
			$course_id = (int) $course_obj->id;

			if ( self::user_owns_course( $user_id, $course_id, $enrolled_ids, $user_gf_products ) ) {
				Relationships::enroll_user( $user_id, $course_id, 'migration' );
				if ( ! isset( $current_progress[ $course_id ] ) ) {
					$current_progress[ $course_id ] = array();
				}
				$current_progress[ $course_id ][ $new_lesson_id ] = $timestamp;
				self::log( $user_label . ': mapped legacy ' . $legacy_lesson_id . ' -> lesson ' . $new_lesson_id . ' in course ' . $course_id . ' (' . $course_obj->title . ') (ownership verified) (ts: ' . $ts_source . ').', 'debug' );
				if ( $stats !== null ) {
					++$stats['lessons_mapped'];
				}
				$mapped_any = true;
			} else {
				self::log( $user_label . ': lesson ' . $new_lesson_id . ' linked to course ' . $course_id . ' (' . $course_obj->title . ') but user has no purchase/enrollment evidence. Skipping.', 'warn' );
				if ( $stats !== null ) {
					++$stats['lessons_skipped_not_enrolled'];
				}
			}
		}

		if ( ! $mapped_any ) {
			self::log( $user_label . ': legacy lesson ' . $legacy_lesson_id . ' (new ' . $new_lesson_id . ') linked to ' . count( $linked_courses ) . ' courses but user has no ownership evidence for any. No progress recorded.', 'warn' );
		}
	}

	/**
	 * Phase 3: Historical Certificate Migration (GF → wp_slms_course_history).
	 *
	 * Queries Gravity Forms certificate entries and inserts permanent compliance
	 * records into the custom history table for 9-year retention.
	 *
	 * @param int $limit Max users to migrate in this batch.
	 * @return array Result summary.
	 */
	public static function migrate_history_batch( $limit = 10, $offset = 0 ) {
		$limit  = absint( $limit );
		$offset = absint( $offset );
		self::log( 'Phase 4: Starting historical certificate migration (limit=' . $limit . ', offset=' . $offset . ').' );
		$start_time = microtime( true );
		global $wpdb;

		$history_table = $wpdb->prefix . 'slms_course_history';

		$users = \get_users(
			array(
				'meta_key'     => '_lms_history_migrated',
				'meta_compare' => 'NOT EXISTS',
				'number'       => $limit,
				'offset'       => $offset,
				'fields'       => 'ID',
			)
		);

		self::log( 'Found ' . count( $users ) . ' users pending history migration.' );

		$count       = 0;
		$inserted    = 0;
		$skipped_dup = 0;

		foreach ( $users as $user_id ) {
			$user_id    = (int) $user_id;
			$user       = get_userdata( $user_id );
			$user_label = $user ? $user->user_email : 'UID:' . $user_id;

			if ( ! class_exists( 'GFAPI' ) ) {
				self::log( 'GFAPI class not available — cannot migrate history for ' . $user_label . '.', 'error' );
				update_user_meta( $user_id, '_lms_history_migrated', time() );
				++$count;
				continue;
			}

			if ( ! $user ) {
				self::log( 'User ' . $user_id . ' not found, marking as migrated.', 'warn' );
				update_user_meta( $user_id, '_lms_history_migrated', time() );
				++$count;
				continue;
			}

			// Discover certificate forms.
			$forms         = \GFAPI::get_forms();
			$cert_form_ids = array();
			foreach ( $forms as $form ) {
				$form_title = $form['title'] ?? '';
				if ( stripos( $form_title, 'Certificate' ) !== false ) {
					$cert_form_ids[] = $form['id'];
				}
			}

			if ( empty( $cert_form_ids ) ) {
				self::log( $user_label . ': no certificate forms found — skipping history migration.', 'warn' );
				update_user_meta( $user_id, '_lms_history_migrated', time() );
				++$count;
				continue;
			}
			$form_ids = $cert_form_ids;
			self::log( $user_label . ': searching ' . count( $form_ids ) . ' certificate form(s) (IDs: ' . implode( ', ', $form_ids ) . ').', 'debug' );

			// Search by user ID.
			$search_criteria = array(
				'status'        => 'active',
				'field_filters' => array(
					'mode' => 'any',
					array(
						'key'   => 'created_by',
						'value' => $user_id,
					),
				),
			);
			$entries         = \GFAPI::get_entries( $form_ids, $search_criteria );

			// Search by email (catches entries not linked by user ID).
			$search_criteria_email = array(
				'status'        => 'active',
				'field_filters' => array(
					'mode' => 'any',
					array( 'value' => $user->user_email ),
				),
			);
			$entries_by_email      = \GFAPI::get_entries( $form_ids, $search_criteria_email );

			// Merge and deduplicate by entry ID.
			$all_entries    = array_merge( (array) $entries, (array) $entries_by_email );
			$unique_entries = array();
			foreach ( $all_entries as $entry ) {
				if ( isset( $entry['id'] ) && ! isset( $unique_entries[ $entry['id'] ] ) ) {
					$unique_entries[ $entry['id'] ] = $entry;
				}
			}

			self::log( $user_label . ': found ' . count( $unique_entries ) . ' unique GF entries (by_id=' . count( (array) $entries ) . ', by_email=' . count( (array) $entries_by_email ) . ').' );

			// Insert each entry into the compliance history table.
			foreach ( $unique_entries as $entry ) {
				try {
					// Strict Validation: Entry must be an array and have an ID.
					if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
						self::log( $user_label . ': skipping malformed Gravity Forms entry record.', 'error' );
						continue;
					}

					$gf_entry_id = absint( $entry['id'] );
					$form_id     = absint( $entry['form_id'] ?? 0 );

					if ( ! $form_id ) {
						self::log( $user_label . ': Entry #' . $gf_entry_id . ' is missing a form_id. Skipping.', 'warn' );
						continue;
					}

					// Guard: only process entries from known certificate forms.
					if ( ! in_array( $form_id, $cert_form_ids, true ) ) {
						self::log( $user_label . ': skipping entry ' . $gf_entry_id . ' — form_id ' . $form_id . ' is not a certificate form.', 'warn' );
						continue;
					}

					$course_name = __( 'Unknown Course', 'simple-lms-bridge' );
					$form        = \GFAPI::get_form( $form_id );

					if ( $form && isset( $form['fields'] ) ) {
						foreach ( $form['fields'] as $field ) {
							$label = $field->label ?? '';
							if ( stripos( $label, 'Course' ) !== false ) {
								$value = rgar( $entry, (string) $field->id );
								if ( ! empty( $value ) ) {
									$course_name = $value;
									break;
								}
							}
						}
					}

					// Fallback: derive course name from form title.
					if ( $course_name === __( 'Unknown Course', 'simple-lms-bridge' ) && $form ) {
						$course_name = str_ireplace( 'Certificate', '', $form['title'] ?? '' );
						$course_name = trim( $course_name, ' -' );
					}

					// Strict Deduplication Check based on user_id and course_name
					$exists = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$history_table} WHERE user_id = %d AND course_name = %s",
							$user_id,
							sanitize_text_field( $course_name )
						)
					);

					if ( $exists ) {
						++$skipped_dup;
						continue;
					}

					$wpdb->insert(
						$history_table,
						array(
							'user_id'        => $user_id,
							'course_name'    => sanitize_text_field( $course_name ),
							'completed_date' => sanitize_text_field( $entry['date_created'] ?? current_time( 'mysql' ) ),
							'form_id'        => $form_id,
							'gf_entry_id'    => $gf_entry_id,
						),
						array( '%d', '%s', '%s', '%d', '%d' )
					);

					// 1. Resolve Course ID: Try exact match first, fallback to fuzzy LIKE match.
					$new_course_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'slms_course' LIMIT 1",
							$course_name
						)
					);

					if ( ! $new_course_id ) {
						// Fuzzy match fallback: strip common words and punctuation.
						$fuzzy_name = preg_replace( '/[^a-zA-Z0-9\s]/', '', $course_name );
						$fuzzy_name = str_ireplace( array( 'Course', 'Hr', 'Hrs', 'Hour', 'Hours' ), '', $fuzzy_name );
						$fuzzy_name = trim( preg_replace( '/\s+/', ' ', $fuzzy_name ) );

						if ( ! empty( $fuzzy_name ) ) {
							$new_course_id = $wpdb->get_var(
								$wpdb->prepare(
									"SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s AND post_type = 'slms_course' LIMIT 1",
									'%' . $wpdb->esc_like( $fuzzy_name ) . '%'
								)
							);

							if ( $new_course_id ) {
								self::log( $user_label . ': Fuzzy match success for "' . $course_name . '" -> resolved to course ID ' . $new_course_id . ' using search string "' . $fuzzy_name . '".', 'info' );
							}
						}
					}

					// 2. Active Enrollment Cleanup: If a certificate exists/was just logged, remove active enrollment.
					if ( $new_course_id ) {
						$deleted = $wpdb->delete(
							$wpdb->prefix . 'slms_user_course',
							array(
								'user_id'   => $user_id,
								'course_id' => $new_course_id,
							),
							array( '%d', '%d' )
						);

						if ( $deleted ) {
							self::log( $user_label . ': retroactive enrollment cleanup for course "' . $course_name . '" (ID: ' . $new_course_id . ').', 'debug' );
						}
					}

					++$inserted;

				} catch ( \Exception $e ) {
					self::log( $user_label . ': Critical error processing GF entry #' . ( $entry['id'] ?? 'unknown' ) . ': ' . $e->getMessage(), 'error' );
					continue;
				} catch ( \Error $e ) {
					self::log( $user_label . ': Fatal type error processing GF entry #' . ( $entry['id'] ?? 'unknown' ) . ': ' . $e->getMessage(), 'error' );
					continue;
				}
			}

			if ( $inserted > 0 ) {
				self::log( $user_label . ': inserted ' . $inserted . ' compliance record(s), skipped ' . $skipped_dup . ' duplicate(s).' );
			} else {
				self::log( $user_label . ': no new certificate entries to insert.' );
			}

			$updated = update_user_meta( $user_id, '_lms_history_migrated', time() );
			if ( ! $updated ) {
				self::log( 'CRITICAL: Failed to set _lms_history_migrated for user ' . $user_id . '. This user will be re-processed next batch.', 'error' );
			}
			++$count;
		}

		$duration = round( microtime( true ) - $start_time, 2 );
		self::log(
			sprintf(
				'Phase 4 complete: users=%d, inserted=%d, duplicates_skipped=%d, duration=%ss.',
				$count,
				$inserted,
				$skipped_dup,
				$duration
			)
		);

		$pending     = self::get_pending_history_count();
		$is_complete = ( $pending === 0 || count( $users ) === 0 );

		return array(
			'processed' => $count,
			'pending'   => $pending,
			'total'     => $count + $pending,
			'inserted'  => $inserted,
			'duration'  => $duration,
			'success'   => true,
			'status'    => $is_complete ? 'complete' : 'processing',
			'offset'    => $is_complete ? 0 : $offset + $limit,
			'log'       => self::flush_log(),
		);
	}

	/**
	 * Alias for Phase 3 (progress) migration for consistency.
	 */
	public static function migrate_student_progress_batch( $limit = 10 ) {
		return self::migrate_progress_batch( $limit );
	}

	/**
	 * Phase 2: PMPro Registration Sync.
	 *
	 * Reads GF Form 2 "Select Your Courses" checkbox field (ID 20) which
	 * contains legacy course post IDs. Maps each old post ID → new slms_course
	 * (via _legacy_id) → PMPro level (via _lms_pmpro_levels), then applies the
	 * 90-day rule based on the original GF entry (purchase) date:
	 *
	 *  - Purchase <= 90 days ago: grant an active PMPro membership with the
	 *    remaining days left.
	 *  - Purchase > 90 days ago: create a historical MemberOrder for audit
	 *    purposes only — no active access is granted.
	 *
	 * Pagination is caller-driven via $offset so the frontend loop stays
	 * deterministic and cannot stall.
	 *
	 * PMPro levels must already exist from Phase 1 (create_pmpro_level_for_course).
	 *
	 * @param int $limit  Max entries to fetch in this batch.
	 * @param int $offset GFAPI page offset (advanced by the caller each batch).
	 * @return array Result summary including next offset.
	 */
	public static function migrate_pmpro_batch( $limit = 10, $offset = 0 ) {
		$limit  = absint( $limit );
		$offset = absint( $offset );
		self::log( 'Phase 2: Starting PMPro registration sync (limit=' . $limit . ', offset=' . $offset . ').' );
		$start_time = microtime( true );

		if ( ! class_exists( 'GFAPI' ) ) {
			self::log( 'GFAPI class not available — cannot run Phase 2.', 'error' );
			return array(
				'processed' => 0,
				'pending'   => 0,
				'total'     => 0,
				'offset'    => $offset,
				'duration'  => 0,
				'success'   => false,
				'log'       => self::flush_log(),
			);
		}

		if ( ! function_exists( 'pmpro_changeMembershipLevel' ) ) {
			self::log( 'PMPro not active — cannot run Phase 2.', 'error' );
			return array(
				'processed' => 0,
				'pending'   => 0,
				'total'     => 0,
				'offset'    => $offset,
				'duration'  => 0,
				'success'   => false,
				'log'       => self::flush_log(),
			);
		}

		$gf_form_id      = 2;
		$course_field_id = 20; // Checkbox field: "Select Your Courses" with legacy post IDs as values.

		// Single deterministic page fetch — caller controls offset, preventing infinite scans.
		$search_criteria = array( 'status' => 'active' );
		$sorting         = array(
			'key'       => 'id',
			'direction' => 'ASC',
		);
		$paging          = array(
			'offset'    => $offset,
			'page_size' => $limit,
		);

		$entries = \GFAPI::get_entries( $gf_form_id, $search_criteria, $sorting, $paging );

		if ( ! is_array( $entries ) || empty( $entries ) ) {
			self::log( 'No more entries at offset ' . $offset . ' — Phase 2 complete.' );
			$pending = self::get_pending_pmpro_count();
			return array(
				'processed' => 0,
				'pending'   => $pending,
				'total'     => $pending,
				'offset'    => $offset,
				'duration'  => round( microtime( true ) - $start_time, 2 ),
				'success'   => true,
				'status'    => 'complete',
				'log'       => self::flush_log(),
			);
		}

		self::log( 'Fetched ' . count( $entries ) . ' entries at offset ' . $offset . '.', 'debug' );

		// Log the first entry's field 20 sub-fields for diagnostics.
		if ( ! empty( $entries[0] ) ) {
			$sample        = $entries[0];
			$sample_fields = array();
			foreach ( $sample as $key => $val ) {
				if ( ! empty( $val ) && strpos( (string) $key, $course_field_id . '.' ) === 0 ) {
					$sample_fields[] = $key . '=' . $val;
				}
			}
			self::log( 'Sample entry #' . $sample['id'] . ' field 20 sub-fields: ' . ( empty( $sample_fields ) ? '(none)' : implode( ' | ', $sample_fields ) ), 'debug' );
		}

		// Build a lookup cache: legacy course ID -> { new_course_id, level_ids[] }.
		$legacy_map = self::build_legacy_course_map();
		self::log( 'Legacy course map has ' . count( $legacy_map ) . ' entries.', 'debug' );

		$count            = 0;
		$skipped          = 0;
		$enrolled_active  = 0;
		$enrolled_expired = 0;
		$now              = current_time( 'timestamp' );

		global $wpdb;

		foreach ( $entries as $entry ) {
			$entry_id = absint( $entry['id'] );

			// Skip entries already processed in a previous run.
			$migrated = \gform_get_meta( $entry_id, '_slms_pmpro_migrated' );
			if ( $migrated ) {
				++$skipped;
				continue;
			}

			// Check for abandoned carts/unpaid entries.
			$payment_amount = rgar( $entry, 'payment_amount' );
			$payment_status = rgar( $entry, 'payment_status' );

			if ( $payment_amount > 0 && ! in_array( $payment_status, array( 'Paid', 'Approved' ) ) ) {
				self::log( 'Entry #' . $entry_id . ': abandoned cart/unpaid (Status: ' . $payment_status . ', Amount: ' . $payment_amount . ').', 'warn' );
				\gform_update_meta( $entry_id, '_slms_pmpro_migrated', time() );
				continue;
			}

			$user_id    = ! empty( $entry['created_by'] ) ? (int) $entry['created_by'] : 0;
			$entry_date = $entry['date_created'] ?? '';

			// Resolve user by email if created_by is missing.
			if ( ! $user_id ) {
				foreach ( $entry as $fkey => $fval ) {
					if ( is_string( $fval ) && \is_email( $fval ) ) {
						$wp_user = get_user_by( 'email', $fval );
						if ( $wp_user ) {
							$user_id = $wp_user->ID;
							break;
						}
					}
				}
			}

			if ( ! $user_id ) {
				self::log( 'Entry #' . $entry_id . ': no user found, marking as migrated and skipping.', 'warn' );
				\gform_update_meta( $entry_id, '_slms_pmpro_migrated', time() );
				++$count;
				continue;
			}

			$user       = get_userdata( $user_id );
			$user_label = $user ? $user->user_email : 'UID:' . $user_id;

			// Extract legacy course post IDs from checkbox field 20.
			// GF checkboxes store each choice in sub-fields: 20.1, 20.2, 20.3, etc.
			// Uses a helper to split mistakenly concatenated IDs (e.g. 546630).
			$legacy_course_ids = self::extract_legacy_course_ids( array( $entry ), $course_field_id );

			if ( empty( $legacy_course_ids ) ) {
				self::log( 'Entry #' . $entry_id . ' (' . $user_label . '): no course selections in field ' . $course_field_id . ', skipping.', 'debug' );
				\gform_update_meta( $entry_id, '_slms_pmpro_migrated', time() );
				++$count;
				continue;
			}

			self::log( 'Entry #' . $entry_id . ' (' . $user_label . '): found ' . count( $legacy_course_ids ) . ' course(s): ' . implode( ', ', $legacy_course_ids ) . '.' );

			// 90-day rule: measure elapsed time since the original purchase date.
			$entry_timestamp = $entry_date ? strtotime( $entry_date ) : 0;
			$days_elapsed    = $entry_timestamp ? (int) floor( ( $now - $entry_timestamp ) / DAY_IN_SECONDS ) : 91;

			foreach ( $legacy_course_ids as $legacy_id ) {
				if ( ! isset( $legacy_map[ $legacy_id ] ) ) {
					self::log( $user_label . ': legacy course ID ' . $legacy_id . ' not found in migration map, skipping.', 'warn' );
					continue;
				}

				$map_entry     = $legacy_map[ $legacy_id ];
				$new_course_id = $map_entry['new_course_id'];
				$level_ids     = $map_entry['level_ids'];

				if ( empty( $level_ids ) ) {
					self::log( $user_label . ': new course ' . $new_course_id . ' (legacy ' . $legacy_id . ') has no PMPro level mapped, skipping.', 'warn' );
					continue;
				}

				foreach ( $level_ids as $level_id ) {
					if ( $days_elapsed > 90 ) {
						// EXPIRED PATH — purchase is older than 90 days.
						// Create a historical order for audit/receipt purposes only.
						// Do NOT grant an active PMPro membership.
						$pmpro_level = function_exists( 'pmpro_getLevel' ) ? pmpro_getLevel( $level_id ) : null;
						$level_price = $pmpro_level ? (float) $pmpro_level->initial_payment : 0.0;

						if ( class_exists( 'MemberOrder' ) ) {
							// Deduplicate: check against existing MemberOrder meta to prevent duplicate record creation.
							global $wpdb;
							$ordermeta_table = count( explode( '_', $wpdb->prefix ) ) > 1 ? $wpdb->prefix . 'pmpro_membership_ordermeta' : 'wp_pmpro_membership_ordermeta';
							$existing_order  = false;

							// First map the amount and timestamp.
							$payment_amount = rgar( $entry, 'payment_amount' );
							$order_total    = is_numeric( $payment_amount ) && $payment_amount > 0 ? (float) $payment_amount : $level_price;

							// For DB compatibility and safety since pmpro_membership_ordermeta is conditionally used the plugin structure.
							if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ordermeta_table ) ) === $ordermeta_table ) {
								$existing_order = $wpdb->get_var(
									$wpdb->prepare(
										"SELECT pmpro_order_id FROM {$ordermeta_table} WHERE meta_key = '_gf_entry_id' AND meta_value = %d LIMIT 1",
										$entry_id
									)
								);
							}

							if ( $existing_order ) {
								self::log( $user_label . ': historical order already exists for GF entry #' . $entry_id . ' (Order ID: ' . $existing_order . '). Skipping duplication.' );
							} else {
								$order                      = new \MemberOrder();
								$order->user_id             = $user_id;
								$order->membership_id       = $level_id;
								$order->subtotal            = $order_total;
								$order->total               = $order_total;
								$order->status              = 'success';
								$order->gateway             = 'free';
								$order->gateway_environment = 'sandbox';
								$order->payment_type        = 'Migration Import';
								$order->notes               = 'Migrated from GF entry #' . $entry_id . '. Access expired (' . $days_elapsed . ' days ago).';
								$order->timestamp           = $entry_timestamp;
								$order->saveOrder();

								// Save deduplication identifier
								if ( function_exists( 'update_pmpro_membership_order_meta' ) && ! empty( $order->id ) ) {
									update_pmpro_membership_order_meta( $order->id, '_gf_entry_id', $entry_id );
								}

								self::log( $user_label . ': historical order created for level ' . $level_id . ' (expired, ' . $days_elapsed . ' days old). No active access granted.' );
							}
						} else {
							self::log( $user_label . ': MemberOrder class unavailable — skipping historical order for level ' . $level_id . '.', 'warn' );
						}

						// Relationships::enroll_user($user_id, $new_course_id, 'pmpro_migration_expired');
						++$enrolled_expired;
					} else {
						// ACTIVE PATH — purchase is within the 90-day window.
						// Grant a live membership with the days remaining.
						$remaining_days = 90 - $days_elapsed;
						$enddate        = gmdate( 'Y-m-d H:i:s', $now + ( $remaining_days * DAY_IN_SECONDS ) );

						$level_params = array(
							'user_id'       => $user_id,
							'membership_id' => $level_id,
							'enddate'       => $enddate,
						);

						$result = \pmpro_changeMembershipLevel( $level_params, $user_id );

						if ( $result ) {
							self::log( $user_label . ': enrolled in PMPro level ' . $level_id . ' with ' . $remaining_days . ' days remaining (enddate=' . $enddate . ').' );
							++$enrolled_active;
						} else {
							self::log( $user_label . ': pmpro_changeMembershipLevel failed for level ' . $level_id . '.', 'error' );
						}

						Relationships::enroll_user( $user_id, $new_course_id, 'pmpro_migration' );
					}
				}
			}

			\gform_update_meta( $entry_id, '_slms_pmpro_migrated', time() );
			++$count;
		}

		$duration    = round( microtime( true ) - $start_time, 2 );
		$next_offset = $offset + count( $entries );
		$pending     = self::get_pending_pmpro_count();

		self::log(
			sprintf(
				'Phase 2 batch complete: processed=%d, skipped=%d, enrolled_active=%d, enrolled_expired=%d, duration=%ss.',
				$count,
				$skipped,
				$enrolled_active,
				$enrolled_expired,
				$duration
			)
		);

		return array(
			'processed' => $count,
			'pending'   => $pending,
			'total'     => $count + $pending,
			'enrolled'  => $enrolled_active,
			'expired'   => $enrolled_expired,
			'offset'    => $next_offset,
			'duration'  => $duration,
			'success'   => true,
			'status'    => ( $pending === 0 || empty( $entries ) ) ? 'complete' : 'processing',
			'log'       => self::flush_log(),
		);
	}

	/**
	 * Build a lookup map: legacy course post ID -> new course + PMPro level(s).
	 *
	 * Queries all slms_course posts that have a _legacy_id meta value, then
	 * reads their _lms_pmpro_levels to build the mapping table.
	 *
	 * @return array { legacy_id => { 'new_course_id' => int, 'level_ids' => int[] } }
	 */
	private static function build_legacy_course_map() {
		$map = array();

		$courses = get_posts(
			array(
				'post_type'    => 'slms_course',
				'post_status'  => 'publish',
				'numberposts'  => -1,
				'meta_key'     => '_legacy_id',
				'meta_compare' => 'EXISTS',
			)
		);

		foreach ( $courses as $course ) {
			$legacy_id = (int) get_post_meta( $course->ID, '_legacy_id', true );
			if ( ! $legacy_id ) {
				continue;
			}

			$level_ids = get_post_meta( $course->ID, '_lms_pmpro_levels', true );
			if ( ! is_array( $level_ids ) ) {
				$level_ids = array();
			}

			$map[ $legacy_id ] = array(
				'new_course_id' => (int) $course->ID,
				'level_ids'     => array_map( 'intval', $level_ids ),
			);
		}

		return $map;
	}

	/**
	 * Phase 5: Legacy Cleanup.
	 * Safely removes legacy posts after verification.
	 *
	 * @return int Number of deleted posts.
	 */
	public static function cleanup_legacy_data() {
		$legacy_posts = get_posts(
			array(
				'post_type'   => 'course',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		$count = 0;
		foreach ( $legacy_posts as $post_id ) {
			// wp_delete_post(..., true) skips trash and goes straight to deletion
			if ( wp_delete_post( $post_id, true ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Helper: Import or deduplicate a lesson.
	 */
	private static function import_lesson( $legacy_lesson ) {
		// Deduplicate by _legacy_id first (most accurate)
		$existing = new \WP_Query(
			array(
				'post_type'      => 'slms_lesson',
				'meta_key'       => '_legacy_id',
				'meta_value'     => $legacy_lesson->ID,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( $existing->have_posts() ) {
			return $existing->posts[0];
		}

		// Fallback: Deduplicate by title/slug
		$existing_title = new \WP_Query(
			array(
				'post_type'      => 'slms_lesson',
				'title'          => $legacy_lesson->post_title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post_status'    => 'publish',
			)
		);

		if ( $existing_title->have_posts() ) {
			$found_id = $existing_title->posts[0];
			update_post_meta( $found_id, '_legacy_id', $legacy_lesson->ID );
			return $found_id;
		}

		$new_lesson_id = wp_insert_post(
			array(
				'post_title'   => $legacy_lesson->post_title,
				'post_content' => $legacy_lesson->post_content,
				'post_name'    => $legacy_lesson->post_name,
				'post_status'  => 'publish',
				'post_type'    => 'slms_lesson',
			)
		);

		if ( ! is_wp_error( $new_lesson_id ) ) {
			update_post_meta( $new_lesson_id, '_legacy_id', $legacy_lesson->ID );

			// Map Video Meta if exists (Legacy Pods)
			$video = get_post_meta( $legacy_lesson->ID, 'lesson_video', true );
			if ( $video ) {
				update_post_meta( $new_lesson_id, '_slms_lesson_type', 'video' );
				update_post_meta( $new_lesson_id, '_slms_presto_video', $video );
			}

			return $new_lesson_id;
		}

		return false;
	}

	/**
	 * Helper: Import or deduplicate a course.
	 */
	private static function import_course( $legacy_course ) {
		$existing = new \WP_Query(
			array(
				'post_type'      => 'slms_course',
				'meta_key'       => '_legacy_id',
				'meta_value'     => $legacy_course->ID,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( $existing->have_posts() ) {
			return $existing->posts[0];
		}

		$new_course_id = wp_insert_post(
			array(
				'post_title'   => $legacy_course->post_title,
				'post_content' => $legacy_course->post_content,
				'post_name'    => $legacy_course->post_name,
				'post_status'  => 'publish',
				'post_type'    => 'slms_course',
			)
		);

		if ( ! is_wp_error( $new_course_id ) ) {
			update_post_meta( $new_course_id, '_legacy_id', $legacy_course->ID );

			// Map Price
			$price = get_post_meta( $legacy_course->ID, 'course_price', true );
			if ( $price ) {
				update_post_meta( $new_course_id, '_slms_course_price', $price );
			}

			return $new_course_id;
		}

		return false;
	}

	/**
	 * Helper: Extract Post ID from WP Complete Key.
	 */
	private static function extract_post_id( $key ) {
		if ( strpos( ( $key ?? '' ), '-' ) !== false ) {
			$parts = explode( '-', $key );
			return (int) $parts[0];
		}
		return (int) $key;
	}

	/**
	 * Create or find a PMPro membership level for a course during Phase 1.
	 *
	 * Creates a "One Time" level with 90-day expiration using the legacy
	 * course_price, then maps it to the new course via _lms_pmpro_levels meta.
	 *
	 * @param int      $new_course_id  New slms_course post ID.
	 * @param \WP_Post $legacy_course  Legacy course post object.
	 * @return int|false PMPro level ID on success, false on failure.
	 */
	private static function create_pmpro_level_for_course( $new_course_id, $legacy_course ) {
		if ( ! function_exists( 'pmpro_getAllLevels' ) ) {
			self::log( 'PMPro not active — skipping level creation for course ' . $new_course_id . '.', 'warn' );
			return false;
		}

		// Check if the course already has a PMPro level mapped.
		$existing_levels = get_post_meta( $new_course_id, '_lms_pmpro_levels', true );
		if ( ! empty( $existing_levels ) && is_array( $existing_levels ) ) {
			self::log( 'Course ' . $new_course_id . ' already has PMPro level(s): ' . implode( ', ', $existing_levels ) . '. Skipping.', 'debug' );
			return (int) $existing_levels[0];
		}

		$course_title = get_the_title( $new_course_id );
		$level_key    = strtolower( trim( $course_title ) );

		// Check if a level with this name already exists.
		$all_levels = pmpro_getAllLevels( false, true );
		foreach ( $all_levels as $level ) {
			if ( strtolower( trim( $level->name ) ) === $level_key ) {
				self::log( 'Found existing PMPro level "' . $level->name . '" (ID: ' . $level->id . ') for course ' . $new_course_id . '.' );
				update_post_meta( $new_course_id, '_lms_pmpro_levels', array( (int) $level->id ) );
				return (int) $level->id;
			}
		}

		// Get the course price from legacy meta.
		$price       = get_post_meta( $legacy_course->ID, 'course_price', true );
		$price_clean = preg_replace( '/[^0-9.]/', '', (string) $price );
		$price_float = $price_clean !== '' ? (float) $price_clean : 0.00;

		// Create a new PMPro level.
		global $wpdb;
		$pmpro_table = $wpdb->prefix . 'pmpro_membership_levels';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pmpro_table ) ) !== $pmpro_table ) {
			self::log( 'PMPro membership_levels table not found.', 'error' );
			return false;
		}

		$wpdb->insert(
			$pmpro_table,
			array(
				'name'              => sanitize_text_field( $course_title ),
				'description'       => sprintf( 'One-time access to "%s".', sanitize_text_field( $course_title ) ),
				'initial_payment'   => $price_float,
				'billing_amount'    => 0,
				'cycle_number'      => 0,
				'cycle_period'      => '',
				'billing_limit'     => 0,
				'trial_amount'      => 0,
				'trial_limit'       => 0,
				'allow_signups'     => 1,
				'expiration_number' => 90,
				'expiration_period' => 'Day',
			),
			array( '%s', '%s', '%f', '%f', '%d', '%s', '%d', '%f', '%d', '%d', '%d', '%s' )
		);
		$level_id = (int) $wpdb->insert_id;

		if ( ! $level_id ) {
			self::log( 'Failed to create PMPro level for course "' . $course_title . '".', 'error' );
			return false;
		}

		// Map the level to the course.
		update_post_meta( $new_course_id, '_lms_pmpro_levels', array( $level_id ) );

		// Set access days to 90 to match the PMPro level expiration.
		update_post_meta( $new_course_id, '_lms_access_days', 90 );

		self::log( 'Created PMPro level "' . $course_title . '" (ID: ' . $level_id . ', price: $' . number_format( $price_float, 2 ) . ', 90-day expiration).' );

		return $level_id;
	}

	/**
	 * Get count of users pending migration.
	 */
	public static function get_pending_migration_count() {
		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(DISTINCT um.user_id) FROM {$wpdb->usermeta} um WHERE um.meta_key = 'wpcomplete' OR um.meta_key LIKE 'wpcomplete_%'" );
		return (int) $count;
	}

	/**
	 * Get count of users pending history migration.
	 */
	public static function get_pending_history_count() {
		return count(
			\get_users(
				array(
					'meta_key'     => '_lms_history_migrated',
					'meta_compare' => 'NOT EXISTS',
					'fields'       => 'ID',
				)
			)
		);
	}

	/**
	 * Get count of GF Form ID 2 entries pending PMPro migration.
	 */
	public static function get_pending_pmpro_count() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return 0;
		}

		$gf_form_id      = 2;
		$search_criteria = array( 'status' => 'active' );
		$total           = \GFAPI::count_entries( $gf_form_id, $search_criteria );

		if ( ! $total ) {
			return 0;
		}

		// Count how many have already been migrated via GF entry meta.
		global $wpdb;
		$gf_meta_table = $wpdb->prefix . 'gf_entry_meta';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gf_meta_table ) ) !== $gf_meta_table ) {
			return (int) $total;
		}

		$migrated = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$gf_meta_table} em
             INNER JOIN {$wpdb->prefix}gf_entry e ON em.entry_id = e.id
             WHERE em.meta_key = '_slms_pmpro_migrated'
             AND e.form_id = %d AND e.status = 'active'",
				$gf_form_id
			)
		);

		return max( 0, (int) $total - $migrated );
	}

	/**
	 * Reset Phase 2 migration meta so all GF Form 2 entries can be re-processed.
	 *
	 * @return array Result summary.
	 */
	public static function reset_pmpro_migration() {
		global $wpdb;
		$gf_meta_table = $wpdb->prefix . 'gf_entry_meta';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gf_meta_table ) ) !== $gf_meta_table ) {
			return array(
				'deleted' => 0,
				'pending' => 0,
				'success' => false,
				'message' => 'GF entry meta table not found.',
			);
		}

		$deleted = $wpdb->query(
			"DELETE FROM {$gf_meta_table} WHERE meta_key = '_slms_pmpro_migrated'"
		);

		self::log( 'Phase 2 reset: removed ' . (int) $deleted . ' migration markers.', 'info' );

		return array(
			'deleted' => (int) $deleted,
			'pending' => self::get_pending_pmpro_count(),
			'success' => true,
			'log'     => self::flush_log(),
		);
	}

	/**
	 * Get count of courses pending migration.
	 */
	public static function get_pending_content_count() {
		$query = new \WP_Query(
			array(
				'post_type'   => 'course',
				'post_parent' => 0,
				'meta_query'  => array(
					array(
						'key'     => '_slms_migrated',
						'compare' => 'NOT EXISTS',
					),
				),
				'fields'      => 'ids',
			)
		);
		return $query->found_posts;
	}

	/**
	 * Task 2: Retroactive Graduation Cleanup.
	 * Identifies students who have completed all lessons but are still in the active enrollment table.
	 *
	 * @return void
	 */
	public static function slms_retroactive_graduation_cleanup() {
		self::log( 'Starting retroactive graduation cleanup script.' );
		global $wpdb;

		$user_course_table    = $wpdb->prefix . 'slms_user_course';
		$course_history_table = $wpdb->prefix . 'slms_course_history';
		$course_lesson_table  = $wpdb->prefix . 'slms_course_lesson';

		// 1. Get all users with progress data.
		$user_ids = $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_lms_progress'" );

		if ( empty( $user_ids ) ) {
			self::log( 'No users with progress metadata found.', 'info' );
			return;
		}

		$graduated_count = 0;

		foreach ( $user_ids as $user_id ) {
			$user_id  = (int) $user_id;
			$progress = \get_user_meta( $user_id, '_lms_progress', true );

			if ( ! is_array( $progress ) ) {
				continue;
			}

			foreach ( $progress as $course_id => $lessons_done ) {
				$course_id = (int) $course_id;

				// 2. Determine total lessons assigned to this course.
				$total_lessons = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$course_lesson_table} WHERE course_id = %d",
						$course_id
					)
				);

				if ( $total_lessons === 0 ) {
					continue; // Course has no lessons or doesn't exist.
				}

				$completed_count = is_array( $lessons_done ) ? count( $lessons_done ) : 0;

				// 3. If progress matches total, check history.
				if ( $completed_count >= $total_lessons ) {
					// Look for corresponding history record.
					$history_exists = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$course_history_table} WHERE user_id = %d AND 
                         (course_name = (SELECT post_title FROM {$wpdb->posts} WHERE ID = %d) OR 
                          course_name LIKE (SELECT CONCAT('%', post_title, '%') FROM {$wpdb->posts} WHERE ID = %d))",
							$user_id,
							$course_id,
							$course_id
						)
					);

					if ( $history_exists ) {
						// 4. Actively delete from user_course (active enrollment).
						$deleted = $wpdb->delete(
							$user_course_table,
							array(
								'user_id'   => $user_id,
								'course_id' => $course_id,
							),
							array( '%d', '%d' )
						);

						if ( $deleted ) {
							$user       = \get_userdata( $user_id );
							$user_label = $user ? $user->user_email : 'UID:' . $user_id;
							self::log( 'Retroactive Graduation: De-enrolled ' . $user_label . ' from course ID ' . $course_id . ' (Progress: ' . $completed_count . '/' . $total_lessons . ').', 'info' );
							++$graduated_count;
						}
					}
				}
			}
		}

		self::log( 'Retroactive graduation cleanup complete. Total students graduated: ' . $graduated_count );
	}
}
