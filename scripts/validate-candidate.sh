#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "$0")/.."
php_bin=${DOCTOR_PHP_BIN:-/bin/php8.3}
echo '============================================================'
echo ' MY UNA DOCTOR — 0.6.4 CANDIDATE VALIDATION'
echo ' OFFLINE / NO UNA BOOTSTRAP / NO INSTALL / NO DATABASE'
echo '============================================================'
[[ -x $php_bin ]] || { echo "STOP: PHP unavailable: $php_bin"; exit 1; }
echo
echo '=== 1. ALL PHP FILES ==='
while IFS= read -r -d '' file; do "$php_bin" -l "$file"; done < <(find classes install tests upgrade-templates -type f -name '*.php' -print0)
echo
echo '=== 2. LOG CLASSIFICATION REGRESSIONS ==='
"$php_bin" tests/log-classification.php
echo
echo '=== 3. MODULE METADATA ==='
"$php_bin" -r 'define("BX_DOL_MODULE_TYPE_MODULE", "module"); require "install/config.php"; if ($aConfig["version"] !== "0.6.4" || $aConfig["vendor"] !== "My UNA World" || $aConfig["name"] !== "my_una_doctor") exit(1); echo "PASS: My UNA Doctor 0.6.4 / My UNA World\n";'
echo '============================================================'
echo ' OFFLINE VALIDATION PASSED — STAGING UPGRADE STILL REQUIRED'
echo '============================================================'
