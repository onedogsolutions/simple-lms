<?php
/**
 * Account Dashboard
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AccountDashboard
 */
class AccountDashboard {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_shortcode( 'simple_lms_account', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Render the account dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML content.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'default_tab' => 'profile',
			),
			$atts,
			'simple_lms_account'
		);

		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to view your account.', 'simple-lms-bridge' ) . '</p>';
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : $atts['default_tab'];

		ob_start();
		self::render_tabs( $current_tab );
		self::render_content( $current_tab );
		return ob_get_clean();
	}

	/**
	 * Render navigation tabs.
	 *
	 * @param string $current_tab Active tab slug.
	 */
	private static function render_tabs( $current_tab ) {
		$tabs = array(
			'profile'      => __( 'Profile', 'simple-lms-bridge' ),
			'orders'       => __( 'Orders', 'simple-lms-bridge' ),
			'certificates' => __( 'Certificates', 'simple-lms-bridge' ),
		);

		$url = remove_query_arg( 'tab' );

		echo '<div class="slms-account-tabs">';
		echo '<ul class="slms-tabs-nav">';
		foreach ( $tabs as $slug => $label ) {
			$class    = ( $slug === $current_tab ) ? 'active' : '';
			$tab_url  = add_query_arg( 'tab', $slug, $url );
			echo '<li class="' . esc_attr( $class ) . '"><a href="' . esc_url( $tab_url ) . '">' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Render tab content.
	 *
	 * @param string $current_tab Active tab slug.
	 */
	private static function render_content( $current_tab ) {
		$user_id = get_current_user_id();

		echo '<div class="slms-tab-content">';
		switch ( $current_tab ) {
			case 'orders':
				self::render_orders( $user_id );
				break;
			case 'certificates':
				self::render_certificates( $user_id );
				break;
			case 'profile':
			default:
				self::render_profile( $user_id );
				break;
		}
		echo '</div>';
	}

	/**
	 * Render Profile Tab.
	 *
	 * @param int $user_id User ID.
	 */
	private static function render_profile( $user_id ) {
		// Handle form submission.
		if ( isset( $_POST['slms_profile_nonce'] ) && wp_verify_nonce( $_POST['slms_profile_nonce'], 'slms_save_profile' ) ) {
			$meta_class = new UserMeta();
			$meta_class->save_fields( $user_id );
			echo '<div class="slms-notice success"><p>' . __( 'Profile updated.', 'simple-lms-bridge' ) . '</p></div>';
		}

		echo '<h3>' . __( 'My Profile', 'simple-lms-bridge' ) . '</h3>';
		echo '<form method="post" action="">';
		wp_nonce_field( 'slms_save_profile', 'slms_profile_nonce' );
		
		$meta_object = new UserMeta();
		$fields      = $meta_object->get_registered_fields();

		foreach ( $fields as $key => $field ) {
			$value       = get_user_meta( $user_id, $key, true );
			$is_read_only = isset( $field['read_only_frontend'] ) && $field['read_only_frontend'];

			echo '<div class="slms-field">';
			echo '<label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label>';
			
			if ( $is_read_only ) {
				// Read-only view: Display value only.
				$display_value = $value;
				
				// Handle array values (e.g. multiselect)
				if ( is_array( $display_value ) ) {
					// If it's the enrolled courses field, we might want to show titles.
					if ( 'post_multiselect' === $field['type'] && isset( $field['options'] ) ) {
						$titles = array();
						foreach ( $display_value as $item_val ) {
							if ( isset( $field['options'][ $item_val ] ) ) {
								$titles[] = $field['options'][ $item_val ];
							} else {
								$titles[] = $item_val;
							}
						}
						$display_value = implode( ', ', $titles );
					} else {
						$display_value = implode( ', ', $display_value );
					}
				} elseif ( 'select' === $field['type'] && isset( $field['options'] ) && isset( $field['options'][ $value ] ) ) {
					$display_value = $field['options'][ $value ];
				}
				
				echo '<div class="slms-read-only-value">' . esc_html( $display_value ) . '</div>';
			} elseif ( 'select' === $field['type'] && isset( $field['options'] ) ) {
				echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
				foreach ( $field['options'] as $option_value => $option_label ) {
					echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'date' === $field['type'] ) {
				echo '<input type="date" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}
			
			if ( ! empty( $field['description'] ) ) {
				echo '<span class="description">' . esc_html( $field['description'] ) . '</span>';
			}
			echo '</div>';
		}

		echo '<p><input type="submit" value="' . __( 'Save Changes', 'simple-lms-bridge' ) . '" /></p>';
		echo '</form>';
	}

	/**
	 * Render Orders Tab.
	 *
	 * @param int $user_id User ID.
	 */
	private static function render_orders( $user_id ) {
		echo '<h3>' . __( 'My Orders', 'simple-lms-bridge' ) . '</h3>';
		// Placeholder for PMPro Orders integration.
		echo '<p>' . __( 'Order history will appear here.', 'simple-lms-bridge' ) . '</p>';
	}

	/**
	 * Render Certificates Tab.
	 *
	 * @param int $user_id User ID.
	 */
	private static function render_certificates( $user_id ) {
		echo '<h3>' . __( 'My Certificates', 'simple-lms-bridge' ) . '</h3>';
		// Placeholder for Certificates integration.
		echo '<p>' . __( 'Certificates will appear here.', 'simple-lms-bridge' ) . '</p>';
	}
}
