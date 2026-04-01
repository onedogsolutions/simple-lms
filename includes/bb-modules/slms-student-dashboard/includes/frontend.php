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
				$update_error = __( 'Passwords do not match.', 'simple-lms' );
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
			$update_success = __( 'Profile updated successfully.', 'simple-lms' );
			$current_user   = wp_get_current_user(); // refresh
		}
	}
}

// ── US States lookup ─────────────────────────────────────────────────────────
$us_states = array(
	'AL' => 'Alabama',        'AK' => 'Alaska',         'AZ' => 'Arizona',
	'AR' => 'Arkansas',       'CA' => 'California',      'CO' => 'Colorado',
	'CT' => 'Connecticut',    'DE' => 'Delaware',        'DC' => 'District of Columbia',
	'FL' => 'Florida',        'GA' => 'Georgia',         'HI' => 'Hawaii',
	'ID' => 'Idaho',          'IL' => 'Illinois',        'IN' => 'Indiana',
	'IA' => 'Iowa',           'KS' => 'Kansas',          'KY' => 'Kentucky',
	'LA' => 'Louisiana',      'ME' => 'Maine',           'MD' => 'Maryland',
	'MA' => 'Massachusetts',  'MI' => 'Michigan',        'MN' => 'Minnesota',
	'MS' => 'Mississippi',    'MO' => 'Missouri',        'MT' => 'Montana',
	'NE' => 'Nebraska',       'NV' => 'Nevada',          'NH' => 'New Hampshire',
	'NJ' => 'New Jersey',     'NM' => 'New Mexico',      'NY' => 'New York',
	'NC' => 'North Carolina', 'ND' => 'North Dakota',    'OH' => 'Ohio',
	'OK' => 'Oklahoma',       'OR' => 'Oregon',          'PA' => 'Pennsylvania',
	'RI' => 'Rhode Island',   'SC' => 'South Carolina',  'SD' => 'South Dakota',
	'TN' => 'Tennessee',      'TX' => 'Texas',           'UT' => 'Utah',
	'VT' => 'Vermont',        'VA' => 'Virginia',        'WA' => 'Washington',
	'WV' => 'West Virginia',  'WI' => 'Wisconsin',       'WY' => 'Wyoming',
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
		<li class="slms-tab-link active" data-tab="profile" role="tab" aria-selected="true"><?php esc_html_e( 'User Profile', 'simple-lms' ); ?></li>
		<li class="slms-tab-link" data-tab="history" role="tab" aria-selected="false"><?php esc_html_e( 'Purchase History', 'simple-lms' ); ?></li>
		<li class="slms-tab-link" data-tab="certificates" role="tab" aria-selected="false"><?php esc_html_e( 'Certificates Earned', 'simple-lms' ); ?></li>
	</ul>

	<div class="slms-tabs-content">

		<?php /* ──────────────────────────────────────────────────────────────
		 * TAB 1 – User Profile
		 * ────────────────────────────────────────────────────────────── */ ?>
		<div id="slms-tab-profile" class="slms-tab-pane active" role="tabpanel">
			<form method="post" action="" class="slms-profile-form">
				<?php wp_nonce_field( 'slms_update_profile_nonce', 'slms_profile_nonce' ); ?>

				<?php /* Name – First & Last side-by-side */ ?>
				<div class="slms-field-row slms-two-col">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_first_name"><?php esc_html_e( 'First Name', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_first_name" name="first_name"
							value="<?php echo esc_attr( $current_user->first_name ); ?>" required />
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_last_name"><?php esc_html_e( 'Last Name', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_last_name" name="last_name"
							value="<?php echo esc_attr( $current_user->last_name ); ?>" required />
					</div>
				</div>

				<?php /* Email */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_user_email"><?php esc_html_e( 'Email', 'simple-lms' ); ?></label>
						<input class="slms-input" type="email" id="slms_user_email" name="user_email"
							value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
					</div>
				</div>

				<?php /* Phone */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_phone"><?php esc_html_e( 'Phone', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_phone" name="phone"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'phone', true ) ); ?>" />
					</div>
				</div>

				<?php /* License Number */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_license_number"><?php esc_html_e( 'Senior or Professional Laser Hair Removal License Number', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_license_number" name="license_number"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'license_number', true ) ); ?>" />
					</div>
				</div>

				<?php /* Update Password Checkbox */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group slms-checkbox-group">
						<label class="slms-checkbox-label">
							<input type="checkbox" id="slms_update_password" name="update_password" value="1" />
							<?php esc_html_e( 'Update Password', 'simple-lms' ); ?>
						</label>
					</div>
				</div>

				<?php /* Password Fields – hidden until checkbox is checked (JS-controlled) */ ?>
				<div id="slms-password-fields" class="slms-field-row slms-two-col slms-hidden" aria-hidden="true">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_user_pass"><?php esc_html_e( 'New Password', 'simple-lms' ); ?></label>
						<input class="slms-input" type="password" id="slms_user_pass" name="user_pass"
							value="" autocomplete="new-password" />
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_user_pass_confirm"><?php esc_html_e( 'Confirm Password', 'simple-lms' ); ?></label>
						<input class="slms-input" type="password" id="slms_user_pass_confirm" name="user_pass_confirm"
							value="" autocomplete="new-password" />
					</div>
				</div>

				<?php /* Address – Street Address */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_billing_address_1"><?php esc_html_e( 'Street Address', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_address_1" name="billing_address_1"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_address_1', true ) ); ?>" />
					</div>
				</div>

				<?php /* Address Line 2 */ ?>
				<div class="slms-field-row">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_billing_address_2"><?php esc_html_e( 'Address Line 2', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_address_2" name="billing_address_2"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_address_2', true ) ); ?>" />
					</div>
				</div>

				<?php /* City / State / ZIP – three columns */ ?>
				<div class="slms-field-row slms-three-col">
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_billing_city"><?php esc_html_e( 'City', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_city" name="billing_city"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_city', true ) ); ?>" />
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_billing_state"><?php esc_html_e( 'State', 'simple-lms' ); ?></label>
						<select class="slms-input" id="slms_billing_state" name="billing_state">
							<option value=""><?php esc_html_e( '— Select State —', 'simple-lms' ); ?></option>
							<?php foreach ( $us_states as $abbr => $name ) : ?>
								<option value="<?php echo esc_attr( $abbr ); ?>"<?php selected( $saved_state, $abbr ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="slms-field-group">
						<label class="slms-field-label" for="slms_billing_postcode"><?php esc_html_e( 'ZIP Code', 'simple-lms' ); ?></label>
						<input class="slms-input" type="text" id="slms_billing_postcode" name="billing_postcode"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_postcode', true ) ); ?>" />
					</div>
				</div>

				<?php /* Submit */ ?>
				<div class="slms-field-row slms-form-footer">
					<button type="submit" class="slms-submit-btn"><?php esc_html_e( 'Update Profile', 'simple-lms' ); ?></button>
				</div>

			</form>
		</div><!-- #slms-tab-profile -->

		<?php /* ──────────────────────────────────────────────────────────────
		 * TAB 2 – Purchase History
		 * ────────────────────────────────────────────────────────────── */ ?>
		<div id="slms-tab-history" class="slms-tab-pane" role="tabpanel">

			<?php
				$orders = class_exists( 'MemberOrder' )
					? MemberOrder::get_orders( array( 'user_id' => $current_user->ID ) )
					: array();
			?>

				<?php if ( ! empty( $orders ) ) : ?>
					<table class="slms-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'ID', 'simple-lms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Purchase Date', 'simple-lms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Course Purchases', 'simple-lms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Total', 'simple-lms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $orders as $order ) : ?>
								<tr>
									<td><?php echo esc_html( $order->code ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $order->timestamp ) ) ); ?></td>
									<td>
										<?php
										$level = function_exists( 'pmpro_getLevel' ) ? pmpro_getLevel( $order->membership_id ) : null;
										echo esc_html( $level ? $level->name : __( 'Unknown', 'simple-lms' ) );
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
					<p class="slms-empty-message"><?php esc_html_e( 'No purchase history found.', 'simple-lms' ); ?></p>
				<?php endif; ?>

			<?php else : ?>
				<p class="slms-empty-message"><?php esc_html_e( 'Paid Memberships Pro is not active.', 'simple-lms' ); ?></p>
			<?php endif; ?>

		</div><!-- #slms-tab-history -->

		<?php /* ──────────────────────────────────────────────────────────────
		 * TAB 3 – Certificates Earned
		 * ────────────────────────────────────────────────────────────── */ ?>
		<div id="slms-tab-certificates" class="slms-tab-pane" role="tabpanel">

			<?php $history = \SimpleLMS\CourseHistory::get_for_user( $current_user->ID ); ?>

				<?php if ( ! empty( $history ) ) : ?>
					<table class="slms-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', 'simple-lms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Course', 'simple-lms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Completion Date', 'simple-lms' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Certificate PDF', 'simple-lms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $row ) :

								// Course title
								$course      = get_post( $row->course_id );
								$course_name = $course ? $course->post_title : __( 'Unknown Course', 'simple-lms' );

								// GF entry & form IDs
								$gf_entry_id = isset( $row->gf_entry_id ) ? absint( $row->gf_entry_id ) : 0;
								$form_id     = isset( $row->form_id )     ? absint( $row->form_id )     : 0;

								// Resolve form_id from the GF entry when not stored on the row
								if ( ! $form_id && $gf_entry_id && class_exists( 'GFAPI' ) ) {
									$entry = GFAPI::get_entry( $gf_entry_id );
									if ( ! is_wp_error( $entry ) && isset( $entry['form_id'] ) ) {
										$form_id = absint( $entry['form_id'] );
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
									<td><?php echo esc_html( $course_name ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->completed_date ) ) ); ?></td>
									<td>
										<?php if ( $gf_entry_id && $form_id ) : ?>
											<a href="<?php echo esc_url( home_url( '/?gf_pdf=1&fid=' . $form_id . '&lid=' . $gf_entry_id ) ); ?>"
												class="slms-pdf-link" target="_blank" rel="noopener noreferrer">
												<?php esc_html_e( 'Download PDF', 'simple-lms' ); ?>
											</a>
										<?php else : ?>
											<span class="slms-na"><?php esc_html_e( 'N/A', 'simple-lms' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="slms-empty-message"><?php esc_html_e( 'No certificates found.', 'simple-lms' ); ?></p>
				<?php endif; ?>

		</div><!-- #slms-tab-certificates -->

	</div><!-- .slms-tabs-content -->

</div><!-- .slms-student-dashboard -->
