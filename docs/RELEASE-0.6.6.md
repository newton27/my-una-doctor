# My UNA Doctor 0.6.6 — UNA 15 installation compatibility

This maintenance release addresses the installation SQL compatibility report from a v0.6.1 customer using UNA 15 and MySQL on AWS RDS. No UNA core or MySQL configuration edits are needed for the SQL formatting correction.

## Changes

- Packages installation SQL as single-line statements, preserving token boundaries when UNA concatenates trimmed input lines.
- Makes Studio page, widget and launcher placement inserts safe for sequential SQL retries, with explicit ID lookups.
- Preserves existing configured settings and scan records during SQL retries.
- Adds permanent MySQL 8.0/MariaDB 10.11 installation and interruption/retry regressions.
- Includes direct version-specific file updaters from 0.6.1, 0.6.2, 0.6.3, 0.6.4 and 0.6.5.
- Corrects stale installation-guide and Marketplace package-version labels.

## Choose the right package

**New or never-completed installation:** use `my-una-doctor-0.6.6.zip` through the site's normal UNA module installation process. Back up first. If a failed attempt left partial database records, preserve the backup and inspect the failure rather than deleting tables manually.

**Already installed:** choose `my-una-doctor-<installed-version>-to-0.6.6.zip` matching the version shown in Studio. Extract its `update_<installed-version>_0.6.6/` directory into `modules/newton/my_una_doctor/updates/`, then apply it through UNA Studio's downloaded-updates workflow. The update is version-gated by UNA. It replaces module files and clears caches; it does not execute the full installation SQL, delete module tables, or uninstall/reinstall the module. Do not manually change `sys_modules.version`.

If UNA blocks an update because tracked files were modified, review those changes and preserve them before proceeding. Do not globally disable UNA checksum protection to force the update. The installation-SQL formatting repair itself is under UNA's excluded `install/` directory, but other local edits can still block a native update.

The client whose installation already succeeded after the SQL repair can use the matching updater. They do not need to uninstall a working module. This release also includes the existing 0.6.5 interface improvements; all repair safety barriers remain in place.

## Validation scope

The release workflow gates packaging on MySQL 8.0 and MariaDB 10.11 SQL execution, fresh/repeat installation, recovery at all 31 statement boundaries, settings/scan-data preservation, PHP lint and existing log-classification regressions. Archive tests verify version-specific updater metadata, payload byte identity, ZIP integrity and the Studio icon path. A contract harness exercises the official UNA updater class with isolated filesystem/database adapters; it is not a complete native Studio acceptance test.

Native UNA Studio installation/update acceptance and AWS RDS acceptance were **not performed for this release**. The original client's repaired-install success is client-reported. Test the matching updater on a backed-up staging copy before rolling it out to additional client sites, then verify Studio version 0.6.6, launcher, saved settings, scan history and a new full scan.

`SHA256SUMS` records the finished ZIP hashes. `RELEASE-EVIDENCE.json` records the exact source commit, artifact sizes and runtime payload hashes. Previously published releases are unchanged.
