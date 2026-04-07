<?php
/** 
 * Frontend rendering for the Student Dashboard Tab 2 (Purchase History).
 *
 * @package SimpleLMS
 */

namespace SimpleLMS\BB\Modules;

use MemberOrder;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Tab2
 */
class Tab2 {

    /**
     * Render the tab content.
     *
     * @param WP_User $user The user object.
     * @return string
     */
    public function render($user) {
        $orders = MemberOrder::get_orders(['user_id' => $user->ID]);
        
        ob_start();
        ?>
        <div class="slms-dashboard-tab-2">
            <?php if (empty($orders)): ?>
                <p>No purchase history found.</p>
            <?php else: ?>
                <table class="slms-purchase-history-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo esc_html($order->code); ?></td>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($order->timestamp))); ?></td>
                                <td><?php echo esc_html($order->total); ?></td>
                                <td><?php echo esc_html($order->status); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}