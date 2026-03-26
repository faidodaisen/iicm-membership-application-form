#!/usr/bin/env bash
# Usage: bash release.sh 1.0.1 "Short description of changes"
# Example: bash release.sh 1.0.2 "Fix export bug, add supporting document field"

set -e

VERSION="$1"
NOTES="$2"
PLUGIN_FILE="iicm-membership/iicm-membership.php"
ZIP_FILE="iicm-membership.zip"

# ── Validate args ─────────────────────────────────────────────────────────────
if [ -z "$VERSION" ]; then
    echo "ERROR: Version number required."
    echo "Usage: bash release.sh 1.0.1 \"Description of changes\""
    exit 1
fi

if [ -z "$NOTES" ]; then
    NOTES="Release v${VERSION}"
fi

echo ""
echo "========================================"
echo " Releasing IICM Membership v${VERSION}"
echo "========================================"
echo ""

# ── Step 1: Bump version in plugin file ──────────────────────────────────────
echo "[1/5] Updating version to ${VERSION}..."

# Update plugin header: * Version: x.x.x
sed -i "s/^\( \* Version:\s*\).*/\1${VERSION}/" "$PLUGIN_FILE"

# Update version constant
sed -i "s/define( 'IICM_MEMBERSHIP_VERSION',\s*'[^']*' )/define( 'IICM_MEMBERSHIP_VERSION', '${VERSION}' )/" "$PLUGIN_FILE"

# Verify both were updated
if ! grep -q "Version:      ${VERSION}" "$PLUGIN_FILE"; then
    echo "ERROR: Failed to update plugin header version."
    exit 1
fi
if ! grep -q "'${VERSION}'" "$PLUGIN_FILE"; then
    echo "ERROR: Failed to update version constant."
    exit 1
fi
echo "    Done."

# ── Step 2: Git commit & push ─────────────────────────────────────────────────
echo "[2/5] Committing changes..."
git add .
git commit -m "release v${VERSION} — ${NOTES}"
echo "    Done."

echo "[3/5] Pushing to GitHub..."
git push
echo "    Done."

# ── Step 3: Create zip ────────────────────────────────────────────────────────
echo "[4/5] Creating plugin zip..."
rm -f "$ZIP_FILE"
powershell -Command "Compress-Archive -Path 'iicm-membership' -DestinationPath '${ZIP_FILE}' -Force"
echo "    Done. (${ZIP_FILE})"

# ── Step 4: Create GitHub release ────────────────────────────────────────────
echo "[5/5] Creating GitHub release v${VERSION}..."
gh release create "v${VERSION}" "$ZIP_FILE" \
    --title "v${VERSION}" \
    --notes "${NOTES}"
echo "    Done."

# ── Cleanup ───────────────────────────────────────────────────────────────────
rm -f "$ZIP_FILE"

echo ""
echo "========================================"
echo " Released! https://github.com/faidodaisen/iicm-membership-application-form/releases/tag/v${VERSION}"
echo "========================================"
echo ""
echo " WordPress sites will show the update automatically."
echo ""
