#!/bin/bash
set -euo pipefail

echo "--- Setting up Flutter SDK ---"
FLUTTER_DIR="${FLUTTER_DIR:-$(pwd)/.flutter}"
if [ ! -d "$FLUTTER_DIR" ]; then
  echo "Cloning Flutter SDK..."
  git clone https://github.com/flutter/flutter.git -b stable --depth 1 "$FLUTTER_DIR"
else
  echo "Flutter SDK already exists. Updating..."
  cd "$FLUTTER_DIR"
  git pull
  cd -
fi

# Add Flutter to PATH
export PATH="$FLUTTER_DIR/bin:$PATH"
export PATH="$FLUTTER_DIR/bin/cache/dart-sdk/bin:$PATH"

# Verify Flutter is available
flutter --version

echo "--- Configuring project for web ---"
flutter create . --platforms web

echo "--- Installing Flutter dependencies ---"
flutter pub get

echo "--- Building Flutter web app ---"
# Use Railway's API_BASE_URL environment variable, with fallback
API_BASE_URL_VAR=${API_BASE_URL:-"https://web-production-f8792.up.railway.app/admin"}
echo "Building with API_BASE_URL: $API_BASE_URL_VAR"

flutter build web --release --dart-define=API_BASE_URL="$API_BASE_URL_VAR"

echo "--- Flutter web build completed ---"
echo "Build output: $(pwd)/build/web"
HARDCODED_URL_CHECK
