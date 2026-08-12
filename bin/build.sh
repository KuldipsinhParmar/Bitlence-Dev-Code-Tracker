#!/usr/bin/env bash
# Produces a clean ZIP of the plugin (no dev tooling) for WP.org submission, or for a
# manual "Upload Plugin" test install. Plugin Check should never scan the raw dev
# checkout, which contains composer.json, tests/, phpunit.xml.dist, vendor/, .claude, etc.
#
# Uses `git archive`, which honors the export-ignore rules in .gitattributes. Snapshots
# the current working tree (staged so untracked new files are included too — e.g. a
# brand-new .gitattributes itself, or a newly added dev file) via `git stash create`, so
# you don't have to commit first. Unstages afterward, leaving the working tree untouched.
# Falls back to HEAD if there's nothing to stash.
#
# Output: build/bitlence-dev-code-tracker.zip — only the zip is kept; the intermediate
# extracted folder is removed once zipping is done.
set -euo pipefail
cd "$(dirname "$0")/.."

SLUG="bitlence-dev-code-tracker"
TMP="build/${SLUG}"

git add -A
SNAP="$(git stash create || true)"
git reset >/dev/null
[ -z "$SNAP" ] && SNAP="HEAD"

rm -rf build
mkdir -p "$TMP"
git archive "$SNAP" | tar -x -C "$TMP"
find "$TMP" -type d -empty -delete

( cd build && zip -rq "${SLUG}.zip" "${SLUG}" )
rm -rf "$TMP"

echo "ZIP ready at: build/${SLUG}.zip (from ${SNAP})"
echo "Use it for Plugin Check (unzip somewhere and point Plugin Check at it) or a manual 'Upload Plugin' test."
