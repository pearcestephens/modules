#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "🔨 Building JS bundles..."
php "$SCRIPT_DIR/build_js_bundles.php" || {
    echo "❌ Bundle build failed"
    exit 1
}

echo "📏 Checking bundle sizes..."
php "$SCRIPT_DIR/size_guard.php" || {
    echo "❌ Size guard failed"
    exit 1
}

echo "✅ Build complete!"
