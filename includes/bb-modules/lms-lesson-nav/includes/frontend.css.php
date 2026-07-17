<?php
/**
 * LMS Lesson Nav — dynamic (settings-driven) CSS.
 *
 * @package SimpleLMS
 */

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
?>
<?php if ( ! empty( $settings->link_color ) ) : ?>
.fl-node-<?php echo $id; ?> .slms-nav-back {
	color: <?php echo slms_color( $settings->link_color ); ?>;
}
.fl-node-<?php echo $id; ?> .slms-nav-link:not(.is-locked):not(.is-empty):hover {
	border-color: <?php echo slms_color( $settings->link_color ); ?>;
}
<?php endif; ?>
