# Repair Barrier 2

Repair Barrier 2 is the control boundary used by My UNA Doctor before any supported repair workflow is allowed to manipulate runtime content.

Its purpose is to reduce concurrent writers, preserve a usable UNA Studio recovery interface, and retain enough durable state to restore the site exactly if a later stage fails.

## Design principle

**Diagnose first. Repair only when the evidence is clear.**

A repair plan is not permission to mutate the site. Preview, preflight and arm planning remain read-only. Mutation begins only after explicit administrator approval and after Doctor has persisted the original state it will need for rollback.

## The three-part barrier

The armed barrier consists of:

1. **Template-cache compression disabled**
2. **UNA soft maintenance enabled**
3. **Only the verified generator cron paused**

All three are verified independently before the journal is allowed to reach `armed`.

## Why template compression must be disabled first

During testing, UNA soft maintenance correctly kept Studio available to an administrator, but Studio styling broke.

The root cause was not missing CSS. With `sys_template_cache_compress_enable = on`, UNA generated stylesheet links through `gzip_loader.php?file=...`. Under soft maintenance, `gzip_loader.php` returned HTTP 503 even though the Studio HTML remained accessible.

With template compression disabled, UNA emitted direct `/cache_public/...` asset URLs instead. Those assets remained available during soft maintenance, so Studio stayed fully styled and usable as the recovery interface.

Therefore the order is intentional:

1. persist journal,
2. disable compression,
3. verify compression is off,
4. enable soft maintenance,
5. pause the verified generator,
6. verify the full barrier.

Enabling maintenance before disabling compression can leave the administrator with a degraded recovery UI.

## Arm sequence

For a supported repair candidate, Doctor performs the following guarded sequence:

1. Revalidate the approved preflight and arm plan.
2. Snapshot the exact current value of `sys_template_cache_compress_enable`.
3. Snapshot the exact current UNA maintenance state.
4. Snapshot the exact registered generator cron schedule.
5. Persist the durable repair journal **before mutation**.
6. Set template compression to the target off value (`''`).
7. Verify the live value is exactly off.
8. Enable UNA soft maintenance.
9. Verify the maintenance state.
10. Change only the verified generator cron schedule to the impossible pause schedule `0 0 31 2 *`.
11. Clear the UNA cron cache and re-read the database row.
12. Verify compression, maintenance and cron state independently.
13. Mark the journal `armed`.

If any mutation or verification fails, Doctor attempts rollback using the durable original state already recorded in the journal.

## Why the pause schedule is used

UNA cron registration does not provide a generic enabled/disabled flag for every job. Doctor therefore pauses a verified generator by changing only its schedule to:

```text
0 0 31 2 *
```

That date cannot occur, effectively pausing the job without deleting it or modifying unrelated cron registrations.

Doctor must retain the exact original schedule and restore that exact string later.

## Disarm and rollback sequence

Restoration deliberately reverses the barrier in a safe order:

1. Restore the exact original generator schedule.
2. Clear UNA cron cache and verify the restored row.
3. Restore the exact original UNA maintenance value.
4. Verify maintenance is restored.
5. Restore the exact original template-compression value.
6. Verify compression independently.
7. Mark the journal restored only after all required checks pass.

Template compression is restored **after maintenance**. This prevents Doctor from re-enabling `gzip_loader.php` routing while soft maintenance is still active.

## Exact-state preservation

Doctor does not assume that the original state was a simple boolean. The repair journal stores the exact original configuration string wherever possible and restores that exact value.

The journal records, among other state:

- original template-compression value,
- original maintenance value,
- original generator cron schedule,
- target armed values,
- preflight ID,
- arm plan ID,
- execution phase,
- error state if restoration cannot be completed.

## Durable journal states

Relevant states include:

```text
planned
arming
maintenance_enabled
generator_paused
armed
runtime_protecting
runtime_protected
runtime_restoring
runtime_rollback_failed
restoring
rollback_failed
restored
```

A journal in an active or unresolved state is treated as a safety condition. Doctor should not begin another repair workflow until the existing journal is resolved.

## Backward compatibility

Older repair journals created before Repair Barrier 2 may not contain a template-compression snapshot. Rollback logic therefore checks for the presence of that key before attempting to restore compression.

New 0.3.4 journals include the compression snapshot and require it during the approved arm flow.

## Proven 0.3.4 cycle

The 0.3.4 implementation was tested live with a repair journal and verified through the complete barrier lifecycle:

```text
normal site
  -> journal persisted
  -> compression OFF
  -> soft maintenance ON
  -> verified generator paused
  -> ARMED
  -> exact generator restored
  -> maintenance restored
  -> compression restored
  -> RESTORED
```

The final state matched the original site state:

- maintenance off,
- template compression on,
- original generator schedule restored,
- runtime content not moved during the Barrier 2 proof,
- journal marked restored,
- no unresolved repair error.

## Safety invariants

Repair Barrier 2 should fail closed when any required fact cannot be established. In particular:

- do not arm if the target module or generator cannot be uniquely verified;
- do not arm if a hard maintenance condition would prevent controlled recovery;
- do not arm if an unresolved repair journal already exists;
- do not silently continue after a failed state verification;
- do not rewrite unrelated cron jobs;
- do not rewrite module checksum baselines as part of repair arming;
- do not start a module update merely because the barrier is armed.

The barrier is a prerequisite for later guarded work. It is not itself an update or repair of third-party source.