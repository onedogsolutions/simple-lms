<?php
/**
 * Student analytics and reporting for SimpleLMS.
 *
 * Owner-facing reporting: enrollment trends, course funnels, lesson drop-off,
 * time-to-complete distributions and at-risk students.
 *
 * Data-model notes (important):
 *  - Active enrollments live in {prefix}slms_user_course (user_id, course_id,
 *    enrolled_at, source). When a course is completed the row is DELETED and the
 *    per-lesson progress / enrolled_at user-meta is wiped, so the enrollment
 *    table only ever reflects *in-progress* learners.
 *  - Per-lesson progress lives in the `{prefix}slms_lesson_progress` database table.
 *  - Completions are durably recorded in {prefix}slms_course_history
 *    (course_name, completed_date, gf_entry_id, cert_data) and in the
 *    `_lms_completed_at` user-meta ([course_id] => unix-timestamp).
 *  - A certificate is a course_history row whose gf_entry_id > 0.
 *
 * Because completed learners leave the enrollment table, funnel stages combine
 * the live in-progress signal with the durable completion records.
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Analytics
 *
 * Query layer + nightly rollup for owner-facing reporting.
 */
class Analytics {

	/**
	 * Nightly rollup table name.
	 *
	 * @var string
	 */
	private static $rollup_table;

	/**
	 * Enrollment (user-course) table name.
	 *
	 * @var string
	 */
	private static $user_course_table;

	/**
	 * Course history table name.
	 *
	 * @var string
	 */
	private static $history_table;

	/**
	 * Hook into WordPress.
	 *
	 * The rollup table itself is provisioned by the central Upgrade runner
	 * (see class-upgrade.php, step 2). Live queries fall back gracefully when
	 * the rollup table is absent, so no schema work happens here.
	 *
	 * @return void
	 */
	public static function init() {
		global $wpdb;
		self::$rollup_table      = $wpdb->prefix . 'slms_analytics_daily';
		self::$user_course_table = $wpdb->prefix . 'slms_user_course';
		self::$history_table     = $wpdb->prefix . 'slms_course_history';

		// Nightly rollup cron — mirrors the Expiration daily-cron pattern.
		add_action( 'slms_daily_analytics_rollup', array( __CLASS__, 'run_daily_rollup' ) );
		if ( ! wp_next_scheduled( 'slms_daily_analytics_rollup' ) ) {
			// Schedule for ~02:15 site time to run after midnight boundaries.
			wp_schedule_event( time(), 'daily', 'slms_daily_analytics_rollup' );
		}
	}

	/* ───────────────────────────────────────────────────────────────────
	 * Schema
	 * ─────────────────────────────────────────────────────────────────── */

	/**
	 * Create the rollup table using dbDelta.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		if ( empty( self::$rollup_table ) ) {
			self::$rollup_table = $wpdb->prefix . 'slms_analytics_daily';
		}

		$charset_collate = $wpdb->get_charset_collate();

		// course_id = 0 is reserved for the site-wide aggregate row per day.
		$sql = 'CREATE TABLE ' . self::$rollup_table . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            snapshot_date date NOT NULL,
            course_id bigint(20) NOT NULL DEFAULT 0,
            enrollments int(11) NOT NULL DEFAULT 0,
            completions int(11) NOT NULL DEFAULT 0,
            certificates int(11) NOT NULL DEFAULT 0,
            active_students int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY date_course (snapshot_date, course_id),
            KEY snapshot_date (snapshot_date),
            KEY course_id (course_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/* ───────────────────────────────────────────────────────────────────
	 * Public query methods
	 * ─────────────────────────────────────────────────────────────────── */

