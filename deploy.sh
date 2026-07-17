#!/usr/bin/env bash
#
# deploy.sh — Build and package the SimpleLMS Bridge plugin into an installable zip.
#
# Steps:
#   1. Install dependencies deterministically (npm ci).
#   2. Compile the React admin bundle and Tailwind CSS (npm run build).
#   3. Verify the enqueued build artifacts exist; fail loudly if any are missing.
#   4. Stage the plugin into a clean directory, excluding dev/source files.
#   5. Zip the staged plugin.
#
# Usage: ./deploy.sh [output-dir]
#   output-dir defaults to ./dist
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

SLUG="simple-lms-bridge"
OUTPUT_DIR="${1:-dist}"
STAGE_DIR="${OUTPUT_DIR}/${SLUG}"
ZIP_PATH="${OUTPUT_DIR}/${SLUG}.zip"

# Required build artifacts that the plugin enqueues at runtime.
REQUIRED_ARTIFACTS=(
    "build/admin/index.js"
    "build/admin/index.asset.php"
    "build/admin/index.css"
    "build/admin/tailwind.css"
)

echo "==> Installing dependencies (npm ci)"
npm ci

echo "==> Building assets (npm run build)"
npm run build

echo "==> Verifying build artifacts"
missing=0
for artifact in "${REQUIRED_ARTIFACTS[@]}"; do
    if [[ ! -f "$artifact" ]]; then
        echo "    MISSING: $artifact"
        missing=1
    else
        echo "    ok: $artifact"
    fi
done
if [[ "$missing" -ne 0 ]]; then
    echo "ERROR: one or more required build artifacts are missing. Aborting package." >&2
    exit 1
fi

echo "==> Staging plugin into ${STAGE_DIR}"
rm -rf "$OUTPUT_DIR"
mkdir -p "$STAGE_DIR"

# Copy the plugin, excluding development/source and VCS files.
rsync -a \
    --exclude='.git' \
    --exclude='.git*' \
    --exclude='.github' \
    --exclude='node_modules' \
    --exclude='src' \
    --exclude='dist' \
    --exclude='*.md' \
    --exclude='*.zip' \
    --exclude='*.log' \
    --exclude='deploy.sh' \
    --exclude='package-lock.json' \
    --exclude='phpcs.xml*' \
    --exclude='phpstan.neon*' \
    ./ "$STAGE_DIR/"

echo "==> Creating zip ${ZIP_PATH}"
( cd "$OUTPUT_DIR" && zip -r -q "${SLUG}.zip" "$SLUG" )

echo "==> Done: ${ZIP_PATH}"
