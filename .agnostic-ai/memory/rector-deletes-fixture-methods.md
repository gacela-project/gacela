---
name: rector-deletes-fixture-methods
description: composer fix lets Rector delete or privatize fixture methods that are unused on purpose
metadata:
  type: project
---

Rector, run by `composer fix`, deletes or privatizes test fixture methods that look dead but exist on purpose, for example to be found by reflection or by a static-analysis rule under test.

**Why:** the fixture then stops exercising the case its test asserts, and the test fails or passes for the wrong reason.

**How to apply:** after `composer fix`, review what Rector changed under `tests/`. Revert a fixture edit and add the file to the skip list in `rector.php`.
