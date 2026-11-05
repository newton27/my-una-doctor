# Doctor 0.6.8 Marketplace publication — November 5, 2026

The Marketplace installer and the accepted native updater were published and independently downloaded on November 5. This is a publication receipt, not a new runtime checkpoint. The authoritative package source remains `0628dd7e6eab7d2af3d7ac21c98b63c9ea9ac96e`.

Product: https://api.unacms.com/view-product/my-una-doctor

Downloads: https://api.unacms.com/download-product/my-una-doctor

Marketplace product ID: 613.

| File ID | Package | Metadata | Bytes | SHA-256 |
| --- | --- | --- | ---: | --- |
| 7557 | my-una-doctor-0.6.8.zip | version 0.6.8; main installer | 1063652 | b737619e55df97a26e964b48ac232718aec7a65ce048470ccdcb49bc62a8c865 |
| 7558 | my-una-doctor-0.6.6-to-0.6.8.zip | update from 0.6.6 to 0.6.8 | 1065563 | fc2b6c87a67a235299708a457235d379ff18b6a3abd3fd1aca37c2acef51e587 |

## Verified result

- Saved download page identifies 0.6.8 as Latest version and lists the exact 0.6.6 to 0.6.8 update.
- Both actual Marketplace download links returned ZIPs with the expected byte counts and SHA-256 values; both passed ZIP CRC checks.
- Existing installers and 0.6.1/0.6.2 to 0.6.6 update routes were retained. Prior main installer 7465 was retained and is no longer Main.
- The saved listing describes maintenance during full scans, recovery, installed-version checks and the icon correction. It directs eligible Marketplace-connected installations to Studio → Apps → Updates, with 0.6.1/0.6.2 sites taking their existing 0.6.6 step first.
- Package source payloads are included. Clients using the matching native updater do not manually upload module files.

## Coverage and limits

Native 0.6.6 to 0.6.8 acceptance and fresh native installer execution are recorded in [NATIVE-ACCEPTANCE-2026-11-04.md](NATIVE-ACCEPTANCE-2026-11-04.md), including installed-file identity, native integrity, icons and guest maintenance 503/restoration evidence.

Other six prepared direct updater routes were not published: their exact icon-corrected bytes still lack native acceptance. Fresh-site Studio UI, the Downloaded information popup and eligible-client Marketplace automatic discovery remain NOT TESTED. Publication and vendor download verification do not establish a client site's purchase association or discovery result.

No runtime files, frozen ZIPs, GitHub tags, releases or release assets were changed by publication. This receipt supersedes the November 4 record's then-current unpublished status without rewriting that historical record. Signed download links and private site screenshots are excluded from this public document.
