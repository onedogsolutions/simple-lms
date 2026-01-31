<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://wpcomplete.co
 * @since      1.0.0
 * @last       2.9.0
 *
 * @package    WPComplete
 * @subpackage wpcomplete/admin/partials
 */

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wpcomplete-settings wrap">
<h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 90 90" class="logo"><path class="inner" fill="#0a4de5" d="M47.6,70.9c-1.1,2.3-4.1,2.8-5.9,1L16.1,46.5c-1.5-1.6-1.4-4.1,0.3-5.4l3.4-3.4c1.3-1.1,3.2-1.1,4.6,0
	l13.5,11.9c1.5,1.2,3.8,1,5-0.5c7.7-8.9,21.7-24.8,40.3-37.4C76.8,2.8,65.2,0,45,0C9,0,0,9,0,45s9,45,45,45s45-9,45-45
	c0-11.3-0.9-19.9-3.2-26.4C69.2,35.9,54.3,56.8,47.6,70.9z"></path></svg></h2>

	<?php if ( isset( $_GET['sl_activation'] ) && ( 'false' === sanitize_text_field( wp_unslash( $_GET['sl_activation'] ) ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="error">
			<?php if ( isset( $_GET['message'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p><?php echo wp_kses_post( urldecode( sanitize_text_field( wp_unslash( $_GET['message'] ) ) ) ); ?></p> <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php else : ?>
				<p>There was an error validating your license. Please make sure you're using a valid one or contact us and we'll help: <a href="mailto:nerds@wpcomplete.co?subject=Invalid WPComplete license.">nerds@wpcomplete.co</a></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<nav class="nav-tab-wrapper">
	<a href="?page=wpcomplete&amp;tab=general" class="nav-tab<?php echo esc_html( 'general' === $active_tab ? ' nav-tab-active' : '' ); ?>">General</a>
	<a href="?page=wpcomplete&amp;tab=buttons" class="nav-tab<?php echo esc_html( 'buttons' === $active_tab ? ' nav-tab-active' : '' ); ?>">Buttons</a>
	<a href="?page=wpcomplete&amp;tab=graphs" class="nav-tab<?php echo esc_html( 'graphs' === $active_tab ? ' nav-tab-active' : '' ); ?>">Graphs</a>
	<a href="?page=wpcomplete&amp;tab=advanced" class="nav-tab<?php echo esc_html( 'advanced' === $active_tab ? ' nav-tab-active' : '' ); ?>">Advanced</a>
</nav>

<div class="content">
	<form action="options.php" method="post">
	<?php
	if ( wpcomplete_is_production() ) {
		$name           = $this->plugin_name . '_license_key';
		$text           = get_option( $name );
		$class          = '';
		$license_status = get_option( $this->plugin_name . '_license_status' );
		$button_name    = $this->plugin_name . '_license_activate';

		include 'wpcomplete-admin-settings-license-status.php';
	}

	settings_fields( $this->plugin_name . '_' . $active_tab );
	do_settings_sections( $this->plugin_name . '_' . $active_tab );
	submit_button();
	?>
	</form>
</div>

<div class="sidebar">

	<?php if ( ! defined( 'WPCOMPLETE_IS_ACTIVATED' ) || ! WPCOMPLETE_IS_ACTIVATED ) : ?>
	<!-- FREE: -->
	<div class="postbox">
		<h2><span>Update to <?php echo esc_html( WPCOMPLETE_PRODUCT_NAME ); ?> PRO</span></h2>
		<div class="inside">
			<p>This plugin has a PRO version with tons more features, like:</p>
			<ul>
			<li>redirecting upon completion</li>
			<li>progress graphs (bar and circle)</li>
			<li>textual progress indicators</li>
			<li>full email support</li>
			<li>and more...</li>
			</ul>
			<p><a href="<?php echo esc_html( WPCOMPLETE_STORE_URL ); ?>">Check out all the benefits</a></p>
		</div>
	</div>

	<div class="postbox">
	<h2><span>Want 10% off the PRO version?</span></h2>
	<div class="inside">
		<p>Get a great deal on the PRO version and all our best course tips.</p>

		<form action="//wpcomplete.us13.list-manage.com/subscribe/post?u=aa3f8a628a4c1c32b221a6399&amp;id=a3f8cf0350" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
			<div id="mce-responses" class="clear">
				<div class="response" id="mce-error-response" style="display:none"></div>
				<div class="response" id="mce-success-response" style="display:none"></div>
			</div>
			<div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_aa3f8a628a4c1c32b221a6399_a3f8cf0350" tabindex="-1" value=""></div>
			<input type="email" value="" name="EMAIL" placeholder="Email Address" class="required email" id="mce-EMAIL">
			<input type="hidden" name="GROUPINGS[7265]" value="Free">
			<input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button">
		</form>
	</div>
	</div>

	<?php endif; ?>

	<div class="postbox">
		<h2><span>Need help?</span></h2>
		<div class="inside">
			<?php if ( ! WPCOMPLETE_IS_ACTIVATED ) : ?>
				<!-- FREE: -->
				<p>Need help? We’ve got <a href="https://help.ithemes.com/hc/en-us/categories/360004523854-WPComplete" target="_blank">a complete support section</a> on our website to help you find what you’re looking for. Need more? Our <a href="<?php echo esc_html( WPCOMPLETE_STORE_URL ); ?>">PRO version</a> comes with email support, but feel free to ping us on the <a href="https://wordpress.org/support/plugin/<?php echo esc_html( WPCOMPLETE_PREFIX ); ?>">WordPress support forum</a>.</p>
			<?php else : ?>
				<!-- PREMIUM: -->
				<p>Need help? We’ve got <a href="https://help.ithemes.com/hc/en-us/categories/360004523854-WPComplete" target="_blank">a complete support section</a> on our website to help you find what you’re looking for. Still need help or found a bug? <a href="mailto:nerds@wpcomplete.co">Let us know</a>.</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( wpcomplete_is_production() && WPCOMPLETE_IS_ACTIVATED ) : ?>
		<div class="postbox">
			<div class="inside">
				<p>Want to change your license URL? Manage your license by logging in <a href="https://members.ithemes.com/login">here</a>, and clicking Manage Sites.</p>
			</div>
		</div>
	<?php endif; ?>

</div>

<hr>
<p>If you like <?php echo esc_html( WPCOMPLETE_PRODUCT_NAME ); ?>, please <a href="https://wordpress.org/support/view/plugin-reviews/<?php echo esc_html( WPCOMPLETE_PREFIX ); ?>">leave us a ★★★★★ rating</a>. Your votes really make a difference! Thanks.</p>

</div>
