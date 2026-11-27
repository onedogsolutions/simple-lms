<?php
/**
 * Gravity Forms verification integration.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pre-populates and validates the post-course verification form.
 */
final class Gravity_Forms {
	/**
	 * Register Gravity Forms hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'gform_pre_render', array( self::class, 'prepopulate' ), 10, 1 );
		add_filter( 'gform_validation', array( self::class, 'validate' ), 10, 1 );
		add_action( 'gform_after_submission', array( self::class, 'after_submission' ), 10, 2 );
	}

	/**
	 * Pre-populate course and user fields on the configured form.
	 *
	 * @param array<string, mixed> $form Gravity Forms form.
	 * @return array<string, mixed>
	 */
	public static function prepopulate( array $form ): array {
		if ( ! self::is_verification_form( $form ) ) {
			return $form;
		}

		$course_id = self::course_id_from_request();
		if ( $course_id < 1 ) {
			return $form;
		}

		$user       = wp_get_current_user();
		$course_url = self::course_choice_value( $course_id, $form );
		$values     = array(
			(string) Settings::get( 'course_field_id', '' )       => $course_url,
			(string) Settings::get( 'course_title_field_id', '' ) => get_the_title( $course_id ),
			(string) Settings::get( 'user_email_field_id', '' )   => $user->user_email,
		);

		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			$field_id = (string) ( $field->id ?? '' );
			if ( '' === $field_id ) {
				continue;
			}

			if ( $field_id === (string) Settings::get( 'user_name_field_id', '' ) && 'name' === ( $field->type ?? '' ) ) {
				$first_id = (string) Settings::get( 'user_name_first_field_id', '' );
				$last_id  = (string) Settings::get( 'user_name_last_field_id', '' );
				$inputs   = (array) ( $field->inputs ?? array() );
				foreach ( $inputs as $index => $input ) {
					$input_id = (string) ( $input['id'] ?? '' );
					if ( $first_id === $input_id ) {
						$inputs[ $index ]['defaultValue'] = $user->first_name;
					} elseif ( $last_id === $input_id ) {
						$inputs[ $index ]['defaultValue'] = $user->last_name;
					}
				}
				$field->inputs = $inputs;
				continue;
			}

			if ( ! array_key_exists( $field_id, $values ) || '' === $values[ $field_id ] ) {
				continue;
			}

			if ( in_array( $field->type ?? '', array( 'select', 'radio', 'checkbox' ), true ) ) {
				$field->choices = array_map(
					static function ( array $choice ) use ( $values, $field_id ): array {
						$choice['isSelected'] = $values[ $field_id ] === ( $choice['value'] ?? '' );
						return $choice;
					},
					(array) $field->choices
				);
			} else {
				$field->defaultValue = $values[ $field_id ];
			}
		}

