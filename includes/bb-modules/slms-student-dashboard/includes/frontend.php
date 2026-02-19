<?php
/**
 * Frontend HTML for the SLMS Student Dashboard module.
 *
 * @package SimpleLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id = get_current_user_id();

if ( ! $user_id ) {
	echo '<p>' . __( 'Please log in to view your dashboard.', 'simple-lms-bridge' ) . '</p>';
	return;
}

// Map settings to variables.
$cert_form_id        = $settings->cert_form_id;
$tab_label_profile   = $settings->tab_label_profile;
$tab_label_history   = $settings->tab_label_history;
$tab_label_certs     = $settings->tab_label_certs;

$current_tab = isset( $_GET['dash_tab'] ) ? sanitize_key( $_GET['dash_tab'] ) : 'profile';
$url         = remove_query_arg( 'dash_tab' );

?>
<div class="slms-dashboard-wrapper">
	<!-- Tab Navigation -->
	<ul class="slms-dash-tabs">
		<li class="<?php echo ( 'profile' === $current_tab ) ? 'active' : ''; ?>">
			<a href="<?php echo esc_url( add_query_arg( 'dash_tab', 'profile', $url ) ); ?>">
				<span class="slms-dash-icon dashicons dashicons-admin-users"></span>
				<div class="slms-dash-label-group">
					<strong><?php echo esc_html( $tab_label_profile ); ?></strong>
					<span><?php _e( 'Update your account information.', 'simple-lms-bridge' ); ?></span>
				</div>
			</a>
		</li>
		<li class="<?php echo ( 'history' === $current_tab ) ? 'active' : ''; ?>">
			<a href="<?php echo esc_url( add_query_arg( 'dash_tab', 'history', $url ) ); ?>">
				<span class="slms-dash-icon dashicons dashicons-cart"></span>
				<div class="slms-dash-label-group">
					<strong><?php echo esc_html( $tab_label_history ); ?></strong>
					<span><?php _e( 'View your course purchases.', 'simple-lms-bridge' ); ?></span>
				</div>
			</a>
		</li>
		<li class="<?php echo ( 'certificates' === $current_tab ) ? 'active' : ''; ?>">
			<a href="<?php echo esc_url( add_query_arg( 'dash_tab', 'certificates', $url ) ); ?>">
				<span class="slms-dash-icon dashicons dashicons-awards"></span>
				<div class="slms-dash-label-group">
					<strong><?php echo esc_html( $tab_label_certs ); ?></strong>
					<span><?php _e( 'Download your certificates.', 'simple-lms-bridge' ); ?></span>
				</div>
			</a>
		</li>
	</ul>

	<!-- Tab Content -->
	<div class="slms-dash-content">
		<?php if ( 'profile' === $current_tab ) : ?>
			<div class="slms-dash-panel" id="slms-dash-profile">
				<?php 
				// Use the internal profile form.
				if ( class_exists( 'SimpleLMS\AccountDashboard' ) ) {
					\SimpleLMS\AccountDashboard::render_profile( $user_id );
				} else {
					echo '<p>' . __( 'Profile system not available.', 'simple-lms-bridge' ) . '</p>';
				}
				?>
			</div>

		<?php elseif ( 'history' === $current_tab ) : ?>
			<div class="slms-dash-panel" id="slms-dash-history">
				<h3><?php echo esc_html( $tab_label_history ); ?></h3>
				<?php
				if ( function_exists( 'pmpro_hasMembershipLevel' ) ) {
					echo do_shortcode( '[pmpro_account sections="membership,invoices"]' );
				} else {
					echo '<div class="slms-notice warning"><p>' . __( 'Purchase history requires Paid Memberships Pro to be installed and active.', 'simple-lms-bridge' ) . '</p></div>';
				}
				?>
			</div>

		<?php elseif ( 'certificates' === $current_tab ) : ?>
			<div class="slms-dash-panel" id="slms-dash-certificates">
				<h3><?php echo esc_html( $tab_label_certs ); ?></h3>
				<?php
				if ( $cert_form_id && class_exists( 'GFAPI' ) ) {
					$search_criteria = array(
						'status'        => 'active',
						'field_filters' => array(
							array(
								'key'   => 'created_by',
								'value' => $user_id,
							),
						),
					);
					$entries = \GFAPI::get_entries( (int) $cert_form_id, $search_criteria );

					if ( ! is_wp_error( $entries ) && ! empty( $entries ) ) : ?>
						<table class="slms-dash-table">
							<thead>
								<tr>
									<th><?php _e( 'Name', 'simple-lms-bridge' ); ?></th>
									<th><?php _e( 'Course', 'simple-lms-bridge' ); ?></th>
									<th><?php _e( 'Completion Date', 'simple-lms-bridge' ); ?></th>
									<th><?php _e( 'Certificate PDF', 'simple-lms-bridge' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $entries as $entry ) : ?>
									<tr>
										<td><?php echo esc_html( rgar( $entry, $settings->cert_field_name ) ); ?></td>
										<td><?php echo esc_html( rgar( $entry, $settings->cert_field_course ) ); ?></td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( rgar( $entry, $settings->cert_field_date ) ) ) ); ?></td>
										<td>
											<?php 
											$pdf_url = rgar( $entry, $settings->cert_field_pdf );
											if ( $pdf_url ) : ?>
												<a href="<?php echo esc_url( $pdf_url ); ?>" class="slms-dash-btn" target="_blank"><?php _e( 'Download PDF', 'simple-lms-bridge' ); ?></a>
											<?php else : ?>
												-
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php _e( 'No certificates found.', 'simple-lms-bridge' ); ?></p>
					<?php endif;
				} else {
					echo '<p>' . __( 'Certificate form not configured or Gravity Forms not active.', 'simple-lms-bridge' ) . '</p>';
				}
				?>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php
// Inline JS for smooth tab interaction (optional).
?>
<script>
jQuery(document).ready(function($) {
	$('.slms-dash-tabs a').on('click', function(e) {
		if ( $('body').hasClass('fl-builder-active') ) return;
	});
});
</script>
