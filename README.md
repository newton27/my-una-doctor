# My UNA Doctor

**Studio-only UNA CMS diagnostics and guarded repair framework**

Current validated checkpoint: **0.3.4**  
Vendor: **NewtronJax**

> **Diagnose first. Repair only when the evidence is clear.**

My UNA Doctor analyzes UNA system health, module integrity, update readiness, database state, cron registrations, filesystem conditions and recent actionable logs. Newer 0.3.x builds add staged, evidence-driven repair controls with durable rollback state instead of performing blind automatic repairs.

## 0.3.4 known-good checkpoint

Version 0.3.4 is preserved in Git as annotated tag **`v0.3.4`**.

Release verification before the tag was created:

- 9 PHP files lint clean under PHP 8.3
- 19 tracked / 19 current hashable Doctor files
- 0 modified tracked
- 0 missing tracked
- 0 new/untracked
- 0 UNA Studio changed files
- source integrity: **clean**

Known-good commit:

```text
e2a12b20a066e0f61c2cc911a8b22fd4d51ce1da
```

Documentation added after the tag lives on `main`; the `v0.3.4` source checkpoint remains unchanged.

## Current architecture

The guarded repair lifecycle is intentionally staged:

```text
Scan
  -> Detect
  -> Preview
  -> Preflight
  -> Arm Plan
  -> Explicit approval
  -> Durable rollback journal
  -> Repair Barrier 2
  -> Protect verified runtime content
  -> Validate
  -> Separate update stage
  -> Restore / merge planning
  -> Roll back when required
```

A successful preview or preflight does not itself authorize mutation.

## Repair Barrier 2

Before supported runtime protection is allowed, Doctor establishes a three-part control barrier:

1. **Disable and verify UNA template-cache compression**
2. **Enable UNA soft maintenance**
3. **Pause only the verified generator cron**

The compression step is critical. During testing, soft maintenance left Studio HTML available but `gzip_loader.php` returned HTTP 503, breaking compressed Studio styling. Disabling template-cache compression first causes Studio to use direct `/cache_public/...` assets and keeps the administrative recovery interface usable.

Disarm reverses the barrier safely: exact generator schedule first, then exact maintenance state, then exact original compression value, with independent verification at each stage.

See [Repair Barrier 2](docs/REPAIR-BARRIER-2.md).

## Guarded runtime protection

Doctor can identify a module that is source-clean but whose update readiness is distorted by a large population of generated files inside its module directory.

For a verified candidate, 0.3.4 can:

- inventory the runtime tree immediately before mutation;
- produce a deterministic protection fingerprint;
- refuse symlinks, hardlinks and special files;
- require a same-filesystem safety destination;
- preserve runtime-root filesystem metadata;
- atomically separate the whole generated runtime tree rather than moving thousands of files individually;
- restore only legitimate tracked runtime files to the live module path;
- verify the protected original independently;
- recover the original runtime tree if protection fails;
- restore a protected runtime tree back to the live module path;
- plan post-update runtime merging without automatically overwriting collisions.

The proven large-tree test contained **18,027 physical files** and **3,059,207,616 bytes** of runtime content.

See [Guarded Runtime Protection](docs/RUNTIME-PROTECTION.md).

## Module integrity and update readiness

Doctor uses UNA Studio-compatible hashing semantics and separately reports:

- tracked files,
- current hashable files,
- modified tracked files,
- missing tracked files,
- new/untracked files,
- the final changed-file set UNA Studio would use for update readiness.

`getModuleIntegritySnapshot()` exposes complete, untruncated integrity data for guarded planning and verification.

Doctor does **not** silently rebaseline a third-party module merely to make an update warning disappear.

## Durable repair journal

Repair mutation begins only after the original state required for rollback has been persisted.

Journal state tracks the repair lifecycle, including arming, runtime protection, runtime restoration and unresolved rollback conditions. An active or unresolved journal is itself a safety condition and blocks a new repair workflow.

Repair state is kept separate from the protected third-party module content.

## Safety boundaries

My UNA Doctor intentionally fails closed when required evidence is incomplete or contradictory.

0.3.4 does not automatically:

- rewrite third-party checksum baselines;
- start a module update merely because a repair is armed;
- overwrite post-update runtime collisions;
- repair findings whose ownership or intent is ambiguous;
- ignore a failed rollback or unresolved journal;
- alter unrelated cron registrations.

Unknown integrity state is reported as unknown/warning rather than clean.

## Diagnostic coverage

The broader Doctor scanner includes checks for:

- UNA/PHP compatibility and versions;
- common PHP extensions;
- writable UNA runtime directories;
- disk-space safety;
- database connectivity and schema conditions;
- module registration and install metadata;
- module file integrity and update readiness;
- notification/alert registration integrity;
- cron file references and ownership;
- recent actionable UNA/PHP logs;
- scan history;
- supported Albums and Timeline media relationship integrity.

## Documentation

- [0.3.4 release checkpoint](docs/RELEASE-0.3.4.md)
- [Repair Barrier 2](docs/REPAIR-BARRIER-2.md)
- [Guarded Runtime Protection](docs/RUNTIME-PROTECTION.md)
- [Changelog](CHANGELOG.md)
- [Installation notes](INSTALLATION.txt)
- [Marketplace notes](MARKETPLACE.txt)

Historical 0.1.x update notes remain in the repository for development history.

## Package layout

The UNA installation package uses the vendor/module hierarchy:

```text
newton/
└── my_una_doctor/
    ├── classes/
    ├── install/
    │   ├── langs/
    │   └── sql/
    ├── template/
    │   └── images/icons/std-icon.svg
    ├── INSTALLATION.txt
    ├── README.md
    └── request.php
```

The Git repository may contain additional development documentation that is not required inside a production UNA module package.

## Development rule

Treat `v0.3.4` as an immutable recovery/reference point. New development belongs on commits after that tag and should be linted, exercised, integrity-checked and deliberately rebaselined only when the changed files are known and reviewed.