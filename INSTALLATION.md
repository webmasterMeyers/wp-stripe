# WP Stripe Payment Plugin - Installation & Troubleshooting Guide

## Current Issues Fixed

I've identified and fixed several critical issues in your plugin:

### 1. ✅ Admin Test Connection Fixed
- **Problem**: Test connection button was not working
- **Solution**: Fixed nonce verification and AJAX handling
- **Files Modified**: 
  - `admin/class-wp-stripe-payment-admin.php`
  - `admin/js/admin.js`
  - `includes/class-wp-stripe-payment.php`

### 2. ✅ Empty Modal Fixed
- **Problem**: Modal was opening but showing no content
- **Solution**: Fixed AJAX form loading and nonce verification
- **Files Modified**: 
  - `includes/class-wp-stripe-payment-forms.php`

### 3. ✅ Credit Card Input Fixed
- **Problem**: Credit card input was disabled and not working
- **Solution**: Fixed Stripe Elements initialization and mounting
- **Files Modified**: 
  - `assets/js/wp-stripe-payment.js`

## Installation Steps

### Step 1: Upload Plugin
1. Upload the entire `wp-stripe` folder to `/wp-content/plugins/`
2. The folder should be named `wp-stripe-payment` in your plugins directory

### Step 2: Activate Plugin
1. Go to WordPress Admin → Plugins
2. Find "WP Stripe Payment" and click "Activate"
3. If you see any errors, check that all files are present

### Step 3: Configure Stripe API Keys
1. Go to WordPress Admin → Stripe Payments → Settings
2. Enter your Stripe API keys:
   - **Secret Key**: Get from Stripe Dashboard → Developers → API Keys
   - **Publishable Key**: Get from Stripe Dashboard → Developers → API Keys
3. Click "Save Changes"

### Step 4: Test Connection
1. After saving API keys, click "Test Connection"
2. You should see "Connection successful!" message
3. If it fails, double-check your API keys

### Step 5: Test Payment Forms
1. Create a new page or post
2. Add this shortcode: `[stripe_payment_form amount="29.99" currency="usd" description="Test Product"]`
3. View the page and test the payment form

## Testing with Stripe Test Cards

Use these test card numbers:
- **Success**: 4242 4242 4242 4242
- **Decline**: 4000 0000 0000 0002
- **3D Secure**: 4000 0025 0000 3155

## URL Parameter Testing

Test dynamic pricing by adding these parameters to your page URL:
```
?stripe_price=49.99&stripe_currency=usd&stripe_description=Premium
```

## Common Issues & Solutions

### Issue: "Plugin not active" error
**Solution**: Make sure the plugin is activated in WordPress Admin → Plugins

### Issue: Test connection fails
**Solution**: 
1. Verify your API keys are correct
2. Make sure you're using test keys for testing
3. Check that your WordPress site has internet access

### Issue: Payment form not loading
**Solution**:
1. Check browser console for JavaScript errors
2. Verify Stripe.js is loading (check Network tab)
3. Make sure your API keys are configured

### Issue: Credit card input not working
**Solution**:
1. Check that Stripe Elements is properly initialized
2. Verify the card element container exists
3. Check for JavaScript errors in console

## File Structure Check

Ensure these files exist in your plugin directory:
```
wp-stripe-payment/
├── wp-stripe-payment.php
├── includes/
│   ├── class-wp-stripe-payment.php
│   ├── class-wp-stripe-payment-stripe-api.php
│   ├── class-wp-stripe-payment-handler.php
│   ├── class-wp-stripe-payment-forms.php
│   ├── class-wp-stripe-payment-shortcodes.php
│   └── class-wp-stripe-payment-activator.php
├── admin/
│   ├── class-wp-stripe-payment-admin.php
│   ├── css/admin.css
│   └── js/admin.js
├── assets/
│   ├── css/wp-stripe-payment.css
│   └── js/wp-stripe-payment.js
└── composer.json
```

## Debug Mode

To enable debug mode, add this to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `/wp-content/debug.log` for any errors.

## Support

If you're still having issues:
1. Check the WordPress debug log
2. Check browser console for JavaScript errors
3. Verify all files are present and have correct permissions
4. Make sure your WordPress version is 6.8.2+ and PHP is 8.2.18+

## Next Steps

Once the plugin is working:
1. Set up webhooks in your Stripe dashboard
2. Test with real payment flows
3. Customize the styling in the CSS files
4. Add custom metadata handling as needed
