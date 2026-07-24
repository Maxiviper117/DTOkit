# Repository Guidance

## Product boundary

DTOKit is a framework-neutral typed data-boundary toolkit. Core code must not depend on Laravel, Symfony, a service container, or global mutable configuration. Data objects describe transport shape; business behavior belongs elsewhere.

## Engineering rules

- Target PHP 8.4 and declare strict types in every PHP file.
- Keep public behavior deterministic, explicit, and explainable.
- Treat `InputData` as strict by default. Nullable and optional are separate concepts.
- Never expose sensitive raw values through errors, explain traces, logs, or serialization.
- Preserve full nested paths in mapping and serialization failures.
- Prefer small immutable value objects and interfaces over configurable inheritance.
- Review and update `AGENTS.md` whenever repository changes affect its guidance, workflows, architecture, conventions, verification steps, or scope. Do not leave known instructions stale.
- Update `Phases.md` whenever phase status or scope changes.

## Commit messages

All commits MUST use [Conventional Commits](https://www.conventionalcommits.org/) so that Google Release Please can version the package and generate `CHANGELOG.md` automatically.

Format:

```
<type>(<scope>): <subject>

<body>

<footer>
```

- `type` is required and must be one Release Please recognizes:
  - `feat` — a new user-facing capability (triggers a minor bump; a `!` after the scope marks a breaking change and triggers a major bump).
  - `fix` — a user-facing bug fix (triggers a patch bump).
  - `perf` — a performance improvement with user-visible effect.
  - `revert` — reverts a prior commit.
  - `docs`, `style`, `test`, `chore`, `refactor`, `build`, `ci` — repository-only changes; these do not appear in the changelog and do not bump the version.
- `scope` is optional but recommended (for example `engine`, `serialization`, `docs`, `ci`).
- `subject` is a short imperative phrase, lowercase, no trailing period.
- Reference issues or PRs in the footer using `Closes #123`, `Refs #123`, or `BREAKING CHANGE: …` for breaking changes.
- Keep the subject under 72 characters. Wrap the body at 100 characters.

Examples:

```
feat(engine): support intersection types in constructor mapping
fix(serialization): preserve nested paths when transformers fail
docs(mapping): document union-type cast requirement
chore(ci): bump checkout action to v4
```

Do not invent types outside the list above; Release Please will ignore them.

## Verification

Run `composer check` before handing off a change. New behavior requires Pest coverage for success, failure, nested paths, and sensitive-data safety. Public API changes require documentation updates.

## Scope control

The current target is the Core MVP in `Phases.md`. Do not introduce framework adapters, contract/schema generation, explicit field-state wrappers, or container-driven extension discovery into core.
