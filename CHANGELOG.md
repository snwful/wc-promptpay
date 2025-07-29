# Changelog

All notable changes to the Woo PromptPay n8n plugin will be documented in this file.

## [1.6.1] - 2025-07-29
### Fixed
- PromptPay payment method now displays correctly even when all other payment methods are disabled
- Place Order button is now properly disabled until slip upload validation passes
- Enhanced container detection for WooCommerce Blocks checkout
- Better adaptive HTML generation for different checkout scenarios
- More robust payment method selection handling

### Improved
- Smart detection system that works with various WooCommerce Blocks configurations
- Fallback mechanisms for different payment method scenarios
- Better user experience with proper button state management

## [1.6.0] - 2025-07-29
### Added
- Full WooCommerce Blocks checkout support with JavaScript DOM injection
- Thai language interface for PromptPay payment method
- Real-time file upload validation with visual feedback
- Responsive design for mobile and desktop checkout
- Smart detection and retry mechanism for WooCommerce Blocks loading

### Fixed
- PromptPay payment method now displays correctly in WooCommerce Blocks
- Resolved all checkout rendering issues across different themes
- Fixed compatibility issues with React-based checkout components

### Improved
- Clean and optimized codebase with removal of all debug/test code
- Enhanced user experience with smooth animations and visual feedback
- Better error handling and user feedback for file uploads
- Streamlined plugin architecture for better maintainability

### Changed
- Refactored plugin architecture for WooCommerce Blocks compatibility
- Renamed and reorganized files for better code structure
- Removed all temporary, debug, and test files
- Bump plugin version to 1.6.0

### Removed
- All debug and test injection files
- Temporary force reload and direct fix solutions
- Unused admin notices and debug logging

## [1.5.0] - 2025-07-28
### Added
- Production-ready JavaScript DOM injection solution for PromptPay checkout rendering
- Comprehensive checkout element detection with multiple fallback selectors
- Thai language support for PromptPay payment interface
- File upload validation with proper error handling
- QR code placeholder with PromptPay ID display
- Animation and visual feedback for better user experience

### Fixed
- Resolved all checkout hook rendering issues by implementing client-side injection
- Fixed PromptPay payment method not appearing in checkout payment options

### Changed
- Replaced test injection with production solution
- Bump plugin version to 1.5.0

### Improved
- Enhanced checkout integration with robust element detection
- Better error handling and user feedback
- Responsive design for mobile and desktop

## [1.4.1] - 2025-07-28
### Improved
- Add stronger debug logging for plugin and checkout rendering hooks

### Fixed
- Ensure plugin reload and debug log freshness after code updates

### Changed
- Bump plugin version to 1.4.1

## [1.4.0] - 2025-07-28
### Added
- Manual injection workaround to display PromptPay payment method in checkout options

### Changed
- Bump plugin version to 1.4.0

### Improved
- Updated debug-gateway output and hooks for checkout rendering

## [1.3.0] - 2023-11-30
### Fixed
- แก้ไข PHP deprecated dynamic property warnings ใน gateway class
- ปรับปรุงการบังคับให้ gateway แสดงใน Payment options ที่ checkout
- แก้ไขปัญหา gateway ไม่แสดงแม้ว่าจะ available

### Added
- เพิ่ม property declarations ใน PP_Payment_Gateway class
- เพิ่ม hooks เพิ่มเติมเพื่อให้แน่ใจว่า gateway จะแสดงผล
- เพิ่ม debug logging ที่ละเอียดขึ้นเพื่อติดตามปัญหา
- เพิ่ม method ensure_gateway_availability และ set_default_payment_method

### Improved
- ปรับปรุงการจัดการ properties ใน gateway class เพื่อความปลอดภัย
- เพิ่มการ log ข้อมูล available gateways เพื่อ debug
- ปรับปรุงการ force gateway availability ให้ทำงานได้ดีขึ้น

