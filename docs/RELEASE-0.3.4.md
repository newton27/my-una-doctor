# My UNA Doctor 0.3.4 Release Checkpoint

Version 0.3.4 is the first deliberately preserved Git checkpoint for the current guarded-repair architecture.

## Release status

This checkpoint was validated before the Git tag was created.

### PHP validation

- PHP files checked: 9
- Lint failures: 0
- Result: all PHP files lint clean under PHP 8.3

### Doctor self-integrity

```text
OK:               YES
Version:          0.3.4
Tracked:          19
Current hashable: 19
Modified tracked: 0
Missing tracked:  0
New/untracked:    0
Studio changed:   0
Source integrity: clean
```

The 0.3.4 baseline update was limited to four verified Doctor source files that had intentionally changed during development:

- `classes/MyUnaDoctorDb.php`
- `classes/MyUnaDoctorModule.php`
- `classes/MyUnaDoctorScanner.php`
- `classes/MyUnaDoctorStudioPage.php`

No third-party module baseline was re-written as part of the Doctor checkpoint.

## Git identity

Known-good commit:

```text
e2a12b20a066e0f61c2cc911a8b22fd4d51ce1da
```

Commit message:

```text
My UNA Doctor 0.3.4 - known-good baseline
```

Annotated tag:

```text
v0.3.4
```

The tag points to the known-good commit above. Documentation added later on `main` does not move or modify `v0.3.4`.

## Proven repair capabilities at this checkpoint

### Repair Barrier 2

The live arm/disarm cycle was verified with:

- exact original template-compression snapshot,
- compression disabled before soft maintenance,
- verified generator pause,
- Studio remaining styled and usable during maintenance,
- exact generator restoration,
- exact maintenance restoration,
- exact compression restoration,
- repair journal ending in restored state.

### Runtime protection

The runtime-protection workflow was proven through:

```text
arm -> protect -> verify -> restore runtime -> verify -> armed
```

The large-tree test preserved a generated runtime population of 18,027 physical files / 3,059,207,616 bytes while keeping the legitimate tracked runtime file distinct from generated content.

### Integrity API

`getModuleIntegritySnapshot()` returns complete, untruncated integrity state for a module, including tracked, current, modified, missing, new/untracked and UNA Studio changed-file semantics.

### Post-update merge planning

A read-only merge planner exists for the later update workflow. It detects newly tracked collisions and live-path conflicts and does not overwrite ambiguous paths automatically.

## Safety boundaries

0.3.4 deliberately does **not** make the following operations automatic:

- third-party module checksum rebaselining,
- module update execution,
- collision overwrites during runtime merge,
- repair of ambiguous findings,
- continuation after unresolved repair-journal state.

The operating rule remains:

> Diagnose first. Repair only when the evidence is clear.

## Recovery value of this tag

`v0.3.4` is the source checkpoint to use when comparing later development against the first fully validated Repair Barrier 2 and runtime-protection implementation.

Future feature work should occur after this tag rather than rewriting it.