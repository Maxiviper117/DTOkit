# DTOKit Product Requirements

## Product summary

DTOKit is a framework-agnostic PHP package for defining, mapping, validating, serializing, and inspecting typed data boundary objects.

It models data crossing application boundaries such as HTTP requests, API responses, CLI input, queue payloads, webhooks, CSV imports, and external API responses. The core remains pure PHP; framework integration belongs in separate adapter packages.

**Positioning:** Typed data boundaries for PHP.

## Problem

Applications commonly pass unstructured arrays between layers. This creates unchecked keys, runtime-only errors, duplicated validation and response shaping, weak contracts, ambiguous missing/null semantics, accidental sensitive-data exposure, and difficult mapping diagnostics.

DTOKit converts loose transport data into explicit immutable objects and safely serializes those objects back into transport structures.

## Users

Primary users are PHP developers building APIs, external integrations, webhooks, workers, CLI tools, and frontend-backed applications. Secondary users include framework and package authors, teams protecting public API contracts, and teams requiring privacy-aware serialization.

## Product principles

- Data objects describe boundary shape, not domain behavior or persistence.
- Core features work in plain PHP without a framework or service container.
- Explicit, deterministic behavior takes precedence over convenience magic.
- Every automatic mapping decision should be inspectable.
- DTOs are immutable by default and use typed promoted constructor properties.
- Missing, explicit `null`, and present values are distinct concepts.
- Sensitive values never appear in routine output, errors, traces, logs, or generated artifacts.
- Framework adapters add convenience without changing core semantics.

## Core MVP requirements

The first release targets PHP 8.4 under Composer package `maxiviper117/dtokit-core` and namespace `DTOKit`.

### Data objects

- Provide neutral `Data`, incoming `InputData`, and outgoing `OutputData` base classes.
- Accept associative arrays, `stdClass`, and readable public properties from plain objects.
- Construct user-defined final readonly classes through promoted constructor parameters.
- Support required, nullable, and defaulted parameters without treating nullable as optional.
- Reject unsupported or ambiguous metadata before construction.

### Mapping

- Map scalars, backed enums, `DateTimeImmutable`, nested data objects, and typed lists.
- Preserve complete nested paths in failures.
- Support explicit input names through attributes.
- Support container-free custom input casts.
- Reject unknown fields on `InputData` by default.
- Ignore unknown fields on `Data` and `OutputData` by default while retaining them in explain traces.
- Permit class-level strictness overrides.
- Reject ambiguous non-null union mapping unless an explicit cast resolves it.
- Protect recursive mapping with a deterministic depth limit.

### Serialization

- Serialize scalars, nulls, enums, dates, nested data objects, and lists into JSON-safe arrays.
- Use array serialization as the sole source for JSON output.
- Support explicit output names and container-free custom transformers.
- Support hidden, sensitive, and explicitly redacted fields.
- Emit backed enum values and ISO-8601 date strings.
- Fail explicitly for unsupported output values.

### Errors and explainability

- Provide specific exceptions for missing fields, unknown fields, type mismatches, ambiguous mapping, casts, metadata, serialization, and transformers.
- Error context includes target class, path, expected type, received type, and pipeline stage where applicable.
- `tryFrom()` returns a non-throwing result containing either data or a mapping error.
- `explain()` returns structured events and supports array, JSON, and plain-text rendering.
- Sensitive raw values must not appear in errors or explain output.

### Quality requirements

- Core has no runtime framework dependencies or global mutable configuration.
- Reflection metadata is cached in memory for the current process.
- Public behavior is covered by Pest tests, PHPStan at maximum level, Pint, and Rector checks.
- Public API changes require documentation and `Phases.md` updates.

## Public API

```php
Data::from(array|object $source): static
Data::tryFrom(array|object $source): MappingResult
Data::explain(array|object $source): ExplainResult
Data::toArray(): array
Data::toJson(int $flags = 0): string
```

MVP attributes:

- `MapInputName`
- `MapOutputName`
- `ListOf`
- `WithCast`
- `WithTransformer`
- `Strict`
- `IgnoreUnknown`
- `Hidden`
- `Sensitive`
- `Redact`

## Non-goals

DTOKit is not an ORM, domain model, validation framework, full API framework, serializer-only package, Action replacement, or framework-owned abstraction. DTOs must not persist records, call services, or execute business decisions.

The Core MVP does not include Laravel integration, CLI commands, schema generation, contract snapshots, output profiles, object cloning/merging/diffing, fake data, or explicit missing/null/present wrapper types.

## Roadmap

1. **Core MVP:** metadata, mapping, serialization, attributes, strong errors, and explain mode.
2. **Framework adapters:** Laravel request, validation, response, cache, and Artisan integration, followed by other frameworks.
3. **Contract toolkit:** snapshots, compatibility checks, versions, deprecation, and CI enforcement.
4. **Advanced safety:** explicit field state, partial updates, profiles, and richer privacy policies.
5. **Schema tooling:** TypeScript, Zod, JSON Schema, OpenAPI, and Markdown generation.
6. **Developer tooling:** doctor, inspect, compile/cache, benchmarks, static-analysis extensions, graphs, and IDE support.

Delivery status is maintained in [Phases.md](Phases.md). Repository implementation rules are maintained in [AGENTS.md](AGENTS.md).

## Success criteria

- A developer can understand accepted and exposed data by reading a DTO declaration.
- Valid supported input maps predictably without framework services.
- Invalid input produces a precise, safe, path-aware explanation.
- Serialization never exposes fields marked hidden or sensitive.
- Nested data and lists behave consistently at every depth.
- The package remains portable across plain PHP and framework environments.
- Future adapters and contract generators can build on public metadata semantics without changing core behavior.
