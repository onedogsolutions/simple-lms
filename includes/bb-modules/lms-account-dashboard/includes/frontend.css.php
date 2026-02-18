<?php
/**
 * Dynamic CSS for the LMS Account Dashboard module.
 *
 * @package SimpleLMS
 */

// Tabs Style
/**
 * @var object $settings
 * @var string $id
 */
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-tabs-nav li",
	'props'    => array(
		'background-color' => $settings->tab_bg_color,
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-tabs-nav li.active",
	'props'    => array(
		'background-color' => $settings->tab_active_bg_color,
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-tabs-nav li a",
	'props'    => array(
		'color' => $settings->tab_text_color,
	),
) );

// Form Fields Style
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-tab-content input[type='text'], .fl-node-$id .slms-tab-content select, .fl-node-$id .slms-tab-content input[type='date']",
	'props'    => array(
		'background-color' => $settings->input_bg_color,
		'color'            => $settings->input_text_color,
		'border-color'     => '#ddd', // Default border, could be made dynamic
	),
) );

// Read-Only Fields Style
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-read-only-value",
	'props'    => array(
		'background-color' => $settings->ro_bg_color,
		'color'            => $settings->ro_text_color,
	),
) );

// Buttons Style
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-tab-content input[type='submit']",
	'props'    => array(
		'background-color' => $settings->btn_bg_color,
		'color'            => $settings->btn_text_color,
	),
) );
