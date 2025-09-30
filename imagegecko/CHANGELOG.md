# Changelog

All notable changes to ImageGecko AI Photos will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.2] - 2025-09-30

### Added
- GPL v2 license compliance
  - Added full license header to main plugin file
  - Added LICENSE.txt with complete GPL v2 text
  - Added license information to README.md
- WordPress.org compliance
  - Added readme.txt in WordPress.org standard format
  - Added detailed external service usage documentation
  - Added privacy policy information
  - Added SECURITY.md for vulnerability reporting
  - Added CONTRIBUTING.md for contributor guidelines
  - Added COMPLIANCE.md documenting guideline adherence
- Internationalization support
  - Added `load_plugin_textdomain()` call
  - Created `/languages/` directory for translations
  - Declared text domain and domain path in plugin header
- Uninstall cleanup
  - Added uninstall.php for proper plugin removal
  - Removes all plugin options and metadata
  - Clears scheduled events
  - Multisite compatible cleanup
- Development files
  - Added .gitignore for version control
  - Added CHANGELOG.md (this file)

### Changed
- Enhanced plugin header with all required fields
  - Added Plugin URI
  - Added Author URI
  - Added License and License URI
  - Added Requires at least, Requires PHP
  - Added Domain Path
- Updated README.md with license and service information
- Improved documentation throughout

### Security
- Confirmed encrypted API key storage (AES-256-CBC)
- Confirmed all AJAX requests use nonce verification
- Confirmed all form submissions use CSRF protection
- Confirmed all inputs are sanitized
- Confirmed all outputs are escaped
- Documented security practices in SECURITY.md

## [0.1.1] - 2025-09-15

### Added
- Image comparison display in admin UI
- Delete functionality for generated images
- Automatic original image restoration after deletion
- Source image tracking to prevent AI-from-AI generation

### Fixed
- Image handling logic to distinguish AI-generated from original images
- Gallery management when deleting generated images
- Featured image restoration when generated image is deleted

### Changed
- Improved logging with detailed context
- Enhanced error messages for better debugging

## [0.1.0] - 2025-09-01

### Added
- Initial release
- Integration with ContentGecko API for AI image generation
- WooCommerce product image enhancement
- Bulk image generation for multiple products
- Category-based product targeting
- Individual product selection
- Custom style prompt configuration
- Automatic featured image replacement
- Product gallery integration
- Admin settings page under WooCommerce menu
- Real-time progress tracking in admin UI
- Encrypted API key storage
- Action Scheduler integration (with WP-Cron fallback)
- Comprehensive logging through WooCommerce logger
- Row actions for single product generation
- Bulk actions for multiple product generation

### Security
- Capability checks (`manage_woocommerce`)
- Nonce verification on all AJAX requests
- Input sanitization and output escaping
- Encrypted API key storage
- HTTPS-only API communications

---

## Version Format

- **Major version (X.0.0)**: Breaking changes or major new features
- **Minor version (0.X.0)**: New features, backwards compatible
- **Patch version (0.0.X)**: Bug fixes, security patches

## Links

- [Plugin Homepage](https://contentgecko.io/woocommerce-product-image-generator/)

## Support

For questions about specific changes, please:
- Contact support at support@contentgecko.io
