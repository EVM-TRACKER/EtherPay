<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.1.0
 *
 * @var WC_Order $order
 */

defined('ABSPATH') || exit;
?>

<div class="woocommerce-order">

    <?php
    if ($order) :

        do_action('woocommerce_before_thankyou', $order->get_id());
        ?>

        <?php if ($order->has_status('failed')) : ?>

        <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce'); ?></p>

        <p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
            <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button pay"><?php esc_html_e('Pay', 'woocommerce'); ?></a>
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button pay"><?php esc_html_e('My account', 'woocommerce'); ?></a>
            <?php endif; ?>
        </p>

    <?php else : ?>

        <?php wc_get_template('checkout/order-received.php', array('order' => $order)); ?>

        <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

            <li class="woocommerce-order-overview__order order">
                <?php esc_html_e('Order number:', 'woocommerce'); ?>
                <strong><?php echo $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
            </li>

            <li class="woocommerce-order-overview__date date">
                <?php esc_html_e('Date:', 'woocommerce'); ?>
                <strong><?php echo wc_format_datetime($order->get_date_created()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
            </li>

            <?php if (is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email()) : ?>
                <li class="woocommerce-order-overview__email email">
                    <?php esc_html_e('Email:', 'woocommerce'); ?>
                    <strong><?php echo $order->get_billing_email(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
                </li>
            <?php endif; ?>

            <li class="woocommerce-order-overview__total total">
                <?php esc_html_e('Total:', 'woocommerce'); ?>
                <strong><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
                <strong>
                    <?php
                    $evmtracker = new WC_Gateway_Evmtracker();
                    $currentcy_rate = $evmtracker->get_option('currentcy_rate');
                    $currentcy_code = $evmtracker->get_option('currentcy_code');
                    $web3_total = 0;
                    if (!empty($currentcy_rate) && (float)$currentcy_rate > 0) {
                        $web3_total = $order->get_total() / (float)$currentcy_rate;
                    }
                    echo $currentcy_code . ' ' . number_format($web3_total, 8, '.', '');
                    ?>
                </strong>
            </li>

            <?php if ($order->get_payment_method_title()) : ?>
                <li class="woocommerce-order-overview__payment-method method">
                    <?php esc_html_e('Payment method:', 'woocommerce'); ?>
                    <strong><?php echo wp_kses_post($order->get_payment_method_title()); ?></strong>
                </li>
            <?php endif; ?>

        </ul>

    <?php endif; ?>

        <?php do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id()); ?>
        <div style="text-align: center">
            <p class="wallet-qr" style="width: 220px; margin: 0 auto">
                <a href="#" target="_blank">
                    <?php
                    $web3_order_id = sanitize_text_field(get_post_meta($order->get_id(), 'web3_order_id', true));
                    echo QRcode::svg($web3_order_id);
                    ?>
                </a>
            </p>
            <p>Wallet Address: <?php echo $web3_order_id; ?></p>
            <p><img src="<?php echo $evmtracker->get_option('currentcy_logo') ?>" style="height: 40px;width: auto"></p>
        </div>
    <?php else : ?>

        <?php wc_get_template('checkout/order-received.php', array('order' => false)); ?>

    <?php endif; ?>

</div>
