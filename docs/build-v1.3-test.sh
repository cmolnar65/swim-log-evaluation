#!/usr/bin/env bash
# Build an audited test ZIP using the existing production packaging process.
set -euo pipefail
SWIMLOG_REPO="${SWIMLOG_TEST_REPO:-$HOME/cmoln/code/swim-log-evaluation}"
SWIMLOG_COMMIT="${1:?Pass the full 40-character verified develop commit SHA.}"
[[ "$SWIMLOG_COMMIT" =~ ^[0-9a-f]{40}$ ]] || { echo "A full commit SHA is required." >&2; exit 1; }
for command_name in git php composer zip unzip python3 sha256sum; do
    command -v "$command_name" >/dev/null || { echo "Missing command: $command_name" >&2; exit 1; }
done
SWIMLOG_REPO="$(git -C "$SWIMLOG_REPO" rev-parse --show-toplevel)"
git -C "$SWIMLOG_REPO" cat-file -e "$SWIMLOG_COMMIT^{commit}"
git -C "$SWIMLOG_REPO" merge-base --is-ancestor "$SWIMLOG_COMMIT" refs/remotes/origin/develop || {
    echo "Commit is not on the fetched origin/develop history." >&2; exit 1;
}
SWIMLOG_SHORT="${SWIMLOG_COMMIT:0:12}"
SWIMLOG_OUTPUT="$SWIMLOG_REPO/build/chriss-swim-training-progress-evaluation-1.3-dev-test-$SWIMLOG_SHORT.zip"
[[ ! -e "$SWIMLOG_OUTPUT" && ! -L "$SWIMLOG_OUTPUT" && ! -e "$SWIMLOG_OUTPUT.sha256" && ! -L "$SWIMLOG_OUTPUT.sha256" ]] || {
    echo "Test output already exists; move that test ZIP/checksum before rebuilding: $SWIMLOG_OUTPUT" >&2; exit 1;
}
SWIMLOG_TEMP="$(mktemp -d "${TMPDIR:-/tmp}/swimlog-test.XXXXXXXX")"
cleanup() {
    if [[ -d "$SWIMLOG_TEMP/tree" ]]; then
        git -C "$SWIMLOG_REPO" worktree remove --force "$SWIMLOG_TEMP/tree" || true
    fi
    rm -rf -- "$SWIMLOG_TEMP"
}
trap cleanup EXIT
git -C "$SWIMLOG_REPO" worktree add --detach "$SWIMLOG_TEMP/tree" "$SWIMLOG_COMMIT"
[[ "$(git -C "$SWIMLOG_TEMP/tree" rev-parse HEAD)" == "$SWIMLOG_COMMIT" ]]
[[ -z "$(git -C "$SWIMLOG_TEMP/tree" status --porcelain)" ]]
mkdir -p -- "$SWIMLOG_REPO/build"
SWIMLOG_STAGED_ZIP="$SWIMLOG_TEMP/test.zip"
bash "$SWIMLOG_TEMP/tree/build-release.sh" "$SWIMLOG_STAGED_ZIP"
unzip -tq "$SWIMLOG_STAGED_ZIP"
python3 -I - "$SWIMLOG_STAGED_ZIP" <<'PY'
import re, sys, zipfile
root = "chriss-swim-training-progress-evaluation/"
with zipfile.ZipFile(sys.argv[1]) as archive:
    names = archive.namelist()
    assert names and all(n.startswith(root) for n in names), "Incorrect plugin directory"
    forbidden = {".git", ".github", "tests", "docs", "build", "wordpress-org-assets",
                 "build-release.sh", "build-release.ps1", "phpunit.xml.dist",
                 "composer.lock", "PLUGIN-CHECK.md", "REST-API.md"}
    for name in names:
        relative = name[len(root):]
        parts = relative.split("/")
        assert parts[0] not in forbidden, "Development file: " + name
        assert "wordpress-org-assets" not in parts, "Artwork archive: " + name
        assert not relative.lower().endswith(".pdf"), "Unexpected PDF: " + name
        assert ".." not in parts, "Unsafe archive path"
    for required in ("composer.json", "vendor/autoload.php",
                     "vendor/smalot/pdfparser/src/Smalot/PdfParser/Parser.php",
                     "vendor/dompdf/dompdf/src/Dompdf.php",
                     "includes/class-garmin-history.php",
                     "includes/class-garmin-history-jobs.php",
                     "admin/class-admin-garmin-history.php",
                     "assets/css/garmin-history.css", "assets/js/garmin-history.js",
                     "blocks/calendar/view.js"):
        assert root + required in names, "Missing runtime file: " + required
    entry = archive.read(root + "swim-log-evaluation.php").decode()
    readme = archive.read(root + "readme.txt").decode()
    assert re.search(r"Version:\s*1\.3-dev\s*$", entry, re.M), "Unexpected plugin version"
    assert re.search(r"Stable tag:\s*1\.3-dev\s*$", readme, re.M), "Unexpected test stable tag"
    assert "define( 'SWIMLOG_EVALUATION_DB_VERSION', '1.3' );" in entry, "Unexpected schema version"
print("Test package audit passed: runtime dependencies, history assets, metadata and exclusions.")
PY
cp -- "$SWIMLOG_STAGED_ZIP" "$SWIMLOG_OUTPUT"
(
    cd -- "$(dirname -- "$SWIMLOG_OUTPUT")"
    sha256sum "$(basename -- "$SWIMLOG_OUTPUT")" > "$(basename -- "$SWIMLOG_OUTPUT").sha256"
    sha256sum -c "$(basename -- "$SWIMLOG_OUTPUT").sha256"
)
printf '\nTest commit: %s\nInstallable ZIP: %s\nChecksum: %s.sha256\n' "$SWIMLOG_COMMIT" "$SWIMLOG_OUTPUT" "$SWIMLOG_OUTPUT"
