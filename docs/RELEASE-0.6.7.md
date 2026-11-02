# My UNA Doctor 0.6.7 — compatibility metadata candidate

Unpublished candidate. No release/tag/asset or Marketplace changes are authorized by this document.

The candidate also recognizes UNA System's exact `BX_DOL_VERSION` metadata value when checking package provenance. A matching RC3 System record is verified against the loaded core version; a different registered version remains a mismatch. No version is hardcoded, inferred from the database, or rewritten, and package PHP is not executed. Unsupported expressions and unavailable constants remain unverified.

This runtime correction changes the candidate source and package hashes. Earlier native acceptance records remain historical evidence for their recorded checkpoints; a new native RC3 scan and final source/package verification are still required before certifying the changed candidate.

The full installer and all six generated updater manifests use `['15.0.x']`, matching official UNA 15 module metadata. Direct native updater packages cover 0.6.1 through 0.6.6. The updater remains file-only: no installation SQL is executed and native version/hash protections remain in place.

The six published 0.6.6 ZIPs were independently inspected using the workflow artifact from run 40347487801, source 71e4f46ed5eb1f775ae7804ef8c4b95515088c67. Each inner ZIP hash matched its published release asset digest. All six passed ZIP integrity checks. The installer, every updater payload installer and every updater manifest declare 15.0.0-A1. The published assets and tag are preserved.

Official reference: unacms/UNA commit 8f45e86cf7bcdffb9096aba05c12c2f14a71c209, studio/classes/BxDolStudioInstaller.php, `_isCompatibleWith()`. It compares the current version against each declaration using PHP `version_compare` with `>=`; this is a minimum-version check, not a strict 15-only range. Changing the metadata does not prove that the former declaration caused an installation failure.

Validation evidence is recorded in docs/COMPATIBILITY-AUDIT-2026-10-28.md. Native Studio fresh installation and all six updates on a backed-up disposable UNA 15 site remain required before release approval. Source tests and isolated official-class contract checks do not certify a native installation, database preservation or browser behavior.

For new installations, use a validated 0.6.7 full installer after publication approval. For installed sites, choose the updater matching Studio's exact installed version and use the normal native update workflow. Do not reinstall, edit sys_modules.version, force modified-file updates or change UNA core files.
