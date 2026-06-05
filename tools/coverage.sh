#!/usr/bin/env bash
# Coverage gate for tiny_fastpix.
#
# Runs the plugin's PHPUnit suite under pcov, emits a clover-format report at
# build/coverage.xml, then invokes tools/coverage_gate.php to enforce:
#
#   overall (classes/)                  85%
#   classes/external/get_my_videos.php  90%
#
# Exits 0 if both targets are met; exits 1 with a remediation report otherwise.
#
# Usage from the plugin root (inside a bootstrapped Moodle):
#   bash tools/coverage.sh
#
# In CI this runs after `moodle-plugin-ci install`; see
# .github/workflows/moodle-plugin-ci.yml.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
BUILD_DIR="${PLUGIN_DIR}/build"
CLOVER_PATH="${BUILD_DIR}/coverage.xml"

mkdir -p "${BUILD_DIR}"

# Resolve the Moodle root. The plugin may live inside the tree (local dev:
# moodle/lib/editor/tiny/plugins/fastpix) or be checked out as a sibling of
# the Moodle install that moodle-plugin-ci builds (CI: $GITHUB_WORKSPACE/moodle).
declare -a candidates=()
[[ -n "${MOODLE_DIR:-}" ]]       && candidates+=("${MOODLE_DIR}")
candidates+=("${PLUGIN_DIR}/../../../../../..")
[[ -n "${GITHUB_WORKSPACE:-}" ]] && candidates+=("${GITHUB_WORKSPACE}/moodle")

MOODLE_ROOT=""
for cand in "${candidates[@]}"; do
    if [[ -x "${cand}/vendor/bin/phpunit" ]]; then
        MOODLE_ROOT="$(cd "${cand}" && pwd -P)"
        break
    fi
done

if [[ -z "${MOODLE_ROOT}" ]]; then
    echo "coverage.sh: vendor/bin/phpunit not found in any candidate path:" >&2
    for cand in "${candidates[@]}"; do
        echo "  - ${cand}" >&2
    done
    echo "Run from a bootstrapped Moodle install with phpunit configured." >&2
    exit 2
fi

echo "coverage.sh: running phpunit with coverage (Moodle: ${MOODLE_ROOT})..."
# pcov.enabled defaults to 0 on the moodle-php-apache image; pass it as a CLI
# ini override so we don't need to edit php.ini in the runner.
(
    cd "${MOODLE_ROOT}"
    php -d pcov.enabled=1 vendor/bin/phpunit \
        --testsuite=tiny_fastpix_testsuite \
        --coverage-clover="${CLOVER_PATH}" 2>&1 \
        | tail -20
)

if [[ ! -s "${CLOVER_PATH}" ]]; then
    echo "coverage.sh: clover report not generated at ${CLOVER_PATH}" >&2
    exit 1
fi

echo "coverage.sh: enforcing coverage targets..."
php "${PLUGIN_DIR}/tools/coverage_gate.php" "${CLOVER_PATH}"
