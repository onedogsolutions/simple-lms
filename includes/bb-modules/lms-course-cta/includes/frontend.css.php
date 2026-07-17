<?php
/**
 * LMS Course CTA — dynamic (settings-driven) CSS.
 *
 * @package SimpleLMS
 */

// Safe hex/rgba output helper (shared naming with other SimpleLMS modules).
if (!function_exists('slms_color')) {
    function slms_color($value)
    {
        if (empty($value)) {
            return '';
        }
        if (false !== strpos($value, '(') || false !== strpos($value, '#')) {
            return $value;
        }
        return '#' . ltrim($value, '#');
    }
}
?>
.fl-node-<?php echo $id; ?> .slms-cta-button {
    <?php if (!empty($settings->bg_color)) : ?>
    background-color: <?php echo slms_color($settings->bg_color); ?>;
    <?php endif; ?>
    <?php if (!empty($settings->text_color)) : ?>
    color: <?php echo slms_color($settings->text_color); ?>;
    <?php endif; ?>
}

.fl-node-<?php echo $id; ?> .slms-cta-button:hover,
.fl-node-<?php echo $id; ?> .slms-cta-button:focus {
    <?php if (!empty($settings->hover_bg_color)) : ?>
    background-color: <?php echo slms_color($settings->hover_bg_color); ?>;
    <?php endif; ?>
    <?php if (!empty($settings->text_color)) : ?>
    color: <?php echo slms_color($settings->text_color); ?>;
    <?php endif; ?>
}
