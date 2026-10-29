---
name: infection-mangled-var-directory
description: composer infection can create a \\ar/ directory that breaks composer archive; use git archive
metadata:
  type: project
---

`composer infection` writes into fixture directories, and a mangled path can create a directory such as `\\ar/...` (from `/var/...`). It is gitignored and skipped by Rector, but it breaks `composer archive`.

**Why:** a release check with `composer archive` fails on a directory that never ships.

**How to apply:** use `git archive HEAD` to see what ships; Packagist builds from it too.
