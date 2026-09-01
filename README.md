# My UNA Doctor

**Studio-only UNA CMS health diagnostics**

Version: **0.1.10 — Diagnostic Test Build**  
Vendor: **NewtronJax**

My UNA Doctor scans an UNA installation for common system, filesystem, database, module, notification, cron and log problems, assigns a health score, and records scan history inside UNA Studio.



## 0.1.10 evidence presentation refinement

Version 0.1.10 keeps the 0.1.9 diagnostic and scoring engine unchanged while refining Studio evidence presentation. Health impact and Repair required now render as separate lines, and placeholder-only technical evidence trees are suppressed so routine healthy findings stay concise. Meaningful technical values remain available for support and diagnosis.

## 0.1.9 Storage & Media Inspector

Version 0.1.9 adds a read-only Storage & Media section. It verifies relational integrity for UNA Albums and Timeline media: Albums media-to-source links, media-to-album links, album thumbnail source references, Timeline photo/video/file source relationships, and Timeline media-to-event relationships. It does not delete or rebuild media. Physical storage-backend verification is deliberately deferred until Doctor can identify local versus remote storage safely.

The Studio findings renderer now shows human-readable evidence summaries, affected IDs/relationships, health impact and whether repair is required, while preserving the detailed JSON under Technical evidence. The log classifier also understands verified stale Timeline media requests and treats residual transcoder cache rows as cache evidence rather than live content references.


## 0.1.8 health-aware log classification

Version 0.1.8 distinguishes current health-impacting log failures from informational history. Verified Albums transcoder requests for source IDs that no longer exist and have no live Albums/media/thumbnail/resized references are classified as stale media requests and do not reduce the health score. Explicit Studio module-operation failures remain visible as administrative history instead of being treated as runtime site-health failures. Unknown or unverified transcoder/storage errors remain health-impacting.

## 0.1.7 presentation consistency update

Version 0.1.7 removes stale hard-coded release numbers from the Studio Safety Model and synchronizes the package documentation with UNA's installed module version. Diagnostic behavior is unchanged from 0.1.6.

## 0.1.4 scan-quality correction

- Warning/critical evidence is expanded by default and encoded with invalid UTF-8 substitution.
- Cron findings now show the registered file, resolved path, job ID and enabled state.
- Log analysis ignores backup/archive files, no longer counts generic uses of the word `Exception`, groups repeated signatures, and provides redacted sample lines.
- Scan history JSON now survives malformed byte sequences in log evidence.

## 0.1.4 first-test correction

The Modules diagnostics page uses the internal route `module_health` and method `getModuleHealth()` so it does not override UNA Studio's inherited `BxDolStudioPage::getModules($bShowCustom = true, $bShowSystem = true)` helper.

## Safety model

This build is deliberately diagnostic-only. Running a health scan reads the UNA environment and writes only My UNA Doctor's own scan-history row. It does not repair, delete, rewrite or alter UNA module registrations, notification handlers, cron jobs, configuration files or core source.

## Studio location

After installation, open **Studio → My UNA Doctor** and choose **Run Full Health Scan**.

## Package layout

The installation ZIP begins with the UNA vendor/module folders:

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

See **INSTALLATION.txt** before the first test installation.

## v0.1.4 log-age diagnostics

- Parses individual UNA/PHP log-entry timestamps instead of treating a recently modified log file as if every line were recent.
- Health score is affected by actionable entries from the last 24 hours; 1-7 day and historical entries remain visible as context.
- Groups multiline UNA module-operation errors with their continuation/detail lines.
- Normalizes timestamps, media IDs and volatile request parameters when grouping recurring signatures.
- Expands log-tail inspection to 256 KiB while keeping the scan read-only.

## Updating from 0.1.3

Use the separate `My-UNA-Doctor-UPDATE-0.1.3-to-0.1.4.zip` through UNA's normal update workflow. The update package uses `version_from`/`version_to` and `BxDolStudioUpdater`; manual editing of `sys_modules.version` is no longer part of the normal update procedure.


## UNA Marketplace / Studio Store artwork

The package includes a root-level `cover.jpg` (1536×835), matching the module packaging convention used by the MasterGate reference. `template/images/icons/std-icon.svg` remains the Studio launcher icon. UNA Studio `store.php` retrieves product-detail popup data from the UNA Marketplace API, so a custom/unpublished module may still show “info can’t be loaded” until a marketplace product record exists for `my_una_doctor`.


## 0.1.5 market presentation update

For sites already on 0.1.4, use the dedicated 0.1.4 → 0.1.5 updater. This adds
the root-level `cover.jpg` and marketplace package notes without downgrading or
re-running an older updater. The UNA Studio Store details popup itself is backed
by the UNA Marketplace service, so a remote Marketplace product record is still
required for full product details to load.
