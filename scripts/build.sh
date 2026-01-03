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
cp -r "$ROOT_DIR/backend/public/". "$BUILD_DIR/public/"
cp "$ROOT_DIR/backend/composer.json" "$BUILD_DIR/"
cp "$ROOT_DIR/backend/composer.lock" "$BUILD_DIR/"

# Copy frontend assets to public directory
cp "$ROOT_DIR/frontend/dist/pop.min.js" "$BUILD_DIR/public/"
cp "$ROOT_DIR/frontend/dist/pop.min.css" "$BUILD_DIR/public/"

# Copy dist files (demo.html, analytics.html)
cp "$ROOT_DIR/dist/"*.html "$BUILD_DIR/public/"

# Install production dependencies in build directory
echo "Installing production dependencies..."
cd "$BUILD_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-scripts --optimize-autoloader --quiet

# Remove composer files (not needed at runtime)
rm -f composer.json composer.lock

# Copy .env.example
cp "$ROOT_DIR/dist/.env.example" "$BUILD_DIR/.env"

# Warm up Symfony cache
echo "Warming up cache..."
APP_ENV=prod php bin/console cache:warmup --no-debug 2>/dev/null || true

echo ""
echo "Build complete: $BUILD_DIR/"
echo ""
echo "Contents:"
ls -la "$BUILD_DIR/"
echo ""
echo "Public:"
ls -la "$BUILD_DIR/public/"
