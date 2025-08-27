<?php

/**
 * Shortcodes handler
 *
 * @package WPStripePayment
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Stripe_Payment_Shortcodes
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_shortcode('stripe_payment_form', array($this, 'payment_form_shortcode'));
        add_shortcode('stripe_payment_button', array($this, 'payment_button_shortcode'));
        add_shortcode('stripe_payment_modal', array($this, 'payment_modal_shortcode'));
    }

    /**
     * Payment form shortcode
     *
     * @param array $atts
     * @return string
     */
    public function payment_form_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'amount' => 0,
            'currency' => 'usd',
            'description' => '',
            'title' => __('Complete Payment', 'wp-stripe-payment'),
            'button_text' => __('Pay now', 'wp-stripe-payment'),
            'show_amount' => 'true',
            'class' => '',
        ), $atts, 'stripe_payment_form');

        // Convert amount to cents if it's a decimal
        $amount = $this->parse_amount($atts['amount']);

        // Get metadata from URL parameters
        $metadata = $this->get_url_metadata();

        // Add shortcode attributes to metadata
        if (!empty($atts['description'])) {
            $metadata['description'] = $atts['description'];
        }

        $forms = new WP_Stripe_Payment_Forms();
        $form_html = $forms->render_payment_form($amount, $atts['currency'], $atts['description'], $metadata);

        $wrapper_class = 'wp-stripe-payment-shortcode';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }

        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr($wrapper_class),
            $form_html
        );
    }

    /**
     * Payment button shortcode
     *
     * @param array $atts
     * @return string
     */
    public function payment_button_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'amount' => 0,
            'currency' => 'usd',
            'description' => '',
            'button_text' => '',
            'style' => 'default',
            'size' => 'medium',
            'class' => '',
        ), $atts, 'stripe_payment_button');

        // Convert amount to cents if it's a decimal
        $amount = $this->parse_amount($atts['amount']);

        if ($amount <= 0) {
            return '<p class="stripe-error">' . __('Error: Amount must be greater than 0', 'wp-stripe-payment') . '</p>';
        }

        // Get metadata from URL parameters
        $metadata = $this->get_url_metadata();

        // Add shortcode attributes to metadata
        if (!empty($atts['description'])) {
            $metadata['description'] = $atts['description'];
        }

        $forms = new WP_Stripe_Payment_Forms();
        $button_html = $forms->render_payment_button($amount, $atts['currency'], $atts['description'], $metadata);

        $wrapper_class = 'wp-stripe-payment-button-shortcode';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }
        if (!empty($atts['style'])) {
            $wrapper_class .= ' style-' . esc_attr($atts['style']);
        }
        if (!empty($atts['size'])) {
            $wrapper_class .= ' size-' . esc_attr($atts['size']);
        }

        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr($wrapper_class),
            $button_html
        );
    }

    /**
     * Payment modal shortcode
     *
     * @param array $atts
     * @return string
     */
    public function payment_modal_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'trigger_text' => __('Open Payment Form', 'wp-stripe-payment'),
            'modal_title' => __('Complete Payment', 'wp-stripe-payment'),
            'class' => '',
        ), $atts, 'stripe_payment_modal');

        $forms = new WP_Stripe_Payment_Forms();
        $modal_html = $forms->render_payment_modal();

        $wrapper_class = 'wp-stripe-payment-modal-shortcode';
        if (!empty($atts['class'])) {
            $wrapper_class .= ' ' . esc_attr($atts['class']);
        }

        $trigger_button = sprintf(
            '<button type="button" class="stripe-modal-trigger" onclick="wpStripePayment.openModal()">%s</button>',
            esc_html($atts['trigger_text'])
        );

        return sprintf(
            '<div class="%s">%s%s</div>',
            esc_attr($wrapper_class),
            $trigger_button,
            $modal_html
        );
    }

    /**
     * Parse amount and convert to cents
     *
     * @param mixed $amount
     * @return int
     */
    private function parse_amount($amount)
    {
        if (is_numeric($amount)) {
            // If amount is less than 100, assume it's in dollars/euros and convert to cents
            if ($amount < 100) {
                return intval($amount * 100);
            }
            return intval($amount);
        }

        // Try to parse from URL parameters
        if (isset($_GET['stripe_price'])) {
            $url_amount = floatval($_GET['stripe_price']);
            if ($url_amount > 0) {
                return $url_amount < 100 ? intval($url_amount * 100) : intval($url_amount);
            }
        }

        return 0;
    }

    /**
     * Get metadata from URL parameters
     *
     * @return array
     */
    private function get_url_metadata()
    {
        $metadata = array();

        foreach ($_GET as $key => $value) {
            if (strpos($key, 'stripe_') === 0) {
                $clean_key = str_replace('stripe_', '', $key);
                $metadata[$clean_key] = sanitize_text_field($value);
            }
        }

        return $metadata;
    }

    /**
     * Get shortcode usage examples
     *
     * @return string
     */
    public static function get_usage_examples()
    {
        ob_start();
?>
        <div class="stripe-shortcode-examples">
            <h3><?php _e('Shortcode Usage Examples', 'wp-stripe-payment'); ?></h3>

            <h4><?php _e('Payment Form', 'wp-stripe-payment'); ?></h4>
            <p><?php _e('Display a complete payment form:', 'wp-stripe-payment'); ?></p>
            <code>[stripe_payment_form amount="29.99" currency="usd" description="Premium Plan"]</code>

            <h4><?php _e('Payment Button', 'wp-stripe-payment'); ?></h4>
            <p><?php _e('Display a payment button that opens a form:', 'wp-stripe-payment'); ?></p>
            <code>[stripe_payment_button amount="19.99" currency="eur" description="Basic Plan"]</code>

            <h4><?php _e('Payment Modal', 'wp-stripe-payment'); ?></h4>
            <p><?php _e('Display a button that opens a payment modal:', 'wp-stripe-payment'); ?></p>
            <code>[stripe_payment_modal trigger_text="Buy Now" modal_title="Complete Purchase"]</code>

            <h4><?php _e('Dynamic Pricing from URL', 'wp-stripe-payment'); ?></h4>
            <p><?php _e('Use URL parameters to set dynamic values:', 'wp-stripe-payment'); ?></p>
            <p><?php _e('Example URL:', 'wp-stripe-payment'); ?> <code>https://yoursite.com/page/?stripe_price=29.99&stripe_currency=usd&stripe_description=Premium&stripe_plan=premium</code></p>
            <code>[stripe_payment_form]</code>

            <h4><?php _e('Available Attributes', 'wp-stripe-payment'); ?></h4>
            <ul>
                <li><strong>amount</strong> - <?php _e('Payment amount (in dollars/euros, will be converted to cents)', 'wp-stripe-payment'); ?></li>
                <li><strong>currency</strong> - <?php _e('Currency code (usd, eur, gbp, cad, aud, jpy)', 'wp-stripe-payment'); ?></li>
                <li><strong>description</strong> - <?php _e('Payment description', 'wp-stripe-payment'); ?></li>
                <li><strong>title</strong> - <?php _e('Form title', 'wp-stripe-payment'); ?></li>
                <li><strong>button_text</strong> - <?php _e('Button text', 'wp-stripe-payment'); ?></li>
                <li><strong>show_amount</strong> - <?php _e('Show/hide amount display (true/false)', 'wp-stripe-payment'); ?></li>
                <li><strong>style</strong> - <?php _e('Button style (default, primary, secondary)', 'wp-stripe-payment'); ?></li>
                <li><strong>size</strong> - <?php _e('Button size (small, medium, large)', 'wp-stripe-payment'); ?></li>
                <li><strong>class</strong> - <?php _e('Additional CSS classes', 'wp-stripe-payment'); ?></li>
            </ul>
        </div>
<?php
        return ob_get_clean();
    }
}
