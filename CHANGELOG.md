# Changelog

All notable development milestones for My UNA Doctor are recorded here.

The project follows a conservative safety model: **Diagnose first. Repair only when the evidence is clear.**

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