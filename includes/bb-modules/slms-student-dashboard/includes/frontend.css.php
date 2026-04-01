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
        'border-bottom' => "2px solid #" . ( isset( $settings->tab_border_color ) ? $settings->tab_border_color : 'e0e0e0' ),
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-tabs li",
    'props'    => array(
        'flex'             => '1',
        'min-width'        => '200px',
        'background-color' => "#" . ( isset( $settings->tab_bg_color ) ? $settings->tab_bg_color : '001f3f' ),
        'border-radius'    => '4px 4px 0 0',
        'transition'       => 'all 0.3s ease',
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-tabs li:hover",
    'props'    => array(
        'background-color' => "#" . ( isset( $settings->tab_hover_bg_color ) ? $settings->tab_hover_bg_color : '113355' ),
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-tabs li a",
    'props'    => array(
        'display'         => 'flex',
        'align-items'     => 'center',
        'padding'         => '20px',
        'text-decoration' => 'none',
        'color'           => "#" . ( isset( $settings->tab_text_color ) ? $settings->tab_text_color : 'ffffff' ),
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-tabs li.active",
    'props'    => array(
        'background-color' => "#" . ( isset( $settings->tab_active_bg_color ) ? $settings->tab_active_bg_color : 'ffffff' ),
        'box-shadow'       => '0 -2px 10px rgba(0,0,0,0.1)',
        'border'           => "1px solid #" . ( isset( $settings->tab_border_color ) ? $settings->tab_border_color : 'e0e0e0' ),
        'border-bottom'    => 'none',
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-tabs li.active a",
    'props'    => array(
        'color' => "#" . ( isset( $settings->tab_active_text_color ) ? $settings->tab_active_text_color : 'e91e63' ),
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
        'font-size' => ( isset( $settings->heading_font_size ) ? $settings->heading_font_size : '18' ) . "px",
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
        'background-color' => "#" . ( isset( $settings->content_bg_color ) ? $settings->content_bg_color : 'ffffff' ),
        'padding'          => ( isset( $settings->content_padding ) ? $settings->content_padding : '20' ) . "px",
        'border'           => "1px solid #" . ( isset( $settings->tab_border_color ) ? $settings->tab_border_color : 'e0e0e0' ),
        'border-top'       => 'none',
        'font-size'        => ( isset( $settings->body_font_size ) ? $settings->body_font_size : '14' ) . "px",
        'border-radius'    => ( isset( $settings->content_border_radius ) ? $settings->content_border_radius : '4' ) . "px",
    ),
) );

// Form Fields
FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-panel input[type='text'], .fl-node-$id .slms-dash-panel input[type='email'], .fl-node-$id .slms-dash-panel input[type='password'], .fl-node-$id .slms-dash-panel input[type='date'], .fl-node-$id .slms-dash-panel select",
    'props'    => array(
        'background-color' => "#" . ( isset( $settings->input_bg_color ) ? $settings->input_bg_color : 'ffffff' ),
        'color'            => "#" . ( isset( $settings->input_text_color ) ? $settings->input_text_color : '333333' ),
        'border'           => "1px solid #" . ( isset( $settings->input_border_color ) ? $settings->input_border_color : 'cccccc' ),
        'padding'          => '8px 12px',
        'border-radius'    => '4px',
        'width'            => '100%',
        'max-width'        => '100%',
        'margin-bottom'    => '15px',
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-panel input:focus, .fl-node-$id .slms-dash-panel select:focus",
    'props'    => array(
        'border-color' => "#" . ( isset( $settings->input_focus_border_color ) ? $settings->input_focus_border_color : '0073aa' ),
        'outline'      => 'none',
        'box-shadow'   => '0 0 0 1px #' . ( isset( $settings->input_focus_border_color ) ? $settings->input_focus_border_color : '0073aa' ),
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
        'background-color' => "#" . ( isset( $settings->table_header_bg ) ? $settings->table_header_bg : 'f7f7f7' ),
        'color'            => "#" . ( isset( $settings->table_header_text ) ? $settings->table_header_text : '333333' ),
        'text-align'       => 'left',
        'padding'          => '12px 15px',
        'border-bottom'    => "2px solid #" . ( isset( $settings->table_border_color ) ? $settings->table_border_color : 'e0e0e0' ),
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-table td",
    'props'    => array(
        'padding'       => '12px 15px',
        'border-bottom' => "1px solid #" . ( isset( $settings->table_border_color ) ? $settings->table_border_color : 'e0e0e0' ),
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-table tr:nth-child(even)",
    'props'    => array(
        'background-color' => "#" . ( isset( $settings->table_row_alt_bg ) ? $settings->table_row_alt_bg : 'fafafa' ),
    ),
) );

// Buttons
FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-btn, .fl-node-$id .slms-dash-panel input[type='submit']",
    'props'    => array(
        'display'          => 'inline-block',
        'padding'          => '8px 16px',
        'background-color' => "#" . ( isset( $settings->btn_bg_color ) ? $settings->btn_bg_color : 'e91e63' ),
        'color'            => "#" . ( isset( $settings->btn_text_color ) ? $settings->btn_text_color : 'ffffff' ),
        'text-decoration'  => 'none',
        'border-radius'    => '4px',
        'font-size'        => '14px',
        'font-weight'      => 'bold',
        'border'           => 'none',
        'cursor'           => 'pointer',
        'transition'       => 'background-color 0.2s ease',
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-dash-btn:hover, .fl-node-$id .slms-dash-panel input[type='submit']:hover",
    'props'    => array(
        'background-color' => "#" . ( isset( $settings->btn_hover_bg_color ) ? $settings->btn_hover_bg_color : 'c2185b' ),
    ),
) );

// Profile specific overrides
FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-field",
    'props'    => array(
        'margin-bottom' => '15px',
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-field label",
    'props'    => array(
        'display'       => 'block',
        'margin-bottom' => '5px',
        'font-weight'   => 'bold',
    ),
) );

FLBuilderCSS::rule( array(
    'selector' => ".fl-node-$id .slms-read-only-value",
    'props'    => array(
        'background-color' => '#f9f9f9',
        'padding'          => '8px 12px',
        'border-radius'    => '4px',
        'border'           => '1px dashed #cccccc',
        'color'            => '#666666',
        'margin-bottom'    => '15px',
    ),
) );
