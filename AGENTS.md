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
- Update `Phases.md` whenever phase status or scope changes.

## Verification

Run `composer check` before handing off a change. New behavior requires Pest coverage for success, failure, nested paths, and sensitive-data safety. Public API changes require documentation updates.

## Scope control

The current target is the Core MVP in `Phases.md`. Do not introduce framework adapters, contract/schema generation, explicit field-state wrappers, or container-driven extension discovery into core.
