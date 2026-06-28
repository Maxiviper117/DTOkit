# Release Guide

DTOKit is released through Google Release Please, driven by [Conventional Commits](https://www.conventionalcommits.org/). This page covers the versioning policy, how Release Please computes versions, the manifest and config files, the release PR workflow, security releases, and troubleshooting.

## Pre-v1 versioning

DTOKit uses normal semantic versions below `1.0.0`; these are not prerelease identifiers.

- `fix:` commits bump the patch version, for example `0.1.0` to `0.1.1`.
- `feat:` commits bump the minor version, for example `0.1.0` to `0.2.0`.
- Breaking changes (`feat!:` or `BREAKING CHANGE:` footer) also bump the minor version before v1, for example `0.2.0` to `0.3.0`. This is the `bump-minor-pre-major: true` setting in `release-please-config.json`.
- After `1.0.0`, breaking changes bump the major version.
- Release tags use the `v0.x.y` format without a component prefix (`include-component-in-tag: false`).

Only `feat`, `fix`, `perf`, and `revert` commits (plus breaking changes) bump the version. `docs`, `style`, `test`, `chore`, `refactor`, `build`, and `ci` commits are included in release notes according to Release Please defaults but do not independently require a version bump.

## How Release Please computes versions

Release Please reads the commit history since the last release tag and groups commits by Conventional Commit type. It then:

1. Determines the highest bump level across all commits since the last release.
2. Bumps the current version by that level (patch for `fix`, minor for `feat`, minor for breaking before v1, major for breaking after v1).
3. Generates a `CHANGELOG.md` entry grouped by type, with breaking changes listed first.
4. Opens (or updates) a release pull request with the version bump, the changelog entry, and any manifest updates.

The release PR is a single commit that bundles everything that will ship in the release. Merging the release PR tags the release and publishes it (if publishing is automated).

## Manifest and config

DTOKit uses two Release Please files:

### `release-please-config.json`

The static configuration. Tells Release Please what to release and how.

```json
{
  "$schema": "https://raw.githubusercontent.com/googleapis/release-please/main/schemas/config.json",
  "bump-minor-pre-major": true,
  "bump-patch-for-minor-pre-major": false,
  "include-component-in-tag": false,
  "include-v-in-tag": true,
  "packages": {
    ".": {
      "release-type": "php",
      "package-name": "dtokit-core",
      "changelog-path": "CHANGELOG.md",
      "release-as": "0.1.0"
    }
  }
}
```

- `bump-minor-pre-major: true` — breaking changes bump minor, not major, before v1.
- `bump-patch-for-minor-pre-major: false` — `feat:` does not also bump patch.
- `include-component-in-tag: false` — tags are `v0.1.0`, not `dtokit-core-v0.1.0`.
- `include-v-in-tag: true` — tags have a leading `v`.
- `release-as: 0.1.0` — a one-time bootstrap override that forces the next release to `0.1.0` regardless of commit history.

### `.release-please-manifest.json`

The runtime state. Tracks the last-released version per package. Release Please updates this file when a release PR is merged.

```json
{
  ".": "0.1.0"
}
```

Do not edit the manifest by hand unless you are correcting a drift. Release Please owns it.

### Removing the bootstrap override

The `release-as: 0.1.0` setting is a one-time bootstrap. After the first release PR is merged and Release Please records `0.1.0` in `.release-please-manifest.json`, remove the `release-as` key from `release-please-config.json` so later versions are calculated from Conventional Commits. Leaving it in would force every release to `0.1.0`.

## The release PR workflow

1. **You merge feature PRs to `main`.** Each PR's commits use Conventional Commit messages.
2. **Release Please opens a release PR.** It collects all Conventional Commits since the last release, computes the next version, updates `CHANGELOG.md`, updates `.release-please-manifest.json`, and opens a PR titled `chore(main): release 0.x.y`.
3. **You review the release PR.** Check the changelog entry, the version bump, and the manifest update.
4. **You merge the release PR.** Release Please tags the release (`v0.x.y`) and the publish workflow runs (if configured).
5. **Packagist picks up the tag.** If the Packagist webhook is configured, the new tag triggers a package update on Packagist.

The release PR is the only PR that should touch `CHANGELOG.md` and `.release-please-manifest.json`. Do not edit those files in feature PRs.

## Version 0.1.0 release checklist

- Confirm Composer package name (`maxiviper117/dtokit-core`), repository URL, and maintainer metadata.
- Confirm GitHub Actions may create pull requests and repository auto-merge is enabled.
- Protect `main` with tests, coverage, PHPStan, Pint, and Rector as required checks.
- Configure GitHub Pages to deploy through GitHub Actions.
- Enable private vulnerability reporting.
- Run `composer validate --strict` and `composer check` on PHP 8.4.
- Require the CI coverage job to pass at 100%.
- Run `pnpm docs:build` and verify the VitePress site builds cleanly.
- Review `CHANGELOG.md`, public API documentation, and deferred scope in `PRD.md`.
- Tag `v0.1.0` only after all required checks pass.
- Publish `maxiviper117/dtokit-core` to Packagist and verify installation in an empty PHP 8.4 project.
- Connect the Packagist GitHub hook so tags trigger package updates.
- Remove the one-time `release-as` override after Release Please records `0.1.0` in `.release-please-manifest.json`.

## Conventional Commit examples

```text
fix: preserve nested mapping paths
feat: add a field-state wrapper
feat!: change the default unknown-field policy
perf: cache reflected list metadata
revert: feat: add a field-state wrapper
docs: explain custom casts
test: cover invalid enum values
chore(ci): bump checkout action to v4
refactor: split engine mapping and serialization
```

The `!` after the type (or after the scope) marks a breaking change. Alternatively, add a `BREAKING CHANGE: …` footer to any commit. Either form bumps the major version after v1, or the minor version before v1.

See [AGENTS.md](https://github.com/Maxiviper117/DTOkit/blob/main/AGENTS.md) for the full commit-message rules that govern this repository.

## Security releases

A security release follows the same workflow as a regular release, with additional care:

1. **Do not disclose the vulnerability in the commit message.** Use a generic `fix:` subject such as `fix: harden input normalization` rather than `fix: patch CVE-2026-12345 buffer overflow`.
2. **Coordinate disclosure.** Open a private security advisory on GitHub, or coordinate with the reporter directly, before the public fix lands.
3. **Open the fix PR privately if possible.** GitHub Security Advisories support private forks for fixes.
4. **Release as a patch bump.** A security fix is a `fix:` commit, which bumps the patch version (`0.1.0` → `0.1.1`).
5. **Publish the advisory after the release is public.** GitHub can publish the advisory and link it to the release tag.

If a security fix requires a breaking change (rare), use `feat!:` or a `BREAKING CHANGE:` footer and document the upgrade path in the changelog.

## Troubleshooting

### Release Please did not open a release PR

- Verify the release-please GitHub Actions workflow is enabled and has run on the latest commit to `main`.
- Verify the commits since the last release tag include at least one `feat:`, `fix:`, `perf:`, or `revert:` commit. `docs:` and `chore:` commits do not trigger a release on their own.
- Verify `release-please-config.json` and `.release-please-manifest.json` are valid JSON and on `main`.
- Check the workflow logs for the release-please action. It logs its reasoning when it decides not to release.

### The release PR has the wrong version

- Check `release-as` in the config. If it is still set, it forces the version. Remove it after the first release.
- Check `.release-please-manifest.json`. The version there is the last released version; Release Please bumps from it.
- Check the commit history since the last release tag. A `feat!:` commit before v1 should bump minor, not major.

### The changelog entry is missing a commit

- The commit was probably not a recognized Conventional Commit type. Release Please ignores commits whose type it does not recognize.
- The commit may have been part of a squash-merge whose squashed message did not follow Conventional Commits. Squash-merge messages must follow Conventional Commits for the work they contain.
- The commit may have landed after the release PR was opened. Release Please updates the PR as new commits arrive; if it has not yet run, the PR may be stale.

### The release PR touches files it should not

- Release Please updates `CHANGELOG.md` and `.release-please-manifest.json` only. If it touches other files, a `post-changelog-update` hook may be configured, or the release-please action's `command` output is being used elsewhere. Check the workflow file.

### I want to skip a release

- Mark the commit with `chore: …` or another non-bumping type. Release Please will include it in the next release's notes (per its defaults) but will not bump the version for it alone.
- To skip a release entirely (for example, a documentation-only sprint), ensure every commit uses a non-bumping type. No release PR will open.

### I need to backport a fix to an older release

The Core MVP does not maintain release branches. Backports require cherry-picking the fix onto a branch off the older tag, bumping the version manually, and tagging. Document the backport in the changelog. Once the package reaches v1 and maintains LTS branches, backport workflows may be added.

## Where to go next

- [AGENTS.md](https://github.com/Maxiviper117/DTOkit/blob/main/AGENTS.md) for the commit-message rules that drive Release Please.
- [Phases.md](https://github.com/Maxiviper117/DTOkit/blob/main/Phases.md) for delivery status.
- [PRD.md](https://github.com/Maxiviper117/DTOkit/blob/main/PRD.md) for the product roadmap.
