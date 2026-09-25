# My UNA Doctor 0.6.4 — historical reconciliation record

Status: SUPERSEDED BY 0.6.5. This document preserves the 0.6.4 reconciliation
history and is not the current release checkpoint.

## Sources

- GitHub main: 63f9bc6bb065be78054e13c7e9666c504e9e742a.
- Server 0.6.2 package SHA-256:
  585196e49d31afdaddfaf34a417a224548b74b5f3fd57ad65272f8145462440d.
- Six-site tested Studio UI SHA-256:
  dc55859ba1983c668cad5456acb96221d46de19d91ada2117e3310103bd1fa75.
- MichaelandBrian UI hotfix SHA-256:
  b25bf83f3a006cdbc572c5a1cc0263af8a7d0a0048e43e9c84e4f5b645e316ed.

The six sites reported 0.6.2/0.6.3 in files but shared the 0.6.2 runtime
baseline, apart from config and UI. MichaelandBrian declares 0.6.0 but contains
later verified-reference features. Database versions have not been audited.
File labels alone do not certify an upgrade starting state.

## Reconciled behavior

- Keep main's verified-reference drift classification, SHA-bound preview,
  read-only preflight, runtime protection, and disabled destructive reference
  repair/Arm. Module and ReferencePackage classes remain byte-identical to main.
- Add 0.6.2's exact non-fatal una_upgrade_poll classifier. Fatal errors and
  unrelated errors retain health impact; evidence remains visible.
- Include working scan/preview/preflight activity overlay, reference-upload
  and saved-comparison overlay, and upload controls above saved-reference cards.
- Include Reference Library Text Size slider, Reset, and localStorage preference.
- Exclude the experimental f37631b timer and delayed-navigation changes.
- Flatten installer/uninstaller statements for UNA's line-oriented SQL execution,
  preserving main's field definitions, charset/collation and option defaults.
  Add missing repair_knowledge DROP to uninstall for table symmetry.
  Uninstall SQL is NEVER part of the upgrade actions.
- Align current version/vendor documentation to 0.6.4 / My UNA World.

## Historical release caution

The GitHub v0.6.1 archive is recorded against b30c0b9 with SHA-256
643deff170e80fd28c4282c7134d78246c2875983955940cf7267e570a13ce29.
The server's independently produced 0.6.2 checkpoint references a different
0.6.1 development line. Preserve both historical records; do not re-label or
overwrite the old archive with the reconciled source. Later Marketplace/README changes
mean main is not byte-identical to the historical release source.

## Upgrade candidates and gates

The reconciliation plan called for separate native 0.6.2-to-0.6.4 and
0.6.3-to-0.6.4 packages. Templates follow UNA's native BxDolStudioUpdater contract:
install/config.php + install/installer.php + source/ payload. No SQL execution,
file deletion, checksum bypass, or custom database writes in update actions.
UNA's normal updater changes version/track records after its checks succeed.

Do NOT run full install SQL over an existing module. Do NOT uninstall first.
Do NOT enable forced updates or rewrite checksums to silence a mismatch.
Native updates can refuse live hotfixes because their tracked hashes differ.
Audit registered versions and tracked modifications; validate on a restored
staging copy with verified file/database backup and rollback first.
No certified 0.6.0/0.6.1 direct upgrade was established by this work.

Required before merge/publish/deploy:
1. Run the release validation with server PHP 8.3.
2. Fresh-install the reconciled build on staging; confirm all four Doctor tables, options,
   Studio registration, scans, and uninstall symmetry in disposable staging only.
3. Test native upgrades from genuine database-registered 0.6.2 and 0.6.3 states.
   Preserve scan history, repair journals, references and local knowledge.
4. Review native modified-file checks on cloned hotfixed sites; stop on mismatch.
5. Browser-check all activity paths, Show Activity on/off, upload validation,
   result loading, Back navigation, Text Size/Reset/persistence, and read-only
   verified-reference preview/preflight. Do not arm/execute a repair for tests.
6. Save test evidence and then build final archives from the exact merged commit.
   Record source commit, SHA-256, and supported paths together.

Primary format reference:
https://github.com/unacms/UNA/blob/master/studio/classes/BxDolStudioUpdater.php
https://github.com/unacms/UNA/blob/master/modules/boonex/albums/updates/15.0.1_15.0.2/install/config.php
