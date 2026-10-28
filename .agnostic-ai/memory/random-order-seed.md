---
name: random-order-seed
description: phpunit runs in random order; reproduce a flaky failure with --random-order-seed
metadata:
  type: project
---

The phpunit suites run in random order, and PHPUnit prints the seed of each run.

**Why:** a test that fails only sometimes usually depends on state another test left behind, not on the change under review.

**How to apply:** re-run with `./vendor/bin/phpunit --random-order-seed=<seed>` to reproduce the order before blaming the change.
