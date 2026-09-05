#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
install -m 0644 "$project_dir/node_modules/chart.js/dist/chart.umd.min.js" "$project_dir/assets/vendor/chart.umd.min.js"
install -m 0644 "$project_dir/node_modules/@fontsource-variable/roboto/files/roboto-latin-wght-normal.woff2" "$project_dir/assets/fonts/roboto-latin-wght-normal.woff2"
install -m 0644 "$project_dir/node_modules/@fontsource-variable/noto-sans-thai/files/noto-sans-thai-thai-wght-normal.woff2" "$project_dir/assets/fonts/noto-sans-thai-thai-wght-normal.woff2"
if command -v fonttools >/dev/null 2>&1 && command -v pyftsubset >/dev/null 2>&1; then
  temporary_font="$(mktemp --suffix=.woff2)"
  trap 'rm -f "$temporary_font"' EXIT
  fonttools varLib.instancer "$project_dir/node_modules/material-symbols/material-symbols-rounded.woff2" \
    FILL=0 GRAD=0 opsz=24 wght=400 --output "$temporary_font" >/dev/null
  pyftsubset "$temporary_font" --output-file="$project_dir/assets/fonts/material-symbols-rounded.woff2" \
    --flavor=woff2 --text='water_drophomewaternotificationswavesrainycampaigninfochevron_rightarrow_backnotifications_nonecloud_off' \
    --layout-features='*' --glyph-names --symbol-cmap --notdef-glyph --recommended-glyphs
  trap - EXIT
  rm -f "$temporary_font"
elif [[ ! -f "$project_dir/assets/fonts/material-symbols-rounded.woff2" ]]; then
  install -m 0644 "$project_dir/node_modules/material-symbols/material-symbols-rounded.woff2" "$project_dir/assets/fonts/material-symbols-rounded.woff2"
fi
php "$project_dir/scripts/build-icons.php"
