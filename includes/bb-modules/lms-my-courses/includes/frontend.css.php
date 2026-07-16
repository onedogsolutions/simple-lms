<?php
/**
 * LMS My Courses — dynamic (settings-driven) CSS.
 *
 * @package SimpleLMS
 */

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
<?php if (!empty($settings->accent_color)) : ?>
.fl-node-<?php echo $id; ?> .slms-mc-progress .slms-progress-bar-fill {
    background-color: <?php echo slms_color($settings->accent_color); ?>;
}
.fl-node-<?php echo $id; ?> .slms-mc-action .slms-cta-button {
    background-color: <?php echo slms_color($settings->accent_color); ?>;
}
<?php endif; ?>

<?php if (!empty($settings->button_text_color)) : ?>
.fl-node-<?php echo $id; ?> .slms-mc-action .slms-cta-button {
    color: <?php echo slms_color($settings->button_text_color); ?>;
}
<?php endif; ?>
