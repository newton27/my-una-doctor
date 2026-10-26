# Changelog

All notable development milestones for My UNA Doctor are recorded here.

The project follows a conservative safety model: **Diagnose first. Repair only when the evidence is clear.**

## 0.6.6 — UNA 15 installation retry safety

- Retained single-line installation SQL compatible with UNA's line concatenation parser (client report: v0.6.1 on MySQL/AWS RDS).
- Guarded Studio page, management widget and home-page placement creation against duplicates on sequential installation retries.
- Resolve existing registration IDs explicitly instead of relying on stale LAST_INSERT_ID() values.
- Added parser regressions and disposable MySQL 8.0/MariaDB 10.11 tests for fresh install, repeat install, interruption after every statement and preservation of existing settings/scan data.
- Added SQL checks to the candidate validator and corrected its stale 0.6.4 metadata expectation to match the current 0.6.5 source.

Includes a corrected full installer and direct file updaters for 0.6.1–0.6.5. Previous release assets remain unchanged. Native Studio and AWS RDS acceptance remain untested; see docs/RELEASE-0.6.6.md.

## 0.6.5 — Activity Motion & Library Accessibility

- Ensured supported forms and links paint the Doctor activity popup before submission or navigation.
- Added rotating work phases and elapsed time for long-running actions.
- Preserved duplicate-submit, duplicate-click and browser restoration protections.
- Replaced continuous animation with a static indicator when reduced motion is requested.
- Raised the Saved Reference Library minimum text scale from 50% to 80%.
- Corrected stale source documentation for saved-reference comparison.
- Preserved all repair barriers and reference-integrity checks.
- Passed staged PHP validation, source-scope audit and browser workflow testing.

See [0.6.5 release checkpoint](docs/RELEASE-0.6.5.md).

## 0.6.0 — Compatibility & Upgrade Intelligence

- Added installed UNA/PHP compatibility detection from local UNA source.
- Added required PHP extension verification.
- Added module compatibility and dependency checks using UNA semantics.
- Added official UNA upgrade-target awareness.
- Added trust-gated inspection of staged official upgrade packages.
- Added limited static compatibility evidence without executing upgrade code.
- Updated package documentation for the final 0.6.0 release.

Source release commit:

`5ea1d6511acd1f97f80fc55424ee905d24791aa9`

See [0.6.0 release checkpoint](docs/RELEASE-0.6.0.md).

## 0.5.0 — Reference Library and Upgrade Readiness

### Module Reference Library

- Added the Studio-only **Module Reference Library** for virgin/original or otherwise known-good module packages.
- Added safe ZIP intake with archive validation before extraction.
- Preserved the `is_uploaded_file()` upload safety check.
- Added module identity/version detection and persistent reference manifests.
- Added durable SHA-256 package identities and package deduplication.
- Added saved comparison history and **Compare Again** without requiring a re-upload.
- Added installed-vs-reference classification for matching, modified, missing and live-only files.
- Added comparison against UNA tracked baselines when available.
- Added approved compatibility modifications and Local Repair Knowledge so a legitimate known deviation is not treated as an unexplained repair target.
- Persistent reference packages remain evidence sources; Doctor does not blindly overwrite live modules from a reference.

### Maintenance & Upgrade State Diagnostics

- Added a read-only maintenance/upgrade detector to the main health scan.
- Added classifications:
  - `NORMAL`
  - `SOFT_MAINTENANCE`
  - `HARD_MAINTENANCE`
  - `STALE_HARD_LOCK`
  - `POSSIBLE_INTERRUPTED_UPGRADE`
  - `DOCTOR_PROTECTED_REPAIR`
- Added correlation with active Doctor repair-journal state so expected Doctor maintenance is distinguishable from an interrupted UNA upgrade.
- Added a conservative six-hour stale hard-lock threshold as diagnostic evidence only.
- Added strong interrupted-upgrade correlation for maintenance plus UNA file/database version disagreement.
- Added bounded supporting inspection of `logs/sys_modules.log` for upgrade-related signals.
- Historical upgrade log signals are advisory only and do not independently reduce health.
- Doctor does not automatically remove `.bx_maintenance` or toggle soft maintenance from this diagnostic path.

### Upgrade Readiness Summary

- Added a separate readiness finding scoped to **maintenance and upgrade state**.
- Current blockers include:
  - active UNA hard maintenance;
  - active UNA soft maintenance;
  - UNA file/database version mismatch;
  - any active Doctor repair journal.
- A maintenance/version mismatch can raise the readiness finding to critical.
- Historical log signals do not independently block readiness.
- The scope is intentionally separate from future readiness dimensions such as source/checksum eligibility, module/database consistency, PHP/UNA compatibility, server health and backup/recoverability.

### Maintenance-page and media assets

- Added `MyUnaDoctorMaintenance.php` as a self-contained public maintenance-page builder.
- Added Doctor-owned server poster/video assets used by the maintenance presentation.
- Added the two media assets to the 0.5.0 tracked-file baseline.

### Validation

Before the 0.5.0 release checkpoint was frozen:

- registered/source version: `0.5.0`;
- module enabled;
- Doctor tracked rows: 25;
- matching tracked rows: 25;
- modified tracked: 0;
- missing tracked: 0;
- all Doctor PHP files linted clean under PHP 8.3;
- `.bx_maintenance`: absent;
- UNA soft maintenance: off;
- UNA file version: `15.0.0-RC1`;
- UNA database version: `15.0.0-RC1`;
- Reference Library: 2 saved / 2 verified / 0 failed;
- historical reference comparison sessions: 12;
- durable reference SHA identities: 2.

