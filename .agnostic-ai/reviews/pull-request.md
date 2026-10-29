---
targets: [codex]
---

- **Module boundaries**: reach another module through its Facade only; build services in the Factory.
- **Naming**: intention-revealing (`$resolvedService`, not `$rs`); pillars keep their suffix.
- **Functions**: under 20 lines, one job, at most 3 arguments.
- **Side effects**: a method is a query or a command, not both.
- **Errors**: specific exceptions, fail fast, no generic `\Exception`.
- **Types**: `final` classes, `readonly` properties, explicit return types.
- **Dead code**: no `var_dump`, `dd`, `print_r`, or commented-out blocks.
- **Tests**: new behavior has a test that passes in any order; paths use `DIRECTORY_SEPARATOR`.
- **Docs**: a user-facing change has its changelog entry and updates `docs/` pages that state the old behavior.
