<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://onedog.solutions
 * @since      1.0.0
 * @last       2.9.0
 *
 * @package    SimpleLMS
 * @subpackage simple-lms/admin/partials
 */

$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="simple-lms-settings wrap">
	<h2><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 90 90" class="logo">
			<path class="inner" fill="#0a4de5" d="M47.6,70.9c-1.1,2.3-4.1,2.8-5.9,1L16.1,46.5c-1.5-1.6-1.4-4.1,0.3-5.4l3.4-3.4c1.3-1.1,3.2-1.1,4.6,0
	l13.5,11.9c1.5,1.2,3.8,1,5-0.5c7.7-8.9,21.7-24.8,40.3-37.4C76.8,2.8,65.2,0,45,0C9,0,0,9,0,45s9,45,45,45s45-9,45-45
	c0-11.3-0.9-19.9-3.2-26.4C69.2,35.9,54.3,56.8,47.6,70.9z"></path>
		</svg></h2>



	<nav class="nav-tab-wrapper">
		<a href="?page=simple-lms&amp;tab=general"
			class="nav-tab<?php echo esc_html('general' === $active_tab ? ' nav-tab-active' : ''); ?>">General</a>
		<a href="?page=simple-lms&amp;tab=buttons"
			class="nav-tab<?php echo esc_html('buttons' === $active_tab ? ' nav-tab-active' : ''); ?>">Buttons</a>
		<a href="?page=simple-lms&amp;tab=graphs"
			class="nav-tab<?php echo esc_html('graphs' === $active_tab ? ' nav-tab-active' : ''); ?>">Graphs</a>
		<a href="?page=simple-lms&amp;tab=advanced"
			class="nav-tab<?php echo esc_html('advanced' === $active_tab ? ' nav-tab-active' : ''); ?>">Advanced</a>
	</nav>

	<div class="content">
		<form action="options.php" method="post">
			<?php
			settings_fields($this->plugin_name . '_' . $active_tab);
			do_settings_sections($this->plugin_name . '_' . $active_tab);
			submit_button();
			?>
		</form>
	</div>

	<div class="sidebar">

		<?php if (!defined('SIMPLE_LMS_IS_ACTIVATED') || !SIMPLE_LMS_IS_ACTIVATED): ?>
		<!-- FREE: -->
		<div class="postbox">
			<h2><span>Update to
					<?php echo esc_html(SIMPLE_LMS_PRODUCT_NAME); ?> PRO
				</span></h2>
			<div class="inside">
				<p>This plugin has a PRO version with tons more features, like:</p>
				<ul>
					<li>redirecting upon completion</li>
					<li>progress graphs (bar and circle)</li>
					<li>textual progress indicators</li>
					<li>full email support</li>
					<li>and more...</li>
				</ul>
				<p><a href="<?php echo esc_html(SIMPLE_LMS_STORE_URL); ?>">Check out all the benefits</a></p>
			</div>
		</div>

		<div