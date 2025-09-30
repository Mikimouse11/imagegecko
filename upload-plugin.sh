#!/usr/bin/env bash
set -euo pipefail

ASSET_BUCKET="contentgecko-wp-plugin"
REGION="eu-central-1"
PLUGIN_DIR="imagegecko"
ZIP_FILE="${PLUGIN_DIR}.zip"

if [ ! -d "$PLUGIN_DIR" ]; then
    echo "Plugin directory '$PLUGIN_DIR' not found. Aborting." >&2
    exit 1
fi

echo "Packaging plugin directory '$PLUGIN_DIR' into $ZIP_FILE"
rm -f "$ZIP_FILE"

ZIP_EXCLUDES=(
    -x "*.DS_Store"
    -x "*/.git/*"
    -x "*/.git"
    -x "*.gitignore"
    -x "*.gitmodules"
)

zip -r "$ZIP_FILE" "$PLUGIN_DIR" "${ZIP_EXCLUDES[@]}" > /dev/null

echo "Uploading $ZIP_FILE to s3://$ASSET_BUCKET/"
aws s3 cp "$ZIP_FILE" "s3://$ASSET_BUCKET/$ZIP_FILE" --region "$REGION"

echo "Upload complete."
