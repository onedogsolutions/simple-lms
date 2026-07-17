<?php
/**
 * Student Dashboard – Frontend Template
 *
 * Handles:
 *   1. Profile form processing (nonce-verified POST)
 *   2. Tab 1 – User Profile form
 *   3. Tab 2 – Purchase History (PMPro MemberOrder)
 *   4. Tab 3 – Certificates Earned (wp_slms_course_history + GF PDF)
 */

// ── Form Processing ──────────────────────────────────────────────────────────
$current_user   = wp_get_current_user();
$update_success = false;
$update_error   = false;

if ( isset( $_POST['slms_profile_nonce'] ) && wp_verify_nonce( $_POST['slms_profile_nonce'], 'slms_update_profile_nonce' ) ) {

	$user_data = array(
		'ID'         => $current_user->ID,
		'first_name' => sanitize_text_field( $_POST['first_name'] ),
		'last_name'  => sanitize_text_field( $_POST['last_name'] ),
		'user_email' => sanitize_email( $_POST['user_email'] ),
	);

	// Password update (only when checkbox is checked and a value is provided)
	if ( isset( $_POST['update_password'] ) && '1' === $_POST['update_password'] ) {
		if ( ! empty( $_POST['user_pass'] ) ) {
			if ( isset( $_POST['user_pass_confirm'] ) && $_POST['user_pass'] === $_POST['user_pass_confirm'] ) {
				$user_data['user_pass'] = $_POST['user_pass'];
			} else {
				$update_error = __( 'Passwords do not match.', 'simple-lms-bridge' );
			}
		}
	}

	if ( ! $update_error ) {
		$user_id = wp_update_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			$update_error = $user_id->get_error_message();
		} else {
			$meta_fields = array(
				'phone',
				'license_number',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
			);
			foreach ( $meta_fields as $meta_key ) {
				if ( isset( $_POST[ $meta_key ] ) ) {
					update_user_meta( $user_id, $meta_key, sanitize_text_field( $_POST[ $meta_key ] ) );
				}
			}
			$update_success = __( 'Profile updated successfully.', 'simple-lms-bridge' );
			$current_user   = wp_get_current_user(); // refresh
		}
	}
}

// ── US States lookup ─────────────────────────────────────────────────────────
$us_states = array(
	'AL' => 'Alabama',
	'AK' => 'Alaska',
	'AZ' => 'Arizona',
	'AR' => 'Arkansas',
	'CA' => 'California',
	'CO' => 'Colorado',
	'CT' => 'Connecticut',
	'DE' => 'Delaware',
	'DC' => 'District of Columbia',
	'FL' => 'Florida',
	'GA' => 'Georgia',
	'HI' => 'Hawaii',
	'ID' => 'Idaho',
	'IL' => 'Illinois',
	'IN' => 'Indiana',
	'IA' => 'Iowa',
	'KS' => 'Kansas',
	'KY' => 'Kentucky',
	'LA' => 'Louisiana',
	'ME' => 'Maine',
	'MD' => 'Maryland',
	'MA' => 'Massachusetts',
	'MI' => 'Michigan',
	'MN' => 'Minnesota',
	'MS' => 'Mississippi',
	'MO' => 'Missouri',
	'MT' => 'Montana',
	'NE' => 'Nebraska',
	'NV' => 'Nevada',
	'NH' => 'New Hampshire',
	'NJ' => 'New Jersey',
	'NM' => 'New Mexico',
	'NY' => 'New York',
	'NC' => 'North Carolina',
	'ND' => 'North Dakota',
	'OH' => 'Ohio',
	'OK' => 'Oklahoma',
	'OR' => 'Oregon',
	'PA' => 'Pennsylvania',
	'RI' => 'Rhode Island',
	'SC' => 'South Carolina',
	'SD' => 'South Dakota',
	'TN' => 'Tennessee',
	'TX' => 'Texas',
	'UT' => 'Utah',
	'VT' => 'Vermont',
	'VA' => 'Virginia',
	'WA' => 'Washington',
	'WV' => 'West Virginia',
	'WI' => 'Wisconsin',
	'WY' => 'Wyoming',
);

