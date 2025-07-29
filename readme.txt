=== Woo PromptPay n8n ===
Contributors: seniordev
Tags: woocommerce, payment, promptpay, thailand, n8n, webhook
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept PromptPay payments in WooCommerce with QR generation, slip upload and n8n webhook confirmation.

== Description ==

Woo PromptPay n8n is a comprehensive WooCommerce payment gateway that enables Thai businesses to accept PromptPay payments with automatic verification through n8n workflows.

**Key Features:**

* **PromptPay QR Code Generation**: Automatically generates QR codes with order amount
* **Secure File Upload**: Customers can upload payment slips with validation and security checks
* **n8n Integration**: Seamless webhook integration for payment verification
* **Anti-Spam Protection**: Upload attempt limits and CSRF protection
* **Order Management**: Complete order notes and status tracking
* **REST API**: RESTful endpoints for webhook integration

**How It Works:**

1. Customer selects PromptPay as payment method during checkout
2. Plugin generates QR code with order amount on Thank You page
3. Customer scans QR code and makes payment via mobile banking
4. Customer uploads payment slip through secure form
5. Plugin sends data to n8n webhook for verification
6. n8n processes payment verification and sends status back
7. Plugin automatically updates order status based on verification result

**Security Features:**

* CSRF protection with WordPress nonces
* File upload validation (MIME type, size, extension)
* Upload attempt limits (configurable, default: 3 attempts)
* Secure file storage with .htaccess protection
* Anti-spam and duplicate request prevention
* Order key verification for webhooks

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/woo-promptpay-n8n/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Settings > Payments > PromptPay (n8n)
4. Configure your PromptPay ID and n8n webhook URL
5. Enable the payment method

== Frequently Asked Questions ==

= What is required to use this plugin? =

* WordPress 6.0+
* WooCommerce 8.0+
* PHP 7.4+
* Valid PromptPay ID (phone number, citizen ID, etc.)
* n8n instance with webhook endpoint (optional but recommended)

= How do I set up n8n integration? =

1. Create an n8n workflow with a webhook trigger
2. Configure the workflow to process payment verification
3. Set up a webhook response to send status back to WooCommerce
4. Enter your n8n webhook URL in the plugin settings

= What file types are supported for slip uploads? =

The plugin supports JPEG, PNG, and PDF files up to 5MB in size.

= How many times can a customer upload a slip? =

By default, customers can upload up to 3 slips per order. This is configurable in the plugin settings.

= Is the plugin secure? =

Yes, the plugin includes multiple security measures:
- CSRF protection with nonces
- File upload validation
- Secure file storage
- Anti-spam protection
- Order verification

== Screenshots ==

1. Plugin settings page
2. PromptPay payment option on checkout
3. QR code and upload form on Thank You page
4. Order notes showing payment verification

== Changelog ==

= 1.6.1 =
* Fixed: PromptPay payment method now displays correctly even when all other payment methods are disabled
* Fixed: Place Order button is now properly disabled until slip upload validation passes
* Improved: Enhanced container detection for WooCommerce Blocks checkout
* Improved: Better adaptive HTML generation for different checkout scenarios
* Improved: More robust payment method selection handling

= 1.6.0 =
* Added: Full WooCommerce Blocks checkout support with JavaScript DOM injection
* Added: Thai language interface for PromptPay payment method
* Added: Real-time file upload validation with visual feedback
* Added: Responsive design for mobile and desktop checkout
* Fixed: PromptPay payment method now displays correctly in WooCommerce Blocks
* Fixed: Resolved all checkout rendering issues across different themes
* Improved: Clean and optimized codebase with removal of debug/test code
* Improved: Enhanced user experience with animations and visual feedback
* Changed: Refactored plugin architecture for WooCommerce Blocks compatibility
* Changed: Bump plugin version to 1.6.0

= 1.5.0 =

