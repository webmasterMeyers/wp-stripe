<?php

/**
 * Test page for WP Stripe Payment Plugin
 * This file demonstrates how to use the plugin
 */

// Include WordPress
require_once('wp-config.php');

// Check if plugin is active
if (!class_exists('WP_Stripe_Payment')) {
    die('Plugin not active. Please activate the WP Stripe Payment plugin first.');
}

get_header();
?>

<div class="container" style="max-width: 800px; margin: 40px auto; padding: 20px;">
    <h1>WP Stripe Payment Plugin Test Page</h1>

    <div class="test-section">
        <h2>1. Basic Payment Form</h2>
        <p>This form has a fixed amount of $29.99:</p>
        <?php echo do_shortcode('[stripe_payment_form amount="29.99" currency="usd" description="Test Product"]'); ?>
    </div>

    <div class="test-section">
        <h2>2. Payment Button</h2>
        <p>This button opens a payment form for $19.99:</p>
        <?php echo do_shortcode('[stripe_payment_button amount="19.99" currency="usd" description="Test Button"]'); ?>
    </div>

    <div class="test-section">
        <h2>3. Payment Modal</h2>
        <p>Click this button to open a payment modal:</p>
        <?php echo do_shortcode('[stripe_payment_modal trigger_text="Open Payment Modal" modal_title="Complete Payment"]'); ?>
    </div>

    <div class="test-section">
        <h2>4. Dynamic Pricing from URL</h2>
        <p>Try these URLs to see dynamic pricing in action:</p>
        <ul>
            <li><a href="?stripe_price=49.99&stripe_currency=usd&stripe_description=Premium">$49.99 Premium</a></li>
            <li><a href="?stripe_price=99.99&stripe_currency=eur&stripe_description=Enterprise">€99.99 Enterprise</a></li>
            <li><a href="?stripe_price=29.99&stripe_currency=gbp&stripe_description=Basic&stripe_plan=basic">£29.99 Basic Plan</a></li>
        </ul>

        <p>Current form with URL parameters:</p>
        <?php echo do_shortcode('[stripe_payment_form]'); ?>
    </div>

    <div class="test-section">
        <h2>5. Custom Metadata</h2>
        <p>Form with custom metadata:</p>
        <?php echo do_shortcode('[stripe_payment_form amount="39.99" stripe_product_id="prod_123" stripe_user_type="premium" stripe_campaign="summer2024"]'); ?>
    </div>

    <div class="test-section">
        <h2>Testing Instructions</h2>
        <ol>
            <li><strong>Configure the plugin first:</strong> Go to WordPress Admin → Stripe Payments → Settings</li>
            <li><strong>Enter your Stripe API keys:</strong> Get them from your Stripe dashboard</li>
            <li><strong>Test the connection:</strong> Use the "Test Connection" button</li>
            <li><strong>Use test card numbers:</strong>
                <ul>
                    <li>Success: 4242 4242 4242 4242</li>
                    <li>Decline: 4000 0000 0000 0002</li>
                    <li>3D Secure: 4000 0025 0000 3155</li>
                </ul>
            </li>
            <li><strong>Test URL parameters:</strong> Click the links above to see dynamic pricing</li>
        </ol>
    </div>

    <div class="test-section">
        <h2>Current URL Parameters</h2>
        <p>These are the current URL parameters that will be used for metadata:</p>
        <ul>
            <?php
            foreach ($_GET as $key => $value) {
                if (strpos($key, 'stripe_') === 0) {
                    echo '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</li>';
                }
            }
            ?>
        </ul>
    </div>
</div>

<style>
    .test-section {
        margin: 30px 0;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #f9f9f9;
    }

    .test-section h2 {
        margin-top: 0;
        color: #333;
    }

    .test-section ul {
        margin-left: 20px;
    }

    .test-section li {
        margin-bottom: 5px;
    }

    .test-section a {
        color: #0073aa;
        text-decoration: none;
    }

    .test-section a:hover {
        text-decoration: underline;
    }
</style>

<?php
get_footer();
?>