<?php

/**
 * Admin interface handler
 *
 * @package WPStripePayment
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Stripe_Payment_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('wp_ajax_wp_stripe_test_connection', array($this, 'test_connection'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Stripe Payments', 'wp-stripe-payment'),
            __('Stripe Payments', 'wp-stripe-payment'),
            'manage_options',
            'wp-stripe-payment',
            array($this, 'admin_page'),
            'dashicons-money-alt',
            30
        );

        add_submenu_page(
            'wp-stripe-payment',
            __('Settings', 'wp-stripe-payment'),
            __('Settings', 'wp-stripe-payment'),
            'manage_options',
            'wp-stripe-payment',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'wp-stripe-payment',
            __('Payments', 'wp-stripe-payment'),
            __('Payments', 'wp-stripe-payment'),
            'manage_options',
            'wp-stripe-payments',
            array($this, 'payments_page')
        );

        add_submenu_page(
            'wp-stripe-payment',
            __('Customers', 'wp-stripe-payment'),
            __('Customers', 'wp-stripe-payment'),
            'manage_options',
            'wp-stripe-customers',
            array($this, 'customers_page')
        );

        add_submenu_page(
            'wp-stripe-payment',
            __('Webhooks', 'wp-stripe-payment'),
            __('Webhooks', 'wp-stripe-payment'),
            'manage_options',
            'wp-stripe-webhooks',
            array($this, 'webhooks_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        register_setting('wp_stripe_payment_settings', 'wp_stripe_secret_key');
        register_setting('wp_stripe_payment_settings', 'wp_stripe_publishable_key');
        register_setting('wp_stripe_payment_settings', 'wp_stripe_webhook_secret');
        register_setting('wp_stripe_payment_settings', 'wp_stripe_currency');
        register_setting('wp_stripe_payment_settings', 'wp_stripe_test_mode');
        register_setting('wp_stripe_payment_settings', 'wp_stripe_debug_mode');
        register_setting('wp_stripe_payment_settings', 'wp_stripe_send_receipts');
        register_setting('wp_stripe_payment_settings', 'wp_stripe_admin_email');
    }

    /**
     * Admin page
     */
    public function admin_page()
    {
?>
        <div class="wrap">
            <h1><?php _e('Stripe Payment Settings', 'wp-stripe-payment'); ?></h1>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'wp-stripe-payment'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('wp_stripe_payment_settings'); ?>
                <?php do_settings_sections('wp_stripe_payment_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wp_stripe_test_mode"><?php _e('Test Mode', 'wp-stripe-payment'); ?></label>
                        </th>
                        <td>
                            <select name="wp_stripe_test_mode" id="wp_stripe_test_mode">
                                <option value="yes" <?php selected(get_option('wp_stripe_test_mode'), 'yes'); ?>>
                                    <?php _e('Yes (Test Keys)', 'wp-stripe-payment'); ?>
                                </option>
                                <option value="no" <?php selected(get_option('wp_stripe_test_mode'), 'no'); ?>>
                                    <?php _e('No (Live Keys)', 'wp-stripe-payment'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Use test mode for development and testing. Switch to live mode for production.', 'wp-stripe-payment'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wp_stripe_secret_key"><?php _e('Secret Key', 'wp-stripe-payment'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="wp_stripe_secret_key" id="wp_stripe_secret_key"
                                value="<?php echo esc_attr(get_option('wp_stripe_secret_key')); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Your Stripe secret key. Get this from your Stripe dashboard.', 'wp-stripe-payment'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wp_stripe_publishable_key"><?php _e('Publishable Key', 'wp-stripe-payment'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="wp_stripe_publishable_key" id="wp_stripe_publishable_key"
                                value="<?php echo esc_attr(get_option('wp_stripe_publishable_key')); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Your Stripe publishable key. Get this from your Stripe dashboard.', 'wp-stripe-payment'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wp_stripe_webhook_secret"><?php _e('Webhook Secret', 'wp-stripe-payment'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="wp_stripe_webhook_secret" id="wp_stripe_webhook_secret"
                                value="<?php echo esc_attr(get_option('wp_stripe_webhook_secret')); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Your Stripe webhook secret for secure webhook processing.', 'wp-stripe-payment'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wp_stripe_currency"><?php _e('Default Currency', 'wp-stripe-payment'); ?></label>
                        </th>
                        <td>
                            <select name="wp_stripe_currency" id="wp_stripe_currency">
                                <option value="usd" <?php selected(get_option('wp_stripe_currency'), 'usd'); ?>>USD</option>
                                <option value="eur" <?php selected(get_option('wp_stripe_currency'), 'eur'); ?>>EUR</option>
                                <option value="gbp" <?php selected(get_option('wp_stripe_currency'), 'gbp'); ?>>GBP</option>
                                <option value="cad" <?php selected(get_option('wp_stripe_currency'), 'cad'); ?>>CAD</option>
                                <option value="aud" <?php selected(get_option('wp_stripe_currency'), 'aud'); ?>>AUD</option>
                                <option value="jpy" <?php selected(get_option('wp_stripe_currency'), 'jpy'); ?>>JPY</option>
                            </select>
                            <p class="description">
                                <?php _e('Default currency for payments.', 'wp-stripe-payment'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wp_stripe_send_receipts"><?php _e('Send Receipts', 'wp-stripe-payment'); ?></label>
                        </th>
                        <td>
                            <select name="wp_stripe_send_receipts" id="wp_stripe_send_receipts">
                                <option value="yes" <?php selected(get_option('wp_stripe_send_receipts'), 'yes'); ?>>
                                    <?php _e('Yes', 'wp-stripe-payment'); ?>
                                </option>
                                <option value="no" <?php selected(get_option('wp_stripe_send_receipts'), 'no'); ?>>
                                    <?php _e('No', 'wp-stripe-payment'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Automatically send payment receipts to customers.', 'wp-stripe-payment'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wp_stripe_admin_email"><?php _e('Admin Email', 'wp-stripe-payment'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="wp_stripe_admin_email" id="wp_stripe_admin_email"
                                value="<?php echo esc_attr(get_option('wp_stripe_admin_email', get_option('admin_email'))); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Email address for admin notifications.', 'wp-stripe-payment'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'wp-stripe-payment'); ?>" />
                    <button type="button" class="button button-secondary" onclick="wpStripePayment.testConnection()">
                        <?php _e('Test Connection', 'wp-stripe-payment'); ?>
                    </button>
                </p>
            </form>

            <div class="stripe-help-section">
                <h2><?php _e('Getting Started', 'wp-stripe-payment'); ?></h2>
                <ol>
                    <li><?php _e('Get your Stripe API keys from your Stripe dashboard', 'wp-stripe-payment'); ?></li>
                    <li><?php _e('Enter your API keys above and save the settings', 'wp-stripe-payment'); ?></li>
                    <li><?php _e('Test the connection using the "Test Connection" button', 'wp-stripe-payment'); ?></li>
                    <li><?php _e('Use the shortcodes to add payment forms to your pages', 'wp-stripe-payment'); ?></li>
                </ol>

                <h3><?php _e('Shortcode Examples', 'wp-stripe-payment'); ?></h3>
                <?php echo WP_Stripe_Payment_Shortcodes::get_usage_examples(); ?>
            </div>
        </div>
    <?php
    }

    /**
     * Payments page
     */
    public function payments_page()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_payments';
        $payments = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 100");

    ?>
        <div class="wrap">
            <h1><?php _e('Stripe Payments', 'wp-stripe-payment'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Payment Intent', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Amount', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Currency', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Status', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Customer', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Date', 'wp-stripe-payment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7"><?php _e('No payments found.', 'wp-stripe-payment'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo esc_html($payment->id); ?></td>
                                <td><?php echo esc_html($payment->payment_intent_id); ?></td>
                                <td><?php echo esc_html(number_format($payment->amount / 100, 2)); ?></td>
                                <td><?php echo esc_html(strtoupper($payment->currency)); ?></td>
                                <td>
                                    <span class="payment-status status-<?php echo esc_attr($payment->status); ?>">
                                        <?php echo esc_html(ucfirst($payment->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($payment->customer_id ?: '-'); ?></td>
                                <td><?php echo esc_html(date('M j, Y g:i A', strtotime($payment->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    /**
     * Customers page
     */
    public function customers_page()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_customers';
        $customers = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 100");

    ?>
        <div class="wrap">
            <h1><?php _e('Stripe Customers', 'wp-stripe-payment'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Customer ID', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Email', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Name', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Phone', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Date', 'wp-stripe-payment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No customers found.', 'wp-stripe-payment'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo esc_html($customer->id); ?></td>
                                <td><?php echo esc_html($customer->customer_id); ?></td>
                                <td><?php echo esc_html($customer->email); ?></td>
                                <td><?php echo esc_html($customer->name ?: '-'); ?></td>
                                <td><?php echo esc_html($customer->phone ?: '-'); ?></td>
                                <td><?php echo esc_html(date('M j, Y g:i A', strtotime($customer->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    /**
     * Webhooks page
     */
    public function webhooks_page()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'stripe_webhooks';
        $webhooks = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 100");

    ?>
        <div class="wrap">
            <h1><?php _e('Stripe Webhooks', 'wp-stripe-payment'); ?></h1>

            <div class="webhook-info">
                <h3><?php _e('Webhook URL', 'wp-stripe-payment'); ?></h3>
                <p><?php _e('Add this URL to your Stripe webhook settings:', 'wp-stripe-payment'); ?></p>
                <code><?php echo esc_url(home_url('/wp-json/wp-stripe-payment/v1/webhook')); ?></code>

                <h3><?php _e('Events to Listen For', 'wp-stripe-payment'); ?></h3>
                <ul>
                    <li><code>payment_intent.succeeded</code> - <?php _e('Payment completed successfully', 'wp-stripe-payment'); ?></li>
                    <li><code>payment_intent.payment_failed</code> - <?php _e('Payment failed', 'wp-stripe-payment'); ?></li>
                    <li><code>charge.refunded</code> - <?php _e('Refund processed', 'wp-stripe-payment'); ?></li>
                </ul>
            </div>

            <h3><?php _e('Recent Webhook Events', 'wp-stripe-payment'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Event ID', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Event Type', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Processed', 'wp-stripe-payment'); ?></th>
                        <th><?php _e('Date', 'wp-stripe-payment'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($webhooks)): ?>
                        <tr>
                            <td colspan="5"><?php _e('No webhook events found.', 'wp-stripe-payment'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($webhooks as $webhook): ?>
                            <tr>
                                <td><?php echo esc_html($webhook->id); ?></td>
                                <td><?php echo esc_html($webhook->event_id); ?></td>
                                <td><?php echo esc_html($webhook->event_type); ?></td>
                                <td>
                                    <?php if ($webhook->processed): ?>
                                        <span class="processed"><?php _e('Yes', 'wp-stripe-payment'); ?></span>
                                    <?php else: ?>
                                        <span class="not-processed"><?php _e('No', 'wp-stripe-payment'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('M j, Y g:i A', strtotime($webhook->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php
    }

    /**
     * Admin notices
     */
    public function admin_notices()
    {
        if (!get_option('wp_stripe_secret_key') || !get_option('wp_stripe_publishable_key')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>WP Stripe Payment:</strong> ' . __('Please configure your Stripe API keys in the settings.', 'wp-stripe-payment') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Test connection AJAX handler
     */
    public function test_connection()
    {
        check_ajax_referer('wp_stripe_payment_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $stripe_api = new WP_Stripe_Payment_Stripe_API();
        $result = $stripe_api->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success('Connection successful!');
    }
}
