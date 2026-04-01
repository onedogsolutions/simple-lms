<?php
// Verify Nonce and Process Form
$current_user = wp_get_current_user();
$update_success = false;
$update_error = false;

if ( isset( $_POST['slms_profile_nonce'] ) && wp_verify_nonce( $_POST['slms_profile_nonce'], 'slms_update_profile_nonce' ) ) {
    $user_data = array(
        'ID'         => $current_user->ID,
        'first_name' => sanitize_text_field( $_POST['first_name'] ),
        'last_name'  => sanitize_text_field( $_POST['last_name'] ),
        'user_email' => sanitize_email( $_POST['user_email'] ),
    );

    if ( isset( $_POST['update_password'] ) && $_POST['update_password'] == '1' && !empty( $_POST['user_pass'] ) ) {
        if ( $_POST['user_pass'] === $_POST['user_pass_confirm'] ) {
            $user_data['user_pass'] = $_POST['user_pass'];
        } else {
            $update_error = __( 'Passwords do not match.', 'simple-lms' );
        }
    }

    if ( !$update_error ) {
        $user_id = wp_update_user( $user_data );
        if ( is_wp_error( $user_id ) ) {
            $update_error = $user_id->get_error_message();
        } else {
            // Update Meta
            $meta_fields = array( 'phone', 'license_number', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode' );
            foreach ( $meta_fields as $meta_key ) {
                if ( isset( $_POST[ $meta_key ] ) ) {
                    update_user_meta( $user_id, $meta_key, sanitize_text_field( $_POST[ $meta_key ] ) );
                }
            }
            $update_success = __( 'Profile updated successfully.', 'simple-lms' );
            $current_user = wp_get_current_user(); // Refresh data
        }
    }
}
?>

<div class="slms-student-dashboard">
    <?php if ( $update_success ) : ?>
        <div class="slms-alert slms-alert-success"><?php echo esc_html( $update_success ); ?></div>
    <?php endif; ?>
    <?php if ( $update_error ) : ?>
        <div class="slms-alert slms-alert-error"><?php echo esc_html( $update_error ); ?></div>
    <?php endif; ?>

    <ul class="slms-tabs-nav">
        <li class="slms-tab-link active" data-tab="profile"><?php esc_html_e( 'Profile', 'simple-lms' ); ?></li>
        <li class="slms-tab-link" data-tab="history"><?php esc_html_e( 'Purchase History', 'simple-lms' ); ?></li>
        <li class="slms-tab-link" data-tab="certificates"><?php esc_html_e( 'Certificates', 'simple-lms' ); ?></li>
    </ul>

    <div class="slms-tabs-content">
        <!-- Profile Tab -->
        <div id="slms-tab-profile" class="slms-tab-pane active">
            <form method="post" action="" class="slms-profile-form gform_wrapper">
                <?php wp_nonce_field( 'slms_update_profile_nonce', 'slms_profile_nonce' ); ?>
                
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'First Name', 'simple-lms' ); ?></label>
                    <input type="text" name="first_name" value="<?php echo esc_attr( $current_user->first_name ); ?>" required />
                </div>
                
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'Last Name', 'simple-lms' ); ?></label>
                    <input type="text" name="last_name" value="<?php echo esc_attr( $current_user->last_name ); ?>" required />
                </div>
                
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'Email', 'simple-lms' ); ?></label>
                    <input type="email" name="user_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required />
                </div>
                
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'Phone', 'simple-lms' ); ?></label>
                    <input type="text" name="phone" value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'phone', true ) ); ?>" />
                </div>

                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'License Number', 'simple-lms' ); ?></label>
                    <input type="text" name="license_number" value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'license_number', true ) ); ?>" />
                </div>

                <h4><?php esc_html_e( 'Billing Address', 'simple-lms' ); ?></h4>
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'Address Line 1', 'simple-lms' ); ?></label>
                    <input type="text" name="billing_address_1" value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_address_1', true ) ); ?>" />
                </div>
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'Address Line 2', 'simple-lms' ); ?></label>
                    <input type="text" name="billing_address_2" value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_address_2', true ) ); ?>" />
                </div>
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'City', 'simple-lms' ); ?></label>
                    <input type="text" name="billing_city" value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_city', true ) ); ?>" />
                </div>
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'State', 'simple-lms' ); ?></label>
                    <input type="text" name="billing_state" value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_state', true ) ); ?>" />
                </div>
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'Zip Code', 'simple-lms' ); ?></label>
                    <input type="text" name="billing_postcode" value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'billing_postcode', true ) ); ?>" />
                </div>

                <h4><?php esc_html_e( 'Change Password', 'simple-lms' ); ?></h4>
                <div class="gfield">
                    <label><input type="checkbox" name="update_password" value="1" /> <?php esc_html_e( 'Update Password', 'simple-lms' ); ?></label>
                </div>
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'New Password', 'simple-lms' ); ?></label>
                    <input type="password" name="user_pass" value="" />
                </div>
                <div class="gfield">
                    <label class="gfield_label"><?php esc_html_e( 'Confirm Password', 'simple-lms' ); ?></label>
                    <input type="password" name="user_pass_confirm" value="" />
                </div>

                <div class="gfield gform_footer">
                    <button type="submit" class="gform_button button"><?php esc_html_e( 'Update Profile', 'simple-lms' ); ?></button>
                </div>
            </form>
        </div>

        <!-- Purchase History Tab -->
        <div id="slms-tab-history" class="slms-tab-pane">
            <?php 
            if ( class_exists( 'MemberOrder' ) ) : 
                $orders = MemberOrder::getMemberOrders( $current_user->ID );
                if ( !empty( $orders ) ) :
            ?>
                <table class="slms-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Order ID', 'simple-lms' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'simple-lms' ); ?></th>
                            <th><?php esc_html_e( 'Level / Course', 'simple-lms' ); ?></th>
                            <th><?php esc_html_e( 'Total', 'simple-lms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $orders as $order ) : ?>
                            <tr>
                                <td><?php echo esc_html( $order->code ); ?></td>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), $order->timestamp ) ); ?></td>
                                <td><?php echo esc_html( pmpro_getLevel( $order->membership_id )->name ?? __( 'Unknown', 'simple-lms' ) ); ?></td>
                                <td><?php echo esc_html( pmpro_formatPrice( $order->total ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php esc_html_e( 'No purchase history found.', 'simple-lms' ); ?></p>
            <?php endif; ?>
            <?php else: ?>
                <p><?php esc_html_e( 'Paid Memberships Pro is not active.', 'simple-lms' ); ?></p>
            <?php endif; ?>
        </div>

        <!-- Certificates Tab -->
        <div id="slms-tab-certificates" class="slms-tab-pane">
            <?php 
            global $wpdb;
            $table_name = $wpdb->prefix . 'slms_course_history';
            // Verify table exists
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) :
                $history = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE user_id = %d", $current_user->ID ) );
                
                if ( !empty( $history ) ) :
            ?>
                <table class="slms-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Course', 'simple-lms' ); ?></th>
                            <th><?php esc_html_e( 'Completion Date', 'simple-lms' ); ?></th>
                            <th><?php esc_html_e( 'Certificate', 'simple-lms' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $history as $row ) : 
                            $course = get_post( $row->course_id );
                            $course_name = $course ? $course->post_title : __( 'Unknown Course', 'simple-lms' );
                            $gf_entry_id = $row->gf_entry_id ?? 0;
                            $form_id = 1; // Assuming a fallback form ID if not dynamically available
                            
                            // Find GF Form ID by entry ID
                            if ( class_exists( 'GFAPI' ) && $gf_entry_id ) {
                                $entry = GFAPI::get_entry( $gf_entry_id );
                                if ( !is_wp_error($entry) && isset( $entry['form_id'] ) ) {
                                    $form_id = $entry['form_id'];
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html( $course_name ); ?></td>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row->completed_date ) ) ); ?></td>
                                <td>
                                    <?php if ( $gf_entry_id ) : ?>
                                        <a href="<?php echo esc_url( home_url( "/?gf_pdf=1&fid={$form_id}&lid={$gf_entry_id}" ) ); ?>" class="button" target="_blank"><?php esc_html_e( 'Download PDF', 'simple-lms' ); ?></a>
                                    <?php else : ?>
                                        <span><?php esc_html_e( 'N/A', 'simple-lms' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php esc_html_e( 'No certificates found.', 'simple-lms' ); ?></p>
            <?php endif; ?>
            <?php else: ?>
                <p><?php esc_html_e( 'Course history table is not available.', 'simple-lms' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
