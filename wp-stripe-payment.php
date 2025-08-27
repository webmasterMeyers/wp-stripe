<?php

/**
 * Plugin Name: WP Stripe Payment
 * Plugin URI: https://github.com/webmasterMeyers/wp-stripe
 * Description: Accept Stripe payments in WordPress with support for URL parameters and metadata.
 * Version: 1.0.0
 * Author: Some Random AI
 * Author URI: https://github.com/webmasterMeyers/wp-stripe
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-stripe-payment
 * Domain Path: /languages
 * Requires at least: 6.8.2
 * Tested up to: 6.8.2
 * Requires PHP: 8.2.18
 * Network: false
 *
 * @package WPStripePayment
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_STRIPE_PAYMENT_VERSION', '1.0.0');
define('WP_STRIPE_PAYMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_STRIPE_PAYMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_STRIPE_PAYMENT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check WordPress and PHP version requirements
function wp_stripe_payment_check_requirements()
{
    global $wp_version;

    if (version_compare($wp_version, '6.8.2', '<')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>WP Stripe Payment</strong> requires WordPress 6.8.2 or higher. ';
            echo 'Please upgrade WordPress to use this plugin.';
            echo '</p></div>';
        });
        return false;
    }

    if (version_compare(PHP_VERSION, '8.2.18', '<')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>WP Stripe Payment</strong> requires PHP 8.2.18 or higher. ';
            echo 'Current PHP version: ' . PHP_VERSION . '. Please upgrade PHP to use this plugin.';
            echo '</p></div>';
        });
        return false;
    }

    return true;
}

// Initialize plugin
function wp_stripe_payment_init()
{
    if (!wp_stripe_payment_check_requirements()) {
        return;
    }

    // Load Composer autoloader if exists
    if (file_exists(WP_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php')) {
        require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php';
    }

    // Initialize the main plugin class
    require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/class-wp-stripe-payment.php';
    WP_Stripe_Payment::get_instance();
}

// Hook into WordPress init
add_action('plugins_loaded', 'wp_stripe_payment_init');

// Activation hook
register_activation_hook(__FILE__, 'wp_stripe_payment_activate');
function wp_stripe_payment_activate()
{
    if (!wp_stripe_payment_check_requirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('WP Stripe Payment requires WordPress 6.8.2+ and PHP 8.2.18+');
    }

    // Create necessary database tables
    require_once WP_STRIPE_PAYMENT_PLUGIN_DIR . 'includes/class-wp-stripe-payment-activator.php';
    WP_Stripe_Payment_Activator::activate();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_stripe_payment_deactivate');
function wp_stripe_payment_deactivate()
{
    // Cleanup if needed
}
