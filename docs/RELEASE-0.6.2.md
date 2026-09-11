# My UNA Doctor 0.6.2 — Release Checkpoint

## Release focus

Packaging integrity, clean uninstall, and UNA upgrade-poll log classification.

## Packaging and uninstall corrections

- Install and uninstall SQL use one complete SQL statement per physical line for UNA's module SQL processor.
- `my_una_doctor_repair_knowledge` is removed during uninstall.
- Doctor-created tables have matching uninstall cleanup.
- Doctor settings and Studio registration cleanup are covered.
- Release ZIP layout uses the single `my_una_doctor/` wrapper expected by UNA.
- Development and backup artifacts are excluded from release packages.

## Log classification correction

The UNA core message:

`[upgrade] Cannot get a list of modules which require to be updated.`

remains visible as diagnostic evidence but is not treated as a current health-impacting error by itself. Separate upgrade-target, maintenance, compatibility, version, and checksum/readiness checks determine actual upgrade health.

Other unrelated error-level entries remain health-impacting.

## Retained public 0.6.1 functionality

Version 0.6.2 retains read-only saved-reference comparison, verified-reference drift classification, SHA-bound Preview, read-only Preflight, the disabled destructive repair barrier, and the My UNA World Marketplace identity introduced in public release 0.6.1.

## Identity

- Version: 0.6.2
- Vendor: My UNA World
- Target reference environment: UNA 15.0.0-RC1
