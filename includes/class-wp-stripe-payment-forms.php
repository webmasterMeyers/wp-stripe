<?php

/**
 * Payment forms handler
 *
 * @package WPStripePayment
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Stripe_Payment_Forms
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_ajax_wp_stripe_get_payment_form', array($this, 'ajax_get_payment_form'));
        add_action('wp_ajax_nopriv_wp_stripe_get_payment_form', array($this, 'ajax_get_payment_form'));
    }

    /**
     * AJAX handler for getting payment form
     */
    public function ajax_get_payment_form()
    {
        check_ajax_referer('wp_stripe_payment_nonce', 'nonce');

        $amount = intval($_POST['amount'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'usd');
        $description = sanitize_text_field($_POST['description'] ?? '');
        $metadata = array();

        // Get metadata from POST or URL parameters
        if (!empty($_POST['metadata'])) {
            $metadata = array_map('sanitize_text_field', $_POST['metadata']);
        }

        // Get URL parameters as metadata
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'stripe_') === 0) {
                $clean_key = str_replace('stripe_', '', $key);
                $metadata[$clean_key] = sanitize_text_field($value);
            }
        }

        $form_html = $this->render_payment_form($amount, $currency, $description, $metadata);

        wp_send_json_success(array('form_html' => $form_html));
    }

    /**
     * Render payment form
     *
     * @param int $amount
     * @param string $currency
     * @param string $description
     * @param array $metadata
     * @return string
     */
    public function render_payment_form($amount = 0, $currency = 'usd', $description = '', $metadata = array())
    {
        // Get amount from URL if not provided
        if ($amount <= 0 && isset($_GET['stripe_price'])) {
            $amount = intval($_GET['stripe_price']);
        }

        // Get currency from URL if not provided
        if (empty($currency) && isset($_GET['stripe_currency'])) {
            $currency = sanitize_text_field($_GET['stripe_currency']);
        }

        // Get description from URL if not provided
        if (empty($description) && isset($_GET['stripe_description'])) {
            $description = sanitize_text_field($_GET['stripe_description']);
        }

        // Get additional metadata from URL
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'stripe_') === 0 && !in_array($key, array('stripe_price', 'stripe_currency', 'stripe_description'))) {
                $clean_key = str_replace('stripe_', '', $key);
                $metadata[$clean_key] = sanitize_text_field($value);
            }
        }

        ob_start();
