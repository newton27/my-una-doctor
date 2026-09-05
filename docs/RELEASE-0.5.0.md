# My UNA Doctor 0.5.0 — Release Checkpoint

Version **0.5.0** is the validated source checkpoint for the Module Reference Library and Maintenance & Upgrade State Diagnostics milestone.

Source release commit:

```text
75a55a493e95cee33e614ea0a8339af70fa62186
```

## Release scope

### Module Reference Library

The Studio-only **Module Reference Library** allows an administrator to preserve known-good or virgin module packages as repair references without treating them as live installed code.

The reference engine can:

- safely accept module ZIP packages;
- validate archive structure before extraction;
- identify module identity and version;
- build persistent reference manifests;
- compare installed source against a reference package;
- compare reference files against UNA tracked baselines when available;
- classify matching, modified, missing and live-only files;
- preserve comparison history;
- retain durable SHA-256 package identities;
- deduplicate repeated uploads by package identity;
- compare a saved reference again without requiring a new upload;
- preserve approved compatibility modifications and local repair knowledge.

The upload path retains the `is_uploaded_file()` safety check.

### Maintenance & Upgrade State Diagnostics

0.5.0 adds read-only maintenance and upgrade-state intelligence to the main health scan.

Current classifications include:

- `NORMAL`
- `SOFT_MAINTENANCE`
- `HARD_MAINTENANCE`
- `STALE_HARD_LOCK`
- `POSSIBLE_INTERRUPTED_UPGRADE`
- `DOCTOR_PROTECTED_REPAIR`

The detector distinguishes expected Doctor-controlled repair maintenance from unexpected UNA hard maintenance and from possible interrupted upgrade conditions.

Strong interrupted-upgrade correlation requires current evidence such as maintenance state plus UNA file/database version disagreement. Historical upgrade log entries are supporting evidence only and do not independently reduce health.

A six-hour hard-lock age threshold is used as a conservative diagnostic heuristic only. Doctor does not automatically remove `.bx_maintenance`.

### Upgrade Readiness Summary

The 0.5.0 readiness summary is deliberately scoped to **maintenance and upgrade state**. It is not yet a single universal upgrade verdict.

Current blockers include:

- active UNA hard maintenance;
- active UNA soft maintenance;
- UNA file/database version mismatch;
- an active My UNA Doctor repair journal.

Historical upgrade log signals are advisory and do not independently block readiness.

Future readiness dimensions can be added separately for source/checksum eligibility, module/database consistency, PHP/UNA compatibility, server health and backup/recoverability.

## Validation

Before the 0.5.0 release checkpoint was frozen:

- registered/source version: **0.5.0**;
- module enabled: **yes**;
- tracked files: **25**;
- tracked files matching baseline: **25**;
- modified tracked files: **0**;
- missing tracked files: **0**;
- all Doctor PHP files linted clean under PHP 8.3;
- UNA hard maintenance file: **absent**;
- UNA soft maintenance: **off**;
- UNA files version: **15.0.0-RC1**;
- UNA database version: **15.0.0-RC1**;
- Reference Library packages saved: **2**;
- Reference Library packages verified: **2**;
- Reference Library failed packages: **0**;
- historical reference comparison sessions: **12**;
- durable reference SHA identities: **2**.

### Synthetic maintenance/readiness matrix

Nine controlled cases were exercised against a temporary scanner copy without changing live UNA maintenance or repair state:

1. normal state;
2. soft maintenance;
3. hard maintenance;
4. stale hard lock;
5. interrupted-upgrade correlation with hard maintenance;
6. interrupted-upgrade correlation with soft maintenance;
7. Doctor-protected repair;
8. Doctor repair state mismatch;
9. unexpected hard maintenance during Doctor repair.

All nine cases passed the expected classification and readiness behavior.

## Frozen release evidence

The release freeze created:

- `my_una_doctor-0.5.0-source.tar.gz`
- `reference-library-0.5.0.tar.gz`
- `release-state.json`
- `module-sha256.txt`
- `freeze-sha256.txt`

The release freeze is an immutable checkpoint and should not be rebaselined casually.

## Safety model

My UNA Doctor remains evidence-first and fail-closed.

Maintenance diagnostics are read-only. They do not:

- clear `.bx_maintenance`;
- toggle UNA soft maintenance;
- alter cron state;
- begin a module update;
- rebaseline third-party source merely to make an update warning disappear;
- treat historical log text alone as proof of a current upgrade failure.

Protected repair workflows continue to require explicit staged controls and durable rollback evidence before mutation.