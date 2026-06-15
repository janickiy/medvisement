<?php
/**
 * Recurring cart subtotals totals
 *
 * @author  WooCommerce
 * @package WooCommerce Subscriptions/Templates
 * @version 1.0.0 - Migrated from WooCommerce Subscriptions v3.1.0
 */

defined( 'ABSPATH' ) || exit;
$display_heading = TRUE;

foreach ( $recurring_carts as $recurring_cart_key => $recurring_cart ) { ?>
    <tr class="order-total recurring-total">
        <td colspan="2">
		    <?php wcs_cart_totals_order_total_html( $recurring_cart ); ?>
        </td>
    </tr> <?php
}
