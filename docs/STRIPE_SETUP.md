# Stripe Integration Setup Guide

This guide will help you set up Stripe integration for the Adeptus Insights plugin.

## Prerequisites

1. **Stripe Account**: You need a Stripe account. If you don't have one, sign up at [stripe.com](https://stripe.com)
2. **Composer**: Ensure Composer is installed on your server
3. **Admin Access**: You need admin access to your Moodle installation

## Step 1: Install Stripe PHP SDK

### Option A: Using Composer (Recommended)

1. Navigate to your Moodle plugin directory:
   ```bash
   cd /var/www/vhosts/stagingwithswift.com/plugin.stagingwithswift.com/report/adeptus_insights
   ```

2. Install Stripe PHP SDK:
   ```bash
   composer require stripe/stripe-php
   ```

### Option B: Manual Installation

1. Download the Stripe PHP SDK from [GitHub](https://github.com/stripe/stripe-php)
2. Extract to `/var/www/vhosts/stagingwithswift.com/plugin.stagingwithswift.com/report/adeptus_insights/vendor/stripe/stripe-php/`

## Step 2: Create Stripe Products and Prices

### Using Stripe Dashboard (Recommended)

1. **Log into your Stripe Dashboard** at [dashboard.stripe.com](https://dashboard.stripe.com)

2. **Create Products**:
   
   **Starter Plan (Free)**:
   - Go to Products → Add Product
   - Name: "Starter Plan"
   - Description: "Free plan for getting started"
   - Price: £0.00
   - Billing: Monthly
   - Product ID: `prod_starter`
   - Price ID: `price_starter`

   **Basic Plan**:
   - Name: "Basic Plan"
   - Description: "Perfect for small institutions"
   - Price: £9.99
   - Billing: Monthly
   - Product ID: `prod_basic`
   - Price ID: `price_basic`

   **Professional Plan**:
   - Name: "Professional Plan"
   - Description: "Ideal for medium-sized institutions"
   - Price: £29.99
   - Billing: Monthly
   - Product ID: `prod_professional`
   - Price ID: `price_professional`

   **Enterprise Plan**:
   - Name: "Enterprise Plan"
   - Description: "For large institutions with high usage"
   - Price: £99.99
   - Billing: Monthly
   - Product ID: `prod_enterprise`
   - Price ID: `price_enterprise`

### Using Stripe API (Alternative)

You can also create products programmatically using the Stripe API. Here's a PHP script:

```php
<?php
require_once 'vendor/autoload.php';

// Set your Stripe secret key
\Stripe\Stripe::setApiKey('sk_test_...'); // Replace with your test key

// Create products
$products = [
    [
        'name' => 'Starter Plan',
        'description' => 'Free plan for getting started',
        'price' => 0,
        'product_id' => 'prod_starter',
        'price_id' => 'price_starter'
    ],
    [
        'name' => 'Basic Plan',
        'description' => 'Perfect for small institutions',
        'price' => 999, // £9.99 in pence
        'product_id' => 'prod_basic',
        'price_id' => 'price_basic'
    ],
    [
        'name' => 'Professional Plan',
        'description' => 'Ideal for medium-sized institutions',
        'price' => 2999, // £29.99 in pence
        'product_id' => 'prod_professional',
        'price_id' => 'price_professional'
    ],
    [
        'name' => 'Enterprise Plan',
        'description' => 'For large institutions with high usage',
        'price' => 9999, // £99.99 in pence
        'product_id' => 'prod_enterprise',
        'price_id' => 'price_enterprise'
    ]
];

foreach ($products as $product_data) {
    // Create product
    $product = \Stripe\Product::create([
        'name' => $product_data['name'],
        'description' => $product_data['description'],
        'id' => $product_data['product_id']
    ]);
    
    // Create price
    $price_data = [
        'product' => $product->id,
        'unit_amount' => $product_data['price'],
        'currency' => 'gbp'
    ];
    
    if ($product_data['price'] > 0) {
        $price_data['recurring'] = [
            'interval' => 'month'
        ];
    }
    
    $price = \Stripe\Price::create($price_data);
    
    echo "Created {$product_data['name']}: Product ID: {$product->id}, Price ID: {$price->id}\n";
}
?>
```

## Step 3: Configure Stripe Keys

1. **Get your API keys** from the Stripe Dashboard:
   - Go to Developers → API Keys
   - Copy your **Publishable key** and **Secret key**

2. **Configure in Moodle**:
   - Go to Site Administration → Plugins → Reports → Adeptus Insights
   - Enter your Stripe keys
   - Set Test Mode to "Yes" for testing
   - Save the configuration

## Step 4: Set Up Webhooks

1. **Create Webhook Endpoint**:
   - Go to Developers → Webhooks in Stripe Dashboard
   - Click "Add endpoint"
   - URL: `https://plugin.stagingwithswift.com/report/adeptus_insights/webhook.php`
   - Events to send:
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `invoice.payment_succeeded`
     - `invoice.payment_failed`

2. **Get Webhook Secret**:
   - After creating the webhook, click on it
   - Copy the "Signing secret"
   - Add it to your Moodle Stripe configuration

## Step 5: Test the Integration

1. **Test Mode**:
   - Ensure Test Mode is enabled in your Moodle configuration
   - Use test card numbers from [Stripe Testing](https://stripe.com/docs/testing)

2. **Test Cards**:
   - Success: `4242424242424242`
   - Decline: `4000000000000002`
   - 3D Secure: `4000002500003155`

3. **Test the Flow**:
   - Register a new installation
   - Verify starter plan is assigned
   - Test upgrading to a paid plan
   - Test subscription cancellation

## Step 6: Go Live

1. **Switch to Live Mode**:
   - Get your live API keys from Stripe Dashboard
   - Update your Moodle configuration with live keys
   - Set Test Mode to "No"

2. **Update Webhook**:
   - Create a new webhook endpoint for live mode
   - Update the webhook URL if needed

3. **Verify Everything**:
   - Test with real payment methods
   - Verify webhook events are received
   - Check subscription management works

## Troubleshooting

### Common Issues

1. **"Stripe SDK not found"**:
   - Ensure Composer is installed
   - Run `composer install` in the plugin directory
   - Check file permissions

2. **"Invalid API key"**:
   - Verify you're using the correct key (test vs live)
   - Check the key format (starts with `sk_test_` or `sk_live_`)

3. **Webhook failures**:
   - Check webhook URL is accessible
   - Verify webhook secret is correct
   - Check server logs for errors

4. **Database errors**:
   - Run Moodle's database upgrade: `php admin/cli/upgrade.php`
   - Check table structure matches schema

### Debug Mode

Enable debug logging in Moodle:
1. Go to Site Administration → Development → Debugging
2. Set "Debug messages" to "ALL"
3. Check the debug log for Stripe-related errors

### Support

If you encounter issues:
1. Check the Moodle debug log
2. Verify Stripe Dashboard for failed payments
3. Test webhook delivery in Stripe Dashboard
4. Contact support with specific error messages

## Security Considerations

1. **Never expose secret keys** in client-side code
2. **Use HTTPS** for all webhook endpoints
3. **Verify webhook signatures** (handled automatically by the plugin)
4. **Regularly rotate API keys**
5. **Monitor for suspicious activity**

## Cost Structure

- **Starter Plan**: Free (automatically assigned)
- **Basic Plan**: £9.99/month
- **Professional Plan**: £29.99/month
- **Enterprise Plan**: £99.99/month

Stripe fees: 1.4% + 20p for UK cards, 2.9% + 20p for international cards.

## Next Steps

After setup:
1. Customize the subscription page UI
2. Set up email notifications
3. Configure usage tracking
4. Set up analytics and reporting
5. Plan for scaling and monitoring 