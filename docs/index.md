# DTOKit

DTOKit is a framework-agnostic PHP 8.4 toolkit for defining, mapping, serializing, and inspecting typed data boundaries. It turns loose arrays and objects entering or leaving an application into immutable, typed value objects with explicit mapping rules and safe output contracts.

Use it where data changes trust level or representation: HTTP request payloads, API responses, queue messages, webhooks, CLI input, CSV rows, file imports, and external service responses.

## Why DTOKit?

Raw arrays hide their shape and defer mistakes until runtime. A payload that "looks right" reaches deep into the application before anyone notices a missing field, a renamed key, or a value of the wrong type. DTOKit makes the accepted and exposed shape explicit at the boundary, so a wrong payload fails loudly at the door instead of silently corrupting state later.

- **Framework neutral:** core mapping works in plain PHP without a container, .env file, or bootstrap. No Laravel, Symfony, or PSR-11 dependency is required or implied.
- **Strict at trust boundaries:** `InputData` rejects unknown fields by default to prevent accidental mass assignment. `Data` and `OutputData` ignore them, opt in with `#[Strict]`.
- **Safe output:** `#[Hidden]`, `#[Sensitive]`, and `#[Redact]` keep secrets out of serialized arrays and JSON, and out of explain traces.
- **Nested and typed:** map nested data objects, typed lists via `#[ListOf]`, backed enums, and `DateTimeInterface` recursively. Nested failures keep their full dotted path.
- **Explainable:** every mapping decision can be inspected through `explain()` without leaking sensitive values. Diagnostics are designed for debugging, logging, and assertions.
- **Deterministic:** constructor metadata is reflected once per process and reused. The same payload always produces the same result, with no global state or environment drift.

## A first data boundary

```php
use DTOKit\InputData;

final readonly class CreateUserData extends InputData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $company = null,
    ) {}
}

$user = CreateUserData::from([
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.com',
]);
```

The constructor is the contract: `name` and `email` are required, `company` may be missing because it has a default, `company` accepts explicit `null` because it is nullable, and unknown fields are rejected because the class extends `InputData`.

## What DTOKit is not

- Not an ORM, domain model, or validation framework. DTOs describe transport shape, not persistence or business rules.
- Not a serializer-only library. Mapping and serialization are paired concerns at the same boundary.
- Not a replacement for application validation. Use it to enforce shape; layer business-rule validation after mapping.
- Not a framework. Adapters for Laravel and others are planned as separate packages.

DTOs must not persist records, call services, query databases, dispatch events, or execute business decisions. Keep them small, declarative, and side-effect free.

## Package layout

| Path | Purpose |
| --- | --- |
| `src/Data.php`, `InputData.php`, `OutputData.php` | Base classes users extend. |
| `src/Attribute/` | `#[Strict]`, `#[IgnoreUnknown]`, `#[MapInputName]`, `#[MapOutputName]`, `#[ListOf]`, `#[WithCast]`, `#[WithTransformer]`, `#[Hidden]`, `#[Sensitive]`, `#[Redact]`. |
| `src/Contract/` | `Cast` and `Transformer` extension interfaces. |
| `src/Internal/Engine.php` | Process-local singleton that maps, serializes, and explains. |
| `src/Result/` | `MappingResult` and `ExplainResult` value objects. |
| `src/Exception/` | Specific, context-carrying exception hierarchy rooted at `DTOKitException`. |

The engine is internal. Program against the public base classes, attributes, contracts, results, and exceptions only.

## Current scope

The Core MVP targets PHP 8.4 and includes mapping, serialization, attributes, custom casts and transformers, structured errors, and explain mode. Framework adapters, validation integrations, contract snapshots, schema generation (TypeScript, JSON Schema, OpenAPI), explicit field-state wrappers, persistent metadata caches, and CLI tooling are intentionally outside the current package. See [Supported Types and Limits](/limitations) for the full boundary.

## Where to go next

- New to the package? Start with [Getting Started](/getting-started).
- Want the mental model? Read [Core Concepts](/concepts).
- Mapping payloads? See [Mapping Input](/mapping).
- Producing responses? See [Serialization](/serialization).
- Debugging a mapping failure? See [Errors and Explain Mode](/diagnostics).
- Ready for production rules? Read [Security](/security) and [Recipes](/recipes).
- Looking up a signature? See [API Reference](/api).
