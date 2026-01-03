#!/bin/bash

# Pop - Emoji Reaction Widget
# @author Sven Eisenschmidt
# @license MIT
#
# Build Script - Creates a deployable build in the build/ directory

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$ROOT_DIR/build"

echo "Building Pop..."

# Clean build directory
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/public" "$BUILD_DIR/var"

# Build frontend
echo "Building frontend..."
cd "$ROOT_DIR/frontend"
npm run build

# Copy backend source
echo "Copying backend..."
cp -r "$ROOT_DIR/backend/src" "$BUILD_DIR/"
cp -r "$ROOT_DIR/backend/config" "$BUILD_DIR/"
cp -r "$ROOT_DIR/backend/bin" "$BUILD_DIR/"
cp -r "$ROOT_DIR/backend/public/"* "$BUILD_DIR/public/"
cp "$ROOT_DIR/backend/composer.json" "$BUILD_DIR/"
cp "$ROOT_DIR/backend/composer.lock" "$BUILD_DIR/"

# Copy frontend assets to public directory
cp "$ROOT_DIR/frontend/dist/pop.min.js" "$BUILD_DIR/public/"
cp "$ROOT_DIR/frontend/dist/pop.min.css" "$BUILD_DIR/public/"

# Install production dependencies in build directory
echo "Installing production dependencies..."
cd "$BUILD_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --optimize-autoloader --quiet

# Remove composer files (not needed at runtime)
rm -f composer.json composer.lock

# Create .env
cat > "$BUILD_DIR/.env" << 'EOF'
APP_ENV=prod
APP_SECRET=CHANGE_ME_TO_RANDOM_STRING
POP_ALLOWED_DOMAINS=https://your-blog.com
POP_DATABASE_PATH=var/data.db
EOF

# Warm up Symfony cache
echo "Warming up cache..."
APP_ENV=prod php bin/console cache:warmup --no-debug 2>/dev/null || true

# Create demo index.html
cat > "$BUILD_DIR/public/demo.html" << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pop Demo</title>
  <link rel="stylesheet" href="pop.min.css">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      max-width: 600px;
      margin: 2rem auto;
      padding: 1rem;
    }
    h1 { margin-bottom: 0.5rem; }
    p { color: #666; margin-bottom: 2rem; }
  </style>
</head>
<body>
  <h1>Pop! Demo</h1>
  <p>Click an emoji to react:</p>
  <div id="pop"></div>

  <script src="pop.min.js"></script>
  <script>
    Pop.init({
      el: '#pop',
      endpoint: '/api',
      pageId: 'demo',
      emojis: ['👋', '🔥', '💡', '❤️']
    });
  </script>
</body>
</html>
EOF

echo ""
echo "Build complete: $BUILD_DIR/"
echo ""
echo "Contents:"
ls -la "$BUILD_DIR/"
echo ""
echo "Public:"
ls -la "$BUILD_DIR/public/"
