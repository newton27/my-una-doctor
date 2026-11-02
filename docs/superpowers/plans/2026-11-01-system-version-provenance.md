# UNA System version provenance correction

The reported RC3 site has 86 successfully checked module packages, zero mismatches and one unverified System package. UNA's System manifest declares `version => BX_DOL_VERSION`; Doctor currently recognizes quoted versions only. UNA's current `inc/version.inc.php` declares `15.0.0-RC3`.

- [x] Reproduce the false unverified result in an offline scan with 86 ordinary packages and the System package.
- [ ] Recognize only the exact `BX_DOL_VERSION` value token for the System module, using the already-loaded core constant without executing package PHP.
- [ ] Keep genuine mismatches, unsupported expressions, unknown/undefined constants and ordinary module behavior protected by tests; run the missing-constant case in a separate PHP process.
- [ ] Run the existing source, SQL, package and native-class contract CI; record the new candidate in draft PR #5. Preserve published assets and historical native evidence, and require a new native RC3 scan/final source freeze for the changed runtime.

No UNA core, database version, live site, tag, release or Marketplace change is part of this source correction.
