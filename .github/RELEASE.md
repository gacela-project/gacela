# Release

Guide to cut a new Gacela release.

## TL;DR — Automated

```bash
./release.sh              # auto-bump minor (e.g., 1.12.0 → 1.13.0)
./release.sh 1.13.0       # explicit version
./release.sh --dry-run    # preview without touching anything
```

The script handles pre-flight checks, file updates, quality+tests, commit, tag, push, and GitHub release.
Run `./release.sh --help` for all options.

## Pre-flight requirements

The script (and a manual release) assume:

- `gh` CLI installed and authenticated (`gh auth login`)
- Working tree is clean and on `main`, in sync with `origin/main`
- `CHANGELOG.md` has a non-empty `## Unreleased` section
- You can push to `origin` and create releases

## Manual steps (if not using the script)

1. **Bump the version** in [`bin/gacela`](../bin/gacela) — update the `version:` argument passed to `ConsoleBootstrap`.
2. **Update [`CHANGELOG.md`](../CHANGELOG.md)**:
   - Rename the current `## Unreleased` header to
     `## [X.Y.Z](https://github.com/gacela-project/gacela/compare/<prev>...<X.Y.Z>) - YYYY-MM-DD`
   - Insert a fresh empty `## Unreleased` section at the top.
3. **Run quality + tests**: `composer quality && composer test`
4. **Commit, tag, push**:
   ```bash
   git add bin/gacela CHANGELOG.md
   git commit -m "chore(release): X.Y.Z"
   git tag -a X.Y.Z -m "Release X.Y.Z"
   git push origin main
   git push origin X.Y.Z
   ```
5. **Create the GitHub release** from the pushed tag, using the CHANGELOG section you just renamed as the body:
   [new release](https://github.com/gacela-project/gacela/releases/new) — or via CLI:
   ```bash
   awk '/^## \[X\.Y\.Z\]/{flag=1;next} /^## /{flag=0} flag' CHANGELOG.md > /tmp/release-notes.md
   gh release create X.Y.Z --title X.Y.Z --notes-file /tmp/release-notes.md
   ```

## Rollback

If the script fails mid-way before pushing, restore local file changes:

```bash
./release.sh --rollback
```

Tags are unprefixed (e.g., `1.12.0`, not `v1.12.0`).
