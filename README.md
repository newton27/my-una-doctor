# My UNA Doctor

**Health, Compatibility & Upgrade Readiness for UNA**

Version: **0.6.6**  
Vendor: **My UNA World**

> Know before you upgrade. Know what changed afterward. Know where to look when something goes wrong.

My UNA Doctor is a Studio-only UNA CMS diagnostic and guarded-repair module. It evaluates application health, module integrity, maintenance and upgrade state, PHP compatibility, database consistency, cron registrations, filesystem conditions, recent actionable logs and protected repair state.

## What's new in 0.6.6

UNA-compatible installation SQL, safe sequential installation retries and direct native file updaters from 0.6.1–0.6.5. See [release notes](docs/RELEASE-0.6.6.md) for package selection and validation limits.

## What's new in 0.6.5

Includes reliably painted scan/reference activity popups, rotating work phases, elapsed time, duplicate-action protection, static reduced-motion feedback, upload-first layout, and persisted Reference Library Text Size controls with an 80%–125% range. Preserves verified-reference repair intelligence; destructive reference repair remains disabled. See docs/RELEASE-0.6.5.md for the validated release scope.

## What's new in 0.6.0

Version 0.6.0 adds Compatibility & Upgrade Intelligence.

Doctor now:

- reads the minimum PHP version from the locally installed UNA Studio audit instead of relying on a hard-coded global value;
- identifies PHP extensions required by the installed UNA source and verifies them against the active PHP runtime;
- evaluates registered module `compatible_with` declarations using UNA core semantics;
- verifies declared module dependencies using UNA installed-and-enabled semantics;
- checks UNA's configured stable/beta upgrade feed for a newer advertised target;
- recognizes an already-staged official UNA upgrade ZIP without downloading or extracting it;
- verifies official patch MD5 data before trusting staged content;
- verifies expected patch structure and target-version identity;
- performs limited static inspection of trusted target `check.php` content for literal PHP-version and `extension_loaded()` checks;
- explicitly distinguishes observed static evidence from a full target preflight.

Doctor does **not** execute a staged upgrade `check.php` during an ordinary health scan.

The trust chain for staged target inspection is:

Official metadata / MD5
→ MD5 verified
→ expected patch structure verified
→ target version verified
→ static evidence inspection permitted

If that trust chain fails, Doctor stops inspection and reports why.

## Module Reference Library

Studio includes a Module Reference Library for virgin/original or otherwise known-good module packages.

Doctor can validate ZIP structure, identify module/version information, create persistent manifests, retain SHA-256 identities, deduplicate uploads and compare installed modules against stored references.

Reference packages are evidence. They are not blindly copied over live modules.

## Maintenance & Upgrade State

Doctor distinguishes:

- NORMAL
- SOFT_MAINTENANCE
- HARD_MAINTENANCE
- STALE_HARD_LOCK
- POSSIBLE_INTERRUPTED_UPGRADE
- DOCTOR_PROTECTED_REPAIR

A separate readiness result reports whether maintenance state, UNA version agreement and Doctor repair state permit an upgrade workflow to proceed.

## Guarded repair architecture

Supported repairs use an evidence-first lifecycle:

Scan
→ Detect
→ Preview
→ Preflight
→ Arm Plan
→ Explicit approval
→ Durable rollback journal
→ Protect runtime content
→ Validate
→ Update stage
→ Restore / merge
→ Roll back when required

A preview or preflight never authorizes mutation by itself.

## Runtime-content protection

Doctor can identify modules whose tracked source is clean while generated runtime content inside the module directory interferes with UNA Studio update readiness.

For supported workflows Doctor can inventory and fingerprint runtime content, protect it outside the live module tree, verify the protected copy, restore legitimate tracked runtime files, and plan collision-aware merging after an update.

Generated content must not be silently re-baselined merely to make an update warning disappear.

## Module integrity

Doctor reports tracked files, modified tracked files, missing tracked files, new/untracked files and UNA Studio update-readiness effects separately.

Approved compatibility overrides remain explicitly identified rather than being hidden.

## Safety boundaries

My UNA Doctor intentionally fails closed when evidence is incomplete or contradictory.

It does not blindly:

- clear UNA hard maintenance;
- rewrite third-party checksum baselines;
- execute future UNA upgrade code during a normal scan;
- overwrite runtime-file collisions;
- ignore unresolved rollback state;
- treat historical log entries alone as proof of a current failure;
- certify a future UNA target when only partial static evidence was observed.

## Studio location

After installation open:

**UNA Studio → My UNA Doctor**

Choose **Run Full Health Scan** for the complete diagnostic report.

## Backup notice

Before performing any repair or upgrade-related operation, create and verify a current backup of the UNA database and site files. My UNA World can be used for this when installed.