	/**
	 * High-level overview: active students, enrollments/completions per period,
	 * certificates issued, trend deltas vs the preceding equal-length period,
	 * plus a daily enrollments-vs-completions time-series for charting.
	 *
	 * @param string|null $from Inclusive start date (Y-m-d). Defaults to 30 days ago.
	 * @param string|null $to   Inclusive end date (Y-m-d). Defaults to today.
	 * @return array
	 */
	public static function overview( $from = null, $to = null ) {
		global $wpdb;

		list($from, $to) = self::normalize_range( $from, $to );

		$from_ts = (int) strtotime( $from . ' 00:00:00' );
		$to_ts   = (int) strtotime( $to . ' 23:59:59' );
		$span    = max( 1, (int) ceil( ( $to_ts - $from_ts ) / DAY_IN_SECONDS ) );

		// Preceding period of equal length for delta comparison.
		$prev_to   = gmdate( 'Y-m-d', $from_ts - DAY_IN_SECONDS );
		$prev_from = gmdate( 'Y-m-d', $from_ts - ( $span * DAY_IN_SECONDS ) );

		$current  = self::period_totals( $from, $to );
		$previous = self::period_totals( $prev_from, $prev_to );

		$progress_rows    = class_exists( __NAMESPACE__ . '\Progress' ) ? Progress::row_count() : 0;
		$enrollment_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::$user_course_table );
		$needs_backfill   = ( $progress_rows === 0 && $enrollment_count > 0 );

