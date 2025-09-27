#!/usr/bin/env python3
"""
Test script for ContentGecko API endpoint
Tests the POST https://dev.api.contentgecko.io/product-image endpoint
"""

import base64
import json
import requests
import mimetypes
import os

def encode_image_to_base64(image_path):
    """Convert image file to base64 string"""
    with open(image_path, 'rb') as image_file:
        encoded_string = base64.b64encode(image_file.read()).decode('utf-8')
    return encoded_string

def get_mime_type(image_path):
    """Get MIME type of the image file"""
    mime_type, _ = mimetypes.guess_type(image_path)
    return mime_type or 'image/jpeg'

def test_contentgecko_api():
    """Test the ContentGecko API endpoint"""
    
    # Configuration
    api_endpoint = "https://dev.api.contentgecko.io/product-image"
    image_path = "/Users/ristorehemagi/Local Documents/ImageGecko/prillipilt.jpg"
    
    # Check if image exists
    if not os.path.exists(image_path):
        print(f"Error: Image file not found at {image_path}")
        return
    
    print(f"Testing ContentGecko API endpoint: {api_endpoint}")
    print(f"Using image: {image_path}")
    
    # Encode image to base64
    try:
        image_base64 = encode_image_to_base64(image_path)
        mime_type = get_mime_type(image_path)
        file_name = os.path.basename(image_path)
        
        print(f"Image encoded successfully:")
        print(f"  - File name: {file_name}")
        print(f"  - MIME type: {mime_type}")
        print(f"  - Base64 length: {len(image_base64)} characters")
        
    except Exception as e:
        print(f"Error encoding image: {e}")
        return
    
    # Prepare API payload according to the contract from README
    payload = {
        "product_id": 123,
        "prompt": "Studio lit model photo with professional lighting and clean background",
        "image": {
            "base64": image_base64,
            "mime_type": mime_type,
            "file_name": file_name
        },
        "metadata": {
            "source_image_id": 456,
            "categories": [1, 2],
            "product_sku": "TEST-SKU-123"
        }
    }
    
    # Headers
    headers = {
        "Content-Type": "application/json",
        "User-Agent": "ImageGecko-Plugin-Test/1.0"
    }
    
    print(f"\nSending POST request to {api_endpoint}")
    print(f"Payload structure:")
    print(f"  - product_id: {payload['product_id']}")
    print(f"  - prompt: {payload['prompt']}")
    print(f"  - image.file_name: {payload['image']['file_name']}")
    print(f"  - image.mime_type: {payload['image']['mime_type']}")
    print(f"  - image.base64: {len(payload['image']['base64'])} chars")
    print(f"  - metadata: {payload['metadata']}")
    
    try:
        # Make the API request
        response = requests.post(
            api_endpoint,
            json=payload,
            headers=headers,
            timeout=30
        )
        
        print(f"\nResponse received:")
        print(f"  - Status code: {response.status_code}")
        print(f"  - Headers: {dict(response.headers)}")
        
        # Try to parse JSON response
        try:
            response_data = response.json()
            print(f"  - Response JSON:")
            print(json.dumps(response_data, indent=2))
        except json.JSONDecodeError:
            print(f"  - Response text: {response.text}")
        
        if response.status_code == 200:
            print("\n✅ API call successful!")
        else:
            print(f"\n❌ API call failed with status {response.status_code}")
            
    except requests.exceptions.RequestException as e:
        print(f"\n❌ Request failed: {e}")
    except Exception as e:
        print(f"\n❌ Unexpected error: {e}")

if __name__ == "__main__":
    test_contentgecko_api()
