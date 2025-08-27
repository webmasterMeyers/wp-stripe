<?php

/**
 * Main plugin class
 *
 * @package WPStripePayment
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Stripe_Payment
{

    /**
     * Plugin instance
     *
     * @var WP_Stripe_Payment
     */
    private static $instance = null;

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = WP_STRIPE_PAYMENT_VERSION;

    /**
     * Stripe API instance
     *
     * @var WP_Stripe_Payment_Stripe_API
     */
    public $stripe_api;

    /**
     * Payment handler instance
     *
     * @var WP_Stripe_Payment_Handler
     */
    public $payment_handler;

    /**
     * Get plugin instance
     *
     * @return WP_Stripe_Payment
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_wp_stripe_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_wp_stripe_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_wp_stripe_create_payment_intent', array($this, 'ajax_create_payment_intent'));
        add_action('wp_ajax_nopriv_wp_stripe_create_payment_intent', array($this, 'ajax_create_payment_intent'));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        // Load core classes
        require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/class-wp-stripe-payment-stripe-api.php';
        require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/class-wp-stripe-payment-handler.php';
        require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/class-wp-stripe-payment-forms.php';
        require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/class-wp-stripe-payment-shortcodes.php';

        // Initialize core components
        $this->stripe_api = new WP_Stripe_Payment_Stripe_API();
        $this->payment_handler = new WP_Stripe_Payment_Handler($this->stripe_api);

        // Load admin if in admin area
        if (is_admin()) {
            require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'admin/class-wp-stripe-payment-admin.php';
            new WP_Stripe_Payment_Admin();
        }

        // Load shortcodes
        new WP_Stripe_Payment_Shortcodes();
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Load text domain
        load_plugin_textdomain('wp-stripe-payment', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize forms
        new WP_Stripe_Payment_Forms();
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts()
    {
        // Only enqueue on pages that need Stripe
        if (!$this->should_enqueue_stripe_scripts()) {
            return;
        }

        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            null,
            true
        );

        wp_enqueue_script(
            'wp-stripe-payment',
            WP_STRIPE_PAYMENT_PLUGIN_URL . 'assets/js/wp-stripe-payment.js',
            array('jquery', 'stripe-js'),
            $this->version,
            true
        );

        wp_enqueue_style(
            'wp-stripe-payment',
            WP_STRIPE_PAYMENT_PLUGIN_URL . 'assets/css/wp-stripe-payment.css',
            array(),
            $this->version
        );

        // Localize script
        wp_localize_script('wp-stripe-payment', 'wpStripePayment', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_stripe_payment_nonce'),
            'publishableKey' => $this->stripe_api->get_publishable_key(),
            'currency' => get_option('wp_stripe_currency', 'usd'),
            'locale' => get_locale(),
            'i18n' => array(
                'processing' => __('Processing payment...', 'wp-stripe-payment'),
                'error' => __('An error occurred. Please try again.', 'wp-stripe-payment'),
                'success' => __('Payment successful!', 'wp-stripe-payment'),
            )
        ));
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook)
    {
        if (strpos($hook, 'wp-stripe-payment') === false) {
            return;
        }

        wp_enqueue_script(
            'wp-stripe-payment-admin',
            WP_STRIPE_PAYMENT_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_enqueue_style(
            'wp-stripe-payment-admin',
            WP_STRIPE_PAYMENT_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version
        );

        // Localize script for admin
        wp_localize_script('wp-stripe-payment-admin', 'wpStripeAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_stripe_payment_admin_nonce'),
            'i18n' => array(
                'testing' => __('Testing...', 'wp-stripe-payment'),
                'testConnection' => __('Test Connection', 'wp-stripe-payment'),
                'connectionSuccess' => __('Connection successful!', 'wp-stripe-payment'),
                'connectionFailed' => __('Connection failed', 'wp-stripe-payment'),
            )
        ));
    }

    /**
     * Check if Stripe scripts should be enqueued
     */
    private function should_enqueue_stripe_scripts()
    {
        global $post;

        // Check if current page has Stripe shortcodes
        if ($post && has_shortcode($post->post_content, 'stripe_payment_form')) {
            return true;
        }

        // Check if URL has Stripe parameters
        if (isset($_GET['stripe_payment']) || isset($_GET['stripe_price']) || isset($_GET['stripe_metadata'])) {
            return true;
        }

        return false;
    }

    /**
     * AJAX handler for processing payments
     */
    public function ajax_process_payment()
    {
        check_ajax_referer('wp_stripe_payment_nonce', 'nonce');

        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');
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

        $result = $this->payment_handler->process_payment($payment_intent_id, $metadata);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for creating payment intents
     */
    public function ajax_create_payment_intent()
    {
        check_ajax_referer('wp_stripe_payment_nonce', 'nonce');

        $amount = intval($_POST['amount'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'usd');
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

        $result = $this->stripe_api->create_payment_intent($amount, $currency, $metadata);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }
}
