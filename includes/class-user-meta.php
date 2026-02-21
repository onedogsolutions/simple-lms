<?php
/**
 * User Meta Management
 *
 * @package SimpleLMS
 */

namespace SimpleLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UserMeta
 */
class UserMeta {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		$self = new self();
		add_action( 'show_user_profile', array( $self, 'render_fields' ) );
		add_action( 'edit_user_profile', array( $self, 'render_fields' ) );
		add_action( 'personal_options_update', array( $self, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( $self, 'save_fields' ) );
	}

	/**
	 * Get registered fields based on Pods JSON.
	 *
	 * @return array
	 */
	public function get_registered_fields() {
		return array(
			// -- Customer Address Group --
			'billing_address_1'    => array(
				'label'       => __( 'Billing Address 1', 'simple-lms-bridge' ),
				'type'        => 'text',
				'description' => __( 'Street Address', 'simple-lms-bridge' ),
			),
			'billing_address_2'    => array(
				'label'       => __( 'Billing Address 2', 'simple-lms-bridge' ),
				'type'        => 'text',
				'description' => __( 'Address Line 2', 'simple-lms-bridge' ),
			),
			'billing_city'         => array(
				'label'       => __( 'Billing City', 'simple-lms-bridge' ),
				'type'        => 'text',
				'description' => __( 'City', 'simple-lms-bridge' ),
			),
			'billing_state'        => array(
				'label'       => __( 'Billing State', 'simple-lms-bridge' ),
				'type'        => 'select',
				'options'     => $this->get_us_states(),
				'description' => __( 'State', 'simple-lms-bridge' ),
			),
			'billing_postcode'     => array(
				'label'       => __( 'Billing Postcode', 'simple-lms-bridge' ),
				'type'        => 'text',
				'description' => __( 'ZIP Code', 'simple-lms-bridge' ),
			),
			'billing_phone'        => array(
				'label'       => __( 'Billing Phone', 'simple-lms-bridge' ),
				'type'        => 'text',
				'description' => __( 'Phone Number', 'simple-lms-bridge' ),
			),

			// -- Customer Information Group --
			'aalp_member'          => array(
				'label'       => __( 'AALP Member', 'simple-lms-bridge' ),
				'type'        => 'select',
				'options'     => array(
					'No'  => __( 'No', 'simple-lms-bridge' ),
					'Yes' => __( 'Yes', 'simple-lms-bridge' ),
				),
				'description' => __( 'Is this student an AALP Member?', 'simple-lms-bridge' ),
			),
			'registration_date'    => array(
				'label'              => __( 'Registration Date', 'simple-lms-bridge' ),
				'type'               => 'date',
				'description'        => __( 'Date registered for last course.', 'simple-lms-bridge' ),
			),
			'enrolled_courses'     => array(
				'label'              => __( 'Enrolled Courses', 'simple-lms-bridge' ),
				'type'               => 'post_multiselect',
				'options'            => $this->get_courses(),
				'description'        => __( 'Courses the student is enrolled in.', 'simple-lms-bridge' ),
				'read_only_frontend' => true,
			),
			'license_number'       => array(
				'label'       => __( 'License Number', 'simple-lms-bridge' ),
				'type'        => 'text',
				'description' => __( 'Laser Technician License Number', 'simple-lms-bridge' ),
			),
			'pro_exam_date'        => array(
				'label'              => __( 'Pro Exam Date', 'simple-lms-bridge' ),
				'type'               => 'date',
				'description'        => __( 'Date student last took the Pro Exam.', 'simple-lms-bridge' ),
				'read_only_frontend' => true,
			),
			'pro_exam_status'      => array(
				'label'              => __( 'Pro Exam Status', 'simple-lms-bridge' ),
				'type'               => 'select',
				'options'            => array(
					''     => __( '-- Select One --', 'simple-lms-bridge' ),
					'Fail' => __( 'Fail', 'simple-lms-bridge' ),
					'Pass' => __( 'Pass', 'simple-lms-bridge' ),
				),
				'description'        => __( 'Exam Status (Pass/Fail).', 'simple-lms-bridge' ),
				'read_only_frontend' => true,
			),
		);
	}

