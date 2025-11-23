#!/bin/bash
set -euo pipefail

# Download Flutter SDK into repo cache if not already present
FLUTTER_DIR="${FLUTTER_DIR:-$(pwd)/.flutter}"
if [ ! -d "$FLUTTER_DIR" ]; then
  git clone https://github.com/flutter/flutter.git -b stable --depth 1 "$FLUTTER_DIR"
fi

# Ensure flutter bin is in PATH for this script
export PATH="$FLUTTER_DIR/bin:$PATH"

# Install dependencies and build
cd smart_timetable_application
flutter pub get
flutter build web --release --dart-define=API_BASE_URL=${API_BASE_URL:-https://web-production-f8792.up.railway.app/admin}

echo "Flutter web build finished: $(pwd)/build/web"


