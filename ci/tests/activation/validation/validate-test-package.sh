#!/usr/bin/env bash

# Checks that need the test package's npm dependencies and a browser. The ones that don't are in
# validate-hook-resilience.sh, which also runs where the package's dependencies are not installed.

set -euo pipefail

ACTIVATION_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE_ROOT="${ACTIVATION_ROOT}/test-package"

npm ci --prefix "${PACKAGE_ROOT}"

# --with-deps installs Chromium's system libraries with sudo, which CI runners need and a local machine usually doesn't.
if [[ "${CI:-}" == "true" ]]; then
    (cd "${PACKAGE_ROOT}" && npx playwright install --with-deps chromium)
else
    (cd "${PACKAGE_ROOT}" && npx playwright install chromium)
fi

node "${ACTIVATION_ROOT}/validation/validate-release-smoke-selection.mjs"
node --test "${ACTIVATION_ROOT}/validation/login-flow.test.mjs"
