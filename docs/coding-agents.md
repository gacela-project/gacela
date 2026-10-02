# Coding agents

A coding agent working in your project knows PHP in general, not Gacela. Left alone, it imports another module's classes directly, builds services with `new` outside the Factory, and never runs the checks that would catch either. Gacela ships a short guide that teaches it the module rules and the commands that verify them.

## Point your agent at the guide

```bash
vendor/bin/gacela agents:install
```

This writes a block into the project's `AGENTS.md`, creating the file if there is none:

```markdown
<!-- gacela:start -->
## Gacela

This project is split into Gacela modules. Before changing PHP code, read `vendor/gacela-project/gacela/resources/agents/gacela.md` and follow it. Check your work with `vendor/bin/gacela doctor --only-problems`.
<!-- gacela:end -->
```

- **A pointer, not a copy.** The guide lives in the installed package, so it always describes the version you run, and upgrading Gacela updates it with nothing to regenerate.
- **Safe to run again.** Only the text between the markers is ever rewritten. Everything else in `AGENTS.md` is yours and is left exactly as it was.
- **Most agents read `AGENTS.md`.** For Claude Code, add `@AGENTS.md` to your `CLAUDE.md`, or run the command and copy the block there.

## What the guide covers

[`resources/agents/gacela.md`](../resources/agents/gacela.md) is about a hundred lines, short enough to sit in an agent's context next to your own rules:

- the four pillars and how they are found by name;
- the rules: cross a module only through its Facade, Facades delegate, Factories build, Providers supply other modules;
- which way to get a dependency, pointing at [getting a dependency](getting-a-dependency.md);
- the commands that answer "is this right?" in one call: `doctor --only-problems`, `debug:graph --check`, `list:modules --json`, `debug:module`;
- the static analysis rules, and how to turn on the cross-module one;
- testing a module with `bootstrapModule()`.

It points at the docs in `vendor/gacela-project/gacela/docs/` for anything deeper, so an agent reads the page for the version you have installed.
