<?php
/**
 * Frontend shortcodes.
 *
 * @package SimpleLMS
 */

declare(strict_types=1);

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders course, account, and certificate views without jQuery.
 */
final class Shortcodes {
	/**
	 * Register shortcode hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_shortcode( 'simple_lms_courses', array( self::class, 'courses' ) );
		add_shortcode( 'simple_lms_account', array( self::class, 'account' ) );
		add_shortcode( 'simple_lms_certificates', array( self::class, 'certificates' ) );
	}

	/**
	 * Render the course catalog.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function courses( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'limit' => -1,
			),
			$atts,
			'simple_lms_courses'
		);

		$courses = get_posts(
			array(
				'post_type'      => Post_Types::COURSE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $atts['limit'],
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);

		if ( empty( $courses ) ) {
			return '<p>' . esc_html__( 'No courses are currently available.', 'simple-lms' ) . '</p>';
		}

		$output = '<div class="simple-lms-courses">';
		foreach ( $courses as $course ) {
			$output .= self::course_card( $course );
		}

		return $output . '</div>';
	}

	/**
	 * Render the student account tabs.
	 *
	 * @return string
	 */
	public static function account(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Sign in to view your account.', 'simple-lms' ) . '</p>';
		}

		$user = wp_get_current_user();
		ob_start();
		?>
		<div class="simple-lms-account" data-simple-lms-account>
			<div class="simple-lms-account__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Account sections', 'simple-lms' ); ?>">
				<button type="button" role="tab" aria-selected="true" aria-controls="slms-profile" id="slms-tab-profile" data-slms-tab="profile">
					<?php esc_html_e( 'User Profile', 'simple-lms' ); ?>
				</button>
				<button type="button" role="tab" aria-selected="false" aria-controls="slms-courses" id="slms-tab-courses" data-slms-tab="courses" tabindex="-1">
					<?php esc_html_e( 'Purchase History', 'simple-lms' ); ?>
				</button>
				<button type="button" role="tab" aria-selected="false" aria-controls="slms-certificates" id="slms-tab-certificates" data-slms-tab="certificates" tabindex="-1">
					<?php esc_html_e( 'Certificates Earned', 'simple-lms' ); ?>
				</button>
			</div>
			<div id="slms-profile" class="simple-lms-account__panel" role="tabpanel" aria-labelledby="slms-tab-profile" data-slms-panel="profile">
				<h2><?php esc_html_e( 'User Profile', 'simple-lms' ); ?></h2>
				<p><?php esc_html( $user->display_name ); ?></p>
				<p><?php esc_html( $user->user_email ); ?></p>
			</div>
			<div id="slms-courses" class="simple-lms-account__panel" role="tabpanel" aria-labelledby="slms-tab-courses" data-slms-panel="courses" hidden>
				<h2><?php esc_html_e( 'Purchase History', 'simple-lms' ); ?></h2>
				<?php echo wp_kses_post( self::purchased_courses() ); ?>
			</div>
			<div id="slms-certificates" class="simple-lms-account__panel" role="tabpanel" aria-labelledby="slms-tab-certificates" data-slms-panel="certificates" hidden>
				<h2><?php esc_html_e( 'Certificates Earned', 'simple-lms' ); ?></h2>
				<?php echo wp_kses_post( self::certificates() ); ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the current student's verified certificates.
	 *
	 * @return string
	 */
	public static function certificates(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Sign in to view your certificates.', 'simple-lms' ) . '</p>';
		}

		$verified = Progress::verified_courses();
		if ( empty( $verified ) ) {
			return '<p>' . esc_html__( 'No earned certificates yet.', 'simple-lms' ) . '</p>';
		}

		$output = '<ul class="simple-lms-certificates">';
		foreach ( $verified as $course_id => $record ) {
			$course_id = absint( $course_id );
			$course    = get_post( $course_id );
			if ( ! $course || Post_Types::COURSE !== $course->post_type || 'publish' !== $course->post_status ) {
				continue;
			}

			$url = Progress::certificate_url( $course_id );
			$output .= '<li>';
			$output .= '<span>' . esc_html( get_the_title( $course_id ) ) . '</span>';
			if ( '' !== $url ) {
				$output .= ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Download PDF', 'simple-lms' ) . '</a>';
			}
			$output .= '</li>';
		}

		return $output . '</ul>';
	}

	/**
	 * Render a course card.
	 *
	 * @param \WP_Post $course Course post.
	 * @return string
	 */
	private static function course_card( \WP_Post $course ): string {
		$user_id   = get_current_user_id();
		$has_access = $user_id > 0 && Access::can_access( $course->ID, $user_id );
		$progress  = Progress::course_progress( $course->ID, $user_id );
		$url       = (string) get_permalink( $course );
		$label     = __( 'View course', 'simple-lms' );

		if ( $progress['complete'] ) {
			$verification_url = Gravity_Forms::verification_url( $course->ID );
			if ( '' !== $verification_url ) {
				$url   = $verification_url;
				$label = __( 'Get certificate', 'simple-lms' );
			}
		} elseif ( $has_access ) {
			$lesson_id = Progress::first_incomplete_lesson( $course->ID, $user_id );
			if ( $lesson_id > 0 ) {
				$url   = Relationships::lesson_url( $lesson_id, $course->ID );
				$label = __( 'Continue course', 'simple-lms' );
			}
		}

		$output = '<article class="simple-lms-course-card">';
		$output .= '<h2><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $course ) ) . '</a></h2>';
		$output .= '<div class="simple-lms-course-card__excerpt">' . wp_kses_post( get_the_excerpt( $course ) ) . '</div>';
		if ( $user_id > 0 && $progress['total'] > 0 ) {
			$output .= '<p>' . esc_html( sprintf( '%1$d%% complete', $progress['percent'] ) ) . '</p>';
		}
		$output .= '<a class="simple-lms-course-card__cta" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		$output .= '</article>';

		return $output;
	}

	/**
	 * Render courses linked to the current student's enrollment.
	 *
	 * @return string
	 */
	private static function purchased_courses(): string {
		$course_ids = Access::accessible_course_ids();
		if ( empty( $course_ids ) ) {
			return '<p>' . esc_html__( 'No purchased courses yet.', 'simple-lms' ) . '</p>';
		}

		$output = '<div class="simple-lms-courses">';
		foreach ( $course_ids as $course_id ) {
			$course = get_post( $course_id );
			if ( $course instanceof \WP_Post ) {
				$output .= self::course_card( $course );
			}
		}

		return $output . '</div>';
	}
}