		return array(
			'range'          => array(
				'from' => $from,
				'to'   => $to,
			),
			'needs_backfill' => $needs_backfill,
			'kpis'           => array(
				'active_students' => self::active_students_count(),
				'enrollments'     => $current['enrollments'],
				'completions'     => $current['completions'],
				'certificates'    => $current['certificates'],
			),
			'deltas'         => array(
				'enrollments'  => self::delta( $current['enrollments'], $previous['enrollments'] ),
				'completions'  => self::delta( $current['completions'], $previous['completions'] ),
				'certificates' => self::delta( $current['certificates'], $previous['certificates'] ),
			),
			'series'         => self::time_series( $from, $to ),
		);
	}

	/**
	 * Course funnel: enrolled → started → per-lesson completion → completed →
	 * certificate. Per-lesson counts are ordered by `_simple_lms_order`.
	 *
	 * @param int $course_id Course post ID.
	 * @return array
	 */
	public static function course_funnel( $course_id ) {
		$course_id  = absint( $course_id );
		$lesson_ids = self::lesson_order( $course_id );

		// Live, in-progress learners for this course + their progress maps.
		$active          = self::active_progress_for_course( $course_id );
		$active_enrolled = count( $active );
		$active_started  = 0;

		// Per-lesson completion tally among active learners.
		$lesson_active_counts = array_fill_keys( $lesson_ids, 0 );
		foreach ( $active as $progress_map ) {
			if ( ! empty( $progress_map ) ) {
				++$active_started;
			}
			foreach ( $lesson_ids as $lid ) {
				if ( isset( $progress_map[ $lid ] ) ) {
					++$lesson_active_counts[ $lid ];
				}
			}
		}

		// Durable completion / certificate records.
		$completed    = self::completed_count( $course_id );
		$certificates = self::certificate_count( $course_id );

		// Completed learners finished every lesson, so add them to each stage.
		$enrolled = $active_enrolled + $completed;
		$started  = $active_started + $completed;

		$lessons = array();
		foreach ( $lesson_ids as $lid ) {
			$post      = get_post( $lid );
			$lessons[] = array(
				'lesson_id' => $lid,
				'title'     => $post ? $post->post_title : sprintf( 'Lesson #%d', $lid ),
				'completed' => $lesson_active_counts[ $lid ] + $completed,
			);
		}

		return array(
			'course_id'    => $course_id,
			'course_title' => get_the_title( $course_id ),
			'stages'       => array(
				'enrolled'    => $enrolled,
				'started'     => $started,
				'completed'   => $completed,
				'certificate' => $certificates,
			),
			'lessons'      => $lessons,
		);
	}

	/**
	 * Lesson drop-off: completion delta between consecutive lessons.
	 *
	 * @param int $course_id Course post ID.
	 * @return array
	 */
	public static function lesson_dropoff( $course_id ) {
		$funnel  = self::course_funnel( $course_id );
		$lessons = $funnel['lessons'];
		$rows    = array();

		$total_lessons = count( $lessons );
		for ( $i = 1; $i < $total_lessons; $i++ ) {
			$prev    = $lessons[ $i - 1 ];
			$curr    = $lessons[ $i ];
			$dropped = max( 0, $prev['completed'] - $curr['completed'] );
			$pct     = $prev['completed'] > 0
				? round( ( $dropped / $prev['completed'] ) * 100, 1 )
				: 0.0;

			$rows[] = array(
				'from_lesson_id' => $prev['lesson_id'],
				'from_title'     => $prev['title'],
				'to_lesson_id'   => $curr['lesson_id'],
				'to_title'       => $curr['title'],
				'from_completed' => $prev['completed'],
				'to_completed'   => $curr['completed'],
				'dropped'        => $dropped,
				'drop_pct'       => $pct,
			);
		}

		return $rows;
	}

	/**
	 * Time-to-complete distribution for a course.
	 *
	 * Uses the enrollment→completion duration captured at completion time in the
	 * course_history `cert_data` metadata (`days_to_complete`). Older rows that
	 * predate duration capture are bucketed as "unknown".
	 *
	 * @param int $course_id Course post ID.
	 * @return array
	 */
	public static function time_to_complete( $course_id ) {
		global $wpdb;
		$course_id = absint( $course_id );
		$title     = get_the_title( $course_id );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT cert_data FROM ' . self::$history_table . ' WHERE course_name = %s',
				$title
			)
		);

		$buckets   = array(
			'lt_7'    => 0,
			'7_30'    => 0,
			'30_90'   => 0,
			'gt_90'   => 0,
			'unknown' => 0,
		);
		$durations = array();

		foreach ( (array) $rows as $row ) {
			$meta = maybe_unserialize( $row->cert_data );
			$days = ( is_array( $meta ) && isset( $meta['days_to_complete'] ) && $meta['days_to_complete'] !== null )
				? (float) $meta['days_to_complete']
				: null;

			if ( $days === null ) {
				++$buckets['unknown'];
				continue;
			}

			$durations[] = $days;
			if ( $days < 7 ) {
				++$buckets['lt_7'];
			} elseif ( $days < 30 ) {
				++$buckets['7_30'];
			} elseif ( $days < 90 ) {
				++$buckets['30_90'];
			} else {
				++$buckets['gt_90'];
			}
		}

		sort( $durations );
		$count  = count( $durations );
		$median = 0.0;
		if ( $count > 0 ) {
			$mid    = (int) floor( $count / 2 );
			$median = ( $count % 2 === 0 )
				? ( $durations[ $mid - 1 ] + $durations[ $mid ] ) / 2
				: $durations[ $mid ];
		}

		return array(
			'course_id'    => $course_id,
			'buckets'      => $buckets,
			'measured'     => $count,
			'average_days' => $count > 0 ? round( array_sum( $durations ) / $count, 1 ) : null,
			'median_days'  => $count > 0 ? round( $median, 1 ) : null,
		);
	}

	/**
	 * At-risk students: currently enrolled learners whose most recent progress
	 * is older than $days_inactive, or whose access is about to expire.
	 *
	 * @param int $days_inactive Inactivity threshold in days.
	 * @param int $expiring_within Expiry-forecast window in days.
	 * @return array List of at-risk rows.
	 */
	public static function at_risk( $days_inactive = 30, $expiring_within = 30 ) {
		global $wpdb;
		$days_inactive   = max( 1, absint( $days_inactive ) );
		$expiring_within = max( 0, absint( $expiring_within ) );
		$now             = time();
		$threshold       = $now - ( $days_inactive * DAY_IN_SECONDS );

		$progress_table = $wpdb->prefix . 'slms_lesson_progress';

		// All active enrollments, joined to progress table + enrolled-at meta.
		$rows = $wpdb->get_results(
			'SELECT uc.user_id, uc.course_id, uc.enrolled_at,
                    u.display_name, u.user_email,
                    em.meta_value AS enrolled_meta,
                    COUNT(lp.id) AS lessons_completed,
                    MAX(lp.completed_at) AS last_activity_date
             FROM ' . self::$user_course_table . " uc
             JOIN {$wpdb->users} u ON u.ID = uc.user_id
             LEFT JOIN {$wpdb->usermeta} em ON em.user_id = uc.user_id AND em.meta_key = '_lms_enrolled_at'
             LEFT JOIN {$progress_table} lp ON lp.user_id = uc.user_id AND lp.course_id = uc.course_id
             GROUP BY uc.user_id, uc.course_id, uc.enrolled_at, u.display_name, u.user_email, em.meta_value"
		);

		$result            = array();
		$access_days_cache = array();

		foreach ( (array) $rows as $row ) {
			$course_id = (int) $row->course_id;

			// Enrollment timestamp: prefer the meta map, fall back to the table.
			$enrolled_meta = maybe_unserialize( $row->enrolled_meta );
			$enrolled_ts   = ( is_array( $enrolled_meta ) && isset( $enrolled_meta[ $course_id ] ) )
				? (int) $enrolled_meta[ $course_id ]
				: (int) strtotime( (string) $row->enrolled_at );

			// Last activity = latest lesson-completion timestamp for this course,
			// falling back to enrollment time when nothing has been completed.
			$last_activity = $row->last_activity_date ? strtotime( $row->last_activity_date . ' UTC' ) : $enrolled_ts;

			$is_inactive = ( $last_activity !== 0 && $last_activity < $threshold );

			// Expiring-access forecast from _lms_access_days (0 = unlimited).
			if ( ! array_key_exists( $course_id, $access_days_cache ) ) {
				$access_days_cache[ $course_id ] = (int) get_post_meta( $course_id, '_lms_access_days', true );
			}
			$access_days       = $access_days_cache[ $course_id ];
			$expires_ts        = null;
			$days_until_expiry = null;
			$is_expiring       = false;
			if ( $access_days > 0 && $enrolled_ts ) {
				$expires_ts        = $enrolled_ts + ( $access_days * DAY_IN_SECONDS );
				$days_until_expiry = (int) floor( ( $expires_ts - $now ) / DAY_IN_SECONDS );
				$is_expiring       = ( $days_until_expiry <= $expiring_within );
			}

			if ( ! $is_inactive && ! $is_expiring ) {
				continue;
			}

			$reasons = array();
			if ( $is_inactive ) {
				$reasons[] = 'inactive';
			}
			if ( $is_expiring ) {
				$reasons[] = 'expiring';
			}

			$result[] = array(
				'user_id'           => (int) $row->user_id,
				'display_name'      => $row->display_name,
				'email'             => $row->user_email,
				'course_id'         => $course_id,
				'course_title'      => get_the_title( $course_id ),
				'enrolled_at'       => $enrolled_ts ? gmdate( 'c', $enrolled_ts ) : null,
				'last_activity'     => $last_activity ? gmdate( 'c', $last_activity ) : null,
				'days_inactive'     => $last_activity ? (int) floor( ( $now - $last_activity ) / DAY_IN_SECONDS ) : null,
				'access_expires'    => $expires_ts ? gmdate( 'c', $expires_ts ) : null,
				'days_until_expiry' => $days_until_expiry,
				'lessons_completed' => (int) $row->lessons_completed,
				'reasons'           => $reasons,
			);
		}

		// Most urgent first: soonest expiry, then longest inactive.
		usort(
			$result,
			function ( $a, $b ) {
				$ae = $a['days_until_expiry'];
				$be = $b['days_until_expiry'];
				if ( $ae !== null && $be !== null && $ae !== $be ) {
					return $ae <=> $be;
				}
				if ( $ae !== null && $be === null ) {
					return -1;
				}
				if ( $ae === null && $be !== null ) {
					return 1;
				}
				return ( $b['days_inactive'] ?? 0 ) <=> ( $a['days_inactive'] ?? 0 );
			}
		);

		return $result;
	}

	/* ───────────────────────────────────────────────────────────────────
	 * Nightly rollup
	 * ─────────────────────────────────────────────────────────────────── */

	/**
	 * Cron callback: snapshot today's per-course and site-wide aggregates into
	 * the rollup table. Idempotent — re-running for the same day overwrites.
	 *
	 * @return void
	 */
	public static function run_daily_rollup() {
		global $wpdb;

		$today     = current_time( 'Y-m-d' );
		$day_start = $today . ' 00:00:00';
		$day_end   = $today . ' 23:59:59';

		// New enrollments today, per course (in-progress table).
		$enroll_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT course_id, COUNT(*) AS c
             FROM ' . self::$user_course_table . '
             WHERE enrolled_at BETWEEN %s AND %s
             GROUP BY course_id',
				$day_start,
				$day_end
			),
			OBJECT_K
		);

		// Active students today, per course (current snapshot).
		$active_rows = $wpdb->get_results(
			'SELECT course_id, COUNT(DISTINCT user_id) AS c
             FROM ' . self::$user_course_table . '
             GROUP BY course_id',
			OBJECT_K
		);

		// Completions today, per course title → id.
		$completions_by_course = self::history_counts_by_course( $day_start, $day_end, false );
		$certs_by_course       = self::history_counts_by_course( $day_start, $day_end, true );

		// Union of every course id referenced today.
		$course_ids = array_unique(
			array_merge(
				array_map( 'intval', array_keys( $enroll_rows ) ),
				array_map( 'intval', array_keys( $active_rows ) ),
				array_keys( $completions_by_course ),
				array_keys( $certs_by_course )
			)
		);

		$site_totals = array(
			'enrollments'     => 0,
			'completions'     => 0,
			'certificates'    => 0,
			'active_students' => 0,
		);

		foreach ( $course_ids as $cid ) {
			$cid = (int) $cid;
			if ( $cid <= 0 ) {
				continue;
			}
			$enrollments = isset( $enroll_rows[ $cid ] ) ? (int) $enroll_rows[ $cid ]->c : 0;
			$active      = isset( $active_rows[ $cid ] ) ? (int) $active_rows[ $cid ]->c : 0;
			$completions = isset( $completions_by_course[ $cid ] ) ? (int) $completions_by_course[ $cid ] : 0;
			$certs       = isset( $certs_by_course[ $cid ] ) ? (int) $certs_by_course[ $cid ] : 0;

			self::upsert_rollup( $today, $cid, $enrollments, $completions, $certs, $active );

			$site_totals['enrollments']     += $enrollments;
			$site_totals['completions']     += $completions;
			$site_totals['certificates']    += $certs;
			$site_totals['active_students'] += $active;
		}

		// Site-wide aggregate row (course_id = 0). Active students counts distinct
		// users, not the sum of per-course actives.
		$site_active = (int) $wpdb->get_var(
			'SELECT COUNT(DISTINCT user_id) FROM ' . self::$user_course_table
		);
		self::upsert_rollup(
			$today,
			0,
			$site_totals['enrollments'],
			$site_totals['completions'],
			$site_totals['certificates'],
			$site_active
		);
	}

	/**
	 * Insert or update a single rollup row.
	 *
	 * @param string $date        Snapshot date (Y-m-d).
	 * @param int    $course_id   Course ID (0 = site aggregate).
	 * @param int    $enrollments Enrollment count.
	 * @param int    $completions Completion count.
	 * @param int    $certs       Certificate count.
	 * @param int    $active      Active-student count.
	 * @return void
	 */
	private static function upsert_rollup( $date, $course_id, $enrollments, $completions, $certs, $active ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::$rollup_table . '
                (snapshot_date, course_id, enrollments, completions, certificates, active_students)
             VALUES (%s, %d, %d, %d, %d, %d)
             ON DUPLICATE KEY UPDATE
                enrollments = VALUES(enrollments),
                completions = VALUES(completions),
                certificates = VALUES(certificates),
                active_students = VALUES(active_students)',
				$date,
				$course_id,
				$enrollments,
				$completions,
				$certs,
				$active
			)
		);
	}

	/* ───────────────────────────────────────────────────────────────────
	 * Internal helpers
	 * ─────────────────────────────────────────────────────────────────── */

	/**
	 * Normalize a date range, defaulting to the trailing 30 days.
	 *
	 * @param string|null $from Start (Y-m-d).
	 * @param string|null $to   End (Y-m-d).
	 * @return array{0:string,1:string}
	 */
	private static function normalize_range( $from, $to ) {
		$to   = $to ? gmdate( 'Y-m-d', (int) strtotime( $to ) ) : (string) current_time( 'Y-m-d' );
		$from = $from ? gmdate( 'Y-m-d', (int) strtotime( $from ) ) : gmdate( 'Y-m-d', (int) strtotime( $to . ' -29 days' ) );

		// Guard against inverted ranges.
		if ( (int) strtotime( $from ) > (int) strtotime( $to ) ) {
			$tmp  = $from;
			$from = $to;
			$to   = $tmp;
		}

		return array( $from, $to );
	}

	/**
	 * Period totals for enrollments, completions and certificates.
	 *
	 * @param string $from Start (Y-m-d).
	 * @param string $to   End (Y-m-d).
	 * @return array
	 */
	private static function period_totals( $from, $to ) {
		global $wpdb;
		$start = $from . ' 00:00:00';
		$end   = $to . ' 23:59:59';

		$enrollments = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::$user_course_table . ' WHERE enrolled_at BETWEEN %s AND %s',
				$start,
				$end
			)
		);

		$completions = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::$history_table . ' WHERE completed_date BETWEEN %s AND %s',
				$start,
				$end
			)
		);

		$certificates = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::$history_table . '
             WHERE completed_date BETWEEN %s AND %s AND gf_entry_id IS NOT NULL AND gf_entry_id > 0',
				$start,
				$end
			)
		);

		return compact( 'enrollments', 'completions', 'certificates' );
	}

	/**
	 * Daily enrollments-vs-completions series for charting. Rollup-backed when
	 * data is present, otherwise computed live.
	 *
	 * @param string $from Start (Y-m-d).
	 * @param string $to   End (Y-m-d).
	 * @return array List of { date, enrollments, completions } day rows.
	 */
	private static function time_series( $from, $to ) {
		global $wpdb;

		// Prefer the rollup table (site aggregate rows) when it covers the range.
		$rollup = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT snapshot_date AS date, enrollments, completions
             FROM ' . self::$rollup_table . '
             WHERE course_id = 0 AND snapshot_date BETWEEN %s AND %s
             ORDER BY snapshot_date ASC',
				$from,
				$to
			),
			OBJECT_K
		);

		// Live daily aggregates as the fallback / gap-filler.
		$enroll_live = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(enrolled_at) AS date, COUNT(*) AS c
             FROM ' . self::$user_course_table . '
             WHERE enrolled_at BETWEEN %s AND %s
             GROUP BY DATE(enrolled_at)',
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			OBJECT_K
		);

		$complete_live = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(completed_date) AS date, COUNT(*) AS c
             FROM ' . self::$history_table . '
             WHERE completed_date BETWEEN %s AND %s
             GROUP BY DATE(completed_date)',
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			OBJECT_K
		);

		$series = array();
		$cursor = (int) strtotime( $from );
		$end    = (int) strtotime( $to );
		while ( $cursor <= $end ) {
			$day = gmdate( 'Y-m-d', $cursor );
			if ( isset( $rollup[ $day ] ) ) {
				$enrollments = (int) $rollup[ $day ]->enrollments;
				$completions = (int) $rollup[ $day ]->completions;
			} else {
				$enrollments = isset( $enroll_live[ $day ] ) ? (int) $enroll_live[ $day ]->c : 0;
				$completions = isset( $complete_live[ $day ] ) ? (int) $complete_live[ $day ]->c : 0;
			}
			$series[] = array(
				'date'        => $day,
				'enrollments' => $enrollments,
				'completions' => $completions,
			);
			$cursor  += DAY_IN_SECONDS;
		}

		return $series;
	}

	/**
	 * Distinct users currently enrolled in at least one course.
	 *
	 * @return int
	 */
	private static function active_students_count() {
		global $wpdb;
		return (int) $wpdb->get_var(
			'SELECT COUNT(DISTINCT user_id) FROM ' . self::$user_course_table
		);
	}

	/**
	 * Ordered lesson IDs for a course from `_simple_lms_order`.
	 *
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	private static function lesson_order( $course_id ) {
		$order = get_post_meta( $course_id, '_simple_lms_order', true );
		if ( ! is_array( $order ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $order ) ) );
	}

	/**
	 * Progress maps for every learner actively enrolled in a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array List of per-course progress maps ([lesson_id => ts]).
	 */
	private static function active_progress_for_course( $course_id ) {
		global $wpdb;

		$progress_table = $wpdb->prefix . 'slms_lesson_progress';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT uc.user_id, lp.lesson_id, lp.completed_at
             FROM ' . self::$user_course_table . " uc
             LEFT JOIN {$progress_table} lp ON lp.user_id = uc.user_id AND lp.course_id = uc.course_id
             WHERE uc.course_id = %d",
				$course_id
			)
		);

		$maps = array();
		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row->user_id;
			if ( ! isset( $maps[ $user_id ] ) ) {
				$maps[ $user_id ] = array();
			}
			if ( $row->lesson_id !== null ) {
				$maps[ $user_id ][ (int) $row->lesson_id ] = strtotime( $row->completed_at . ' UTC' );
			}
		}
		return array_values( $maps );
	}

	/**
	 * All-time completion count for a course (durable history records).
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	private static function completed_count( $course_id ) {
		global $wpdb;
		$title = get_the_title( $course_id );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::$history_table . ' WHERE course_name = %s',
				$title
			)
		);
	}

	/**
	 * All-time certificate count for a course (history rows with a GF entry).
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	private static function certificate_count( $course_id ) {
		global $wpdb;
		$title = get_the_title( $course_id );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::$history_table . '
             WHERE course_name = %s AND gf_entry_id IS NOT NULL AND gf_entry_id > 0',
				$title
			)
		);
	}

	/**
	 * History completion/certificate counts within a window, keyed by course ID.
	 *
	 * Course history stores course_name (title), so titles are resolved back to
	 * IDs via a title→id lookup over published courses.
	 *
	 * @param string $start        Range start (mysql datetime).
	 * @param string $end          Range end (mysql datetime).
	 * @param bool   $certificates When true, count only rows with a GF entry.
	 * @return array [course_id => count]
	 */
	private static function history_counts_by_course( $start, $end, $certificates = false ) {
		global $wpdb;

		$cert_clause = $certificates ? ' AND gf_entry_id IS NOT NULL AND gf_entry_id > 0' : '';
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT course_name, COUNT(*) AS c
             FROM ' . self::$history_table . '
             WHERE completed_date BETWEEN %s AND %s' . $cert_clause . '
             GROUP BY course_name',
				$start,
				$end
			)
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$title_to_id = self::course_title_index();
		$out         = array();
		foreach ( $rows as $row ) {
			$cid = isset( $title_to_id[ $row->course_name ] ) ? (int) $title_to_id[ $row->course_name ] : 0;
			if ( $cid <= 0 ) {
				continue;
			}
			$out[ $cid ] = isset( $out[ $cid ] ) ? $out[ $cid ] + (int) $row->c : (int) $row->c;
		}
		return $out;
	}

	/**
	 * Map of course title → course ID for published courses.
	 *
	 * @return array
	 */
	private static function course_title_index() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'slms_course' AND post_status = 'publish'"
		);
		$map  = array();
		foreach ( (array) $rows as $row ) {
			$map[ $row->post_title ] = (int) $row->ID;
		}
		return $map;
	}

	/**
	 * Percentage delta between a current and previous value.
	 *
	 * @param int|float $current  Current value.
	 * @param int|float $previous Previous value.
	 * @return array { value, previous, change, pct }
	 */
	private static function delta( $current, $previous ) {
		$change = $current - $previous;
		$pct    = $previous > 0
			? round( ( $change / $previous ) * 100, 1 )
			: ( $current > 0 ? 100.0 : 0.0 );

		return array(
			'value'    => $current,
			'previous' => $previous,
			'change'   => $change,
			'pct'      => $pct,
		);
	}

	/* ───────────────────────────────────────────────────────────────────
	 * CSV export
	 * ─────────────────────────────────────────────────────────────────── */

	/**
	 * Build a CSV payload (filename + rows) for a named report.
	 *
	 * @param string $report  Report key: overview|course|at-risk.
	 * @param array  $args     Report arguments (course_id, from, to, days).
	 * @return array { filename, header, rows }
	 */
	public static function build_csv( $report, $args = array() ) {
		switch ( $report ) {
			case 'course':
				return self::csv_course( (int) ( $args['course_id'] ?? 0 ) );
			case 'at-risk':
				return self::csv_at_risk( (int) ( $args['days'] ?? 30 ) );
			case 'overview':
			default:
				return self::csv_overview( $args['from'] ?? null, $args['to'] ?? null );
		}
	}

	/**
	 * Overview time-series CSV.
	 *
	 * @param string|null $from Start.
	 * @param string|null $to   End.
	 * @return array
	 */
	private static function csv_overview( $from, $to ) {
		$data = self::overview( $from, $to );
		$rows = array();
		foreach ( $data['series'] as $point ) {
			$rows[] = array( $point['date'], $point['enrollments'], $point['completions'] );
		}
		return array(
			'filename' => 'slms-overview-' . $data['range']['from'] . '_' . $data['range']['to'] . '.csv',
			'header'   => array( 'Date', 'Enrollments', 'Completions' ),
			'rows'     => $rows,
		);
	}

	/**
	 * Course funnel + drop-off CSV.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function csv_course( $course_id ) {
		$funnel = self::course_funnel( $course_id );
		$rows   = array();
		$rows[] = array( 'Stage', 'Count' );
		foreach ( $funnel['stages'] as $stage => $count ) {
			$rows[] = array( ucfirst( $stage ), $count );
		}
		$rows[] = array( '', '' );
		$rows[] = array( 'Lesson', 'Completed' );
		foreach ( $funnel['lessons'] as $lesson ) {
			$rows[] = array( $lesson['title'], $lesson['completed'] );
		}

		return array(
			'filename' => 'slms-course-' . $course_id . '.csv',
			'header'   => array( $funnel['course_title'], '' ),
			'rows'     => $rows,
		);
	}

	/**
	 * At-risk students CSV.
	 *
	 * @param int $days Inactivity threshold.
	 * @return array
	 */
	private static function csv_at_risk( $days ) {
		$students = self::at_risk( $days );
		$rows     = array();
		foreach ( $students as $s ) {
			$rows[] = array(
				$s['display_name'],
				$s['email'],
				$s['course_title'],
				$s['enrolled_at'],
				$s['last_activity'],
				$s['days_inactive'],
				$s['access_expires'],
				$s['days_until_expiry'],
				implode( '|', $s['reasons'] ),
			);
		}
		return array(
			'filename' => 'slms-at-risk-' . $days . 'd.csv',
			'header'   => array(
				'Student',
				'Email',
				'Course',
				'Enrolled',
				'Last Activity',
				'Days Inactive',
				'Access Expires',
				'Days Until Expiry',
				'Reasons',
			),
			'rows'     => $rows,
		);
	}
}
