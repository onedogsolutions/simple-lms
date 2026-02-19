<?php
/**
 * Dynamic CSS for the SLMS Student Dashboard module.
 *
 * @package SimpleLMS
 */

/**
 * @var object $settings
 * @var string $id
 */

// Tab Navigation Style
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-tabs",
	'props'    => array(
		'list-style'    => 'none',
		'padding'       => '0',
		'margin'        => '0 0 20px 0',
		'display'       => 'flex',
		'flex-wrap'     => 'wrap',
		'gap'           => '10px',
		'border-bottom' => "2px solid #{$settings->tab_border_color}",
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-tabs li",
	'props'    => array(
		'flex'             => '1',
		'min-width'        => '200px',
		'background-color' => "#{$settings->tab_bg_color}",
		'border-radius'    => '4px 4px 0 0',
		'transition'       => 'all 0.3s ease',
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-tabs li a",
	'props'    => array(
		'display'         => 'flex',
		'align-items'     => 'center',
		'padding'         => '20px',
		'text-decoration' => 'none',
		'color'           => "#{$settings->tab_text_color}",
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-tabs li.active",
	'props'    => array(
		'background-color' => "#{$settings->tab_active_bg_color}",
		'box-shadow'       => '0 -2px 10px rgba(0,0,0,0.1)',
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-tabs li.active a",
	'props'    => array(
		'color' => "#{$settings->tab_active_text_color}",
	),
) );

// Icons and Labels
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-icon",
	'props'    => array(
		'font-size'    => '24px',
		'width'        => '24px',
		'height'       => '24px',
		'margin-right' => '15px',
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-label-group strong",
	'props'    => array(
		'display'   => 'block',
		'font-size' => "{$settings->heading_font_size}px",
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-label-group span",
	'props'    => array(
		'display'   => 'block',
		'font-size' => '12px',
		'opacity'   => '0.8',
	),
) );

// Content Panel
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-content",
	'props'    => array(
		'background-color' => "#{$settings->content_bg_color}",
		'padding'          => "{$settings->content_padding}px",
		'border'           => "1px solid #{$settings->tab_border_color}",
		'border-top'       => 'none',
		'font-size'        => "{$settings->body_font_size}px",
	),
) );

// Table Styling
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-table",
	'props'    => array(
		'width'           => '100%',
		'border-collapse' => 'collapse',
		'margin-top'      => '20px',
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-table th",
	'props'    => array(
		'background-color' => "#{$settings->table_header_bg}",
		'color'            => "#{$settings->table_header_text}",
		'text-align'       => 'left',
		'padding'          => '12px 15px',
		'border-bottom'    => "2px solid #{$settings->table_border_color}",
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-table td",
	'props'    => array(
		'padding'       => '12px 15px',
		'border-bottom' => "1px solid #{$settings->table_border_color}",
	),
) );

FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-table tr:nth-child(even)",
	'props'    => array(
		'background-color' => "#{$settings->table_row_alt_bg}",
	),
) );

// Buttons
FLBuilderCSS::rule( array(
	'selector' => ".fl-node-$id .slms-dash-btn",
	'props'    => array(
		'display'          => 'inline-block',
		'padding'          => '8px 16px',
		'background-color' => "#{$settings->tab_active_text_color}", // Using pink as default action color
		'color'            => '#ffffff',
		'text-decoration'  => 'none',
		'border-radius'    => '4px',
		'font-size'        => '12px',
		'font-weight'      => 'bold',
	),
) );
