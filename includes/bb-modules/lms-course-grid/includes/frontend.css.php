<?php
/**
 * LMS Course Grid — dynamic (settings-driven) CSS.
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
<?php if (!empty($settings->card_bg_color)) : ?>
.fl-node-<?php echo $id; ?> .slms-course-card {
    background-color: <?php echo slms_color($settings->card_bg_color); ?>;
}
<?php endif; ?>

<?php if (!empty($settings->accent_color)) : ?>
.fl-node-<?php echo $id; ?> .slms-course-grid .slms-cta-button {
    background-color: <?php echo slms_color($settings->accent_color); ?>;
}
.fl-node-<?php echo $id; ?> .slms-card-progress .slms-progress-bar-fill {
    background-color: <?php echo slms_color($settings->accent_color); ?>;
}
<?php endif; ?>

<?php if (!empty($settings->cta_text_color)) : ?>
.fl-node-<?php echo $id; ?> .slms-course-grid .slms-cta-button {
    color: <?php echo slms_color($settings->cta_text_color); ?>;
}
<?php endif; ?>
