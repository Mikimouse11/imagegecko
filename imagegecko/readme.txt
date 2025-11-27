=== ImageGecko AI Product Images ===
Contributors: ristorehemagi
Tags: woocommerce, ai, images, product photos
Requires at least: 6.0
Tested up to: 6.8.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate professional on-model lifestyle imagery for WooCommerce products using AI-powered image generation.

== Description ==

ImageGecko AI Photos transforms your WooCommerce product images into professional lifestyle shots featuring models wearing or using your products. Simply connect your ContentGecko API key, select your target products, and let AI create stunning marketing imagery automatically.

= Key Features =

* **AI-Powered Image Generation**: Convert product photos into lifestyle imagery with models
* **Bulk Processing**: Generate images for multiple products simultaneously
* **WooCommerce Integration**: Seamlessly integrates with your WooCommerce product catalog
* **Flexible Targeting**: Choose specific products or categories for image generation
* **Custom Styling**: Define default style prompts to maintain brand consistency
* **Automatic Gallery Management**: Generated images are automatically added to product galleries
* **Secure API Key Storage**: API keys are encrypted using WordPress security standards

= How It Works =

1. Install and activate the plugin
2. Navigate to WooCommerce → ImageGecko AI Photos
3. Enter your ContentGecko API key
4. Configure your default style prompt and select target products/categories
5. Click GO to start generating lifestyle images

= External Service Usage =

This plugin requires an active ContentGecko API account and sends product images to the ContentGecko API service for AI processing. 

**Service Details:**
* Service: ContentGecko AI Image Generation API
* Endpoint: https://api.contentgecko.io/product-image
* Privacy: Product images and metadata are transmitted to the ContentGecko service for processing
* Terms: By using this plugin, you agree to ContentGecko's Terms of Service
* Learn more: [ContentGecko Terms of Service](https://contentgecko.io/terms-conditions/)

**Data Transmitted:**
* Product images (base64 encoded)
* Product ID, SKU, and category information
* Style prompt text

The plugin does not collect or transmit any customer data, personal information, or analytics. All image processing occurs on ContentGecko's servers.

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 6.0 or higher
* PHP 7.4 or higher
* ContentGecko API account and key

== Installation ==

1. Upload the `imagegecko` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce → ImageGecko AI Photos
4. Enter your ContentGecko API key (received from ContentGecko)
5. Configure your settings and start generating images

== Frequently Asked Questions ==

= Do I need a ContentGecko API account? =

Yes, this plugin requires an active ContentGecko API account. The API key connects your WordPress site to the ContentGecko image generation service.

= What happens to my original product images? =

Your original images are preserved. Generated images are added to the product gallery, and you can optionally set them as featured images. The plugin tracks which images are AI-generated so it always uses original images as source material.

= Can I delete generated images? =

Yes, the plugin includes a delete function for generated images. If you delete a generated featured image, the plugin will automatically restore the original image.

= How many images can I generate? =

The number of images you can generate depends on your ContentGecko API plan and available credits.

= Is my API key secure? =

Yes, API keys are encrypted using AES-256-CBC encryption with WordPress security salts when OpenSSL is available.

= Does this work with variable products? =

Yes, the plugin works with all WooCommerce product types.

== Screenshots ==

1. Settings page - Configure API key and generation options
2. Product selection - Choose categories or individual products
3. Generation progress - Real-time tracking of image generation
4. Before/after comparison - View source and generated images side-by-side

== Changelog ==

= 1.0.0 =
* Initial release

= 0.1.2 =
* Added GPL license compliance
* Added proper readme.txt for WordPress.org
* Improved external service documentation
* Enhanced security with encrypted API key storage

= 0.1.1 =
* Fixed image handling for AI-generated vs original images
* Added delete functionality for generated images
* Improved gallery management

= 0.1.0 =
* Initial release
* Bulk image generation for WooCommerce products
* Category and product targeting
* Automatic gallery and featured image management

== Upgrade Notice ==

= 0.1.2 =
This version adds WordPress.org compliance requirements including GPL licensing and improved service documentation.

== Privacy Policy ==

ImageGecko AI Photos does not collect any personal data from your site visitors. The plugin only transmits product images and metadata to the ContentGecko API service for processing. No customer data, analytics, or tracking information is collected or transmitted.

When you use this plugin:
* Product images are sent to ContentGecko servers for AI processing
* Product metadata (ID, SKU, categories) is included in API requests
* API keys are stored encrypted in your WordPress database
* No cookies or tracking scripts are added to your site

For more information about how ContentGecko processes your data, please review their privacy policy at https://contentgecko.io/privacy-policy/

== Support ==

For support questions, please write us at support@contentgecko.io