$saved_state = get_user_meta( $current_user->ID, 'billing_state', true );
?>

<div class="slms-student-dashboard">

	<?php if ( $update_success ) : ?>
		<div class="slms-alert slms-alert-success"><?php echo esc_html( $update_success ); ?></div>
	<?php endif; ?>
	<?php if ( $update_error ) : ?>
		<div class="slms-alert slms-alert-error"><?php echo esc_html( $update_error ); ?></div>
	<?php endif; ?>

	<ul class="slms-tabs-nav" role="tablist">
		<li class="slms-tab-link active" data-tab="profile" role="tab" aria-selected="true">
			<?php esc_html_e( 'User Profile', 'simple-lms-bridge' ); ?></li>
		<li class="slms-tab-link" data-tab="courses" role="tab" aria-selected="false">
			<?php esc_html_e( 'My Courses', 'simple-lms-bridge' ); ?></li>
		<li class="slms-tab-link" data-tab="history" role="tab" aria-selected="false">
			<?php esc_html_e( 'Purchase History', 'simple-lms-bridge' ); ?></li>
		<li class="slms-tab-link" data-tab="certificates" role="tab" aria-selected="false">
			<?php esc_html_e( 'Certificates Earned', 'simple-lms-bridge' ); ?></li>
	</ul>

	<div class="slms-tabs-content">

		<?php
		/* ──────────────────────────────────────────────────────────────
		 * TAB 1 – User Profile
		 * ────────────────────────────────────────────────────────────── */
		?>
		<div id="slms-tab-profile" class="slms-tab-pane active" role="tabpanel">
			<form method="post" action="" class="slms-profile-form">
				<?php wp_nonce_field( 'slms_update_profile_nonce', 'slms_profile_nonce' ); ?>

				<?php /* Name – First & Last side-by-side */ ?>
				<div class="slms-field-row slms-two-col">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_first_name"><?php esc_html_e( 'First Name', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_first_name" name="first_name"
							value="<?php echo esc_attr( $current_user->first_name ); ?>" required />
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_last_name"><?php esc_html_e( 'Last Name', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_last_name" name="last_name"
							value="<?php echo esc_attr( $current_user->last_name ); ?>" required />
					</div>
				</div>

				<?php /* Email */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_user_email"><?php esc_html_e( 'Email', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="email" id="slms_user_email" name="user_email"
							value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
					</div>
				</div>

				<?php /* Phone */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_phone"><?php esc_html_e( 'Phone', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_phone" name="phone"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'phone', true ) ); ?>" />
					</div>
				</div>

				<?php /* License Number */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_license_number"><?php esc_html_e( 'Senior or Professional Laser Hair Removal License Number', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_license_number" name="license_number"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'license_number', true ) ); ?>" />
					</div>
				</div>

				<?php /* Update Password Checkbox */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group slms-checkbox-group">
						<label class="slms-checkbox-label">
							<input type="checkbox" id="slms_update_password" name="update_password" value="1" />
							<?php esc_html_e( 'Update Password', 'simple-lms-bridge' ); ?>
						</label>
					</div>
				</div>

				<?php /* Password Fields – hidden until checkbox is checked (JS-controlled) */ ?>
				<div id="slms-password-fields" class="slms-field-row slms-two-col slms-hidden" aria-hidden="true">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_user_pass"><?php esc_html_e( 'New Password', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="password" id="slms_user_pass" name="user_pass" value=""
							autocomplete="new-password" />
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_user_pass_confirm"><?php esc_html_e( 'Confirm Password', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="password" id="slms_user_pass_confirm" name="user_pass_confirm"
							value="" autocomplete="new-password" />
					</div>
				</div>

				<?php /* Address – Street Address */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_billing_address_1"><?php esc_html_e( 'Street Address', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_address_1" name="billing_address_1"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_address_1', true ) ); ?>" />
					</div>
				</div>

				<?php /* Address Line 2 */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_billing_address_2"><?php esc_html_e( 'Address Line 2', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_address_2" name="billing_address_2"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_address_2', true ) ); ?>" />
					</div>
				</div>

				<?php /* City / State / ZIP – three columns */ ?>
				<div class="slms-field-row slms-three-col">
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_billing_city"><?php esc_html_e( 'City', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_city" name="billing_city"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_city', true ) ); ?>" />
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_billing_state"><?php esc_html_e( 'State', 'simple-lms-bridge' ); ?></label>
						<select class="slms-input" id="slms_billing_state" name="billing_state">
							<option value=""><?php esc_html_e( '— Select State —', 'simple-lms-bridge' ); ?></option>
							<?php foreach ( $us_states as $abbr => $name ) : ?>
								<option value="<?php echo esc_attr( $abbr ); ?>" <?php selected( $saved_state, $abbr ); ?>>
									<?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label"
							for="slms_billing_postcode"><?php esc_html_e( 'ZIP Code', 'simple-lms-bridge' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_postcode" name="billing_postcode"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_postcode', true ) ); ?>" />
					</div>
				</div>

				<?php /* Submit */ ?>
				<div class="slms-field-row slms-form-footer">
					<button type="submit"
						class="slms-submit-btn"><?php esc_html_e( 'Update Profile', 'simple-lms-bridge' ); ?></button>
				</div>

			</form>
		</div><!-- #slms-tab-profile -->

		<?php
		/* ──────────────────────────────────────────────────────────────
		 * TAB – My Courses (reuses the lms-my-courses query logic)
		 * ────────────────────────────────────────────────────────────── */
		?>
		<div id="slms-tab-courses" class="slms-tab-pane" role="tabpanel">
			<?php
			$my_courses = class_exists( '\\SimpleLMS\\Access' )
				? \SimpleLMS\Access::get_enrolled_courses_with_progress( $current_user->ID )
				: array();
			?>
			<?php if ( ! empty( $my_courses ) ) : ?>
				<div class="slms-dashboard-courses">
					<?php
					foreach ( $my_courses as $course ) :
						$is_complete = ( 'completed' === $course['state'] );
						$btn_label   = $is_complete
							? __( 'Review', 'simple-lms-bridge' )
							: ( $course['completed'] > 0 ? __( 'Continue', 'simple-lms-bridge' ) : __( 'Start', 'simple-lms-bridge' ) );
						?>
						<div class="slms-dc-item<?php echo $is_complete ? ' is-complete' : ''; ?>">
							<div class="slms-dc-body">
								<h3 class="slms-dc-title">
									<a href="<?php echo esc_url( $course['permalink'] ); ?>"><?php echo esc_html( $course['title'] ); ?></a>
								</h3>
								<div class="slms-dc-progress">
									<div class="slms-progress-bar-container">
										<div class="slms-progress-bar-fill" style="width: <?php echo esc_attr( $course['percent'] ); ?>%;"></div>
									</div>
									<span class="slms-progress-label">
										<?php
										printf(
											/* translators: 1: completed, 2: total, 3: percent */
											esc_html__( '%1$d of %2$d lessons · %3$d%% complete', 'simple-lms-bridge' ),
											(int) $course['completed'],
											(int) $course['total'],
											(int) $course['percent']
										);
										?>
									</span>
								</div>
							</div>
							<div class="slms-dc-action">
								<a class="slms-cta-button" href="<?php echo esc_url( $course['continue_url'] ); ?>"><?php echo esc_html( $btn_label ); ?></a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="slms-empty-message"><?php esc_html_e( 'You are not enrolled in any courses yet.', 'simple-lms-bridge' ); ?></p>
			<?php endif; ?>
		</div><!-- #slms-tab-courses -->

		<?php
		/* ──────────────────────────────────────────────────────────────
		 * TAB 2 – Purchase History
		 * ────────────────────────────────────────────────────────────── */
		?>
		<div id="slms-tab-history" class="slms-tab-pane" role="tabpanel">

			<?php
			$orders = class_exists( '\MemberOrder' )
				? \MemberOrder::get_orders( array( 'user_id' => $current_user->ID ) )
				: array();
			?>

			<?php if ( ! empty( $orders ) ) : ?>
				<table class="slms-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'ID', 'simple-lms-bridge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Purchase Date', 'simple-lms-bridge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Course Purchases', 'simple-lms-bridge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Total', 'simple-lms-bridge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $orders as $order ) : ?>
							<tr>
								<td><?php echo esc_html( $order->code ); ?></td>
								<td>
								<?php
								// Handle both MySQL DATETIME strings and UNIX integer timestamps.
								$ts_raw   = ! empty( $order->timestamp ) ? $order->timestamp : ( ! empty( $order->datetime ) ? $order->datetime : '' );
								$order_ts = $ts_raw ? ( is_numeric( $ts_raw ) ? (int) $ts_raw : strtotime( $ts_raw ) ) : false;
								echo esc_html( $order_ts ? date_i18n( 'F j, Y', $order_ts ) : '—' );
								?>
								</td>
								<td>
									<?php
									$level      = function_exists( 'pmpro_getLevel' ) ? pmpro_getLevel( $order->membership_id ) : null;
									$level_name = $level ? $level->name : '';
									// Resolve PMPro level name to an slms_course permalink.
									global $wpdb;
									$course_post_id = $level_name ? (int) $wpdb->get_var(
										$wpdb->prepare(
											"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'slms_course' AND post_status = 'publish' LIMIT 1",
											$level_name
										)
									) : 0;
									if ( $course_post_id ) {
										printf(
											'<a href="%s">%s</a>',
											esc_url( get_permalink( $course_post_id ) ),
											esc_html( get_the_title( $course_post_id ) )
										);
									} else {
										echo esc_html( $level_name ?: __( 'Unknown', 'simple-lms-bridge' ) );
									}
									?>
								</td>
								<td>
									<?php
									echo function_exists( 'pmpro_formatPrice' )
										? esc_html( pmpro_formatPrice( $order->total ) )
										: esc_html( '$' . number_format( (float) $order->total, 2 ) );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="slms-empty-message"><?php esc_html_e( 'No purchase history found.', 'simple-lms-bridge' ); ?></p>
			<?php endif; ?>

		</div><!-- #slms-tab-history -->

		<?php
		/* ──────────────────────────────────────────────────────────────
		 * TAB 3 – Certificates Earned
		 * ────────────────────────────────────────────────────────────── */
		?>
		<div id="slms-tab-certificates" class="slms-tab-pane" role="tabpanel">

			<?php $history = \SimpleLMS\CourseHistory::get_for_user( $current_user->ID ); ?>

			<?php if ( ! empty( $history ) ) : ?>
				<table class="slms-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'simple-lms-bridge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Course', 'simple-lms-bridge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Completion Date', 'simple-lms-bridge' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Certificate PDF', 'simple-lms-bridge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $history as $row ) :

							// ── Course Resolution ────────────────────────────────────────────────
							// course_name may be a legacy Pods URL, a plain string, or empty.
							$raw_course_name = isset( $row->course_name ) ? $row->course_name : '';
							$course_name     = '';
							$course_link     = '';

							if ( filter_var( $raw_course_name, FILTER_VALIDATE_URL ) ) {

								// Step A: Try url_to_postid() for exact WP post resolution.
								$post_id = url_to_postid( $raw_course_name );
								if ( $post_id ) {
									$post_type = get_post_type( $post_id );
									if ( 'slms_lesson' === $post_type ) {
										// Lesson URL → walk up to parent course.
										$courses   = class_exists( '\\SimpleLMS\\Relationships' )
											? \SimpleLMS\Relationships::get_courses_for_lesson( $post_id )
											: array();
										$course_id = ! empty( $courses ) ? (int) reset( $courses )->ID : 0;
									} else {
										$course_id = ( 'slms_course' === $post_type ) ? $post_id : 0;
									}
									if ( $course_id ) {
										$course_name = get_the_title( $course_id );
										$course_link = get_permalink( $course_id );
									}
								}

								// Step B/C: Pods slug fallback — parse URL path and match slms_course by post_name.
								if ( ! $course_name ) {
									$path     = (string) parse_url( $raw_course_name, PHP_URL_PATH );
									$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
									// Extract the segment immediately after the 'course' directory.
									$course_key = array_search( 'course', $segments, true );
									$slug       = ( false !== $course_key && isset( $segments[ $course_key + 1 ] ) )
										? $segments[ $course_key + 1 ]
										: ( ! empty( $segments ) ? end( $segments ) : '' );
									if ( $slug ) {
										$matched = get_posts(
											array(
												'name' => sanitize_title( $slug ),
												'post_type' => 'slms_course',
												'posts_per_page' => 1,
												'post_status' => array( 'publish', 'private' ),
											)
										);
										if ( ! empty( $matched ) ) {
											$course_name = $matched[0]->post_title;
											$course_link = get_permalink( $matched[0]->ID );
										}
									}
								}

								// Step E: Format slug as readable plain text — last resort.
								if ( ! $course_name ) {
									$path      = (string) parse_url( $raw_course_name, PHP_URL_PATH );
									$segments  = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
									$last_slug = ! empty( $segments ) ? end( $segments ) : '';
									if ( $last_slug ) {
										$course_name = ucwords( str_replace( '-', ' ', $last_slug ) );
									} else {
										// Absolute last resort: strip scheme and host, use full path.
										$course_name = trim( $path, '/' ) ?: $raw_course_name;
									}
								}
							} else {
								// Plain string (post-migration data or new completion title) — use directly.
								$course_name = ! empty( $raw_course_name ) ? $raw_course_name : __( 'Unknown Course', 'simple-lms-bridge' );

								// Try to resolve a permalink from the title so PDF backfill has a valid URL.
								if ( ! empty( $raw_course_name ) ) {
									$title_posts = get_posts(
										array(
											'post_type'   => 'slms_course',
											'title'       => $raw_course_name,
											'posts_per_page' => 1,
											'post_status' => array( 'publish', 'private' ),
											'no_found_rows' => true,
										)
									);
									if ( ! empty( $title_posts ) ) {
										$course_name = $title_posts[0]->post_title;
										$course_link = get_permalink( $title_posts[0]->ID );
									}
								}
							}

							// ── Native Certificate (preferred) ─────────────────────────────
							$pdf_link_html = '';
							$cert_uuid     = isset( $row->cert_uuid ) ? (string) $row->cert_uuid : '';

							if ( $cert_uuid && class_exists( '\\SimpleLMS\\Certificates\\Issuer' )
								&& \SimpleLMS\Certificates\Issuer::pdf_exists( $cert_uuid ) ) {
								$native_url    = \SimpleLMS\Certificates\Issuer::download_url( $cert_uuid );
								$pdf_link_html = '<a href="' . esc_url( $native_url ) . '" class="slms-pdf-link">'
									. esc_html__( 'Download PDF', 'simple-lms' )
									. '</a>';
							}

							// ── GravityPDF Link Generation (legacy fallback) ────────────────
							$gf_entry_id = isset( $row->gf_entry_id ) ? absint( $row->gf_entry_id ) : 0;
							$pdf_form_id = isset( $row->form_id ) ? absint( $row->form_id ) : 0;

							// If form_id missing from row, resolve via GFAPI.
							if ( '' === $pdf_link_html && ! $pdf_form_id && $gf_entry_id && class_exists( 'GFAPI' ) ) {
								$_gf_entry = \GFAPI::get_entry( $gf_entry_id );
								if ( ! is_wp_error( $_gf_entry ) && ! empty( $_gf_entry['form_id'] ) ) {
									$pdf_form_id = absint( $_gf_entry['form_id'] );
								}
							}

							if ( '' === $pdf_link_html && $gf_entry_id && $pdf_form_id && class_exists( 'GPDFAPI' ) ) {
								try {
									// Prefer resolved permalink; fall back to raw stored course name.
									$url_to_match = $course_link ?: $raw_course_name;

									$pdf_url = \SimpleLMS\Certificates::pdf_url(
										$gf_entry_id,
										$pdf_form_id,
										(string) $url_to_match,
										$current_user->ID
									);

									if ( $pdf_url ) {
										$pdf_link_html = '<a href="' . esc_url( $pdf_url ) . '" class="slms-pdf-link">'
											. esc_html__( 'Download PDF', 'simple-lms-bridge' )
											. '</a>';
									}
								} catch ( \Throwable $e ) {
									$pdf_link_html = '';
								}
							}

							// Student full name for the certificate
							$student_name = trim( $current_user->first_name . ' ' . $current_user->last_name );
							if ( '' === $student_name ) {
								$student_name = $current_user->display_name;
							}

							?>
							<tr>
								<td><?php echo esc_html( $student_name ); ?></td>
								<td>
								<?php
								if ( $course_link ) {
									printf( '<a href="%s">%s</a>', esc_url( $course_link ), esc_html( $course_name ) );
								} else {
									echo esc_html( $course_name );
								}
								?>
								</td>
								<td><?php echo esc_html( ! empty( $row->completed_date ) ? date_i18n( get_option( 'date_format' ), strtotime( $row->completed_date ) ) : '—' ); ?>
								</td>
								<td class="slms-pdf-link">
									<?php
									if ( $pdf_link_html ) {
										echo $pdf_link_html;
									} else {
										echo '<span class="slms-na">' . esc_html__( 'N/A', 'simple-lms-bridge' ) . '</span>';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="slms-empty-message"><?php esc_html_e( 'No certificates found.', 'simple-lms-bridge' ); ?></p>
			<?php endif; ?>

		</div><!-- #slms-tab-certificates -->

	</div><!-- .slms-tabs-content -->

</div><!-- .slms-student-dashboard -->

<script>
	(function () {
		var dashboard = document.currentScript.previousElementSibling;
		if (!dashboard) { return; }

		// ── Tab Switching ────────────────────────────────────────────────────────
		var tabLinks = dashboard.querySelectorAll('.slms-tab-link');
		var tabPanes = dashboard.querySelectorAll('.slms-tab-pane');

		tabLinks.forEach(function (link) {
			link.addEventListener('click', function () {
				var targetId = 'slms-tab-' + this.getAttribute('data-tab');

				tabLinks.forEach(function (l) {
					l.classList.remove('active');
					l.setAttribute('aria-selected', 'false');
				});
				tabPanes.forEach(function (p) {
					p.classList.remove('active');
				});

				this.classList.add('active');
				this.setAttribute('aria-selected', 'true');

				var targetPane = dashboard.querySelector('#' + targetId);
				if (targetPane) {
					targetPane.classList.add('active');
				}
			});
		});

		// ── Password Toggle ──────────────────────────────────────────────────────
		var passwordCheckbox = dashboard.querySelector('#slms_update_password');
		var passwordFields = dashboard.querySelector('#slms-password-fields');

		if (passwordCheckbox && passwordFields) {
			passwordCheckbox.addEventListener('change', function () {
				if (this.checked) {
					passwordFields.classList.remove('slms-hidden');
					passwordFields.setAttribute('aria-hidden', 'false');
				} else {
					passwordFields.classList.add('slms-hidden');
					passwordFields.setAttribute('aria-hidden', 'true');
					passwordFields.querySelectorAll('input[type="password"]').forEach(function (i) {
						i.value = '';
					});
				}
			});
		}
	}());
</script>