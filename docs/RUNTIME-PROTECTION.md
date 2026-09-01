# Guarded Runtime Protection

My UNA Doctor 0.3.4 includes a guarded mechanism for temporarily separating verified runtime-generated content from tracked module source when generated files inside a module directory distort UNA Studio update readiness.

## Purpose

A third-party module can remain source-clean while UNA Studio reports a very high changed-file percentage because the module stores generated data in a hashable directory. Doctor keeps these concepts separate:

- tracked module source,
- modified tracked source,
- missing tracked source,
- new or untracked content,
- runtime-generated content.

Runtime protection is intended only for a verified runtime-content repair candidate whose tracked source remains clean.

## Guarded lifecycle

The current design is:

```text
Scan -> Detect -> Preview -> Preflight -> Arm Plan -> Approval
     -> Durable Journal -> Repair Barrier 2 -> Protect Runtime
     -> Verify -> Separate Update Stage -> Restore/Merge Plan
     -> Restore -> Rollback if required
```

Runtime protection does not itself execute a module update.

## Preconditions

Before any runtime transition, Doctor revalidates the approved state. Protection stops unless all required facts still match, including:

- expected repair journal state,
- active Repair Barrier 2,
- verified generator pause,
- expected runtime ownership and module path,
- same-filesystem safety destination,
- fresh runtime inventory,
- clean tracked runtime files,
- acceptable filesystem state,
- absence of symlinks, hardlinks and special files.

Ambiguity is treated as a stop condition rather than permission to continue.

## Deterministic inventory

Doctor inventories the runtime tree immediately before the protected transition and records a deterministic fingerprint. The inventory distinguishes physical totals from tracked and untracked counts and records enough metadata to verify the same content later.

This prevents a stale preview from being treated as proof that the live tree is still unchanged.

## Atomic whole-directory strategy

For a verified same-filesystem runtime path, Doctor uses a whole-directory atomic transition into the repair-safety journal area instead of processing thousands of generated files one by one.

The live runtime directory is then recreated with its expected metadata, and only legitimate tracked runtime files are returned to the live module path.

This design provides three important properties:

1. the large generated tree moves as one filesystem namespace transition;
2. there is no prolonged partially processed tree;
3. the original protected tree remains intact for exact verification and recovery.

## Filesystem metadata

Doctor records required runtime-root metadata such as mode, owner, group and filesystem device. Recreated runtime paths are verified against the recorded state rather than being normalized to arbitrary permissions.

PHP filesystem-stat caches are explicitly cleared before post-change metadata verification so cached values cannot produce a false result.

## Tracked runtime files

A runtime directory can contain legitimate tracked source alongside generated content. Doctor therefore verifies tracked runtime files against their stored module baseline before protection, then reconstructs the live runtime path with only those verified tracked files.

Tracked runtime files are never silently reclassified as generated content.

## Protected state

After successful protection:

- the original generated tree remains intact in the repair-safety journal location;
- the live runtime path contains only verified tracked module content;
- the original inventory remains independently fingerprint-verifiable;
- the repair journal records the protected transition;
- Repair Barrier 2 remains active.

The journal state becomes `runtime_protected`.

## Recovery and restoration

If a protection step fails after the atomic transition, Doctor has a recovery path designed to return the original protected tree to its live location and verify its original inventory. A failed recovery is recorded as an explicit unresolved repair state rather than hidden as a routine warning.

A successfully protected tree can also be restored deliberately while leaving the higher-level barrier armed. The proven state cycle is:

```text
armed -> runtime_protected -> verified -> runtime restored -> armed
```

The administrator can then disarm Repair Barrier 2 separately.

## Post-update merge planning

The read-only merge planner is conservative:

- previously tracked runtime source is not treated as generated content;
- files newly tracked by an updated module take precedence;
- generated/newly-tracked collisions block automatic overwrite;
- existing live-path collisions block automatic overwrite;
- parent-path type conflicts and symlink conditions block the plan;
- generating a plan does not mutate the filesystem.

## Proven large-tree test

The 0.3.4 runtime-protection implementation was proven against a test case containing:

- 18,027 physical runtime files,
- 18,026 generated/untracked files,
- 1 legitimate tracked runtime file,
- 3,059,207,616 bytes of runtime content,
- no symlinks,
- no hardlinks,
- no special files.

The runtime tree was protected, the tracked file remained available in the live module path, the protected original matched its recorded fingerprint, and the complete runtime tree was later restored successfully.

An early test exposed PHP stat-cache behavior during metadata verification. The recovery path restored the full original tree, and the implementation was hardened by clearing stat caches before verification. The subsequent live cycle completed successfully.

## Non-goals

Runtime protection does not:

- delete generated content,
- rewrite third-party module checksum baselines,
- treat generated content as tracked source,
- modify unrelated modules,
- execute a module update automatically,
- overwrite merge collisions,
- bypass an unresolved repair journal.

Protected runtime content remains recovery state until the administrator completes and validates the wider repair/update workflow.