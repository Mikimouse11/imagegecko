# Contributing to ImageGecko AI Photos

Thank you for your interest in contributing to ImageGecko AI Photos! This document provides guidelines for contributing to the project.

## Code of Conduct

Please be respectful and constructive in all interactions. We aim to maintain a welcoming environment for all contributors.

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in our issue tracker
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - WordPress version, WooCommerce version, PHP version
   - Any error messages or logs

### Suggesting Features

1. Check if the feature has already been suggested
2. Create a new issue describing:
   - The problem you're trying to solve
   - Your proposed solution
   - Any alternatives you've considered
   - How this benefits other users

### Pull Requests

1. Fork the repository
2. Create a new branch for your feature/fix
3. Write clear, commented code
4. Follow WordPress coding standards
5. Test your changes thoroughly
6. Submit a pull request with a clear description

## Development Guidelines

### Coding Standards

We follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

- Use tabs for indentation
- Use single quotes for strings
- Add proper PHPDoc comments
- Escape all output
- Sanitize all input
- Use WordPress functions when available

### Testing

Before submitting code:

1. Test on a clean WordPress installation
2. Test with WooCommerce active
3. Test with PHP 7.4+ and 8.0+
4. Verify no PHP errors or warnings
5. Test in major browsers (Chrome, Firefox, Safari, Edge)

### Security

- Never commit API keys or sensitive data
- Use nonces for all AJAX requests
- Verify user capabilities
- Sanitize inputs and escape outputs
- Use prepared statements for database queries

### File Structure

```
imagegecko/
├── assets/
│   ├── css/          # Stylesheets
│   └── js/           # JavaScript files
├── includes/
│   └── class-*.php   # PHP classes
├── languages/        # Translation files
├── imagegecko.php    # Main plugin file
├── uninstall.php     # Uninstall cleanup
└── readme.txt        # WordPress.org readme
```

### Naming Conventions

- Classes: `class-name-with-hyphens.php`
- Functions: `imagegecko_function_name()`
- Hooks: `imagegecko/hook_name` or `imagegecko_hook_name`
- CSS classes: `.imagegecko-class-name`
- JavaScript: `camelCase` for functions, `PascalCase` for classes

## WordPress.org Guidelines

This plugin complies with [WordPress.org Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/):

- GPL v2 or later license
- Human-readable code (no obfuscation)
- Uses WordPress default libraries
- Properly escapes output and sanitizes input
- Documents external service usage
- No tracking without user consent
- Respects trademarks and copyrights

## Git Workflow

1. Create a feature branch: `git checkout -b feature/your-feature-name`
2. Commit changes: `git commit -m "Add feature description"`
3. Push to your fork: `git push origin feature/your-feature-name`
4. Submit a pull request to `main` branch

### Commit Messages

- Use present tense ("Add feature" not "Added feature")
- Use imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit first line to 72 characters
- Reference issues and pull requests when relevant

## Translation

We welcome translations! The plugin uses the `imagegecko` text domain.

1. Translation files go in `/languages/` directory
2. Use the provided `.pot` file as a template
3. Submit `.po` and `.mo` files via pull request

## Documentation

- Update README.md for developer documentation
- Update readme.txt for user-facing documentation
- Add PHPDoc comments to all functions and classes
- Include inline comments for complex logic

## Support

- For development questions: Create an issue on GitHub
- For usage questions: see https://contentgecko.io/woocommerce-product-image-generator/
- For security issues: Email support@contentgecko.io

## License

By contributing, you agree that your contributions will be licensed under the GPL v2 or later license.

## Questions?

If you have questions about contributing, feel free to create an issue asking for clarification.

Thank you for contributing to ImageGecko AI Photos!
