#!/bin/bash
# Test script for ContentGecko API endpoint using curl
# Tests the POST https://dev.api.contentgecko.io/product-image endpoint

API_ENDPOINT="https://dev.api.contentgecko.io/product-image"
IMAGE_PATH="/Users/ristorehemagi/Local Documents/ImageGecko/prillipilt.jpg"

echo "Testing ContentGecko API endpoint: $API_ENDPOINT"
echo "Using image: $IMAGE_PATH"

# Check if image exists
if [ ! -f "$IMAGE_PATH" ]; then
    echo "Error: Image file not found at $IMAGE_PATH"
    exit 1
fi

# Encode image to base64
echo "Encoding image to base64..."
IMAGE_BASE64=$(base64 -i "$IMAGE_PATH")
IMAGE_NAME=$(basename "$IMAGE_PATH")

echo "Image encoded successfully:"
echo "  - File name: $IMAGE_NAME"
echo "  - Base64 length: ${#IMAGE_BASE64} characters"

# Create JSON payload
JSON_PAYLOAD=$(cat << EOF
{
  "product_id": 123,
  "prompt": "Studio lit model photo with professional lighting and clean background",
  "image": {
    "base64": "$IMAGE_BASE64",
    "mime_type": "image/jpeg",
    "file_name": "$IMAGE_NAME"
  },
  "metadata": {
    "source_image_id": 456,
    "categories": [1, 2],
    "product_sku": "TEST-SKU-123"
  }
}
EOF
)

echo ""
echo "Sending POST request to $API_ENDPOINT"
echo "Payload structure:"
echo "  - product_id: 123"
echo "  - prompt: Studio lit model photo with professional lighting and clean background"
echo "  - image.file_name: $IMAGE_NAME"
echo "  - image.mime_type: image/jpeg"
echo "  - image.base64: ${#IMAGE_BASE64} chars"
echo "  - metadata: {source_image_id: 456, categories: [1,2], product_sku: TEST-SKU-123}"

echo ""
echo "Making API request..."

# Make the API request with curl
curl -X POST "$API_ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "User-Agent: ImageGecko-Plugin-Test/1.0" \
  -d "$JSON_PAYLOAD" \
  -w "\n\nHTTP Status: %{http_code}\nTotal time: %{time_total}s\n" \
  -v

echo ""
echo "API test completed."
