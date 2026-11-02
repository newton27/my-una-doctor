# UNA 15 compatibility audit — October 28, 2026

## Confirmed scope

| Module/ref | Finding | Disposition |
| --- | --- | --- |
| Doctor published 0.6.6 | Full ZIP plus five updater ZIPs use 15.0.0-A1, including updater payload configs | Preserve tag/assets; prepare 0.6.7 with direct 0.6.1–0.6.6 paths |
| Translator PR #3, 0.1.11 | Installer, 0.1.10-to-0.1.11 updater and payload regress to 15.0.0-A1 | Correct all three to 15.0.x; preserve historic 0.1.9-to-0.1.10 updater |
| Translator PR #2, 0.1.10 | Branch installer uses 15.0.0-A1 | Correct branch installer; no republishing of existing artifacts |
| Mail Doctor development 0.6.0 | Installer, 0.5.1-to-0.6.0 updater and payload use 15.0.0-A1 | Correct candidate metadata; no production deployment |
| TV PRs #1/#2; Stream Doctor PR #1; Tickets PRs #1/#2; LightGallery PR #1; Events PR #3 | Current installer manifests use 15.0.x | No installer correction needed |
| World 1.3.4, Jitsi 0.1.0, Finances 0.1.1, Weather 0.1.1, Mail Doctor main 0.5.1, Module Doctor 0.1.1, Styled Posts main 0.1.0 | Current main installer manifests use 15.0.x | No installer correction needed |
| Historical Translator rc4 branch and Mail Doctor 0.2.0-to-0.3.0 updater | Older declaration retained | Historical evidence, not certified current release candidates; no blind rewrite |

This is a source audit, not proof that every Marketplace download or deployed site matches these branches. Modzzz's legacy compatibility claims are outside the My UNA World release policy and were not changed.

## Official native requirements

Official Paid Levels (`modules/boonex/acl/install/config.php`) and Plyr (`modules/boonex/plyr/install/config.php`) use 15.0.x. The pinned official UNA installer at commit 8f45e86cf7bcdffb9096aba05c12c2f14a71c209 uses `_isCompatibleWith()` with `version_compare(current, required, '>=')`. This is a minimum-version comparison, not an upper-version limit. Isolated execution of the unchanged official class confirms 15.0.x rejects 14.0.0 and accepts 15.0.0-A1, 15.0.0-RC2, 15.0.0, 15.0.4 and 16.0.0. No core method is changed.

Native updates also require an exact installed `version_from` match and retain modified-file/checksum protections. Metadata normalization alone does not demonstrate a previously failing native install has been fixed.

## Published Doctor package evidence

Recovered Actions artifact 12661803348 from run 40347487801. Outer SHA-256: 4ccf5be819361ae4b127e373839cc693c96fc0159490d5d5a21eaee7804a66bc. All inner ZIP hashes match the GitHub published v0.6.6 asset digests; all archives pass integrity checks.

| Asset | SHA-256 |
| --- | --- |
| my-una-doctor-0.6.6.zip | affeb19e97bc35fc2814ea34aa58714da46dc429a9a53df9ca0abeb2dbda03c6 |
| my-una-doctor-0.6.1-to-0.6.6.zip | da07d1037832a0e06480ec36a475f4c198b16d51a11717714b4ea52398d1676a |
| my-una-doctor-0.6.2-to-0.6.6.zip | e0ff02a02ccac0f5f2e6052148c0648271541b7deb4696d0c36a11ac05cad84d |
| my-una-doctor-0.6.3-to-0.6.6.zip | d3610c32b1dc0852b3250eaccd28e867bae62a62e118e6e3209c009b7883818d |
| my-una-doctor-0.6.4-to-0.6.6.zip | 31da21f29b6d040a5dd35a822104b66923dd2f428ab443a023b3cc48dac615c5 |
| my-una-doctor-0.6.5-to-0.6.6.zip | 0fabd2a6c5d1095a190c4abc65c03f9d644ef6f5a3bc347c3fa944d67a39a9fe |

## Validation and remaining gates

