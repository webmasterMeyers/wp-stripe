# WP Stripe Payment Plugin

A professional WordPress plugin for accepting Stripe payments with support for URL parameters and metadata.

## Features

- **Secure Payment Processing**: Integrates with Stripe's secure payment infrastructure
- **URL Parameter Support**: Accept dynamic pricing and metadata from URL parameters
- **Multiple Payment Forms**: Payment forms, buttons, and modals
- **Shortcode Support**: Easy integration with pages and posts
- **Responsive Design**: Mobile-friendly payment forms
- **Admin Dashboard**: Comprehensive admin interface for managing payments
- **Webhook Support**: Secure webhook processing for payment updates
- **Multi-currency Support**: USD, EUR, GBP, CAD, AUD, JPY
- **Customer Management**: Track and manage customer information
- **Payment History**: Complete payment and refund tracking
- **Security Features**: Nonce verification, input sanitization, and secure API calls

## Requirements

- WordPress 6.8.2 or higher
- PHP 8.2.18 or higher
- Stripe account with API keys
- SSL certificate (required for production)

## Installation

1. **Download the plugin** and upload it to your `/wp-content/plugins/` directory
2. **Activate the plugin** through the 'Plugins' menu in WordPress
3. **Configure Stripe API keys** in the admin settings
4. **Set up webhooks** in your Stripe dashboard
5. **Use shortcodes** to add payment forms to your pages

## Configuration

### 1. Stripe API Keys

1. Go to your [Stripe Dashboard](https://dashboard.stripe.com/)
2. Navigate to Developers > API keys
3. Copy your Publishable Key and Secret Key
4. In WordPress admin, go to Stripe Payments > Settings
5. Enter your API keys and save

### 2. Webhook Setup

1. In Stripe Dashboard, go to Developers > Webhooks
2. Add endpoint: `https://yoursite.com/wp-json/wp-stripe-payment/v1/webhook`
3. Select events to listen for:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
4. Copy the webhook signing secret
5. Add it to your WordPress settings

## Usage

### Shortcodes

#### Payment Form
```php
[stripe_payment_form amount="29.99" currency="usd" description="Premium Plan"]
```

#### Payment Button
```php
[stripe_payment_button amount="19.99" currency="eur" description="Basic Plan"]
```

#### Payment Modal
```php
[stripe_payment_modal trigger_text="Buy Now" modal_title="Complete Purchase"]
```

### URL Parameters

The plugin automatically reads Stripe-related parameters from URLs:

- `stripe_price` - Set payment amount
- `stripe_currency` - Set currency
- `stripe_description` - Set payment description
- `stripe_[custom]` - Any custom metadata

**Example URL:**
```
https://yoursite.com/page/?stripe_price=29.99&stripe_currency=usd&stripe_description=Premium&stripe_plan=premium
```

### Dynamic Pricing

You can create dynamic pricing by combining shortcodes with URL parameters:

```php
[stripe_payment_form]
```

When someone visits with `?stripe_price=49.99`, the form will automatically show $49.99.

### Custom Metadata

Add custom fields to track additional information:

```php
[stripe_payment_form amount="99.99" stripe_product_id="prod_123" stripe_user_type="premium"]
```

## API Integration

### JavaScript Functions

```javascript
// Show payment form
wpStripePayment.showPaymentForm(amount, currency, description, metadata);

// Open payment modal
wpStripePayment.openModal();

// Close payment modal
wpStripePayment.closeModal();

// Retry payment
wpStripePayment.retryPayment();
```

### AJAX Endpoints

- `wp_stripe_create_payment_intent` - Create payment intent
- `wp_stripe_process_payment` - Process payment
- `wp_stripe_get_payment_form` - Get payment form HTML

### Webhook Events

The plugin handles these Stripe webhook events:

- `payment_intent.succeeded` - Payment completed
- `payment_intent.payment_failed` - Payment failed
- `charge.refunded` - Refund processed

## Customization

### CSS Classes

- `.wp-stripe-payment-form` - Main payment form
- `.stripe-payment-button` - Payment buttons
- `.stripe-payment-modal` - Payment modal
- `.payment-summary` - Payment amount display
- `.stripe-card-element` - Stripe card input

### Hooks and Filters

```php
// Customize payment processing
add_action('wp_stripe_payment_succeeded', 'my_custom_function');
add_action('wp_stripe_payment_failed', 'my_custom_function');
add_action('wp_stripe_refund_processed', 'my_custom_function');

// Filter payment metadata
add_filter('wp_stripe_payment_metadata', 'my_metadata_filter');
```

### Database Tables

The plugin creates these tables:

- `wp_stripe_payments` - Payment records
- `wp_stripe_refunds` - Refund records
- `wp_stripe_customers` - Customer information
- `wp_stripe_webhooks` - Webhook events

## Security Features

- **Nonce Verification**: All AJAX requests use WordPress nonces
- **Input Sanitization**: All user inputs are properly sanitized
- **API Key Security**: Secret keys are stored securely
- **Webhook Verification**: Webhook signatures are verified
- **HTTPS Required**: Production requires SSL certificate

## Testing

### Test Mode

1. Enable test mode in plugin settings
2. Use Stripe test API keys
3. Use test card numbers:
   - **Success**: 4242 4242 4242 4242
   - **Decline**: 4000 0000 0000 0002
   - **3D Secure**: 4000 0025 0000 3155

### Test Cards

- **Visa**: 4242 4242 4242 4242
- **Mastercard**: 5555 5555 5555 4444
- **American Express**: 3782 822463 10005

## Troubleshooting

### Common Issues

1. **Payment Form Not Loading**
   - Check if Stripe.js is loaded
   - Verify API keys are correct
   - Check browser console for errors

2. **Webhook Not Working**
   - Verify webhook URL is correct
   - Check webhook secret in settings
   - Ensure SSL certificate is valid

3. **Payment Declined**
   - Use test cards in test mode
   - Check Stripe dashboard for error details
   - Verify payment method is supported

### Debug Mode

Enable debug mode in settings to log detailed information:

```php
// Add to wp-config.php for additional logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

### Documentation

- [Stripe Documentation](https://stripe.com/docs)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)
- [Stripe Testing Guide](https://stripe.com/docs/testing)

### Getting Help

1. Check the troubleshooting section above
2. Review WordPress error logs
3. Check Stripe dashboard for payment status
4. Verify webhook delivery in Stripe

## Changelog

### Version 1.0.0
- Initial release
- Stripe payment processing
- URL parameter support
- Admin dashboard
- Webhook handling
- Multi-currency support

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Built for WordPress
- Powered by Stripe
- Icons by Dashicons

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

**Note**: This plugin requires a Stripe account and API keys. Stripe charges fees for payment processing. Please review [Stripe's pricing](https://stripe.com/pricing) for current rates.
