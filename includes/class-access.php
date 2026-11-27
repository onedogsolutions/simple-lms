<?php
/**
 * Course access and native enrollment.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves native enrollment with optional commerce and membership adapters.
 */
final class Access {
	private static array $cache = array();

	/**
	 * Register native enrollment meta.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'register_meta', array( self::class, 'register_meta' ) );
	}

	/**
	 * Register the enrollment array with the REST meta API.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		register_meta(
			'user',
			Settings::ENROLLMENT_META,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( Relationships::class, 'normalize_ids' ),
				'auth_callback'     => static fn(): bool => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Whether a user can access a course.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID. Defaults to current user.
	 * @return bool
	 */
	public static function can_access( int $course_id, int $user_id = 0 ): bool {
		$course_id = absint( $course_id );
		$user_id   = $user_id > 0 ? $user_id : get_current_user_id();

		if ( $course_id < 1 || $user_id < 1 || Post_Types::COURSE !== get_post_type( $course_id )
			|| 'publish' !== get_post_status( $course_id ) ) {
			return false;
		}

		$cache_key = $user_id . ':' . $course_id;
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$allowed = match ( (string) Settings::get( 'access_method', 'native' ) ) {
			'native'       => self::from_native( $course_id, $user_id ),
			'logged_in'    => true,
			'woocommerce'  => self::from_woocommerce( $course_id, $user_id ),
			'pmpro'        => self::from_pmpro( $course_id, $user_id ),
			default        => false,
		};

		/** This filter is documented in includes/class-access.php. */
		$allowed = (bool) apply_filters( 'simple_lms_user_course_access', $allowed, $user_id, $course_id );
		/** Backward-compatible alias for the course access filter. */
		$allowed = (bool) apply_filters( 'simple_lms_user_can_access_course', $allowed, $course_id, $user_id );
		self::$cache[ $cache_key ] = $allowed;

		return $allowed;
	}

	/**
	 * Enroll a user in one or more native courses.
	 *
	 * @param int   $user_id   User ID.
	 * @param int[] $course_ids Course post IDs.
	 * @return bool
	 */
	public static function enroll_user( int $user_id, array $course_ids ): bool {
		$user_id = absint( $user_id );
		if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
			return false;
		}

		$current = self::enrolled_course_ids( $user_id );
		$added   = array();
		foreach ( $course_ids as $course_id ) {
			$course_id = absint( $course_id );
			if ( Post_Types::COURSE === get_post_type( $course_id ) && ! in_array( $course_id, $current, true ) ) {
				$current[] = $course_id;
				$added[]   = $course_id;
			}
		}
		if ( empty( $added ) ) {
			return true;
		}

