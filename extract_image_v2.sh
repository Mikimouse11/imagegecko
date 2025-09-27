#!/bin/bash
# Script to extract and save the generated image from API response
# Usage: ./extract_image_v2.sh

API_ENDPOINT="https://dev.api.contentgecko.io/product-image"
IMAGE_PATH="/Users/ristorehemagi/Local Documents/ImageGecko/prillipilt.jpg"
API_KEY="sk_3f8a9c72e1b54d0ab6c27d8f49bce5a1"

echo "Making API request and extracting image..."

# Check if image exists
if [ ! -f "$IMAGE_PATH" ]; then
    echo "Error: Image file not found at $IMAGE_PATH"
    exit 1
fi

# Encode image to base64
IMAGE_BASE64=$(base64 -i "$IMAGE_PATH")
IMAGE_NAME=$(basename "$IMAGE_PATH")

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

# Make the API request and save response to file
echo "Making API request..."
RESPONSE_FILE="api_response.json"
curl -s -X POST "$API_ENDPOINT" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $API_KEY" \
  -H "User-Agent: ImageGecko-Plugin-Test/1.0" \
  -d "$JSON_PAYLOAD" > "$RESPONSE_FILE"

echo "Response received. Processing..."

# Check if response file was created and has content
if [ ! -f "$RESPONSE_FILE" ] || [ ! -s "$RESPONSE_FILE" ]; then
    echo "❌ No response received or empty response"
    exit 1
fi

# Extract the base64 data using Python
python3 << 'PYTHON_SCRIPT'
import json
import base64
import sys
import os
import datetime

try:
    # Read the response from file
    with open('api_response.json', 'r') as f:
        response_content = f.read()
    
    print(f"Response size: {len(response_content)} characters")
    
    # Parse JSON response
    data = json.loads(response_content)
    
    # Check if image data exists (try both possible structures)
    base64_data = None
    mime_type = 'image/png'  # default
    
    if 'imageBase64' in data:
        base64_data = data['imageBase64']
        mime_type = data.get('mimeType', 'image/png')
        print("Found imageBase64 field in response")
    elif 'image' in data and 'base64' in data['image']:
        base64_data = data['image']['base64']
        mime_type = data['image'].get('mimeType', 'image/png')
        print("Found nested image.base64 field in response")
    else:
        print("Available keys in response:", list(data.keys()))
    
    if base64_data:
        # Determine file extension from mime_type or default to png
        if 'jpeg' in mime_type or 'jpg' in mime_type:
            ext = 'jpg'
        elif 'png' in mime_type:
            ext = 'png'
        else:
            ext = 'png'  # default
        
        # Generate output filename with timestamp
        timestamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
        output_file = f"generated_image_{timestamp}.{ext}"
        
        # Decode and save the image
        image_data = base64.b64decode(base64_data)
        with open(output_file, 'wb') as f:
            f.write(image_data)
        
        print(f"✅ Image saved as: {output_file}")
        print(f"📁 File size: {len(image_data)} bytes")
        print(f"🖼️  MIME type: {mime_type}")
        print(f"📏 Base64 length: {len(base64_data)} characters")
        
    else:
        print("❌ No image data found in response")
        print("Response structure (first 1000 chars):")
        print(response_content[:1000])
        
except json.JSONDecodeError as e:
    print(f"❌ Error parsing JSON: {e}")
    print("Raw response (first 1000 chars):")
    with open('api_response.json', 'r') as f:
        print(f.read()[:1000])
except Exception as e:
    print(f"❌ Error processing image: {e}")

PYTHON_SCRIPT

# Clean up response file
rm -f "$RESPONSE_FILE"

echo ""
echo "Script completed."
