#!/usr/bin/env bash
set -euo pipefail
cd -- "$(dirname -- "$0")/.."
php_bin=${DOCTOR_PHP_BIN:-/bin/php8.3}
echo '============================================================'
echo ' MY UNA DOCTOR — SOURCE VALIDATION'
echo ' OFFLINE / NO UNA BOOTSTRAP / NO INSTALL / NO DATABASE'
echo '============================================================'
[[ -x $php_bin ]] || { echo "STOP: PHP unavailable: $php_bin"; exit 1; }
echo
echo '=== 1. ALL PHP FILES ==='
while IFS= read -r -d '' file; do "$php_bin" -l "$file"; done < <(find classes install tests upgrade-templates request.php -type f -name '*.php' -print0)
echo
echo '=== 2. LOG CLASSIFICATION REGRESSIONS ==='
"$php_bin" tests/log-classification.php
echo '=== 2b. MODULE AND SYSTEM VERSION PROVENANCE ==='
"$php_bin" tests/module-version-provenance.php
"$php_bin" tests/module-version-provenance.php --missing-core-version
echo
echo '=== 3. INSTALLATION SQL REGRESSIONS ==='
python3 -m unittest discover -s tests -p 'test_*.py' -v
echo '=== 4. MODULE METADATA ==='
"$php_bin" -r 'define("BX_DOL_MODULE_TYPE_MODULE", "module"); require "install/config.php"; if ($aConfig["version"] !== "0.6.7" || $aConfig["vendor"] !== "My UNA World" || $aConfig["name"] !== "my_una_doctor") exit(1); echo "PASS: My UNA Doctor 0.6.7 / My UNA World\n";'
echo '============================================================'
echo ' OFFLINE VALIDATION PASSED — STAGING UPGRADE STILL REQUIRED'
echo '============================================================'