- Translator candidate: complete suite passes on isolated PHP 8.3.6: 26 PHP groups, 16 Node tests, 11 Python tests, PHP/JS syntax and diff checks. Added compatibility test failed on all three manifests before correction and passed afterward.
- Doctor: package regression reproduced the generated old declaration before correction. Source validator passes: PHP lint, six log regressions, five Python tests; two database integration tests require a disposable service and are skipped locally.
- Temporary Doctor 0.6.7 builds pass ZIP/content contracts. All six updater paths pass the existing isolated official-updater contract, including wrong-version rejection and modified-file rejection. The contract uses database/platform adapters and is NOT native Studio validation.
- Unchanged official installer compatibility checks pass for candidate manifests and generated updater payloads. The isolated test substitutes only the version input and constructor setup; it does not change the core compatibility method.
- BLOCKED: no configured disposable UNA site, database baseline, authenticated Studio session or rollback checkpoint is available in this workspace. Native fresh installs, all affected native updater paths, settings/scan preservation and visual acceptance are NOT TESTED. Do not certify or publish based on source/contract checks alone.
- Release/tag/asset changes and Marketplace uploads remain separate approval steps after native validation. No UNA core files, published tags or release assets were modified.

## Marketplace plan

Update only affected listings/packages. Doctor needs a newly versioned full installer for new customers and native updaters for existing installations, including 0.6.6. Unpublished Translator and Mail Doctor candidates can be corrected before release. Modules whose current distributed bytes already declare 15.0.x do not need a compatibility-only release. Existing Marketplace ZIPs still need byte-level comparison before calling the entire collection compliant. Replacing a new-install download does not upgrade installed client sites.

## Committed corrections and CI evidence

- Translator PR #3: a538bb5ef6b976d4c39f32120ff8cca838c2fd52. Three manifests, regression and validation notes corrected; full local suite passes.
- Translator PR #2: 40bcc300448e468fe079278888f967d5edd73759. Branch installer corrected; PHP lint and unchanged official compatibility method pass.
- Mail Doctor 0.6.0 development branch: bf7c7ad88c715b8b1f53ebd061af683ec38951fe. Three manifests, regression and documentation corrected. CI run 40814879676 passes all eleven PHP test scripts, all PHP lint and the tracked-source credential scan.
- Doctor PR #5 runtime/source checkpoint: 66d97e90cdfe23d8650f5cc2be9adeaa9dd5a886. CI runs 40814749750 and 40814749800 PASS on MySQL 8.0 and MariaDB 10.11. The two locally skipped database tests both pass in CI, including interruption/retry and saved-data checks. These are module SQL regressions, not native Studio acceptance.
- Artifact 12871837711 passes independent downloaded-byte inspection: all seven ZIP hashes/sizes match RELEASE-EVIDENCE.json; ZIP integrity and every installer/updater compatibility declaration pass. The workflow originally built GitHub's PR test-merge commit 825daac9fb72868c30ea7d75322b8c5582e7ae62 (tree 157b5fdb29a0c256e266c80173d61af50863f4a3), which is the source recorded in that evidence. The candidate workflow now explicitly checks out the PR head for subsequent builds; retain the recorded merge provenance for this earlier artifact.
- Source review reported no material findings. Native Studio fresh installation, every native updater, launcher/info popup, real settings/history/scan acceptance remain NOT TESTED. No certification, deployment, merge or release approval is implied.

Candidate package hashes (validation evidence, not a frozen release):

| Asset | SHA-256 |
| --- | --- |
| my-una-doctor-0.6.7.zip | 4a17b3e728368bf9087fb3f42da83ebec35be1b7dd8fef59d20ab4028d67f43b |
| my-una-doctor-0.6.1-to-0.6.7.zip | ff8a9dc8dbcb583992821a7b20f3fd54e95d57f034617fa7aa231d875eb7847d |
| my-una-doctor-0.6.2-to-0.6.7.zip | 087be166bb3b41782fa9d9d95636591e33c7f9d59ad29a65c182f002c2d068f1 |
| my-una-doctor-0.6.3-to-0.6.7.zip | 7d0b982903a9708fb5cbde74e2573a457d3b8b4677cf018c22d013f5d1966fe0 |
| my-una-doctor-0.6.4-to-0.6.7.zip | d72228d2ee1b1485e0cba9a418a5d31514ae7bf5ec1b777d7a97aaed9fe0e66a |
| my-una-doctor-0.6.5-to-0.6.7.zip | 119c9b34ef42a04c701e2b0619f6811f4fdfb156a99096fedc18eb5d67a3e1e8 |
| my-una-doctor-0.6.6-to-0.6.7.zip | a18b1d89f457ca98576919fc4f3782265f90ef7d636439417337dd1beae55ea5 |
