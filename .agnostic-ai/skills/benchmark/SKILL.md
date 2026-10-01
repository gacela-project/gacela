---
description: Run performance benchmarks, create baselines, and compare results
argument-hint: "[run|baseline|compare|filter]"
disable-model-invocation: true
x-claude:
  allowed-tools: "Read, Bash(composer *), Bash(./vendor/bin/phpbench *), Bash(git *)"
x-codex:
  interface:
    display_name: "Benchmark"
    short_description: "Run, baseline, or compare PHPBench results. Args: run, baseline, compare, or a filter"
---

# Benchmark Runner

## Context

::target claude
!`git branch --show-current`
!`git log --oneline -1`
::end
::target codex
Run `git branch --show-current` and `git log --oneline -1` first.
::end

## Instructions

1. If the argument is empty or `run`:
   ```bash
   composer phpbench
   ```

2. If the argument is `baseline`, tag the current code as the baseline:
   ```bash
   composer phpbench-base
   ```
   Report the commit the baseline was taken on. Future `compare` runs diff against it.

3. If the argument is `compare`:
   ```bash
   composer phpbench-ref
   ```
   Highlight regressions (over 5% slower) and improvements (over 5% faster).

4. If the argument looks like a class or method filter:
   ```bash
   ./vendor/bin/phpbench run --filter="<argument>" --report=aggregate --ansi
   ```

5. Report:
   - Mean time and memory per affected benchmark class.
   - Regressions and improvements when comparing, with the baseline commit.

## Reading results

- Sub-microsecond subjects move on noise. Re-run an anomalous reading before calling it a regression.
- A local A/B on a warm machine can fake a win. Interleave runs, or run the same code on both sides first to see the harness bias.
