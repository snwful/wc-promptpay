# PromptPay n8n Gateway

A comprehensive WooCommerce payment gateway plugin that enables merchants to accept payments via PromptPay QR codes with automated payment verification through n8n workflows.

## Features

- **PromptPay QR Code Generation**: Automatically generates QR codes for payments using Thai PromptPay standard
- **Payment Slip Upload**: AJAX-powered file upload system for payment verification
- **n8n Integration**: Seamless webhook integration with n8n for automated payment verification
- **Admin Dashboard**: Complete management interface with statistics, order tracking, and webhook logs
- **Custom Order Statuses**: Specialized order statuses for payment workflow management
- **Security**: Secure REST API endpoints with shared secret key authentication
- **Responsive Design**: Mobile-friendly interface for both frontend and admin
- **Multi-language Support**: Ready for internationalization with proper text domains

## Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the payment gateway in WooCommerce Settings > Payments

## Configuration

### Basic Settings

1. Go to **WooCommerce > Settings > Payments**
2. Find "PromptPay n8n Gateway" and click "Manage"
3. Configure the following settings:
   - **Enable/Disable**: Enable the payment gateway
   - **Title**: Display name for customers (default: "PromptPay")
   - **Description**: Payment method description
   - **PromptPay ID**: Your PromptPay ID (phone number or National ID)
   - **n8n Webhook URL**: Your n8n webhook endpoint for payment verification
   - **Max Upload Attempts**: Maximum retry attempts for slip uploads (default: 3)
   - **Shared Secret Key**: Security key for webhook authentication

### n8n Workflow Setup

Your n8n workflow should:

1. **Receive webhook data** containing:
   - `order_id`: WooCommerce order ID
   - `order_total`: Expected payment amount
   - `slip_file`: Uploaded payment slip
   - `customer_details`: Customer information

2. **Process payment verification** (your custom logic)

3. **Send callback** to: `yoursite.com/wp-json/promptpay-gateway/v1/callback`
   - **Headers**: `X-PromptPay-Secret: your_shared_secret_key`
   - **Body**: JSON with verification results

#### Callback Format

```json
{
  "order_id": 123,
  "status": "success|failed|pending",
  "amount_paid": "35040.00",
  "transaction_id": "FT123456",
  "reason": "Optional failure reason"
}
```

## Usage

### Customer Payment Flow

1. Customer selects "PromptPay" at checkout
2. System generates QR code with payment amount
3. Customer scans QR code with mobile banking app
4. Customer completes payment and uploads slip
5. System sends slip to n8n for verification
6. Order status updates automatically based on verification result

### Admin Management

Access the admin dashboard at **PromptPay n8n** in WordPress admin menu:

- **Dashboard**: Overview statistics and recent activity
- **Paid Orders**: List of successfully processed payments
- **Pending Slips**: Orders awaiting verification with manual approval options
- **Webhook Events**: Complete log of n8n interactions for debugging

## File Structure

```
promptpay-n8n-gateway/
├── promptpay-n8n-gateway.php          # Main plugin file
├── includes/
│   ├── class-wc-payment-gateway-promptpay-n8n.php  # Payment gateway class
│   ├── class-admin-menu.php           # Admin dashboard
│   ├── class-rest-api.php             # REST API endpoints
│   ├── class-qr-generator.php         # QR code generation
│   └── class-ajax-handler.php         # AJAX request handling
├── assets/
│   ├── css/
│   │   ├── frontend.css               # Customer-facing styles
│   │   └── admin.css                  # Admin interface styles
│   ├── js/
│   │   ├── frontend.js                # Customer-facing JavaScript
│   │   └── admin.js                   # Admin interface JavaScript
│   └── images/
│       └── promptpay-logo.png         # Payment method icon
└── README.md                          # This file
```

## Custom Order Statuses

The plugin introduces two custom order statuses:

- **Awaiting Payment Slip**: Order placed, waiting for customer to upload payment slip
- **Pending Verification**: Payment slip uploaded, awaiting n8n verification

## REST API Endpoints

### Callback Endpoint
- **URL**: `/wp-json/promptpay-gateway/v1/callback`
- **Method**: POST
- **Authentication**: Shared secret key in `X-PromptPay-Secret` header
- **Purpose**: Receive verification results from n8n

### Status Endpoint
- **URL**: `/wp-json/promptpay-gateway/v1/status/{order_id}`
- **Method**: GET
- **Authentication**: WordPress nonce or user login
- **Purpose**: Check order payment status

## Security Features

- **Nonce Verification**: All AJAX requests protected with WordPress nonces
- **File Upload Validation**: Strict file type and size validation
- **Secure File Storage**: Payment slips stored in protected directory
- **API Authentication**: Webhook endpoints secured with shared secret keys
- **Input Sanitization**: All user inputs properly sanitized and escaped

## Hooks and Filters

### Actions
- `promptpay_n8n_payment_verified`: Triggered when payment is successfully verified
- `promptpay_n8n_payment_failed`: Triggered when payment verification fails
- `promptpay_n8n_payment_pending`: Triggered when payment requires manual review

### Example Usage
```php
add_action('promptpay_n8n_payment_verified', function($order, $verification_data) {
    // Custom logic after successful payment
    error_log('Payment verified for order: ' . $order->get_id());
});
```

## Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **n8n Instance**: For payment verification workflow

## Troubleshooting

### Common Issues

1. **QR Code Not Displaying**
   - Check PromptPay ID format (10-digit phone or 13-digit National ID)
   - Verify Google Charts API accessibility

2. **Webhook Not Working**
   - Confirm n8n webhook URL is accessible
   - Check shared secret key matches in both systems
   - Review webhook logs in admin dashboard

3. **File Upload Fails**
   - Verify upload directory permissions
   - Check file size limits (5MB max)
   - Ensure allowed file types (JPG, PNG, PDF)

### Debug Mode

Enable WordPress debug mode to see detailed error logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and bug reports, please check the webhook events log in the admin dashboard first, as it contains detailed information about payment processing issues.

## License

This plugin is licensed under the GPL v3 or later.

## Changelog

### Version 1.0.0
- Initial release
- PromptPay QR code generation
- Payment slip upload functionality
- n8n webhook integration
- Admin dashboard with statistics
- REST API endpoints
- Custom order statuses
- Security features and validation
