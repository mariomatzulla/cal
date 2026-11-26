#!/usr/bin/env bash
#
# Creates the TER package (zip) of cal from a git revision.
#
#   Build/Scripts/release.sh             cal_<version>.zip, for Composer installations (TER)
#   Build/Scripts/release.sh --bundle    cal_<version>-bundled.zip with sabre/dav and sabre/vobject
#                                        for installations without Composer (classic mode)
#
# Options:
#   --bundle         install the non-TYPO3 Composer packages of "require" into
#                    Resources/Private/Php/ComposerVendor and declare them in providesPackages
#   --ref <ref>      git revision to package (default: HEAD, uncommitted changes are not included)
#   --output <dir>   target directory (default: .Build/release)
#
# Requirements: git, php, zip, and composer for --bundle.
# Files marked with export-ignore in .gitattributes are not part of the package.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BUNDLE=0
REF="HEAD"
OUTPUT="$ROOT/.Build/release"
VENDOR_DIR="Resources/Private/Php/ComposerVendor"
# lowest PHP version of cal, the bundled packages must run with it
PHP_PLATFORM="8.2.0"

fail() {
    echo "Error: $*" >&2
    exit 1
}

while [ $# -gt 0 ]; do
    case "$1" in
        --bundle) BUNDLE=1 ;;
        --ref) REF="${2:?--ref needs a git revision}"; shift ;;
        --output) OUTPUT="${2:?--output needs a directory}"; shift ;;
        -h|--help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) fail "unknown option $1 (see --help)" ;;
    esac
    shift
done

for tool in git php zip; do
    command -v "$tool" >/dev/null || fail "$tool is not installed"
done
if [ "$BUNDLE" -eq 1 ]; then
    command -v composer >/dev/null || fail "composer is not installed"
fi

BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT
PACKAGE="$BUILD/cal"
mkdir -p "$PACKAGE"

git -C "$ROOT" rev-parse --verify --quiet "$REF^{commit}" >/dev/null || fail "unknown git revision $REF"
git -C "$ROOT" archive --format=tar "$REF" | tar -x -C "$PACKAGE"

# The version of composer.json and ext_emconf.php must match
VERSION="$(php -r '$c = json_decode(file_get_contents($argv[1])); echo $c->extra->{"typo3/cms"}->version ?? $c->version ?? "";' "$PACKAGE/composer.json")"
EMCONF_VERSION="$(php -r '$_EXTKEY = "cal"; $EM_CONF = []; include $argv[1]; echo $EM_CONF["cal"]["version"] ?? "";' "$PACKAGE/ext_emconf.php")"
[ -n "$VERSION" ] || fail "composer.json has no version (extra.typo3/cms.version)"
[ "$VERSION" = "$EMCONF_VERSION" ] || fail "version $VERSION of composer.json differs from version $EMCONF_VERSION of ext_emconf.php"

SUFFIX=""
if [ "$BUNDLE" -eq 1 ]; then
    SUFFIX="-bundled"
    DEPS="$BUILD/dependencies"
    mkdir -p "$DEPS"
    # Composer project with the requirements of cal that are no TYPO3 packages and no platform requirements.
    # psr/log is shipped by TYPO3 itself.
    php -r '
        $cal = json_decode(file_get_contents($argv[1]), true);
        $require = array_filter(
            $cal["require"] ?? [],
            static fn(string $name): bool => $name !== "php" && !str_starts_with($name, "ext-") && !str_starts_with($name, "lib-") && !str_starts_with($name, "typo3/"),
            ARRAY_FILTER_USE_KEY,
        );
        if ($require === []) {
            fwrite(STDERR, "Error: no packages to bundle\n");
            exit(1);
        }
        file_put_contents($argv[2] . "/composer.json", json_encode([
            "name" => "mariomatzulla/cal-dependencies",
            "require" => $require,
            "replace" => ["psr/log" => "*"],
            "config" => [
                "vendor-dir" => $argv[3],
                "platform" => ["php" => $argv[4]],
                "optimize-autoloader" => true,
                "platform-check" => false,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    ' "$PACKAGE/composer.json" "$DEPS" "$PACKAGE/$VENDOR_DIR" "$PHP_PLATFORM"
    composer update --working-dir="$DEPS" --no-dev --no-interaction --no-progress --prefer-dist
    [ -f "$PACKAGE/$VENDOR_DIR/autoload.php" ] || fail "composer did not create $VENDOR_DIR/autoload.php"
    # TYPO3 loads the autoload.php of the directory of providesPackages in classic mode
    php -r '
        $file = $argv[1];
        $cal = json_decode(file_get_contents($file));
        $deps = json_decode(file_get_contents($argv[2]), true);
        $provides = $cal->extra->{"typo3/cms"}->Package->providesPackages ?? new stdClass();
        foreach (array_keys($deps["require"]) as $name) {
            $provides->{$name} = $argv[3];
        }
        $cal->extra->{"typo3/cms"}->Package ??= new stdClass();
        $cal->extra->{"typo3/cms"}->Package->providesPackages = $provides;
        file_put_contents($file, json_encode($cal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    ' "$PACKAGE/composer.json" "$DEPS/composer.json" "$VENDOR_DIR"
fi

mkdir -p "$OUTPUT"
OUTPUT="$(cd "$OUTPUT" && pwd)"
ZIP="$OUTPUT/cal_${VERSION}${SUFFIX}.zip"
rm -f "$ZIP"
(cd "$PACKAGE" && zip -qr -X "$ZIP" .)

echo "Created $ZIP"
if [ "$BUNDLE" -eq 1 ]; then
    echo "Bundled: $(php -r '$c = json_decode(file_get_contents($argv[1])); echo implode(", ", array_keys((array)$c->extra->{"typo3/cms"}->Package->providesPackages));' "$PACKAGE/composer.json") (for installations without Composer)"
else
    echo "Without bundled packages (for Composer installations and the TER)"
fi
