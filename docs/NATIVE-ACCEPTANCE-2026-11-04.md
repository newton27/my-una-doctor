# Doctor 0.6.8 native acceptance — November 4, 2026

This record covers the icon-corrected packages built from `0628dd7e6eab7d2af3d7ac21c98b63c9ea9ac96e`. The executing deployment agent supplied the native observations below; this documentation step rechecked the locally frozen package hashes and did not repeat the server actions. Evidence-only commits containing this record do not replace the authoritative build checkpoint or alter any ZIP.

## Exact packages and safeguards

| Package | SHA-256 |
| --- | --- |
| Full installer: my-una-doctor-0.6.8.zip | b737619e55df97a26e964b48ac232718aec7a65ce048470ccdcb49bc62a8c865 |
| Tested update: my-una-doctor-0.6.6-to-0.6.8.zip | fc2b6c87a67a235299708a457235d379ff18b6a3abd3fd1aca37c2acef51e587 |

CI artifact 13362661634, run [41910894472](https://github.com/newton27/my-una-doctor/actions/runs/41910894472), has outer SHA-256 `c765023dfa0599f720fb2669f88ce82ba36669e5d847f688715a358fdc4cf788`. Its eight inner ZIPs match the frozen local artifacts. All five candidate/SQL CI jobs passed at the exact source checkpoint, including MySQL 8.0 and MariaDB 10.11. Static validation remains distinct from the native observations below.

## Native 0.6.6 → 0.6.8 update on duvallocals.com

| Gate | Observed evidence | Status |
| --- | --- | --- |
| Authentic baseline | Registered and config versions both 0.6.6; enabled module; native changed-files/integrity result `[[],0]` | PASS |
| Rollback checkpoint | World full backup 7, `my_una_world_full_20261104_205536.zip`, completed; SHA-256 `83bc1891d5c9a26effc3b08906d170cb09960021b7ea68580936c14577f0df77`; restore dry run returned can_restore=true and no issues | PASS |
| Native staging | Actual UNA performUnarchive and performCopy staged the exact 0.6.6 updater | PASS |
| Native application | Actual Studio → Apps → Downloaded → Install click succeeded | PASS |
| Version and enablement | Registered and config versions both 0.6.8; enabled=1 | PASS |
| Installed payload | All 37 installed files byte-identical to target full ZIP; native changed-files/integrity `[[],0]` | PASS |
| Saved options | All saved module options unchanged; canonical option snapshot SHA-256 before and after `418c03735845d99a4b4fd0f7836b55caed4ed517dd6ed53d13fea315b33b73` | PASS |
| Data preservation checks | All module table row counts unchanged | PASS for counts checked |
| Native icon rendering | All visible Doctor UI icons had glyphs; Overview and scan heartbeat U+F21E, Modules puzzle-piece U+F12E; screenshot observed | PASS |
| Real scan | Installed runHealthScan completed in 0.732166 seconds; PHP exit 0 and empty stderr | PASS |
| Guest HTTP barrier | Independent no-cookie HTTPS statuses `[200,503,503,503,503,200,200]`; all four 503 responses contained the mud-maintenance marker | PASS |
| Native settings restoration | All three original maintenance/compression options restored exactly | PASS |

The backup restore result above is a dry run; no actual backup restoration is claimed. Row-count equality establishes the checked preservation scope and does not establish complete row-content identity.

Private server scan evidence is retained at:
`/home/duvallocals/Maintenance-Archive/2026-11-04-marketplace-updates/doctor-scan-native.json` and
`/home/duvallocals/Maintenance-Archive/2026-11-04-marketplace-updates/doctor-public-native.json`.

## Fresh native installation on gayjax.online

| Gate | Observed evidence | Status |
| --- | --- | --- |
| Clean module baseline | Doctor directory and module registration both absent before staging | PASS |
| Rollback checkpoint | World full backup 1, `my_una_world_full_20261104_211840.zip`, completed; SHA-256 `6147d2b0c600d306d40345b5804178afaae91dc2db3ec3aad45bc93183e34cff`; restore dry run returned can_restore=true and no issues | PASS |
| Native staging and install | Exact full ZIP extracted/copied through native UNA functions; UNAInstallerUtils perform install with auto_enable in the authorized Webmin PHP native cron context returned code 0 | PASS |
| Version, enablement and integrity | Registered/config 0.6.8, enabled=1, native changed-files/integrity `[[],0]` | PASS |
| Fresh Studio launcher and page | Studio login prompt prevented checking the fresh installation's UI; Doctor UI was independently verified on Duvallocals | NOT TESTED on fresh site |

The fresh installation used native installer execution in the cron context; it was not a Studio installation click.

## Screenshot evidence

The deployment agent captured and reviewed a browser clip showing the native Doctor header/sidebar, both heartbeat icons and the Modules puzzle-piece after the Duvallocals update. The screenshot remains in the private acceptance evidence. Its size is 27,796 bytes and SHA-256 is `18e6d0169547b2500ec6cc88019e091235f02bf6e76e2c00626587cb5d375e4b`. The image is not included in this source record.

## Remaining gates and publication

The current 0.6.6 → 0.6.8 native route and fresh native installer execution have passed with the exact packages above. Other six direct native routes remain NOT TESTED for these bytes. Fresh-site Studio launcher/page access, the Downloaded information popup, and eligible-account Marketplace automatic discovery/download remain separate checks. Runtime recovery-cron registration was not separately reported in this new test window.

The earlier e18a2e66 0.6.4 → 0.6.8 acceptance record remains evidence for its original frozen packages only. This record adds acceptance for the new icon-corrected packages and does not rewrite that historical evidence.

A Marketplace full-installer upload attempt timed out. No attachment was observed, Submit was not clicked, and no publication was performed. Published GitHub tags, releases and assets were unchanged.
