---
name: no-double-dash-in-xml-fixture-comments
description: a -- inside an XML comment in a Psalm fixture makes Psalm report "Missing psalm node"
metadata:
  type: project
---

XML forbids `--` inside a comment. In a Psalm config fixture, Psalm then reports `Missing psalm node`, which names the wrong problem (#934).

**Why:** the error points at the config structure, so the real cause is hard to find.

**How to apply:** never write `--` inside `<!-- -->` in an XML fixture; use a single dash or reword.
