# WordPress.org Plugin Directory Compliance

This document outlines how ImageGecko AI Photos complies with the [WordPress.org Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

## Compliance Checklist

### ✅ 1. GPL License Compatible

- **Status**: Compliant
- **Implementation**:
  - Plugin header declares "GPL v2 or later"
  - Full GPL v2 license text included in `LICENSE.txt`
  - License information in `readme.txt`
  - All code, data, and images are GPL-compatible

### ✅ 2. Developer Responsibility

- **Status**: Compliant
- **Implementation**:
  - All files comply with guidelines
  - No obfuscated or hidden code
  - All third-party code is GPL-compatible
  - External service (ContentGecko API) properly documented

### ✅ 3. Stable Version Available

- **Status**: Compliant
- **Implementation**:
  - Complete, functional plugin available
  - Version 0.1.2 is stable and tested
  - Distributed via WordPress.org directory structure

### ✅ 4. Human Readable Code

- **Status**: Compliant
- **Implementation**:
  - All code is properly formatted and commented
  - No obfuscation, minification, or code hiding
  - Clear variable and function names
  - PHPDoc comments throughout
  - Development code available in repository

### ✅ 5. Trialware Not Permitted

- **Status**: Compliant
- **Implementation**:
  - No trial period or quota limitations in code
  - All plugin functionality is fully available
  - External API service (ContentGecko) requires account, which is permitted under guideline #6

### ✅ 6. Software as a Service Permitted

- **Status**: Compliant
- **Implementation**:
  - Plugin acts as interface to ContentGecko API service
  - Service provides genuine AI image generation functionality
  - Clearly documented in `readme.txt` under "External Service Usage"
  - Terms of Service link provided
  - Service purpose and data transmission documented

### ✅ 7. No Tracking Without Consent

- **Status**: Compliant
- **Implementation**:
  - No analytics, tracking, or telemetry code
  - No cookies or tracking scripts added to site
  - Only functional API calls to ContentGecko service
  - No user behavior tracking
  - Privacy policy clearly states no tracking

### ✅ 8. No Executable Code via Third-Party

- **Status**: Compliant
- **Implementation**:
  - No external JavaScript files loaded
  - No eval() or dynamic code execution
  - Only data (images) transmitted/received from API
  - All code is local and verifiable

### ✅ 9. Legal and Ethical

- **Status**: Compliant
- **Implementation**:
  - No illegal, dishonest, or morally offensive functionality
  - Respects user data and privacy
  - Transparent about external service usage
  - Secure API key storage

### ✅ 10. No External Links Without Permission

- **Status**: Compliant
- **Implementation**:
  - No external links embedded in public site
  - No credits or promotional links on frontend
  - Admin area links only point to plugin settings
  - No affiliate links

### ✅ 11. No Admin Dashboard Hijacking

- **Status**: Compliant
- **Implementation**:
  - Settings page under WooCommerce menu (appropriate context)
  - No intrusive popups or notifications
  - Admin notices only for relevant actions
  - No promotional content in admin
  - Clean, WordPress-standard UI

### ✅ 12. No Readme Spam

- **Status**: Compliant
- **Implementation**:
  - Only 4 relevant tags used
  - No competitor plugin names in tags
  - No keyword stuffing
  - No blackhat SEO
  - Related product links only (WooCommerce requirement)
  - No hidden affiliate links

### ✅ 13. Uses WordPress Default Libraries

- **Status**: Compliant
- **Implementation**:
  - Uses WordPress bundled jQuery (not included separately)
  - Uses jQuery UI Autocomplete from WordPress
  - Uses `wp_enqueue_script()` and `wp_enqueue_style()`
  - No duplicate libraries included

### ✅ 14. Avoids Frequent Commits

- **Status**: Compliant
- **Implementation**:
  - Semantic versioning for releases
  - Meaningful commit messages
  - Changes grouped into logical releases
  - No "trash" commits like "update" or "cleanup"

### ✅ 15. Version Numbers Incremented

- **Status**: Compliant
- **Implementation**:
  - Version in plugin header: 0.1.2
  - Version in readme.txt stable tag: 0.1.2
  - Versions match across all files
  - Follows semantic versioning

### ✅ 16. Complete Plugin at Submission

- **Status**: Compliant
- **Implementation**:
  - Fully functional plugin
  - Not a placeholder or reserved name
  - Complete feature set as advertised
  - Tested and working

### ✅ 17. Respects Trademarks and Copyrights

- **Status**: Compliant
- **Implementation**:
  - Plugin name "ImageGecko" is original branding
  - References "WooCommerce" appropriately as requirement
  - Uses "ContentGecko" with permission (own service)
  - No misleading brand usage

### ✅ 18. Plugin Directory Maintenance Rights

- **Status**: Acknowledged
- **Implementation**:
  - Accept WordPress.org's right to update guidelines
  - Accept right to disable/remove plugin if needed
  - Accept right to grant exceptions
  - Commit to addressing issues promptly

## Additional Best Practices Implemented

### Security
- ✅ Nonce verification on all AJAX requests
- ✅ Capability checks (`manage_woocommerce`)
- ✅ Input sanitization using WordPress functions
- ✅ Output escaping (`esc_html`, `esc_attr`, etc.)
- ✅ Encrypted API key storage (AES-256-CBC)
- ✅ Prepared statements (via WordPress meta functions)
- ✅ HTTPS-only API communications

### Internationalization
- ✅ Text domain declared: `imagegecko`
- ✅ Domain path declared: `/languages/`
- ✅ `load_plugin_textdomain()` called
- ✅ All strings wrapped in translation functions
- ✅ Language directory created

### Uninstallation
- ✅ `uninstall.php` for cleanup
- ✅ Removes all options on uninstall
- ✅ Removes all custom post meta
- ✅ Clears scheduled events
- ✅ Multisite compatible

### Documentation
- ✅ Complete `readme.txt` in WordPress.org format
- ✅ Developer `README.md`
- ✅ `LICENSE.txt` with full GPL text
- ✅ `SECURITY.md` for vulnerability reporting
- ✅ `CONTRIBUTING.md` for contributors
- ✅ PHPDoc comments throughout code

### Code Quality
- ✅ WordPress coding standards followed
- ✅ PSR-4 autoloading
- ✅ Object-oriented architecture
- ✅ Separation of concerns
- ✅ No deprecated WordPress functions
- ✅ No PHP warnings or notices

## Files Added for Compliance

1. `LICENSE.txt` - Full GPL v2 license text
2. `readme.txt` - WordPress.org standard readme
3. `uninstall.php` - Proper cleanup on deletion
4. `SECURITY.md` - Security policy and reporting
5. `CONTRIBUTING.md` - Contribution guidelines
6. `COMPLIANCE.md` - This document
7. `.gitignore` - VCS ignore file
8. `languages/` - Translation directory

## Files Modified for Compliance

1. `imagegecko.php` - Added GPL license header
2. `README.md` - Added license and service documentation
3. `class-plugin.php` - Added text domain loading

## External Services Documentation

### ContentGecko API

**Service URL**: https://dev.api.contentgecko.io/product-image

**Purpose**: AI-powered image generation

**Data Sent**:
- Product images (base64 encoded)
- Product ID, SKU, categories
- Style prompt text

**Data Received**:
- Generated images (base64 or URL)

**Privacy**:
- No customer personal data transmitted
- No tracking or analytics
- Only product-related data sent

**User Control**:
- Requires explicit API key entry
- User must actively trigger generation
- Can delete generated images
- Can disable plugin at any time

## Submission Checklist

Before submitting to WordPress.org:

- [x] GPL license declared and included
- [x] readme.txt in proper format
- [x] All text domain strings use `imagegecko`
- [x] External service clearly documented
- [x] No tracking or analytics code
- [x] Uses WordPress default libraries
- [x] Security measures implemented
- [x] Uninstall script provided
- [x] Version numbers match
- [x] Code is human readable
- [x] Tested with WordPress 5.8+
- [x] Tested with WooCommerce 6.0+
- [x] Tested with PHP 7.4+
- [x] No PHP errors or warnings

## Contact

For compliance questions or concerns:
- Email: support@imagegecko.io
- Security: security@imagegecko.io

## Last Updated

This compliance document was last updated with version 0.1.2 on September 30, 2025.