= 1.5.0 =
* Added: Production-ready JavaScript DOM injection solution for PromptPay checkout rendering
* Added: Comprehensive checkout element detection with multiple fallback selectors
* Added: Thai language support for PromptPay payment interface
* Added: File upload validation with proper error handling
* Added: QR code placeholder with PromptPay ID display
* Fixed: Resolved all checkout hook rendering issues by implementing client-side injection
* Improved: Enhanced user experience with animations and visual feedback
* Changed: Replaced test injection with production solution
* Changed: Bump plugin version to 1.5.0

= 1.4.1 =

= 1.4.1 =
* Improved: Add stronger debug logging for plugin and checkout rendering hooks
* Fixed: Ensure plugin reload and debug log freshness after code updates
* Changed: Bump plugin version to 1.4.1

= 1.4.0 =

= 1.4.0 =
* Added: Manual injection workaround to display PromptPay payment method in checkout options
* Changed: Bump plugin version to 1.4.0
* Improved: Updated debug-gateway output and hooks for checkout rendering

= 1.3.0 =

= 1.3.0 =
* Fixed: แก้ไข PHP deprecated dynamic property warnings
* Fixed: ปรับปรุงการบังคับให้ gateway แสดงใน Payment options
* Improved: เพิ่ม debug logging ที่ละเอียดขึ้นเพื่อติดตามปัญหา
* Improved: เพิ่ม hooks เพิ่มเติมเพื่อให้แน่ใจว่า gateway จะแสดงผล
* Security: ปรับปรุงการจัดการ properties ใน gateway class

= 1.2.0 =
* Fixed: แก้ไขปัญหาไม่แสดงตัวเลือกการชำระเงินในหน้า Checkout
* Fixed: ปรับปรุงการตรวจสอบความพร้อมใช้งานของช่องทางการชำระเงิน
* Improved: เพิ่มค่าเริ่มต้นสำหรับการตั้งค่า PromptPay ID
* Improved: ปรับปรุงการแสดงผล QR Code และฟอร์มอัปโหลดสลิป
* Improved: เพิ่มการตรวจสอบความถูกต้องของข้อมูลก่อนส่งคำสั่งซื้อ

= 1.1.0 =
* Improved: เพิ่มการยืนยันการชำระเงินแบบเรียลไทม์
* Improved: ปรับปรุง UX ของหน้า Checkout

= 1.0.1 =
* Fixed: WooCommerce High-Performance Order Storage (HPOS) compatibility
* Updated: Admin dashboard to use HPOS-compatible WooCommerce functions
* Updated: Order queries to use wc_get_orders() instead of direct SQL
* Improved: Plugin performance with HPOS enabled
* Added: Proper HPOS compatibility declaration
* Fixed: Admin dashboard statistics calculation for HPOS

= 1.0.0 =
* Initial release
* PromptPay QR code generation
* Secure file upload system
* n8n webhook integration
* REST API endpoints
* Complete security implementation

== Upgrade Notice ==

= 1.6.1 =
Bug fix release: Fixes PromptPay display issues when other payment methods are disabled and improves Place Order button control. Recommended update for all users.

= 1.6.0 =
Major release: Full WooCommerce Blocks support with clean, optimized codebase. PromptPay now works perfectly with modern WooCommerce checkout. Highly recommended for all users.

= 1.5.0 =

= 1.5.0 =
Major update: Production-ready PromptPay checkout solution with JavaScript DOM injection. Resolves all rendering issues. Highly recommended for all users.

= 1.4.1 =

= 1.4.1 =
Improved debug logging and plugin reload detection for troubleshooting. Recommended update for all users.

= 1.4.0 =

= 1.4.0 =
Manual injection workaround for PromptPay rendering in checkout. Highly recommended update.

= 1.3.0 =

= 1.3.0 =
Critical update: Fixes PHP deprecated warnings and improves gateway visibility. Highly recommended for all users.

= 1.2.0 =
Important update: Adds fixes for payment option display and availability checks. Recommended for all users.

= 1.1.0 =
Important update: Adds real-time payment verification and improved checkout UX. Recommended for all users.

= 1.0.1 =
Important update: Adds WooCommerce High-Performance Order Storage (HPOS) compatibility. Recommended for all users using WooCommerce 8.2+.

= 1.0.0 =
Initial release of Woo PromptPay n8n plugin.
