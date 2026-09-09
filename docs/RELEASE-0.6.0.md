# My UNA Doctor 0.6.0 — Release Checkpoint

Version 0.6.0 includes Compatibility & Upgrade Intelligence, the Module
Reference Library, verified-reference comparison, repair candidate
classification, SHA-bound repair preview, and read-only repair preflight.

## Highlights

- Detects the installed UNA minimum PHP requirement.
- Verifies required PHP extensions.
- Evaluates module compatibility and dependencies using UNA semantics.
- Checks UNA's configured upgrade channel for newer targets.
- Verifies staged official upgrade packages before static inspection.
- Does not execute staged upgrade code during a normal health scan.
- Maintains a verified Module Reference Library.
- Classifies baseline-backed, approved, runtime-generated and unexplained drift.
- Provides SHA-bound Verified Reference Repair Preview.
- Provides read-only Verified Reference Preflight.
- Keeps destructive verified-reference repair / Arm disabled at this checkpoint.
- Protects runtime-generated module content during guarded maintenance workflows.

## Marketplace identity

- Product: My UNA Doctor
- Internal module name: my_una_doctor
- Version: 0.6.0
- Vendor: My UNA World
- Package directory: my_una_doctor/

## Final package validation

- PHP package lint: PASS
- Development-only content excluded: PASS
- Historical update-evidence content excluded: PASS
- Package files: 22
- Package bytes: 1030849
- Package MD5: 30b2119d740035a4c24950e6f51fd758

## Release identity

Source commit:
1c4feac5575320973d83335c747728cf2f62b1dd

Authoritative archive SHA256:
c3750412f0537be447a4e0dcf88bd47ac96ae0b6afdf5e1387a5c55813ea4db7

Authoritative archive filename:
my-una-doctor-0.6.0.zip
