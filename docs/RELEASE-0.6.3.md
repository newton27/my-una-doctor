# My UNA Doctor 0.6.3 Release Checkpoint

## Release focus

Activity feedback and long-running action UX.

## Changes

- Reference Package upload comparisons now display Doctor activity feedback.
- Saved Reference Package comparisons now display Doctor activity feedback.
- Full Health Scan requests provide visible long-running activity feedback.
- Verified repair Preview requests provide visible activity feedback.
- Read-only Preflight requests provide visible activity feedback.
- Duplicate-click protection prevents accidental repeat submission while these actions are active.
- Browser back/forward-cache restoration clears stale Doctor activity overlays and busy states.

## Safety

These changes are presentation and interaction safeguards. They do not weaken repair barriers, alter reference packages, or introduce new database mutation behavior.

## Compatibility

Retains the 0.6.2 UNA upgrade-poll log classification behavior and all 0.6.1 packaging-integrity and clean-uninstall corrections.
