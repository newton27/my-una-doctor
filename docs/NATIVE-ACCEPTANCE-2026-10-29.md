# Native compatibility acceptance — gayjax.life — 2026-10-29

## Environment and rollback

- Site: https://gayjax.life/ (user-designated test site), UNA 15.0.0-RC3, PHP 8.3.6.
- My UNA World backup ID 8: my_una_world_full_20261029_054103.zip, completed 05:41:03 UTC (01:41:03 EDT).
- Backup SHA-256 b70e400526aa915ba28829068024a19a901e05fe2e47a9a629449aa86f94a68a. Inspection confirms matching digest, valid ZIP, database present and same-site restore eligibility.
- A separate private Doctor module tar and scoped table/settings snapshots were retained. No credentials or customer data are included in this report.
- Source checkpoint: 3acc819b668c5fd0b62306d66b03d46cc62772b0. CI artifact 12872520381 SHA-256 f4d759688c8fde26bd9e8cd9f4e897a40e24ccf80c47196aa48b1b4c59e91f99. All seven inner ZIP digests, sizes and integrity checks pass on the server.

## Native update: 0.6.6 to 0.6.7 — PASS

- Exact updater my-una-doctor-0.6.6-to-0.6.7.zip; SHA-256 a18b1d89f457ca98576919fc4f3782265f90ef7d636439417337dd1beae55ea5.
- Initial registration was genuinely 0.6.6, enabled. No installed-version row was edited to manufacture an update path.
- Manually extracted only the matching update directory into modules/newton/my_una_doctor/updates/. Executed Install under Studio Apps → Downloaded → Updates.
- Studio and the database report 0.6.7 after completion.
- All 11 saved settings and the complete contents of all four Doctor tables match their pre-update snapshots, including two reference records and four existing scans.
- All 1,186 checked core files (inc/, studio/, and root PHP files) retain their pre-test SHA-256 hashes. This inventory is explicitly not a claim that every file under plugins/ was hashed.
- Downloaded info popup loads successfully. The Marketplace response currently advertises 0.6.2 while its text describes 0.6.6; the listing was not edited.
- A full scan completed at 05:47:05 UTC in 237 ms under Doctor 0.6.7, creating scan #5.

## Clean Studio installation: 0.6.7 — PASS

- Exact full installer my-una-doctor-0.6.7.zip; SHA-256 4a17b3e728368bf9087fb3f42da83ebec35be1b7dd8fef59d20ab4028d67f43b.
- After preserving scoped data/settings, removed only Doctor using UNA's unmodified native uninstall implementation (CLI result code 0).
- Moved the previous module directory into private rollback storage; extracted the full ZIP into a clean module directory. Other modules were not uninstalled.
- Installed through authenticated Studio Apps → Downloaded. Native registration is 0.6.7, enabled; all four Doctor tables were initially empty.
- Every installed payload file matches the full package SHA-256 inventory. The Studio icon renders (non-zero natural image width).
- The fresh overview explicitly reported no recorded scans. Its first scan completed under 0.6.7 at 05:58:25 UTC in 414 ms.
- Restored the scoped Doctor data and settings after this disposable fresh-install cycle, without changing native module version/identity fields. Final state retains five prior scans (four original plus the post-update scan), two reference records and all 11 original setting values. Row-content comparisons pass; all 1,186 checked core hashes remain unchanged.
- A test helper initially treated a native no-op setting write as failure. Verification was corrected to compare persisted values; all values match. This required no module or core fix.

## Observations and limits

- The site's existing Force modified files setting was ON. No checksum/version protection setting was changed. This acceptance does not certify the checksum-gate OFF configuration.
- The scan reports filesystem free space near 5% (about 12 GB), and eight modified tracked files in LightGallery, Module Doctor and TV. None of these listed drift files belongs to Doctor. No drift was re-baselined or repaired.
- A full private clone would exceed available space; a source-only private fixture could not be completed because database-admin CLI access was unavailable. Clean installation was therefore tested on the designated test site with scoped backup/restoration.
- Native updates from 0.6.1 through 0.6.5 remain NOT TESTED: no genuine installed baselines for those versions were available. Their package and isolated official-class contracts pass; those are not a substitute for native acceptance.
- Rollback archive inspection was tested; a full-site restore was not executed.
- No UNA core source edits, manual installed-version changes, tags, release assets or Marketplace uploads were made. PR #5 remains draft. Release freeze/publication requires the remaining gates and separate approval.
