#!/usr/bin/env bash
set -euo pipefail

PLUGIN_NAME="bunnyvideo"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"
DIST_DIR="$PLUGIN_DIR/dist"
ZIP_PATH="${1:-$DIST_DIR/${PLUGIN_NAME}.zip}"

mkdir -p "$(dirname "$ZIP_PATH")"
rm -f "$ZIP_PATH"

cd "$PARENT_DIR"

zip -r "$ZIP_PATH" "$PLUGIN_NAME" \
    -x "$PLUGIN_NAME/.git/*" \
    -x "$PLUGIN_NAME/dist/*" \
    -x "$PLUGIN_NAME/.DS_Store" \
    -x "$PLUGIN_NAME/**/.DS_Store" \
    -x "$PLUGIN_NAME/__MACOSX/*" \
    -x "$PLUGIN_NAME/**/__MACOSX/*" \
    -x "$PLUGIN_NAME/.phpunit.result.cache" \
    -x "$PLUGIN_NAME/scripts/*" \
    -x "$PLUGIN_NAME/test_*.php" \
    -x "$PLUGIN_NAME/vendor/*" \
    -x "$PLUGIN_NAME/node_modules/*"

echo "Bundle created: $ZIP_PATH"
