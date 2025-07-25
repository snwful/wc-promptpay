# WC PromptPay Installation Guide

## Prerequisites

1. **WordPress** 5.0 or higher
2. **WooCommerce** 5.0 or higher
3. **PHP** 7.4 or higher
4. **GD Library** (usually included with PHP)
5. **phpqrcode library** (will be installed automatically)

## Installation Steps

### 1. Plugin Installation

1. Upload the `wc-promptpay` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Install phpqrcode library (see below)

### 2. Install phpqrcode Library

The plugin requires the phpqrcode library for QR code generation. You can install it in several ways:

#### Option A: Using Composer (Recommended)
```bash
cd /path/to/wp-content/plugins/wc-promptpay/
composer install
composer run-script install-phpqrcode
```

#### Option B: Manual Installation
1. Download phpqrcode from: https://github.com/t0k4rt/phpqrcode
2. Extract to `wp-content/plugins/wc-promptpay/vendor/phpqrcode/`
3. Ensure `qrlib.php` exists in the phpqrcode folder

#### Option C: Alternative Library
If you prefer a different QR code library, modify the `load_phpqrcode()` method in `class-wc-promptpay-qr-generator.php`

### 3. Plugin Configuration

1. Go to **WooCommerce > Settings > Payments**
2. Click on **PromptPay** to configure
3. Fill in the required settings:

#### Basic Settings
- **Enable PromptPay Payment**: Check to enable
- **Title**: Display name for customers (e.g., "PromptPay")
- **Description**: Payment method description

#### PromptPay Settings
- **PromptPay ID**: Your PromptPay identifier
- **PromptPay ID Type**: Select appropriate type:
  - Phone Number (10 digits, starts with 0)
  - Citizen ID (13 digits)
  - Company Tax ID (13 digits)
  - E-Wallet ID
  - K Shop ID
- **Include Amount in QR Code**: Recommended to enable
- **Extra Message**: Optional additional instructions

#### n8n Webhook Settings (Optional)
- **n8n Webhook URL**: Your n8n webhook endpoint
- **Webhook Secret Key**: Secret for signature verification
- **Auto Complete Orders**: Enable to auto-complete instead of processing

### 4. n8n Workflow Setup (Optional)

If you want automatic payment verification:

1. Import the example workflow from `examples/n8n-workflow-example.json`
2. Modify the payment verification logic for your bank's API
3. Set the webhook URL in plugin settings
4. Configure the same secret key in both n8n and plugin

## Testing

### 1. Test QR Code Generation
1. Create a test order
2. Select PromptPay as payment method
3. Verify QR code appears on thank you page
4. Test QR code download functionality

### 2. Test Webhook Integration
1. Set up a test n8n workflow
2. Create a test order
3. Check n8n receives webhook data
4. Verify callback updates order status

### 3. Test Different PromptPay ID Types
- Test with phone number format
- Test with citizen ID format
- Verify QR codes work with banking apps

## Troubleshooting

### QR Code Not Displaying
1. Check if GD library is installed: `php -m | grep -i gd`
2. Verify phpqrcode library is properly installed
3. Check file permissions on uploads directory
4. Enable WordPress debug mode to see errors

### Webhook Not Working
1. Verify webhook URL is accessible
2. Check secret key matches between plugin and n8n
3. Review order notes for webhook status
4. Test webhook endpoint manually with curl

### Permission Issues
1. Ensure uploads directory is writable
2. Check file permissions: `chmod 755 wp-content/uploads`
3. Verify web server can create directories

## Security Considerations

1. **Use HTTPS** for webhook endpoints
2. **Set strong secret keys** for webhook verification
3. **Regularly update** the plugin and dependencies
4. **Monitor webhook logs** for suspicious activity
5. **Validate webhook signatures** in your n8n workflow

## File Structure

```
wc-promptpay/
├── wc-promptpay.php              # Main plugin file
├── includes/
│   ├── class-wc-promptpay-gateway.php      # Payment gateway
│   ├── class-wc-promptpay-qr-generator.php # QR code generation
│   ├── class-wc-promptpay-webhook.php      # Webhook handler
│   └── class-wc-promptpay-blocks.php       # Blocks support
├── assets/
│   └── js/frontend/
│       ├── blocks.js             # Blocks JavaScript
│       └── blocks.asset.php      # Asset dependencies
├── vendor/
│   └── phpqrcode/               # QR code library
├── examples/
│   └── n8n-workflow-example.json # Example n8n workflow
├── composer.json                # Composer configuration
├── readme.txt                   # WordPress plugin readme
├── uninstall.php               # Uninstall script
└── INSTALLATION.md             # This file
```

## Support

For issues and questions:
1. Check WordPress debug logs
2. Review WooCommerce system status
3. Test with default theme and minimal plugins
4. Contact plugin developer with detailed error information

## Changelog

- **v1.0.0**: Initial release with full PromptPay and n8n integration
