# ImageGecko AI Photos Plugin

Generate on-model lifestyle imagery for WooCommerce products by sending source photos to the ContentGecko mediator endpoint.

## Features
- Securely store ContentGecko API key in WordPress options (encrypted when OpenSSL is available).
- Configure default style prompt plus targeted categories/products from the settings page under WooCommerce → ImageGecko AI Photos.
- Bulk or single-product actions to enqueue image generation jobs.
- Asynchronous processing via Action Scheduler when available, with WP-Cron fallback.
- Automatically sideload returned media into the Media Library, set as the featured image, and append to the product gallery.
- Detailed logging through WooCommerce logs (fallbacks to `error_log`).

## Setup
1. Copy the `imagegecko` directory into `wp-content/plugins/`.
2. Activate **ImageGecko AI Photos** inside your WordPress admin.
3. Visit **WooCommerce → ImageGecko AI Photos**:
   - Paste the API key provided in your ContentGecko dashboard.
   - Enter a default style prompt describing the photoshoot aesthetic you expect.
   - Optionally pick specific product categories or individual products to limit generation.
4. From the Products list:
   - Use the bulk action **Generate AI Photos (ImageGecko)** to enqueue multiple products, or
   - Use the row action **Generate AI Photo** for a single product.

## Mediator API Contract
- Endpoint: `POST https://dev.api.contentgecko.io/product-image`
- Payload (JSON):
  ```json
  {
    "product_id": 123,
    "prompt": "Studio lit model photo...",
    "image": {
      "base64": "...",
      "mime_type": "image/jpeg",
      "file_name": "original.jpg"
    },
    "metadata": {
      "source_image_id": 456,
      "categories": [1,2],
      "product_sku": "SKU-123"
    }
  }
  ```
- Expected response (example):
  ```json
  {
    "image_url": "https://cdn.contentgecko.io/.../generated.jpg",
    "image_base64": null,
    "file_name": "generated.jpg",
    "mime_type": "image/jpeg",
    "prompt": "Studio lit model photo..."
  }
  ```

The plugin will consume either `image_base64` or `image_url` (preferring base64 when both are present).

## Status Metadata
- `_imagegecko_status`: `queued`, `processing`, `completed`, or `failed`.
- `_imagegecko_status_message`: Additional context when failures occur.
- `_imagegecko_generated_attachment`: Attachment ID of the saved AI image.

## Extensibility
- `imagegecko_generation_prompt`: Filter to override the prompt prior to dispatching a job.
- `imagegecko_set_featured_image`: Filter to control whether the generated attachment becomes the featured image.

## Development Notes
- Logging is bridged through `wc_get_logger` when WooCommerce is active.
- Action Scheduler is optional; WP-Cron is used otherwise.
- API keys are encrypted using AES-256-CBC with WordPress salts when `openssl` is available.
