<?php

/**
 * Payment handler
 *
 * @package WPStripePayment
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Stripe_Payment_Handler
{

    /**
     * Stripe API instance
     *
     * @var WP_Stripe_Payment_Stripe_API
     */
    private $stripe_api;

    /**
     * Constructor
     *
     * @param WP_Stripe_Payment_Stripe_API $stripe_api
     */
    public function __construct($stripe_api)
    {
        $this->stripe_api = $stripe_api;
    }

    /**
     * Process a payment
     *
     * @param string $payment_intent_id
     * @param array $metadata
     * @return array|WP_Error
     */
    public function process_payment($payment_intent_id, $metadata = array())
    {
        if (empty($payment_intent_id)) {
            return new WP_Error('invalid_payment_intent', 'Payment intent ID is required');
        }

        // Retrieve the payment intent from Stripe
        $payment_intent = $this->stripe_api->retrieve_payment_intent($payment_intent_id);

        if (is_wp_error($payment_intent)) {
            return $payment_intent;
        }

        // Check payment status
        $status = $payment_intent['status'] ?? '';

        if ($status === 'succeeded') {
            // Payment already succeeded, just log it
            $this->log_payment($payment_intent, $metadata);
            return $this->format_payment_result($payment_intent, 'success');
        }

        if ($status === 'requires_confirmation') {
            // Confirm the payment intent
            $confirmed_intent = $this->stripe_api->confirm_payment_intent($payment_intent_id);

            if (is_wp_error($confirmed_intent)) {
                return $confirmed_intent;
            }

            $status = $confirmed_intent['status'] ?? '';
        }

        // Handle different payment statuses
        switch ($status) {
            case 'succeeded':
                $this->log_payment($payment_intent, $metadata);
                return $this->format_payment_result($payment_intent, 'success');

            case 'requires_payment_method':
                return new WP_Error('payment_failed', 'Payment method failed. Please try again.');

            case 'requires_action':
                return new WP_Error('requires_action', 'Payment requires additional action.');

            case 'canceled':
                return new WP_Error('payment_canceled', 'Payment was canceled.');

            default:
                return new WP_Error('unknown_status', 'Unknown payment status: ' . $status);
        }
    }

    /**
     * Create a payment intent
     *
     * @param int $amount
     * @param string $currency
     * @param array $metadata
     * @return array|WP_Error
     */
    public function create_payment_intent($amount, $currency = 'usd', $metadata = array())
    {
        // Validate amount
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than 0');
        }

        // Validate currency
        $supported_currencies = array('usd', 'eur', 'gbp', 'cad', 'aud', 'jpy');
        if (!in_array(strtolower($currency), $supported_currencies)) {
            return new WP_Error('unsupported_currency', 'Unsupported currency: ' . $currency);
        }

        // Create payment intent
        $result = $this->stripe_api->create_payment_intent($amount, $currency, $metadata);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * Log payment to database
     *
     * @param array $payment_intent
     * @param array $metadata
     */
    private function log_payment($payment_intent, $metadata)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_payments';

        $data = array(
            'payment_intent_id' => $payment_intent['id'],
            'amount' => $payment_intent['amount'],
            'currency' => $payment_intent['currency'],
            'status' => $payment_intent['status'],
            'customer_id' => $payment_intent['customer'] ?? null,
            'metadata' => json_encode($metadata),
            'stripe_data' => json_encode($payment_intent),
            'created_at' => current_time('mysql'),
        );

        $wpdb->insert($table_name, $data);

        // Log to WordPress error log for debugging
        error_log('Stripe payment processed: ' . $payment_intent['id'] . ' - Amount: ' . $data['amount'] . ' ' . $data['currency']);
    }

    /**
     * Format payment result for response
     *
     * @param array $payment_intent
     * @param string $status
     * @return array
     */
    private function format_payment_result($payment_intent, $status)
    {
        return array(
            'status' => $status,
            'payment_intent_id' => $payment_intent['id'],
            'amount' => $payment_intent['amount'],
            'currency' => $payment_intent['currency'],
            'customer_id' => $payment_intent['customer'] ?? null,
            'created' => $payment_intent['created'],
            'metadata' => $payment_intent['metadata'] ?? array(),
        );
    }

    /**
     * Get payment by ID
     *
     * @param string $payment_intent_id
     * @return array|false
     */
    public function get_payment($payment_intent_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_payments';

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE payment_intent_id = %s",
                $payment_intent_id
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
     * Get payments by customer
     *
     * @param string $customer_id
     * @param int $limit
     * @return array
     */
    public function get_customer_payments($customer_id, $limit = 10)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_payments';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE customer_id = %s ORDER BY created_at DESC LIMIT %d",
                $customer_id,
                $limit
            ),
            ARRAY_A
        );

        return $results;
    }

    /**
     * Process refund
     *
     * @param string $payment_intent_id
     * @param int $amount
     * @param array $metadata
     * @return array|WP_Error
     */
    public function process_refund($payment_intent_id, $amount = null, $metadata = array())
    {
        $result = $this->stripe_api->create_refund($payment_intent_id, $amount, $metadata);

        if (is_wp_error($result)) {
            return $result;
        }

        // Log refund
        $this->log_refund($result, $payment_intent_id);

        return $result;
    }

    /**
     * Log refund to database
     *
     * @param array $refund
     * @param string $payment_intent_id
     */
    private function log_refund($refund, $payment_intent_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_refunds';

        $data = array(
            'refund_id' => $refund['id'],
            'payment_intent_id' => $payment_intent_id,
            'amount' => $refund['amount'],
            'currency' => $refund['currency'],
            'status' => $refund['status'],
            'stripe_data' => json_encode($refund),
            'created_at' => current_time('mysql'),
        );

        $wpdb->insert($table_name, $data);
    }

    /**
     * Handle webhook events
     *
     * @param array $event_data
     * @return bool|WP_Error
     */
    public function handle_webhook($event_data)
    {
        $event_type = $event_data['type'] ?? '';

        switch ($event_type) {
            case 'payment_intent.succeeded':
                return $this->handle_payment_succeeded($event_data['data']['object']);

            case 'payment_intent.payment_failed':
                return $this->handle_payment_failed($event_data['data']['object']);

            case 'charge.refunded':
                return $this->handle_refund_processed($event_data['data']['object']);

            default:
                // Log unhandled event types
                error_log('Unhandled Stripe webhook event: ' . $event_type);
                return true;
        }
    }

    /**
     * Handle successful payment
     *
     * @param array $payment_intent
     * @return bool
     */
    private function handle_payment_succeeded($payment_intent)
    {
        // Update payment status in database
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_payments';

        $wpdb->update(
            $table_name,
            array('status' => 'succeeded'),
            array('payment_intent_id' => $payment_intent['id'])
        );

        // Trigger action for other plugins/themes
        do_action('wp_stripe_payment_succeeded', $payment_intent);

        return true;
    }

    /**
     * Handle failed payment
     *
     * @param array $payment_intent
     * @return bool
     */
    private function handle_payment_failed($payment_intent)
    {
        // Update payment status in database
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_payments';

        $wpdb->update(
            $table_name,
            array('status' => 'failed'),
            array('payment_intent_id' => $payment_intent['id'])
        );

        // Trigger action for other plugins/themes
        do_action('wp_stripe_payment_failed', $payment_intent);

        return true;
    }

    /**
     * Handle refund processed
     *
     * @param array $charge
     * @return bool
     */
    private function handle_refund_processed($charge)
    {
        // Update refund status in database
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_refunds';

        $wpdb->update(
            $table_name,
            array('status' => 'succeeded'),
            array('refund_id' => $charge['refunds']['data'][0]['id'] ?? '')
        );

        // Trigger action for other plugins/themes
        do_action('wp_stripe_refund_processed', $charge);

        return true;
    }
}
