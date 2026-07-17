#!/usr/bin/env bash
#
# deploy.sh — Build and package the SimpleLMS Bridge plugin into an installable zip.
#
# Steps:
#   1. Install dependencies deterministically (npm ci + composer install --no-dev).
#   2. Compile the React admin bundle and Tailwind CSS (npm run build).
#   3. Verify the enqueued build artifacts exist; fail loudly if any are missing.
#   4. Stage the plugin into a clean directory, excluding dev/source files.
#   5. Zip the staged plugin (including the bundled certificate vendor tree).
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

echo "==> Installing PHP runtime dependencies (composer install --no-dev)"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

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

if command -v rsync >/dev/null 2>&1; then
    echo "==> Staging core plugin via rsync"
    rsync -a \
        --exclude='.git' \
        --exclude='.git*' \
        --exclude='.github' \
        --exclude='node_modules' \
        --exclude='src' \
        --exclude='dist' \
        --exclude='simple-lms-migrator' \
        --exclude='*.md' \
        --exclude='*.zip' \
        --exclude='*.log' \
        --exclude='deploy.sh' \
        --exclude='package-lock.json' \
        --exclude='phpcs.xml*' \
        --exclude='phpstan.neon*' \
        ./ "$STAGE_DIR/"
else
    echo "==> rsync not found; staging core plugin via tar fallback"
    tar --exclude='.git' \
        --exclude='.git*' \
        --exclude='.github' \
        --exclude='node_modules' \
        --exclude='src' \
        --exclude='dist' \
        --exclude='simple-lms-migrator' \
        --exclude='*.md' \
        --exclude='*.zip' \
        --exclude='*.log' \
        --exclude='deploy.sh' \
        --exclude='package-lock.json' \
        --exclude='phpcs.xml*' \
        --exclude='phpstan.neon*' \
        -cf - . | (cd "$STAGE_DIR" && tar -xf -)
fi

if [[ -d "${STAGE_DIR}/vendor" ]]; then
    echo "==> Pruning vendor cruft from stage"
    find "${STAGE_DIR}/vendor" -type d \( -name .git -o -name tests -o -name Tests -o -name docs -o -name examples -o -name .github \) -prune -exec rm -rf {} +
fi

echo "==> Creating zip ${ZIP_PATH}"
( cd "$OUTPUT_DIR" && zip -r -q "${SLUG}.zip" "$SLUG" )
rm -rf "$STAGE_DIR"

ZIP_SIZE=$(wc -c < "${ZIP_PATH}")
MAX_SIZE=$((10 * 1024 * 1024)) # 10MB
if [[ "$ZIP_SIZE" -gt "$MAX_SIZE" ]]; then
    echo "ERROR: Final zip ${ZIP_PATH} exceeds 10MB limit (size: $((ZIP_SIZE / 1024 / 1024))MB). Aborting." >&2
    exit 1
else
    echo "==> Size guard passed: ${ZIP_PATH} is $((ZIP_SIZE / 1024))KB"
fi

MIGRATOR_SLUG="simple-lms-migrator"
MIGRATOR_STAGE_DIR="${OUTPUT_DIR}/${MIGRATOR_SLUG}"
MIGRATOR_ZIP_PATH="${OUTPUT_DIR}/${MIGRATOR_SLUG}.zip"

if [[ -d "simple-lms-migrator" ]]; then
    echo "==> Building simple-lms-migrator assets"
    ( cd simple-lms-migrator && npm ci && npm run build )

    echo "==> Staging simple-lms-migrator"
    mkdir -p "$MIGRATOR_STAGE_DIR"

    if command -v rsync >/dev/null 2>&1; then
        rsync -a \
            --exclude='.git' \
            --exclude='.git*' \
            --exclude='node_modules' \
            --exclude='src' \
            --exclude='package-lock.json' \
            ./simple-lms-migrator/ "$MIGRATOR_STAGE_DIR/"
    else
        tar --exclude='.git' \
            --exclude='.git*' \
            --exclude='node_modules' \
            --exclude='src' \
            --exclude='package-lock.json' \
            -cf - -C ./simple-lms-migrator . | (cd "$MIGRATOR_STAGE_DIR" && tar -xf -)
    fi

    echo "==> Creating simple-lms-migrator zip"
    ( cd "$OUTPUT_DIR" && zip -r -q "${MIGRATOR_SLUG}.zip" "$MIGRATOR_SLUG" )
    rm -rf "$MIGRATOR_STAGE_DIR"
    echo "==> Done: ${MIGRATOR_ZIP_PATH}"
fi

echo "==> All plugins packaged successfully"
