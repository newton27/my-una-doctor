# My UNA Doctor 0.6.4 Release Checkpoint

## Release focus

Reference Library Display Scaling.

## Changes

- Added a Text Size control to the Saved Module Reference Library.
- Supported range is 50% through 125% in 5% increments.
- Current scale percentage updates live while the slider moves.
- Reset returns the Reference Library display to 100%.
- The selected scale is retained using browser local storage.
- Reference Library typography uses relative sizing so titles, metadata, badges, hashes, notes, and evidence scale consistently.

## Scope

The control affects Saved Reference Library display content only.

It does not change UNA Studio navigation, Doctor review logic, module files, stored reference packages, immutable manifests, repair behavior, or database data.

## Safety

This release introduces no new database mutation behavior and does not weaken Doctor's repair barriers or reference-package protections.

## Previous release retained

My UNA Doctor 0.6.4 retains the activity feedback, duplicate-action protection, and long-running operation UX introduced in 0.6.3, together with all earlier packaging, uninstall, compatibility, and diagnostic corrections.
