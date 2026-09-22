#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="chriss-swim-training-progress-evaluation"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$SCRIPT_DIR"
OUTPUT="${1:-../chriss-swim-training-progress-evaluation.zip}"

if [[ "$OUTPUT" = /* ]]; then
    OUTPUT_PATH="$OUTPUT"
else
    OUTPUT_PATH="$REPO_ROOT/$OUTPUT"
fi

TEMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/swimlog-release.XXXXXXXX")"
STAGE_DIR="$TEMP_ROOT/$PLUGIN_SLUG"

cleanup() {
    rm -rf -- "$TEMP_ROOT"
}
trap cleanup EXIT

# Development/repository files that must not ship in the WordPress release package.
EXCLUDE_TOP_LEVEL=(
    ".git"
    ".github"
    "tests"
    "phpunit.xml.dist"
    "composer.json"
    "composer.lock"
    "build-release.ps1"
    "build-release.sh"
    "PLUGIN-CHECK.md"
)

mkdir -p -- "$STAGE_DIR"

shopt -s dotglob nullglob
for item in "$REPO_ROOT"/*; do
    name="$(basename -- "$item")"
    exclude=false

    for excluded in "${EXCLUDE_TOP_LEVEL[@]}"; do
        if [[ "$name" == "$excluded" ]]; then
            exclude=true
            break
        fi
    done

    if [[ "$exclude" == false ]]; then
        cp -a -- "$item" "$STAGE_DIR/"
    fi
done
shopt -u dotglob nullglob

mkdir -p -- "$(dirname -- "$OUTPUT_PATH")"
rm -f -- "$OUTPUT_PATH"

if ! command -v zip >/dev/null 2>&1; then
    echo "Error: the 'zip' command is required to build the release package." >&2
    exit 1
fi

(
    cd -- "$TEMP_ROOT"
    zip -qr "$OUTPUT_PATH" "$PLUGIN_SLUG"
)

echo "Release package created:"
echo "  $OUTPUT_PATH"
echo
echo "Excluded development files:"
printf '  %s\n' "${EXCLUDE_TOP_LEVEL[@]}"