	/**
	 * Helper: Get All LMS Courses.
	 *
	 * @return array
	 */
	private function get_courses() {
		$courses = get_posts(
			array(
				'post_type'      => 'slms_course',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		if ( ! empty( $courses ) ) {
			foreach ( $courses as $course ) {
				$options[ $course->ID ] = $course->post_title;
			}
		}

		return $options;
	}

	/**
	 * Helper: Get US States.
	 *
	 * @return array
	 */
	private function get_us_states() {
		return array(
			''   => __( '-- Select State --', 'simple-lms-bridge' ),
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);
	}

	/**
	 * Render fields in user profile.
	 *
	 * @param \WP_User $user User object.
	 */
	public function render_fields( $user ) {
		$fields = $this->get_registered_fields();

		if ( empty( $fields ) ) {
			return;
		}

		?>
		<h3><?php esc_html_e( 'Simple LMS Profile Information', 'simple-lms-bridge' ); ?></h3>

			<?php foreach ( $fields as $key => $field ) : ?>
				<?php
				if ( 'enrolled_courses' === $key ) {
					$user_courses = Relationships::get_courses_for_user( $user->ID );
					$value        = wp_list_pluck( $user_courses, 'id' );
				} else {
					$value = get_user_meta( $user->ID, $key, true );
				}
				?>
				<tr>
					<th>
						<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					</th>
					<td>
						<?php
						if ( 'select' === $field['type'] && isset( $field['options'] ) ) {
							echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
							foreach ( $field['options'] as $option_value => $option_label ) {
								echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
							}
							echo '</select>';
						} elseif ( 'post_multiselect' === $field['type'] && isset( $field['options'] ) ) {
							// For multiselect, value might be an array or serialized.
							if ( ! is_array( $value ) ) {
								$value = (array) $value;
							}
							echo '<select name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" multiple="multiple" class="slms-select2" style="width: 100%; min-width:300px; height: 150px;">';
							foreach ( $field['options'] as $option_value => $option_label ) {
								$selected = in_array( (string) $option_value, $value ) || in_array( (int) $option_value, $value );
								echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $selected, true, false ) . '>' . esc_html( $option_label ) . '</option>';
							}
							echo '</select>';
							echo '<p class="description" style="font-size: 11px;">' . __( 'Hold Ctrl/Cmd to select multiple options.', 'simple-lms-bridge' ) . '</p>';
						} elseif ( 'date' === $field['type'] ) {
							echo '<input type="date" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
						} else {
							echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
						}
						?>

						<?php if ( ! empty( $field['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Save fields.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$fields = $this->get_registered_fields();

		foreach ( $fields as $key => $field ) {
			if ( 'enrolled_courses' === $key ) {
				$new_courses = isset( $_POST[ $key ] ) ? (array) $_POST[ $key ] : array();
				$new_courses = array_map( 'absint', $new_courses );

				$old_courses_data = Relationships::get_courses_for_user( $user_id );
				$old_courses      = wp_list_pluck( $old_courses_data, 'id' );

				// Enroll new courses.
				foreach ( $new_courses as $course_id ) {
					if ( ! in_array( $course_id, $old_courses, true ) ) {
						Relationships::enroll_user( $user_id, $course_id, 'manual' );
					}
				}

				// Unenroll removed courses.
				foreach ( $old_courses as $course_id ) {
					if ( ! in_array( $course_id, $new_courses, true ) ) {
						Relationships::unenroll_user( $user_id, $course_id );
					}
				}
				continue;
			}

			if ( isset( $_POST[ $key ] ) ) {
				$val = $_POST[ $key ];
				if ( is_array( $val ) ) {
					$val = array_map( 'sanitize_text_field', wp_unslash( $val ) );
				} else {
					$val = sanitize_text_field( wp_unslash( $val ) );
				}
				update_user_meta( $user_id, $key, $val );
			} else {
				// Handle unchecked checkboxes or empty multiselects if necessary (optional).
				if ( 'post_multiselect' === $field['type'] ) {
					delete_user_meta( $user_id, $key ); // Clear if empty
				}
			}
		}
	}
}