?>
        <div class="wp-stripe-payment-form" data-amount="<?php echo esc_attr($amount); ?>" data-currency="<?php echo esc_attr($currency); ?>">
            <?php if ($amount > 0): ?>
                <div class="payment-summary">
                    <h3><?php _e('Payment Summary', 'wp-stripe-payment'); ?></h3>
                    <div class="amount-display">
                        <span class="currency"><?php echo esc_html(strtoupper($currency)); ?></span>
                        <span class="amount"><?php echo esc_html(number_format($amount / 100, 2)); ?></span>
                    </div>
                    <?php if (!empty($description)): ?>
                        <p class="description"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form id="stripe-payment-form">
                <div class="form-row">
                    <label for="card-element">
                        <?php _e('Credit or debit card', 'wp-stripe-payment'); ?>
                    </label>
                    <div id="card-element" class="stripe-card-element">
                        <!-- Stripe Elements will create input elements here -->
                    </div>
                    <div id="card-errors" class="stripe-error" role="alert"></div>
                </div>

                <?php if ($amount <= 0): ?>
                    <div class="form-row">
                        <label for="amount-input">
                            <?php _e('Amount', 'wp-stripe-payment'); ?>
                        </label>
                        <div class="amount-input-wrapper">
                            <span class="currency-symbol"><?php echo esc_html($this->get_currency_symbol($currency)); ?></span>
                            <input type="number" id="amount-input" name="amount" min="1" step="1" required>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <label for="email-input">
                        <?php _e('Email address', 'wp-stripe-payment'); ?>
                    </label>
                    <input type="email" id="email-input" name="email" required>
                </div>

                <div class="form-row">
                    <label for="name-input">
                        <?php _e('Full name', 'wp-stripe-payment'); ?>
                    </label>
                    <input type="text" id="name-input" name="name" required>
                </div>

                <!-- Hidden fields for metadata -->
                <?php foreach ($metadata as $key => $value): ?>
                    <input type="hidden" name="metadata[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                <?php endforeach; ?>

                <button type="submit" class="submit-button">
                    <span class="button-text"><?php _e('Pay now', 'wp-stripe-payment'); ?></span>
                    <span class="button-loading" style="display: none;">
                        <?php _e('Processing...', 'wp-stripe-payment'); ?>
                    </span>
                </button>
            </form>

            <div id="payment-message" class="payment-message" style="display: none;"></div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Get currency symbol
     *
     * @param string $currency
     * @return string
     */
    private function get_currency_symbol($currency)
    {
        $symbols = array(
            'usd' => '$',
            'eur' => '€',
            'gbp' => '£',
            'cad' => 'C$',
            'aud' => 'A$',
            'jpy' => '¥',
        );

        return $symbols[strtolower($currency)] ?? strtoupper($currency);
    }

    /**
     * Render simple payment button
     *
     * @param int $amount
     * @param string $currency
     * @param string $description
     * @param array $metadata
     * @return string
     */
    public function render_payment_button($amount, $currency = 'usd', $description = '', $metadata = array())
    {
        ob_start();
    ?>
        <div class="wp-stripe-payment-button" data-amount="<?php echo esc_attr($amount); ?>" data-currency="<?php echo esc_attr($currency); ?>">
            <button type="button" class="stripe-payment-button" onclick="wpStripePayment.showPaymentForm(<?php echo esc_js($amount); ?>, '<?php echo esc_js($currency); ?>', '<?php echo esc_js($description); ?>', <?php echo esc_js(json_encode($metadata)); ?>)">
                <?php printf(__('Pay %s %s', 'wp-stripe-payment'), esc_html($this->get_currency_symbol($currency)), esc_html(number_format($amount / 100, 2))); ?>
            </button>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Render payment modal
     *
     * @return string
     */
    public function render_payment_modal()
    {
        ob_start();
    ?>
        <div id="stripe-payment-modal" class="stripe-payment-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Complete Payment', 'wp-stripe-payment'); ?></h3>
                    <button type="button" class="modal-close" onclick="wpStripePayment.closeModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="modal-payment-form"></div>
                </div>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Render success page
     *
     * @param array $payment_data
     * @return string
     */
    public function render_success_page($payment_data)
    {
        ob_start();
    ?>
        <div class="wp-stripe-payment-success">
            <div class="success-icon">✓</div>
            <h2><?php _e('Payment Successful!', 'wp-stripe-payment'); ?></h2>
            <p><?php _e('Thank you for your payment. Your transaction has been completed successfully.', 'wp-stripe-payment'); ?></p>

            <div class="payment-details">
                <h3><?php _e('Payment Details', 'wp-stripe-payment'); ?></h3>
                <table>
                    <tr>
                        <td><strong><?php _e('Transaction ID:', 'wp-stripe-payment'); ?></strong></td>
                        <td><?php echo esc_html($payment_data['payment_intent_id'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Amount:', 'wp-stripe-payment'); ?></strong></td>
                        <td><?php echo esc_html(number_format(($payment_data['amount'] ?? 0) / 100, 2)); ?> <?php echo esc_html(strtoupper($payment_data['currency'] ?? 'usd')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Date:', 'wp-stripe-payment'); ?></strong></td>
                        <td><?php echo esc_html(date('F j, Y, g:i a', ($payment_data['created'] ?? time()))); ?></td>
                    </tr>
                </table>
            </div>

            <div class="success-actions">
                <a href="<?php echo esc_url(home_url()); ?>" class="button"><?php _e('Return to Home', 'wp-stripe-payment'); ?></a>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Render error page
     *
     * @param string $error_message
     * @return string
     */
    public function render_error_page($error_message)
    {
        ob_start();
    ?>
        <div class="wp-stripe-payment-error">
            <div class="error-icon">✗</div>
            <h2><?php _e('Payment Failed', 'wp-stripe-payment'); ?></h2>
            <p><?php echo esc_html($error_message); ?></p>

            <div class="error-actions">
                <button type="button" class="button" onclick="history.back()"><?php _e('Go Back', 'wp-stripe-payment'); ?></button>
                <button type="button" class="button" onclick="wpStripePayment.retryPayment()"><?php _e('Try Again', 'wp-stripe-payment'); ?></button>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
