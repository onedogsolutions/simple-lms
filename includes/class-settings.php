<?php
/**
 * Settings storage and sanitization.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores plugin settings without assuming a site-specific Pods schema.
 */
final class Settings {
	/**
	 * Option name.
	 */
	private const OPTION = 'simple_lms_settings';

	/**
	 * Default settings verified against the staging integration.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'course_post_type'                    => 'course',
			'lesson_post_type'                    => 'course',
			'relationship_mode'                   => 'post_parent',
			'relationship_field'                  => '',
			'relationship_storage'                => 'meta',
			'relationship_direction'              => 'course_to_lessons',
			'lesson_order_field'                  => 'menu_order',
			'standalone_courses_as_lessons'       => true,
			'access_method'                       => 'pods',
			'pods_access_field'                   => 'enrolled_courses',
			'access_meta_key'                     => 'enrolled_courses',
			'woocommerce_course_meta'             => '_slms_course_id',
			'pmpro_course_meta'                   => '_slms_course_ids',
			'tracking_enabled'                    => true,
			'presto_event'                        => 'endedMedia',
			'verification_page_id'                => 465,
			'verification_form_id'                => 5,
			'course_field_id'                     => '18',
			'course_title_field_id'               => '',
			'user_name_field_id'                  => '1',
			'user_name_first_field_id'            => '1.3',
			'user_name_last_field_id'             => '1.6',
			'user_email_field_id'                 => '17',
			'state_field_id'                      => '6',
			'completion_date_field_id'            => '21',
			'pdf_template_id'                     => 334,
			'pdf_template_slug'                   => 'aalp-certificate',
			'account_page_id'                     => 322,
			'completed_lessons_meta'              => '_slms_completed_lessons',
			'completed_courses_meta'              => '_slms_completed_courses',
			'verified_courses_meta'               => '_slms_verified_courses',
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_replace( self::defaults(), $saved );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::all();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$clean = array();

		$slugs = array( 'course_post_type', 'lesson_post_type' );
		foreach ( $slugs as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = sanitize_key( (string) $input[ $key ] );
			}
		}

		$keys = array(
			'relationship_field',
			'lesson_order_field',
			'pods_access_field',
			'access_meta_key',
			'woocommerce_course_meta',
			'pmpro_course_meta',
			'presto_event',
			'course_field_id',
			'course_title_field_id',
			'user_name_field_id',
			'user_name_first_field_id',
			'user_name_last_field_id',
			'user_email_field_id',
			'state_field_id',
			'completion_date_field_id',
			'pdf_template_slug',
			'completed_lessons_meta',
			'completed_courses_meta',
			'verified_courses_meta',
		);
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}

		$ids = array( 'verification_page_id', 'verification_form_id', 'pdf_template_id', 'account_page_id' );
		foreach ( $ids as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = absint( $input[ $key ] );
			}
		}

		$booleans = array( 'tracking_enabled', 'standalone_courses_as_lessons' );
		foreach ( $booleans as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = rest_sanitize_boolean( $input[ $key ] );
			}
		}

		$enums = array(
			'relationship_mode'     => array( 'post_parent', 'pods_field' ),
			'relationship_storage'   => array( 'meta', 'table' ),
			'relationship_direction' => array( 'course_to_lessons', 'lesson_to_course' ),
			'access_method'          => array( 'pods', 'woocommerce', 'pmpro', 'logged_in' ),
		);
		foreach ( $enums as $key => $allowed ) {
			if ( array_key_exists( $key, $input ) ) {
				$value = sanitize_key( (string) $input[ $key ] );
				$clean[ $key ] = in_array( $value, $allowed, true ) ? $value : self::defaults()[ $key ];
			}
		}

		return $clean;
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function update( array $input ): array {
		$settings = array_replace( self::all(), self::sanitize( $input ) );
		update_option( self::OPTION, $settings, false );

		return $settings;
	}

	/**
	 * Whether required integration values are present.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$settings = self::all();

		$relationship_ok = 'post_parent' === $settings['relationship_mode']
			|| '' !== $settings['relationship_field'];
		$access_ok = match ( $settings['access_method'] ) {
			'pods'        => '' !== $settings['pods_access_field'] || '' !== $settings['access_meta_key'],
			'woocommerce' => '' !== $settings['woocommerce_course_meta'],
			'pmpro'       => '' !== $settings['pmpro_course_meta'],
			'logged_in'   => true,
			default       => false,
		};

		return $relationship_ok
			&& $access_ok
			&& post_type_exists( (string) $settings['course_post_type'] )
			&& post_type_exists( (string) $settings['lesson_post_type'] )
			&& absint( $settings['verification_form_id'] ) > 0
			&& '' !== $settings['course_field_id'];
	}
}
