# Security Policy

## Supported Versions

We release security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 0.1.x   | :white_check_mark: |

## Reporting a Vulnerability

We take the security of ImageGecko AI Photos seriously. If you discover a security vulnerability, please follow these steps:

### How to Report

1. **Do not** open a public GitHub issue
2. Email us directly at: support@contentgecko.io
3. Include the following in your report:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your report within 48 hours
- **Assessment**: We will assess the vulnerability and determine its severity
- **Fix Timeline**: Critical vulnerabilities will be addressed within 7 days
- **Disclosure**: We will coordinate public disclosure with you after a fix is available

### Security Best Practices

When using this plugin:

1. **API Key Security**
   - Keep your ContentGecko API key confidential
   - Never commit API keys to version control
   - API keys are encrypted in the database using WordPress salts

2. **Access Control**
   - Only users with `manage_woocommerce` capability can access plugin settings
   - All AJAX requests are nonce-protected
   - All form submissions include CSRF protection

3. **Data Sanitization**
   - All user inputs are sanitized using WordPress functions
   - All outputs are escaped appropriately
   - Database queries use prepared statements

4. **External Service**
   - API communications use HTTPS only
   - No sensitive customer data is transmitted
   - API requests include proper authentication headers

### Security Measures Implemented

- ✅ Nonce verification on all AJAX and form submissions
- ✅ Capability checks before processing requests
- ✅ Input sanitization and output escaping
- ✅ Encrypted API key storage (AES-256-CBC)
- ✅ HTTPS-only API communications
- ✅ No eval() or dynamic code execution
- ✅ No SQL injection vulnerabilities (prepared statements)
- ✅ XSS protection (escaped outputs)
- ✅ CSRF protection (nonces)

## Responsible Disclosure

We follow responsible disclosure practices:

1. We will work with security researchers to verify and address reported vulnerabilities
2. We will provide credit to researchers who report valid vulnerabilities (unless anonymity is requested)
3. We will coordinate disclosure timing to ensure users have time to update

## Third-Party Dependencies

This plugin uses:
- WordPress core libraries (jQuery, jQuery UI Autocomplete)
- WooCommerce (required dependency)
- ContentGecko API (external service)

All dependencies are kept up-to-date and security advisories are monitored.

## Questions?

For non-security questions, please write us at support@contentgecko.io

Thank you for helping keep ImageGecko AI Photos secure!
