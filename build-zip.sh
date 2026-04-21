#!/usr/bin/env bash
# Build dist/mangazscans.zip ready to upload via WP Admin > Themes > Add New > Upload
set -euo pipefail
cd "$(dirname "$0")"

OUT=dist/mangazscans.zip
mkdir -p dist
rm -f "$OUT"

# WordPress wants the unpacked theme folder to live at the top of the zip,
# named exactly the same as the folder slug (mangazscans).
zip -rq "$OUT" mangazscans \
    -x 'mangazscans/.git/*' \
       'mangazscans/**/.DS_Store' \
       'mangazscans/**/.gitkeep' \
       'mangazscans/**/__MACOSX/*'

echo
echo "  built $OUT  ($(du -h "$OUT" | cut -f1))"
echo
unzip -l "$OUT" | tail -3
