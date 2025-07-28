=== WC PromptPay ===
Contributors: yourname
Tags: woocommerce, payment, promptpay, thailand, qr-code
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce PromptPay payment gateway with QR code generation and n8n webhook integration for automatic payment verification.

== Description ==

WC PromptPay is a comprehensive WooCommerce payment gateway that enables Thai businesses to accept payments via PromptPay QR codes. The plugin features automatic payment verification through n8n webhook integration, making it perfect for automated payment processing.

**Key Features:**

* **Multiple PromptPay ID Types**: Support for phone numbers, citizen ID, company tax ID, e-wallet ID, and K Shop ID
* **QR Code Generation**: Automatic generation of PromptPay QR codes with or without amount
* **Download QR Codes**: Customers can download QR codes for offline use
* **n8n Integration**: Webhook integration for automatic payment verification
* **Security**: Signature-based webhook verification for secure communication
* **WooCommerce Blocks**: Full support for WooCommerce Blocks checkout
* **HPOS Compatible**: Supports High-Performance Order Storage
* **Auto-Update**: Built-in support for plugin auto-updates

**How It Works:**

1. Customer selects PromptPay as payment method during checkout
2. Plugin generates a PromptPay QR code with order details
3. Customer scans QR code and makes payment via mobile banking app
4. Plugin sends order details to n8n webhook for payment verification
5. n8n verifies payment through bank APIs or other methods
6. n8n sends confirmation back to plugin via webhook
7. Plugin automatically updates order status upon payment confirmation

**n8n Webhook Integration:**

The plugin sends the following data to your n8n webhook:
* Order ID and details
* PromptPay ID and type
* Payment amount
* Customer information
* Callback URL for status updates

Your n8n workflow should verify the payment and send back a response to:
`https://yoursite.com/wc-promptpay/webhook/{order_id}`

**Webhook Response Format:**
```json
{
  "order_id": "123",
  "status": "success|failed|pending",
  "message": "Payment verified",
  "transaction_id": "optional_transaction_id",
  "amount": 100.00
}
```

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wc-promptpay/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Settings > Payments > PromptPay
4. Configure your PromptPay ID and n8n webhook settings
5. Enable the payment method

**Requirements:**
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* GD Library (for QR code generation)

== Frequently Asked Questions ==

= What PromptPay ID types are supported? =

The plugin supports:
* Phone numbers (10 digits starting with 0)
* Citizen ID (13 digits)
* Company Tax ID (13 digits)
* E-Wallet ID
* K Shop ID

= Do I need n8n for the plugin to work? =

No, the plugin can generate QR codes without n8n. However, for automatic payment verification, you'll need to set up an n8n workflow or similar webhook endpoint.

= How secure is the webhook integration? =

The plugin uses HMAC-SHA256 signature verification to ensure webhook authenticity. Make sure to set a strong secret key in the plugin settings.

= Can customers download QR codes? =

Yes, customers can download QR codes as PNG images for offline use or sharing.

== Screenshots ==

1. Plugin settings page
2. PromptPay payment method on checkout
3. QR code display on thank you page
4. Order notes showing payment verification

== Changelog ==

= 1.0.0 =
* Initial release
* PromptPay QR code generation
* n8n webhook integration
* WooCommerce Blocks support
* HPOS compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of WC PromptPay plugin.
