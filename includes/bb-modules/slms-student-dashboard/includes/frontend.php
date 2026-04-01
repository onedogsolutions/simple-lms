<?php
/**
 * Frontend HTML for the SLMS Student Dashboard module.
 *
 * @package SimpleLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var object $settings */
/** @var string $id */

$user_id = get_current_user_id();

if ( ! $user_id ) {
    echo '<p>' . __( 'Please log in to view your dashboard.', 'simple-lms-bridge' ) . '</p>';
    return;
}

// Map settings to variables.
$cert_data_source  = isset( $settings->cert_data_source ) ? $settings->cert_data_source : 'history_table';
$cert_form_id      = isset( $settings->cert_form_id ) ? $settings->cert_form_id : '';
$tab_label_profile = isset( $settings->tab_label_profile ) ? $settings->tab_label_profile : __( 'User Profile', 'simple-lms-bridge' );
$tab_label_history = isset( $settings->tab_label_history ) ? $settings->tab_label_history : __( 'Purchase History', 'simple-lms-bridge' );
$tab_label_certs   = isset( $settings->tab_label_certs ) ? $settings->tab_label_certs : __( 'Certificates Earned', 'simple-lms-bridge' );

$current_tab = isset( $_GET['dash_tab'] ) ? sanitize_key( $_GET['dash_tab'] ) : 'profile';
if ( \FLBuilderModel::is_builder_active() ) {
    $current_tab = 'profile'; // default to profile in builder
}
$url = remove_query_arg( 'dash_tab' );