### Security
- ปรับปรุงการจัดการ properties ใน gateway class
- เพิ่มการตรวจสอบความปลอดภัยในการ register gateway

## [1.2.0] - 2023-11-28
### Fixed
- แก้ไขปัญหาไม่แสดงตัวเลือกการชำระเงินในหน้า Checkout
- ปรับปรุงการตรวจสอบความพร้อมใช้งานของช่องทางการชำระเงิน

### Improved
- เพิ่มค่าเริ่มต้นสำหรับการตั้งค่า PromptPay ID
- ปรับปรุงการแสดงผล QR Code และฟอร์มอัปโหลดสลิป
- เพิ่มการตรวจสอบความถูกต้องของข้อมูลก่อนส่งคำสั่งซื้อ

### Security
- ปรับปรุงความปลอดภัยในการตรวจสอบ Nonce
- ปรับปรุงการจัดการข้อผิดพลาด

## [1.1.0] - 2023-11-20
### Added
- Pre-checkout payment verification with n8n
- Inline payment slip upload on checkout
- Client-side validation and feedback
- AJAX handlers for verification

### Changed
- Moved verification to pre-checkout flow
- Enhanced checkout UX
- Improved security with nonce verification

### Fixed
- Fixed gateway visibility on checkout
- Improved error handling

## [1.0.1] - 2025-01-28

### Fixed
- **WooCommerce HPOS Compatibility**: Fixed compatibility issue with WooCommerce High-Performance Order Storage (HPOS)
- **Admin Dashboard**: Updated admin dashboard to use HPOS-compatible WooCommerce functions instead of direct SQL queries
- **Order Statistics**: Fixed dashboard statistics calculation to work properly with HPOS enabled
- **Database Queries**: Replaced direct SQL queries with `wc_get_orders()` function for better compatibility

### Added
- **HPOS Declaration**: Added proper HPOS compatibility declaration using `FeaturesUtil::declare_compatibility()`
- **Order Object Support**: Enhanced order handling to work with both traditional posts and HPOS order objects

### Changed
- **Performance**: Improved plugin performance when HPOS is enabled
- **Code Quality**: Updated codebase to follow WooCommerce best practices for order handling
- **Compatibility**: Enhanced compatibility with WooCommerce 8.2+ and future versions

### Technical Details
- Updated `get_dashboard_statistics()` method to use `wc_get_orders()`
- Updated `get_paid_orders()` method to use WooCommerce order objects
- Updated `get_pending_slips()` method to use WooCommerce order objects
- Added `declare_hpos_compatibility()` method in main plugin class
- Replaced `mysql2date()` with `$order->get_date_created()->date_i18n()`
- Enhanced order meta retrieval using `$order->get_meta()` method

## [1.0.0] - 2025-01-27

### Added
- **Initial Release**: Complete WooCommerce PromptPay payment gateway plugin
- **PromptPay QR Generation**: Automatic QR code generation with proper PromptPay payload format
- **File Upload System**: Secure slip upload functionality with validation and attempt limits
- **n8n Integration**: Webhook system for automatic payment verification via n8n
- **Admin Dashboard**: Complete admin interface with order tracking and webhook logs
- **REST API**: Secure REST endpoints for webhook callbacks and dashboard data
- **Security Features**: CSRF protection, file validation, upload limits, and anti-spam measures
- **WooCommerce Integration**: Full integration with WooCommerce order system and hooks

### Features
- Multiple PromptPay ID format support (phone, citizen ID, etc.)
- QR code generation with amount integration
- Secure file upload with MIME type validation
- Asynchronous webhook processing
- Order status automation
- Comprehensive admin dashboard with statistics
- Timeline view for webhook events
- Search and filter functionality
- Responsive admin interface

### Security
- CSRF token validation
- File upload restrictions and validation
- Upload attempt limits
- Secure file storage with .htaccess protection
- Input sanitization and validation
- Capability-based access control
