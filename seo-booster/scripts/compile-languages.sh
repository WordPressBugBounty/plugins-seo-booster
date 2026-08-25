#!/usr/bin/env bash
# Compile plugin translation artifacts from languages/*.po
# Run from plugin root: ./scripts/compile-languages.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PLUGIN_ROOT"

if ! command -v msgfmt &>/dev/null; then
	echo "Error: msgfmt is required (gettext)." >&2
	exit 1
fi

if ! command -v wp &>/dev/null; then
	echo "Error: wp (WP-CLI) is required for .l10n.php and JSON." >&2
	exit 1
fi

shopt -s nullglob
po_files=(languages/*.po)
if [ ${#po_files[@]} -eq 0 ]; then
	echo "No .po files found in languages/." >&2
	exit 1
fi

for po in "${po_files[@]}"; do
	base="${po%.po}"
	echo "Compiling $(basename "$po")..."
	msgfmt --statistics "$po" -o "${base}.mo"
	wp i18n make-php "$po" languages/ --quiet
done

wp i18n make-json languages/ --no-purge --quiet

echo "Done: compiled ${#po_files[@]} locale(s)."

echo "Tip: after editing .po files, run ./scripts/update-pot.sh then msgmerge -U --no-fuzzy-matching languages/seo-booster-da_DK.po languages/seo-booster.pot"
