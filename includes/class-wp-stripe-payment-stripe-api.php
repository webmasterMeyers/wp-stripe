<?php

/**
 * Stripe API handler
 *
 * @package WPStripePayment
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Stripe_Payment_Stripe_API
{

    /**
     * Stripe secret key
     *
     * @var string
     */
    private $secret_key;

    /**
     * Stripe publishable key
     *
     * @var string
     */
    private $publishable_key;

    /**
     * Stripe webhook secret
     *
     * @var string
     */
    private $webhook_secret;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->secret_key = get_option('wp_stripe_secret_key', '');
        $this->publishable_key = get_option('wp_stripe_publishable_key', '');
        $this->webhook_secret = get_option('wp_stripe_webhook_secret', '');
    }

    /**
     * Get publishable key
     *
     * @return string
     */
    public function get_publishable_key()
    {
        return $this->publishable_key;
    }

    /**
     * Check if Stripe is configured
     *
     * @return bool
     */
    public function is_configured()
    {
        return !empty($this->secret_key) && !empty($this->publishable_key);
    }

    /**
     * Make a request to Stripe API
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array|WP_Error
     */
    private function make_request($endpoint, $data = array(), $method = 'POST')
    {
        if (!$this->is_configured()) {
            return new WP_Error('stripe_not_configured', 'Stripe is not configured');
        }

        $url = 'https://api.stripe.com/v1/' . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'timeout' => 30,
        );

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = http_build_query($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            return new WP_Error('stripe_api_error', $error_message, $status_code);
        }

        return json_decode($body, true);
    }

    /**
     * Create a payment intent
     *
     * @param int $amount Amount in cents
     * @param string $currency Currency code
     * @param array $metadata Additional metadata
     * @return array|WP_Error
     */
    public function create_payment_intent($amount, $currency = 'usd', $metadata = array())
    {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be greater than 0');
        }

        $data = array(
            'amount' => $amount,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => array(
                'enabled' => true,
            ),
        );

        // Add metadata if provided
        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        // Add description if available
        if (isset($metadata['description'])) {
            $data['description'] = $metadata['description'];
        }

        return $this->make_request('payment_intents', $data);
    }

    /**
     * Retrieve a payment intent
     *
     * @param string $payment_intent_id
     * @return array|WP_Error
     */
    public function retrieve_payment_intent($payment_intent_id)
    {
        if (empty($payment_intent_id)) {
            return new WP_Error('invalid_payment_intent_id', 'Payment intent ID is required');
        }

        return $this->make_request('payment_intents/' . $payment_intent_id, array(), 'GET');
    }

    /**
     * Confirm a payment intent
     *
     * @param string $payment_intent_id
     * @param array $data
     * @return array|WP_Error
     */
    public function confirm_payment_intent($payment_intent_id, $data = array())
    {
        if (empty($payment_intent_id)) {
            return new WP_Error('invalid_payment_intent_id', 'Payment intent ID is required');
        }

        return $this->make_request('payment_intents/' . $payment_intent_id . '/confirm', $data);
    }

    /**
     * Cancel a payment intent
     *
     * @param string $payment_intent_id
     * @return array|WP_Error
     */
    public function cancel_payment_intent($payment_intent_id)
    {
        if (empty($payment_intent_id)) {
            return new WP_Error('invalid_payment_intent_id', 'Payment intent ID is required');
        }

        return $this->make_request('payment_intents/' . $payment_intent_id . '/cancel', array());
    }

    /**
     * Create a customer
     *
     * @param array $customer_data
     * @return array|WP_Error
     */
    public function create_customer($customer_data)
    {
        $required_fields = array('email');
        foreach ($required_fields as $field) {
            if (empty($customer_data[$field])) {
                return new WP_Error('missing_field', "Missing required field: {$field}");
            }
        }

        return $this->make_request('customers', $customer_data);
    }

    /**
     * Retrieve a customer
     *
     * @param string $customer_id
     * @return array|WP_Error
     */
    public function retrieve_customer($customer_id)
    {
        if (empty($customer_id)) {
            return new WP_Error('invalid_customer_id', 'Customer ID is required');
        }

        return $this->make_request('customers/' . $customer_id, array(), 'GET');
    }

    /**
     * Update a customer
     *
     * @param string $customer_id
     * @param array $customer_data
     * @return array|WP_Error
     */
    public function update_customer($customer_id, $customer_data)
    {
        if (empty($customer_id)) {
            return new WP_Error('invalid_customer_id', 'Customer ID is required');
        }

        return $this->make_request('customers/' . $customer_id, $customer_data, 'POST');
    }

    /**
     * Create a refund
     *
     * @param string $payment_intent_id
     * @param int $amount Amount in cents (optional, defaults to full amount)
     * @param array $metadata Additional metadata
     * @return array|WP_Error
     */
    public function create_refund($payment_intent_id, $amount = null, $metadata = array())
    {
        if (empty($payment_intent_id)) {
            return new WP_Error('invalid_payment_intent_id', 'Payment intent ID is required');
        }

        $data = array(
            'payment_intent' => $payment_intent_id,
        );

        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        return $this->make_request('refunds', $data);
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string $signature
     * @return bool|WP_Error
     */
    public function verify_webhook_signature($payload, $signature)
    {
        if (empty($this->webhook_secret)) {
            return new WP_Error('webhook_not_configured', 'Webhook secret is not configured');
        }

        // This is a simplified verification - in production, you should use Stripe's webhook library
        $expected_signature = hash_hmac('sha256', $payload, $this->webhook_secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Get account information
     *
     * @return array|WP_Error
     */
    public function get_account()
    {
        return $this->make_request('account', array(), 'GET');
    }

    /**
     * Test API connection
     *
     * @return bool|WP_Error
     */
    public function test_connection()
    {
        $result = $this->get_account();

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}
