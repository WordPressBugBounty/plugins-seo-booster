#!/usr/bin/env bash
# Regenerate languages/seo-booster.pot from source (excludes vendor + heavy JS trees).
# Run from plugin root: ./scripts/update-pot.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PLUGIN_ROOT"

if ! command -v wp &>/dev/null; then
	echo "Error: wp (WP-CLI) is required." >&2
	exit 1
fi

PHP_MEMORY_LIMIT=512M wp i18n make-pot . languages/seo-booster.pot \
	--domain=seo-booster \
	--exclude=vendor,build,tests,node_modules,languages,js/chartjs,js/tabulator

echo "Updated languages/seo-booster.pot"
