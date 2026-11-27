<?php
/**
 * Course access resolution.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves access from the configured site source of truth.
 */
final class Access {
	/**
	 * Per-request access cache.
	 *
	 * @var array<string, bool>
	 */
	private static array $cache = array();

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

		if ( $course_id < 1 || $user_id < 1 || 'publish' !== get_post_status( $course_id ) ) {
			return false;
		}

		$cache_key = $user_id . ':' . $course_id;
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$method  = (string) Settings::get( 'access_method', 'pods' );
		$allowed = false;

		switch ( $method ) {
			case 'logged_in':
				$allowed = true;
				break;
			case 'pods':
				$allowed = self::from_pods( $course_id, $user_id );
				break;
			case 'woocommerce':
				$allowed = self::from_woocommerce( $course_id, $user_id );
				break;
			case 'pmpro':
				$allowed = self::from_pmpro( $course_id, $user_id );
				break;
		}

		/**
		 * Filters the final course access decision.
		 *
		 * @param bool $allowed   Whether access is allowed.
		 * @param int  $user_id   User ID.
		 * @param int  $course_id Course post ID.
		 */
		$allowed = (bool) apply_filters( 'simple_lms_user_course_access', $allowed, $user_id, $course_id );

		/**
		 * Backward-compatible alias for the course access filter.
		 *
		 * @param bool $allowed   Whether access is allowed.
		 * @param int  $course_id Course post ID.
		 * @param int  $user_id   User ID.
		 */
		$allowed = (bool) apply_filters( 'simple_lms_user_can_access_course', $allowed, $course_id, $user_id );

		self::$cache[ $cache_key ] = $allowed;

		return $allowed;
	}

	/**
	 * Get published course IDs available to a user through the configured source.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public static function accessible_course_ids( int $user_id = 0 ): array {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id < 1 || 'pods' !== Settings::get( 'access_method', 'pods' ) ) {
			return array();
		}

		$field    = (string) Settings::get( 'pods_access_field', '' );
		$meta_key = (string) Settings::get( 'access_meta_key', '' );
		$values   = array();

		if ( '' !== $field && function_exists( 'pods' ) ) {
			$pod = pods( 'user', $user_id );
			if ( $pod && $pod->exists() ) {
				$values = self::normalize_list( $pod->field( $field ) );
			}
		}

		if ( '' !== $meta_key ) {
			$values = array_merge( $values, self::normalize_list( get_user_meta( $user_id, $meta_key, true ) ) );
		}

		$course_type = (string) Settings::get( 'course_post_type', 'course' );
		$course_ids  = array_values(
			array_unique(
				array_filter(
					$values,
					static fn( int $id ): bool => $id > 0
						&& $course_type === get_post_type( $id )
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
	 * Resolve access from a Pods user relationship or user meta.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	private static function from_pods( int $course_id, int $user_id ): bool {
		$field = (string) Settings::get( 'pods_access_field', '' );

		if ( '' !== $field && function_exists( 'pods' ) ) {
			$pod = pods( 'user', $user_id );
			if ( $pod && $pod->exists() ) {
				$related = $pod->field( $field );
				if ( self::normalize_ids( $related, $course_id ) ) {
					return true;
				}
			}
		}

		$meta_key = (string) Settings::get( 'access_meta_key', '_slms_accessible_courses' );
		if ( '' === $meta_key ) {
			return false;
		}

		return self::normalize_ids( get_user_meta( $user_id, $meta_key, true ), $course_id );
	}

	/**
	 * Resolve access from paid WooCommerce orders.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	private static function from_woocommerce( int $course_id, int $user_id ): bool {
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_order_statuses' ) ) {
			return false;
		}

		$meta_key = (string) Settings::get( 'woocommerce_course_meta', '_slms_course_id' );
		if ( '' === $meta_key ) {
			return false;
		}

		$statuses = array_keys( wc_get_order_statuses() );
		$statuses = array_values(
			array_diff(
				$statuses,
				array( 'wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-trash' )
			)
		);

		$order_ids = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'status'      => $statuses,
				'return'      => 'ids',
			)
		);

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$mapped     = get_post_meta( $product_id, $meta_key, true );
				$item_value = $item->get_meta( $meta_key );

				if ( self::normalize_ids( $mapped, $course_id ) || self::normalize_ids( $item_value, $course_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Resolve access from PMPro membership levels.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	private static function from_pmpro( int $course_id, int $user_id ): bool {
		if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return false;
		}

		$meta_key = (string) Settings::get( 'pmpro_course_meta', '_slms_course_ids' );
		if ( '' === $meta_key ) {
			return false;
		}

		$levels = pmpro_getMembershipLevelsForUser( $user_id );
		foreach ( $levels as $level ) {
			$level_id = is_object( $level ) ? (int) $level->ID : (int) $level;
			if ( self::normalize_ids( get_post_meta( $level_id, $meta_key, true ), $course_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize scalar, serialized array, or Pods relationship values to IDs.
	 *
	 * @param mixed $value Relationship value.
	 * @return int[]
	 */
	private static function normalize_list( mixed $value ): array {
		if ( is_object( $value ) && method_exists( $value, 'ID' ) ) {
			$value = $value->ID();
		}

		$items = is_array( $value ) ? $value : array( $value );
		$ids   = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$item = $item['ID'] ?? $item['id'] ?? $item['post_id'] ?? 0;
			} elseif ( is_object( $item ) && method_exists( $item, 'ID' ) ) {
				$item = $item->ID();
			}

			$id = absint( $item );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether normalized relationship values contain a course ID.
	 *
	 * @param mixed $value     Relationship value.
	 * @param int   $course_id Course post ID.
	 * @return bool
	 */
	private static function normalize_ids( mixed $value, int $course_id ): bool {
		return in_array( $course_id, self::normalize_list( $value ), true );
	}

	/**
	 * Clear request cache after settings or order changes.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