		return $form;
	}

	/**
	 * Validate course ownership and completion before accepting verification.
	 *
	 * @param array<string, mixed> $result Gravity Forms validation result.
	 * @return array<string, mixed>
	 */
	public static function validate( array $result ): array {
		if ( ! self::is_verification_form( $result['form'] ?? array() ) ) {
			return $result;
		}

		$course_id = self::submitted_course_id();
		$message   = '';

		if ( ! is_user_logged_in() ) {
			$message = __( 'Sign in before verifying your course.', 'simple-lms' );
		} elseif ( $course_id < 1 || 'publish' !== get_post_status( $course_id ) ) {
			$message = __( 'The selected course is not available.', 'simple-lms' );
		} elseif ( ! Access::can_access( $course_id ) ) {
			$message = __( 'Your account does not have access to this course.', 'simple-lms' );
		} elseif ( ! Progress::course_progress( $course_id )['complete'] ) {
			$message = __( 'Complete every lesson before requesting verification.', 'simple-lms' );
		}

		if ( '' !== $message ) {
			$result['is_valid'] = false;
			$result['form']['validation_message'] = $message;
		}

		return $result;
	}

	/**
	 * Record a successful verification.
	 *
	 * @param array<string, mixed> $entry Gravity Forms entry.
	 * @param array<string, mixed> $form  Gravity Forms form.
	 * @return void
	 */
	public static function after_submission( array $entry, array $form ): void {
		if ( ! self::is_verification_form( $form ) || ! is_user_logged_in() ) {
			return;
		}

		$field_id  = (string) Settings::get( 'course_field_id', '' );
		$course_id = '' !== $field_id ? self::course_id_from_url( (string) ( $entry[ $field_id ] ?? '' ) ) : 0;
		if ( $course_id < 1 ) {
			return;
		}

		Progress::mark_course_verified(
			$course_id,
			absint( $entry['id'] ?? 0 ),
			get_current_user_id(),
			self::resolve_pdf_configuration( $entry )
		);
	}

	/**
	 * Resolve the conditional Gravity PDF configuration for a stored entry.
	 *
	 * @param int $entry_id Gravity Forms entry ID.
	 * @return string
	 */
	public static function resolve_pdf_configuration_for_entry( int $entry_id ): string {
		if ( $entry_id < 1 || ! class_exists( 'GFAPI' ) ) {
			return '';
		}

		$entry = \GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
			return '';
		}

		return self::resolve_pdf_configuration( $entry );
	}

	/**
	 * Resolve a Gravity PDF configuration from the verification form settings.
	 *
	 * @param array<string, mixed> $entry Gravity Forms entry.
	 * @return string
	 */
	public static function resolve_pdf_configuration( array $entry ): string {
		$entry_id = absint( $entry['id'] ?? 0 );
		if ( $entry_id > 0 && class_exists( 'GPDFAPI' ) && method_exists( 'GPDFAPI', 'get_entry_pdfs' ) ) {
			$pdfs = \GPDFAPI::get_entry_pdfs( $entry_id );
			if ( ! is_wp_error( $pdfs ) ) {
				$template_slug = (string) Settings::get( 'pdf_template_slug', '' );
				$first_id      = '';
				foreach ( (array) $pdfs as $key => $pdf ) {
					if ( ! is_array( $pdf ) ) {
						continue;
					}

					$config_id = (string) ( $pdf['id'] ?? $pdf['pdf_id'] ?? $key );
					$template  = (string) ( $pdf['template'] ?? '' );
					if ( '' === $config_id ) {
						continue;
					}
					if ( '' === $first_id ) {
						$first_id = $config_id;
					}
					if ( '' === $template_slug || $template_slug === $template ) {
						return $config_id;
					}
				}

				return '' === $template_slug ? $first_id : '';
			}
		}

		$form_id = absint( Settings::get( 'verification_form_id', 0 ) );
		if ( $form_id < 1 || ! class_exists( 'GFAPI' ) ) {
			return '';
		}

		$form = \GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) || ! is_array( $form ) ) {
			return '';
		}

		$configs  = (array) ( $form['gfpdf_form_settings'] ?? array() );
		$fallback = '';
		foreach ( $configs as $config ) {
			if ( empty( $config['active'] ) ) {
				continue;
			}

			$config_id = (string) ( $config['id'] ?? '' );
			if ( '' === $config_id ) {
				continue;
			}

			if ( empty( $config['conditional'] ) ) {
				$fallback = $config_id;
				continue;
			}

			$logic = $config['conditionalLogic'] ?? array();
			if ( is_string( $logic ) ) {
				$logic = json_decode( $logic, true );
			}
			if ( self::matches_logic( $entry, (array) $logic ) ) {
				return $config_id;
			}
		}

		return $fallback;
	}

	/**
	 * Resolve a course URL to a published course post ID.
	 *
	 * @param string $url Course URL.
	 * @return int
	 */
	public static function course_id_from_url( string $url ): int {
		$url = esc_url_raw( trim( $url ) );
		if ( '' === $url ) {
			return 0;
		}

		$course_id  = url_to_postid( $url );
		$course_type = Post_Types::COURSE;
		if ( $course_id > 0 && $course_type === get_post_type( $course_id ) && 'publish' === get_post_status( $course_id ) ) {
			return $course_id;
		}

		$path      = self::url_path( $url );
		$course_ids = get_posts(
			array(
				'post_type'      => $course_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $course_ids as $candidate_id ) {
			if ( $path === self::url_path( (string) get_permalink( $candidate_id ) ) ) {
				return absint( $candidate_id );
			}
		}

		return 0;
	}

	/**
	 * Build the configured verification page URL.
	 *
	 * @param int $course_id Course post ID.
	 * @return string
	 */
	public static function verification_url( int $course_id ): string {
		$page_id = absint( Settings::get( 'verification_page_id', 0 ) );
		$url     = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		return add_query_arg( 'simple_lms_course', $course_id, $url );
	}

	/**
	 * Whether an array represents the configured verification form.
	 *
	 * @param array<string, mixed> $form Form data.
	 * @return bool
	 */
	private static function is_verification_form( array $form ): bool {
		$form_id = absint( Settings::get( 'verification_form_id', 0 ) );
		if ( $form_id < 1 ) {
			return false;
		}

		$candidate = absint( $form['id'] ?? 0 );
		if ( $candidate < 1 && isset( $form['form_id'] ) ) {
			$candidate = absint( $form['form_id'] );
		}

		return $candidate === $form_id;
	}

	/**
	 * Resolve the course from the request or current singular content.
	 *
	 * @return int
	 */
	private static function course_id_from_request(): int {
		$course_id = absint( $_GET['simple_lms_course'] ?? 0 );
		if ( $course_id > 0 ) {
			return Post_Types::COURSE === get_post_type( $course_id ) ? $course_id : 0;
		}

		$course_type = Post_Types::COURSE;
		if ( ! is_singular( $course_type ) ) {
			return 0;
		}

		return Progress::course_for_lesson( get_queried_object_id() );
	}

	/**
	 * Read the submitted course URL from Gravity Forms.
	 *
	 * @return int
	 */
	private static function submitted_course_id(): int {
		$field_id = (string) Settings::get( 'course_field_id', '' );
		if ( '' === $field_id ) {
			return 0;
		}

		$prefix = 'input_' . $field_id;
		foreach ( $_POST as $key => $value ) {
			if ( $prefix === $key || str_starts_with( $key, $prefix . '_' ) || str_starts_with( $key, $prefix . '.' ) ) {
				return self::course_id_from_url( (string) wp_unslash( $value ) );
			}
		}

		return 0;
	}

	/**
	 * Find the exact configured choice value for a course URL.
	 *
	 * @param int                  $course_id Course post ID.
	 * @param array<string, mixed> $form      Gravity Forms form.
	 * @return string
	 */
	private static function course_choice_value( int $course_id, array $form ): string {
		$field_id = (string) Settings::get( 'course_field_id', '' );
		$path     = self::url_path( (string) get_permalink( $course_id ) );
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( $field_id !== (string) ( $field->id ?? '' ) ) {
				continue;
			}
			foreach ( (array) ( $field->choices ?? array() ) as $choice ) {
				$value = (string) ( $choice['value'] ?? '' );
				if ( '' !== $value && $path === self::url_path( $value ) ) {
					return $value;
				}
			}
		}

		return (string) get_permalink( $course_id );
	}

	/**
	 * Evaluate Gravity Forms conditional logic against an entry.
	 *
	 * @param array<string, mixed> $entry Gravity Forms entry.
	 * @param array<string, mixed> $logic Conditional logic.
	 * @return bool
	 */
	private static function matches_logic( array $entry, array $logic ): bool {
		$rules   = (array) ( $logic['rules'] ?? array() );
		$results = array();
		foreach ( $rules as $rule ) {
			$field_id = (string) ( $rule['fieldId'] ?? '' );
			$actual   = (string) ( $entry[ $field_id ] ?? '' );
			$expected = (string) ( $rule['value'] ?? '' );
			$operator = (string) ( $rule['operator'] ?? 'is' );

			if ( str_contains( $expected, '://' ) ) {
				$actual   = self::url_path( $actual );
				$expected = self::url_path( $expected );
			}

			$results[] = match ( $operator ) {
				'isnot'     => $actual !== $expected,
				'contains'  => str_contains( $actual, $expected ),
				'starts_with' => str_starts_with( $actual, $expected ),
				'ends_with' => str_ends_with( $actual, $expected ),
				default     => $actual === $expected,
			};
		}

		if ( empty( $results ) ) {
			return true;
		}

		return 'any' === ( $logic['logicType'] ?? 'all' )
			? in_array( true, $results, true )
			: ! in_array( false, $results, true );
	}

	/**
	 * Normalize a URL to its path for cross-domain staging comparisons.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function url_path( string $url ): string {
		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );

		return untrailingslashit( '' !== $path ? $path : '/' );
	}
}