A nine-case synthetic maintenance/readiness matrix passed against a temporary scanner copy without changing live UNA maintenance, repair journals or checksum baselines.

Source release commit:

```text
75a55a493e95cee33e614ea0a8339af70fa62186
```

See [0.5.0 release checkpoint](docs/RELEASE-0.5.0.md).

## 0.4.0 — Reference Library checkpoint

- Added the initial Module Reference Library engine and Studio route.
- Added persistent immutable SHA-256 reference storage.
- Added reference manifests, package validation and installed/reference comparison.
- Added approved modification handling and Local Repair Knowledge.
- Added durable comparison sessions and re-comparison of saved packages.
- Preserved this milestone as an internal frozen checkpoint before 0.5.0 maintenance/upgrade diagnostics were added.

## 0.3.4 — Known-good checkpoint

### Repair Barrier 2

- Added a three-part repair barrier for guarded repair workflows:
  1. persist a durable repair journal,
  2. disable UNA template-cache compression and verify it,
  3. enable UNA soft maintenance and pause only the verified module generator.
- Added exact restoration of the original template-compression value after maintenance is restored.
- Added independent verification of compression, maintenance and cron state during both arm and disarm.
- Kept backward compatibility with repair journals created before the compression snapshot existed.
- Proven live in both directions: arm -> verify -> disarm -> exact original state restored.

### Why Barrier 2 was required

- UNA Studio remained administratively accessible during soft maintenance, but compressed Studio assets were routed through `gzip_loader.php`.
- `gzip_loader.php` returned HTTP 503 while soft maintenance was active, leaving Studio visually unstyled.
- Disabling `sys_template_cache_compress_enable` before enabling maintenance causes Studio to use direct `/cache_public/...` assets instead, preserving a usable recovery interface.

### Runtime-generated content protection

- Added read-only runtime protection planning with deterministic plan IDs and inventory fingerprints.
- Added guarded atomic runtime protection using whole-directory rename on the same filesystem instead of thousands of individual moves.
- Added tracked-file-only restoration to the recreated runtime directory.
- Added runtime metadata preservation and verification.
- Added automatic rollback/recovery if protection fails after the atomic rename.
- Added restoration of protected runtime content back to the live module tree.
- Refuses unsafe trees containing symlinks, hardlinks or special files.
- Proven against a large generated-content test case containing 18,027 physical files and approximately 2.9 GB of data.

### Integrity and update planning

- Added `getModuleIntegritySnapshot()` for complete, untruncated module integrity state.
- Separately reports tracked files, modified tracked files, missing tracked files, new/untracked files and UNA Studio changed-file semantics.
- Added a read-only post-update runtime merge planner.
- Existing tracked runtime files are never treated as generated content.
- Newly tracked collisions and live-path collisions block automatic overwrite.
- Module update execution remains a separate guarded stage.

### Repair journal and recovery

- Expanded durable journal states for arming, runtime protection, runtime restoration and rollback failure handling.
- Repair state remains under the Doctor repair-safety area and is not mixed with the protected module tree.
- Exact original maintenance, cron and compression values are retained for restoration.

### Studio and CSRF hardening

- Added local CSRF token generation inside the repair-arm renderer to remove undefined-token warnings.
- Confirmed UNA CSRF tokens are one-time-use by default; stale browser POST replay can therefore produce a validation failure even after a previous action succeeded.
- Updated Studio wording to describe the current three-part control barrier and runtime protection capabilities accurately.

### Release verification

Before the 0.3.4 checkpoint was tagged:

- 9 PHP files linted clean under PHP 8.3.
- Doctor integrity: 19 tracked / 19 current hashable.
- Modified tracked: 0.
- Missing tracked: 0.
- New/untracked: 0.
- Studio changed: 0.
- Source integrity: clean.

Git checkpoint:

- Commit: `e2a12b20a066e0f61c2cc911a8b22fd4d51ce1da`
- Annotated tag: `v0.3.4`

## 0.3.3

- Added read-only repair arm planning.
- Arm plans require a successful preflight.
- Added exact generator schedule snapshots and rollback plans.
- Added durable repair journaling before mutating repair state.
- Initial repair barrier used soft maintenance plus verified generator pause.

## 0.3.2

- Corrected runtime inventory semantics so physical totals and untracked runtime counts are reported separately.
- Strengthened Studio/web execution requirements for mutating repair preflight.
- Hardened repair-safety directory ownership and permissions.

## 0.3.1

- Added read-only repair preflight.
- Added runtime-path ownership, writable-destination, filesystem-device, free-space and cron ownership checks.
- Added generic module cron discovery by registered cron file path.

## 0.3.0

- Added read-only repair preview generation.

## 0.2.2

- Added detection of runtime-generated module content that can distort UNA Studio update readiness.
- Added generic runtime-content classification for high-volume untracked files concentrated under runtime-like directories.

## 0.2.1

- Added repair-candidate intelligence and richer evidence.
- Added UNA Studio-compatible module hashing semantics.
- Added explicit UNKNOWN/WARNING behavior when integrity inspection cannot be completed safely.

## 0.2.0

- Added module file integrity and update-readiness diagnostics.
- Added separate reporting for modified tracked, missing tracked and new/untracked files.

## Earlier 0.1.x builds

The 0.1.x series established the Studio-only diagnostic scanner, scan history, health scoring, log classification, media/storage relationship inspection and presentation refinements. Historical updater notes remain in the repository for those builds.
