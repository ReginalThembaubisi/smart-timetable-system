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
# Use Railway's environment variables
API_BASE_URL_VAR=${API_BASE_URL:-"https://web-production-f8792.up.railway.app"}
GEMINI_API_KEY_VAR=${GEMINI_API_KEY:-""}
echo "Building with API_BASE_URL: $API_BASE_URL_VAR"

if [ -z "$GEMINI_API_KEY_VAR" ]; then
  echo "Warning: GEMINI_API_KEY environment variable is not set!"
fi

flutter build web --release \
  --dart-define=API_BASE_URL="$API_BASE_URL_VAR" \
  --dart-define=GEMINI_API_KEY="$GEMINI_API_KEY_VAR"

echo "--- Flutter web build completed ---"
echo "Build output: $(pwd)/build/web"
