# My UNA Doctor 0.6.1 — Release Checkpoint

Version 0.6.1 is a packaging-integrity and clean-uninstall release for My UNA Doctor.

## Release focus

This release retains the Compatibility & Upgrade Intelligence introduced in 0.6.0 while strengthening module installation, packaging and removal reliability on UNA 15.

## Corrections

- UNA install SQL uses one complete SQL statement per physical line.
- UNA uninstall SQL uses one complete SQL statement per physical line.
- The `my_una_doctor_repair_knowledge` table is now removed during uninstall.
- Every My UNA Doctor table created by `install.sql` has a corresponding uninstall operation.
- All 11 My UNA Doctor settings are covered by uninstall cleanup.
- Studio page/widget registration has matching uninstall cleanup.
- Development and backup artifacts are excluded from release packages.
- Full-package ZIP layout uses the single `my_una_doctor/` wrapper expected by UNA.

## Validation

The 0.6.1 package was built from the verified My UNA Doctor 0.6.0 working checkpoint and validated against a certified pristine UNA 15.0.0-RC1 installation.

Validation includes:

- SQL physical-line safety
- install/uninstall table symmetry
- settings cleanup symmetry
- Studio registration cleanup
- PHP 8.3 syntax validation
- ZIP structure validation
- package SHA256 generation

## Version

- Version: 0.6.1
- Target baseline: UNA 15.0.0-RC1 and later compatible UNA 15 builds
