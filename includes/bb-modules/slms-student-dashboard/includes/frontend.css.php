<?php
// Tabs Styling
if ( ! empty( $settings->tab_bg_color ) ) {
    echo ".fl-node-$id .slms-tabs-nav .slms-tab-link { background-color: " . FLBuilderColor::hex_or_rgb( $settings->tab_bg_color ) . "; }\n";
}
if ( ! empty( $settings->tab_active_bg_color ) ) {
    echo ".fl-node-$id .slms-tabs-nav .slms-tab-link.active { background-color: " . FLBuilderColor::hex_or_rgb( $settings->tab_active_bg_color ) . "; }\n";
}
if ( ! empty( $settings->tab_text_color ) ) {
    echo ".fl-node-$id .slms-tabs-nav .slms-tab-link { color: " . FLBuilderColor::hex_or_rgb( $settings->tab_text_color ) . "; }\n";
}
if ( ! empty( $settings->tab_active_text_color ) ) {
    echo ".fl-node-$id .slms-tabs-nav .slms-tab-link.active { color: " . FLBuilderColor::hex_or_rgb( $settings->tab_active_text_color ) . "; }\n";
}

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

// Form Input Styling
if ( ! empty( $settings->input_bg_color ) ) {
    echo ".fl-node-$id .slms-profile-form input, .fl-node-$id .slms-profile-form select { background-color: " . FLBuilderColor::hex_or_rgb( $settings->input_bg_color ) . "; }\n";
}
if ( ! empty( $settings->input_text_color ) ) {
    echo ".fl-node-$id .slms-profile-form input, .fl-node-$id .slms-profile-form select { color: " . FLBuilderColor::hex_or_rgb( $settings->input_text_color ) . "; }\n";
}

FLBuilderCSS::border_field_rule( array(
    'settings'     => $settings,
    'setting_name' => 'input_border_group',
    'selector'     => ".fl-node-$id .slms-profile-form input, .fl-node-$id .slms-profile-form select",
) );

FLBuilderCSS::typography_field_rule( array(
    'settings'     => $settings,
    'setting_name' => 'input_typography',
    'selector'     => ".fl-node-$id .slms-profile-form input, .fl-node-$id .slms-profile-form select",
) );

// Button Styling
if ( ! empty( $settings->button_bg_color ) ) {
    echo ".fl-node-$id .slms-profile-form .gform_button { background-color: " . FLBuilderColor::hex_or_rgb( $settings->button_bg_color ) . "; }\n";
}
if ( ! empty( $settings->button_text_color ) ) {
    echo ".fl-node-$id .slms-profile-form .gform_button { color: " . FLBuilderColor::hex_or_rgb( $settings->button_text_color ) . "; }\n";
}
if ( ! empty( $settings->button_hover_bg_color ) ) {
    echo ".fl-node-$id .slms-profile-form .gform_button:hover { background-color: " . FLBuilderColor::hex_or_rgb( $settings->button_hover_bg_color ) . "; }\n";
}
if ( ! empty( $settings->button_hover_text_color ) ) {
    echo ".fl-node-$id .slms-profile-form .gform_button:hover { color: " . FLBuilderColor::hex_or_rgb( $settings->button_hover_text_color ) . "; }\n";
}

FLBuilderCSS::border_field_rule( array(
    'settings'     => $settings,
    'setting_name' => 'button_border_group',
    'selector'     => ".fl-node-$id .slms-profile-form .gform_button",
) );

FLBuilderCSS::typography_field_rule( array(
    'settings'     => $settings,
    'setting_name' => 'button_typography',
    'selector'     => ".fl-node-$id .slms-profile-form .gform_button",
) );
?>