<?php

/**
 * Plugin activator
 *
 * @package WPStripePayment
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Stripe_Payment_Activator
{

    /**
     * Activate plugin
     */
    public static function activate()
    {
        self::create_database_tables();
        self::set_default_options();
        self::create_pages();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function create_database_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Payments table
        $table_name = $wpdb->prefix . 'stripe_payments';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            payment_intent_id varchar(255) NOT NULL,
            amount int(11) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'usd',
            status varchar(50) NOT NULL DEFAULT 'pending',
            customer_id varchar(255) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            stripe_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY payment_intent_id (payment_intent_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Refunds table
        $refunds_table_name = $wpdb->prefix . 'stripe_refunds';
        $refunds_sql = "CREATE TABLE $refunds_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            refund_id varchar(255) NOT NULL,
            payment_intent_id varchar(255) NOT NULL,
            amount int(11) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'usd',
            status varchar(50) NOT NULL DEFAULT 'pending',
            stripe_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY refund_id (refund_id),
            KEY payment_intent_id (payment_intent_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Customers table
        $customers_table_name = $wpdb->prefix . 'stripe_customers';
        $customers_sql = "CREATE TABLE $customers_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_id varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            address longtext DEFAULT NULL,
            stripe_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY customer_id (customer_id),
            KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Webhooks table
        $webhooks_table_name = $wpdb->prefix . 'stripe_webhooks';
        $webhooks_sql = "CREATE TABLE $webhooks_table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id varchar(255) NOT NULL,
            event_type varchar(100) NOT NULL,
            event_data longtext DEFAULT NULL,
            processed tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_id (event_id),
            KEY event_type (event_type),
            KEY processed (processed),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);
        dbDelta($refunds_sql);
        dbDelta($customers_sql);
        dbDelta($webhooks_sql);

        // Add version to options
        add_option('wp_stripe_payment_db_version', '1.0.0');
    }

    /**
     * Set default options
     */
    private static function set_default_options()
    {
        // Stripe settings
        if (!get_option('wp_stripe_secret_key')) {
            add_option('wp_stripe_secret_key', '');
        }

        if (!get_option('wp_stripe_publishable_key')) {
            add_option('wp_stripe_publishable_key', '');
        }

        if (!get_option('wp_stripe_webhook_secret')) {
            add_option('wp_stripe_webhook_secret', '');
        }

        // General settings
        if (!get_option('wp_stripe_currency')) {
            add_option('wp_stripe_currency', 'usd');
        }

        if (!get_option('wp_stripe_success_page')) {
            add_option('wp_stripe_success_page', '');
        }

        if (!get_option('wp_stripe_cancel_page')) {
            add_option('wp_stripe_cancel_page', '');
        }

        if (!get_option('wp_stripe_error_page')) {
            add_option('wp_stripe_error_page', '');
        }

        // Payment settings
        if (!get_option('wp_stripe_capture_method')) {
            add_option('wp_stripe_capture_method', 'automatic');
        }

        if (!get_option('wp_stripe_payment_method_types')) {
            add_option('wp_stripe_payment_method_types', array('card'));
        }

        // Email settings
        if (!get_option('wp_stripe_send_receipts')) {
            add_option('wp_stripe_send_receipts', 'yes');
        }

        if (!get_option('wp_stripe_admin_email')) {
            add_option('wp_stripe_admin_email', get_option('admin_email'));
        }

        // Advanced settings
        if (!get_option('wp_stripe_test_mode')) {
            add_option('wp_stripe_test_mode', 'yes');
        }

        if (!get_option('wp_stripe_debug_mode')) {
            add_option('wp_stripe_debug_mode', 'no');
        }

        if (!get_option('wp_stripe_log_retention_days')) {
            add_option('wp_stripe_log_retention_days', 30);
        }
    }

    /**
     * Create default pages
     */
    private static function create_pages()
    {
        // Success page
        if (!get_option('wp_stripe_success_page')) {
            $success_page_id = wp_insert_post(array(
                'post_title' => __('Payment Successful', 'wp-stripe-payment'),
                'post_content' => '[stripe_payment_success]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'payment-successful',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ));

            if ($success_page_id && !is_wp_error($success_page_id)) {
                update_option('wp_stripe_success_page', $success_page_id);
            }
        }

        // Cancel page
        if (!get_option('wp_stripe_cancel_page')) {
            $cancel_page_id = wp_insert_post(array(
                'post_title' => __('Payment Cancelled', 'wp-stripe-payment'),
                'post_content' => '[stripe_payment_cancel]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'payment-cancelled',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ));

            if ($cancel_page_id && !is_wp_error($cancel_page_id)) {
                update_option('wp_stripe_cancel_page', $cancel_page_id);
            }
        }

        // Error page
        if (!get_option('wp_stripe_error_page')) {
            $error_page_id = wp_insert_post(array(
                'post_title' => __('Payment Error', 'wp-stripe-payment'),
                'post_content' => '[stripe_payment_error]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'payment-error',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ));

            if ($error_page_id && !is_wp_error($error_page_id)) {
                update_option('wp_stripe_error_page', $error_page_id);
            }
        }
    }

    /**
     * Check if database tables exist
     *
     * @return bool
     */
    public static function check_database_tables()
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'stripe_payments',
            $wpdb->prefix . 'stripe_refunds',
            $wpdb->prefix . 'stripe_customers',
            $wpdb->prefix . 'stripe_webhooks',
        );

        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get database version
     *
     * @return string
     */
    public static function get_database_version()
    {
        return get_option('wp_stripe_payment_db_version', '0.0.0');
    }

    /**
     * Check if plugin needs database update
     *
     * @return bool
     */
    public static function needs_database_update()
    {
        $current_version = self::get_database_version();
        $plugin_version = WP_STRIPE_PAYMENT_VERSION;

        return version_compare($current_version, $plugin_version, '<');
    }
}
