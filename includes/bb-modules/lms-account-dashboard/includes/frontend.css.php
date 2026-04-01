<?php
/**
 * Student Dashboard – Dynamic CSS
 *
 * Maps every BB settings field to its corresponding HTML selector.
 *
 * Available helpers (standard Beaver Builder API):
 *   FLBuilderCSS::rule()                  – generic property/value rule
 *   FLBuilderCSS::border_field_rule()     – border group (style/width/color/radius/shadow)
 *   FLBuilderCSS::typography_field_rule() – typography group
 *   FLBuilderCSS::dimension_field_rule()  – dimension group (padding/margin)
 */

// ── Helper: safe hex output (handles plain hex or rgba strings) ────────────
if ( ! function_exists( 'slms_color' ) ) {
	function slms_color( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		if ( false !== strpos( $value, '(' ) || false !== strpos( $value, '#' ) ) {
			return $value;
		}
		return '#' . ltrim( $value, '#' );
	}
}

// SECTION 1 – TABS STYLE
FLBuilderCSS::typography_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'tab_typography',
	'selector'     => ".fl-node-$id .slms-tabs-nav .slms-tab-link",
) );

FLBuilderCSS::dimension_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'tab_padding',
	'selector'     => ".fl-node-$id .slms-tabs-nav .slms-tab-link",
	'unit'         => 'px',
	'props'        => array(
		'padding-top'    => 'tab_padding_top',
		'padding-right'  => 'tab_padding_right',
		'padding-bottom' => 'tab_padding_bottom',
		'padding-left'   => 'tab_padding_left',
	),
) );

FLBuilderCSS::dimension_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'tab_margin',
	'selector'     => ".fl-node-$id .slms-tabs-nav .slms-tab-link",
	'unit'         => 'px',
	'props'        => array(
		'margin-top'    => 'tab_margin_top',
		'margin-right'  => 'tab_margin_right',
		'margin-bottom' => 'tab_margin_bottom',
		'margin-left'   => 'tab_margin_left',
	),
) );

FLBuilderCSS::border_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'tab_border_group',
	'selector'     => ".fl-node-$id .slms-tabs-nav .slms-tab-link",
) );

FLBuilderCSS::border_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'tab_active_border_group',
	'selector'     => ".fl-node-$id .slms-tabs-nav .slms-tab-link.active",
) );
?>

.fl-node-<?php echo $id; ?> .slms-tabs-nav .slms-tab-link {
	<?php if ( ! empty( $settings->tab_bg_color ) ) : ?>
	background-color: <?php echo slms_color( $settings->tab_bg_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->tab_text_color ) ) : ?>
	color: <?php echo slms_color( $settings->tab_text_color ); ?>;
	<?php endif; ?>
}

.fl-node-<?php echo $id; ?> .slms-tabs-nav .slms-tab-link.active {
	<?php if ( ! empty( $settings->tab_active_bg_color ) ) : ?>
	background-color: <?php echo slms_color( $settings->tab_active_bg_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->tab_active_text_color ) ) : ?>
	color: <?php echo slms_color( $settings->tab_active_text_color ); ?>;
	<?php endif; ?>
}

.fl-node-<?php echo $id; ?> .slms-tabs-nav .slms-tab-link:hover {
	<?php if ( ! empty( $settings->tab_hover_bg_color ) ) : ?>
	background-color: <?php echo slms_color( $settings->tab_hover_bg_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->tab_hover_text_color ) ) : ?>
	color: <?php echo slms_color( $settings->tab_hover_text_color ); ?>;
	<?php endif; ?>
}

<?php
// SECTION 2 – FORM STYLE
FLBuilderCSS::typography_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'input_typography',
	'selector'     => ".fl-node-$id .slms-profile-form .slms-input",
) );

FLBuilderCSS::dimension_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'input_padding',
	'selector'     => ".fl-node-$id .slms-profile-form .slms-input",
	'unit'         => 'px',
	'props'        => array(
		'padding-top'    => 'input_padding_top',
		'padding-right'  => 'input_padding_right',
		'padding-bottom' => 'input_padding_bottom',
		'padding-left'   => 'input_padding_left',
	),
) );

FLBuilderCSS::border_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'input_border_group',
	'selector'     => ".fl-node-$id .slms-profile-form .slms-input",
) );
?>

.fl-node-<?php echo $id; ?> .slms-profile-form .slms-input {
	<?php if ( ! empty( $settings->input_bg_color ) ) : ?>
	background-color: <?php echo slms_color( $settings->input_bg_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->input_text_color ) ) : ?>
	color: <?php echo slms_color( $settings->input_text_color ); ?>;
	<?php endif; ?>
}

.fl-node-<?php echo $id; ?> .slms-profile-form .slms-field-label {
	<?php if ( ! empty( $settings->input_label_color ) ) : ?>
	color: <?php echo slms_color( $settings->input_label_color ); ?>;
	<?php endif; ?>
}

.fl-node-<?php echo $id; ?> .slms-profile-form .slms-input:focus {
	outline: none;
	<?php if ( ! empty( $settings->input_focus_bg_color ) ) : ?>
	background-color: <?php echo slms_color( $settings->input_focus_bg_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->input_focus_border_color ) ) : ?>
	border-color: <?php echo slms_color( $settings->input_focus_border_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->input_focus_text_color ) ) : ?>
	color: <?php echo slms_color( $settings->input_focus_text_color ); ?>;
	<?php endif; ?>
}

<?php
// SECTION 3 – BUTTON STYLE
FLBuilderCSS::typography_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'button_typography',
	'selector'     => ".fl-node-$id .slms-profile-form .slms-submit-btn",
) );

FLBuilderCSS::dimension_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'button_padding',
	'selector'     => ".fl-node-$id .slms-profile-form .slms-submit-btn",
	'unit'         => 'px',
	'props'        => array(
		'padding-top'    => 'button_padding_top',
		'padding-right'  => 'button_padding_right',
		'padding-bottom' => 'button_padding_bottom',
		'padding-left'   => 'button_padding_left',
	),
) );

FLBuilderCSS::border_field_rule( array(
	'settings'     => $settings,
	'setting_name' => 'button_border_group',
	'selector'     => ".fl-node-$id .slms-profile-form .slms-submit-btn",
) );
?>

.fl-node-<?php echo $id; ?> .slms-profile-form .slms-submit-btn {
	<?php if ( ! empty( $settings->button_bg_color ) ) : ?>
	background-color: <?php echo slms_color( $settings->button_bg_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->button_text_color ) ) : ?>
	color: <?php echo slms_color( $settings->button_text_color ); ?>;
	<?php endif; ?>
}

.fl-node-<?php echo $id; ?> .slms-profile-form .slms-submit-btn:hover {
	<?php if ( ! empty( $settings->button_hover_bg_color ) ) : ?>
	background-color: <?php echo slms_color( $settings->button_hover_bg_color ); ?>;
	<?php endif; ?>
	<?php if ( ! empty( $settings->button_hover_text_color ) ) : ?>
	color: <?php echo slms_color( $settings->button_hover_text_color ); ?>;
	<?php endif; ?>
}
