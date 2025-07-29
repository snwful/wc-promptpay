# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-01-29

### Added
- HPOS (High-Performance Order Storage) compatibility declaration
- Better payment gateway registration with priority handling
- Enhanced payment gateway availability checks

### Changed
- Updated plugin author to Lumi-dev
- Improved payment gateway initialization order
- Added `supports` property to payment gateway for better WooCommerce integration
- Enhanced `enabled` option handling in payment gateway constructor

### Fixed
- Fixed WooCommerce HPOS compatibility issue that was flagging plugin as incompatible
- Fixed payment gateway not appearing in WooCommerce Payment providers list
- Improved plugin initialization to ensure proper WooCommerce integration
- Fixed payment gateway registration timing issues

### Technical
- Added `\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility()` for HPOS
- Increased `plugins_loaded` hook priority to 11 for better initialization order
- Added proper `enabled` option initialization in payment gateway constructor
- Enhanced error handling and availability checks

## [1.0.0] - 2025-01-27

### Added
- Initial release of PromptPay n8n Gateway plugin
- Complete WooCommerce payment gateway implementation
- PromptPay QR code generation following Thai EMV standards
- AJAX-powered payment slip upload functionality
- n8n webhook integration for automated payment verification
- Secure REST API endpoints for payment callbacks
- Admin dashboard with statistics and order management
- Custom order statuses: "Awaiting Payment Slip" and "Pending Verification"
- Comprehensive security features including nonces and file validation
- Responsive design with Thai language support
- Complete documentation and setup instructions

### Features
- **Payment Gateway**: Full WooCommerce integration extending WC_Payment_Gateway
- **QR Code Generation**: Authentic Thai PromptPay QR codes with proper EMV formatting
- **File Upload**: Secure payment slip upload with validation (JPG, PNG, PDF, 5MB max)
- **Webhook Integration**: Seamless n8n workflow integration for payment verification
- **Admin Interface**: Professional dashboard matching WooCommerce design standards
- **Security**: WordPress nonces, input sanitization, secure file storage
- **Internationalization**: Ready for translation with proper text domains
- **Documentation**: Comprehensive README and inline code documentation