		$written = update_user_meta( $user_id, Settings::ENROLLMENT_META, array_values( array_unique( $current ) ) );
		if ( false === $written && self::enrolled_course_ids( $user_id, true ) !== $current ) {
			return false;
		}
		self::flush_cache();
		do_action( 'simple_lms_user_enrolled', $user_id, $added );
		return true;
	}

	/**
	 * Remove native course enrollments for a user.
	 *
	 * @param int   $user_id   User ID.
	 * @param int[] $course_ids Course post IDs.
	 * @return bool
	 */
	public static function unenroll_user( int $user_id, array $course_ids ): bool {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		$current  = self::enrolled_course_ids( $user_id );
		$removed  = array_values( array_intersect( $current, array_map( 'absint', $course_ids ) ) );
		$desired  = array_values( array_diff( $current, $removed ) );
		if ( empty( $removed ) ) {
			return true;
		}
		$written = update_user_meta( $user_id, Settings::ENROLLMENT_META, $desired );
		if ( false === $written && self::enrolled_course_ids( $user_id, true ) !== $desired ) {
			return false;
		}
		self::flush_cache();
		do_action( 'simple_lms_user_unenrolled', $user_id, $removed );
		return true;
	}

	/**
	 * Get a user's native enrollment list.
	 *
	 * @param int  $user_id User ID.
	 * @param bool $refresh Bypass the per-request access cache.
	 * @return int[]
	 */
	public static function enrolled_course_ids( int $user_id, bool $refresh = false ): array {
		$user_id = absint( $user_id );
		if ( $refresh ) {
			wp_cache_delete( $user_id, 'user_meta' );
		}
		return Relationships::normalize_ids( get_user_meta( $user_id, Settings::ENROLLMENT_META, true ) );
	}

	/**
	 * Get published course IDs available through the configured adapter.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public static function accessible_course_ids( int $user_id = 0 ): array {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id < 1 ) {
			return array();
		}

		$method = (string) Settings::get( 'access_method', 'native' );
		if ( 'native' === $method ) {
			$values = self::enrolled_course_ids( $user_id );
		} elseif ( 'logged_in' === $method ) {
			$values = get_posts(
				array(
					'post_type'      => Post_Types::COURSE,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
		} elseif ( 'woocommerce' === $method ) {
			$values = self::woocommerce_course_ids( $user_id );
		} elseif ( 'pmpro' === $method ) {
			$values = self::pmpro_course_ids( $user_id );
		} else {
			$values = array();
		}

		$course_ids = array_values(
			array_unique(
				array_filter(
					Relationships::normalize_ids( $values ),
					static fn( int $id ): bool => Post_Types::COURSE === get_post_type( $id )
						&& 'publish' === get_post_status( $id )
				)
			)
		);

		/**
		 * Filters the course IDs available to a user.
		 *
		 * @param int[] $course_ids Published course IDs.
		 * @param int   $user_id    User ID.
		 */
		return array_values( array_filter( (array) apply_filters( 'simple_lms_accessible_course_ids', $course_ids, $user_id ), 'absint' ) );
	}

	/**
	 * Resolve access from native enrollment meta.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	private static function from_native( int $course_id, int $user_id ): bool {
		return in_array( $course_id, self::enrolled_course_ids( $user_id ), true );
	}

	/**
	 * Resolve access from paid WooCommerce orders.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	private static function from_woocommerce( int $course_id, int $user_id ): bool {
		return in_array( $course_id, self::woocommerce_course_ids( $user_id ), true );
	}

	/**
	 * Get course IDs mapped to the user's paid WooCommerce products.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	private static function woocommerce_course_ids( int $user_id ): array {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		$meta_key = (string) Settings::get( 'woocommerce_course_meta', '_slms_course_id' );
		$statuses = array_values(
			array_diff(
				array_keys( wc_get_order_statuses() ),
				array( 'wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-trash' )
			)
		);
		$course_ids = array();
		$order_ids  = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'status'      => $statuses,
				'return'      => 'ids',
			)
		);
		foreach ( (array) $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			foreach ( $order->get_items() as $item ) {
				$course_ids = array_merge(
					$course_ids,
					Relationships::normalize_ids( get_post_meta( $item->get_product_id(), $meta_key, true ) ),
					Relationships::normalize_ids( $item->get_meta( $meta_key ) )
				);
			}
		}
		return array_values( array_unique( $course_ids ) );
	}

	/**
	 * Resolve access from PMPro membership levels.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	private static function from_pmpro( int $course_id, int $user_id ): bool {
		return in_array( $course_id, self::pmpro_course_ids( $user_id ), true );
	}

	/**
	 * Get course IDs mapped to the user's PMPro levels.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	private static function pmpro_course_ids( int $user_id ): array {
		if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return array();
		}
		$meta_key   = (string) Settings::get( 'pmpro_course_meta', '_slms_course_ids' );
		$course_ids = array();
		foreach ( (array) pmpro_getMembershipLevelsForUser( $user_id ) as $level ) {
			$level_id = is_object( $level ) ? (int) $level->ID : (int) $level;
			$course_ids = array_merge( $course_ids, Relationships::normalize_ids( get_post_meta( $level_id, $meta_key, true ) ) );
		}
		return array_values( array_unique( $course_ids ) );
	}

	/**
	 * Clear request cache after settings, enrollment, or order changes.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