?>
<div class="slms-dashboard-wrapper">
    <!-- Tab Navigation -->
    <ul class="slms-dash-tabs">
        <li class="<?php echo ( 'profile' === $current_tab ) ? 'active' : ''; ?>">
            <a href="<?php echo esc_url( add_query_arg( 'dash_tab', 'profile', $url ) ); ?>">
                <span class="slms-dash-icon dashicons dashicons-admin-users"></span>
                <div class="slms-dash-label-group">
                    <strong><?php echo esc_html( $tab_label_profile ); ?></strong>
                    <span><?php _e( 'Update your account information.', 'simple-lms-bridge' ); ?></span>
                </div>
            </a>
        </li>
        <li class="<?php echo ( 'history' === $current_tab ) ? 'active' : ''; ?>">
            <a href="<?php echo esc_url( add_query_arg( 'dash_tab', 'history', $url ) ); ?>">
                <span class="slms-dash-icon dashicons dashicons-cart"></span>
                <div class="slms-dash-label-group">
                    <strong><?php echo esc_html( $tab_label_history ); ?></strong>
                    <span><?php _e( 'View your course purchases.', 'simple-lms-bridge' ); ?></span>
                </div>
            </a>
        </li>
        <li class="<?php echo ( 'certificates' === $current_tab ) ? 'active' : ''; ?>">
            <a href="<?php echo esc_url( add_query_arg( 'dash_tab', 'certificates', $url ) ); ?>">
                <span class="slms-dash-icon dashicons dashicons-awards"></span>
                <div class="slms-dash-label-group">
                    <strong><?php echo esc_html( $tab_label_certs ); ?></strong>
                    <span><?php _e( 'Download your certificates.', 'simple-lms-bridge' ); ?></span>
                </div>
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="slms-dash-content">
        <?php if ( 'profile' === $current_tab ) : ?>
            <div class="slms-dash-panel" id="slms-dash-profile">
                <?php
                if ( class_exists( 'SimpleLMS\AccountDashboard' ) ) {
                    \SimpleLMS\AccountDashboard::render_profile( $user_id );
                } else {
                    echo '<p>' . __( 'Profile system not available.', 'simple-lms-bridge' ) . '</p>';
                }
                ?>
            </div>

        <?php elseif ( 'history' === $current_tab ) : ?>
            <div class="slms-dash-panel" id="slms-dash-history">
                <h3><?php echo esc_html( $tab_label_history ); ?></h3>
                <?php
                if ( class_exists( 'MemberOrder' ) ) {
                    $order_obj = new \MemberOrder();
                    $orders    = $order_obj->getOrders( array( 'user_id' => $user_id ) );
                    
                    if ( ! empty( $orders ) ) : ?>
                        <table class="slms-dash-table">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Date', 'simple-lms-bridge' ); ?></th>
                                    <th><?php _e( 'Order #', 'simple-lms-bridge' ); ?></th>
                                    <th><?php _e( 'Membership Level', 'simple-lms-bridge' ); ?></th>
                                    <th><?php _e( 'Total', 'simple-lms-bridge' ); ?></th>
                                    <th><?php _e( 'Status', 'simple-lms-bridge' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $orders as $order ) : ?>
                                    <?php 
                                    $level_name = __( 'Unknown', 'simple-lms-bridge' );
                                    if ( ! empty( $order->membership_id ) && function_exists( 'pmpro_getLevel' ) ) {
                                        $level = pmpro_getLevel( $order->membership_id );
                                        if ( $level ) {
                                            $level_name = $level->name;
                                        }
                                    } elseif ( isset( $order->membership_level ) ) {
                                        $level_name = $order->membership_level->name;
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo date_i18n( get_option( 'date_format' ), $order->timestamp ); ?></td>
                                        <td><?php echo esc_html( $order->code ); ?></td>
                                        <td><?php echo esc_html( $level_name ); ?></td>
                                        <td><?php echo function_exists( 'pmpro_formatPrice' ) ? pmpro_formatPrice( $order->total ) : esc_html( $order->total ); ?></td>
                                        <td><?php echo esc_html( $order->status ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e( 'No purchase history found.', 'simple-lms-bridge' ); ?></p>
                    <?php endif;
                } else {
                    echo '<div class="slms-notice warning"><p>' . __( 'Purchase history requires Paid Memberships Pro to be active.', 'simple-lms-bridge' ) . '</p></div>';
                }
                ?>
            </div>

        <?php elseif ( 'certificates' === $current_tab ) : ?>
            <div class="slms-dash-panel" id="slms-dash-certificates">
                <h3><?php echo esc_html( $tab_label_certs ); ?></h3>
                
                <?php if ( 'history_table' === $cert_data_source ) : ?>
                    <?php
                    // Source: wp_slms_course_history
                    if ( class_exists( 'SimpleLMS\CourseHistory' ) ) {
                        $records = \SimpleLMS\CourseHistory::get_for_user( $user_id );
                        
                        if ( ! empty( $records ) ) : ?>
                            <table class="slms-dash-table">
                                <thead>
                                    <tr>
                                        <th><?php _e( 'Class', 'simple-lms-bridge' ); ?></th>
                                        <th><?php _e( 'Completion Date', 'simple-lms-bridge' ); ?></th>
                                        <th><?php _e( 'Certificate', 'simple-lms-bridge' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $records as $record ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( $record->course_name ); ?></td>
                                            <td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $record->completed_date ) ); ?></td>
                                            <td>
                                                <?php if ( ! empty( $record->gf_entry_id ) && ! empty( $cert_form_id ) ) : 
                                                    $pdf_url = home_url( "/?gf_pdf=1&fid=" . intval( $cert_form_id ) . "&lid=" . intval( $record->gf_entry_id ) );
                                                ?>
                                                    <a href="<?php echo esc_url( $pdf_url ); ?>" class="slms-dash-btn" target="_blank">
                                                        <?php _e( 'Download PDF', 'simple-lms-bridge' ); ?>
                                                    </a>
                                                <?php else : ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p><?php _e( 'No certificates found.', 'simple-lms-bridge' ); ?></p>
                        <?php endif;
                    } else {
                        echo '<p>' . __( 'Course History component is not available.', 'simple-lms-bridge' ) . '</p>';
                    }
                    ?>
                
                <?php else : ?>
                    <?php
                    // Source: Gravity Forms Entries (Legacy)
                    if ( $cert_form_id && class_exists( 'GFAPI' ) ) {
                        $search_criteria = array(
                            'status'        => 'active',
                            'field_filters' => array(
                                array(
                                    'key'   => 'created_by',
                                    'value' => $user_id,
                                ),
                            ),
                        );
                        $entries = \GFAPI::get_entries( (int) $cert_form_id, $search_criteria );

                        if ( ! is_wp_error( $entries ) && ! empty( $entries ) ) : ?>
                            <table class="slms-dash-table">
                                <thead>
                                    <tr>
                                        <th><?php _e( 'Name', 'simple-lms-bridge' ); ?></th>
                                        <th><?php _e( 'Course', 'simple-lms-bridge' ); ?></th>
                                        <th><?php _e( 'Completion Date', 'simple-lms-bridge' ); ?></th>
                                        <th><?php _e( 'Certificate PDF', 'simple-lms-bridge' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $entries as $entry ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( rgar( $entry, $settings->cert_field_name ) ); ?></td>
                                            <td><?php echo esc_html( rgar( $entry, $settings->cert_field_course ) ); ?></td>
                                            <td>
                                                <?php 
                                                $date_val = rgar( $entry, $settings->cert_field_date );
                                                echo ! empty( $date_val ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date_val ) ) ) : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $pdf_url = rgar( $entry, $settings->cert_field_pdf );
                                                if ( $pdf_url ) : ?>
                                                    <a href="<?php echo esc_url( $pdf_url ); ?>" class="slms-dash-btn" target="_blank">
                                                        <?php _e( 'Download PDF', 'simple-lms-bridge' ); ?>
                                                    </a>
                                                <?php else : ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p><?php _e( 'No certificates found.', 'simple-lms-bridge' ); ?></p>
                        <?php endif;
                    } else {
                        echo '<p>' . __( 'Certificate form not configured or Gravity Forms not active.', 'simple-lms-bridge' ) . '</p>';
                    }
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tabs = document.querySelectorAll('.fl-node-<?php echo esc_js( $id ); ?> .slms-dash-tabs a');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                if (document.body.classList.contains('fl-builder-active')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>