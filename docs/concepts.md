# Core Concepts

## Data boundaries

A **boundary** is any point where data changes trust level or representation. HTTP requests crossing from a client into your process, queue messages crossing from a producer into a worker, webhook deliveries crossing from a payment provider into your application, CSV rows crossing from a file into your domain — all are boundaries. Trust is lower on the inbound side; representation is different on each side.

DTOKit makes the accepted shape on the inbound side and the exposed shape on the outbound side explicit at the boundary itself, by encoding each side as a small typed value object. The constructor is the input contract; the public properties plus output attributes are the output contract.

### What a DTO is — and is not

A data transfer object describes **transport data**. It is not any of the following:

- **A domain entity.** Entities have identity, lifecycle, and behavior. DTOs are snapshots of a payload at a moment in time.
- **A persistence model.** DTOs do not know about databases, ORM columns, or relationships.
- **A service.** DTOs do not call repositories, dispatch events, send mail, or query caches.
- **A validator.** DTOs enforce shape (presence, type, name). Business rules belong in the application or domain layer after mapping.
- **A value object in the DDD sense.** DTOs may wrap primitives and other DTOs, but they do not carry invariant-enforcing behavior. They are transport-shaped records.

The rule of thumb: if a class needs a unit test for its behavior, it is not a DTO. DTOs are tested by mapping and serialization, not by method calls.

## Base classes

### `Data`

The neutral base for typed boundary objects. Unknown input fields are ignored unless `#[Strict]` is applied. Use `Data` when the same class doubles as input and output and you do not want strict-by-default behavior, or when you are building a value object that flows both ways.

### `InputData`

For incoming and generally untrusted payloads. Unknown fields are rejected by default to prevent accidental acceptance of undeclared values — the most common mass-assignment vector. Override with `#[IgnoreUnknown]` only when the boundary intentionally accepts forward-compatible extra fields (for example, a webhook payload that may grow new keys over time).

### `OutputData`

For deliberate response and export shapes. Unknown fields supplied during construction mapping are ignored by default, because output shapes are deliberate export contracts rather than untrusted payloads. Override with `#[Strict]` to reject unknown keys at the output boundary too.

### Choosing a base

| Base | Strict by default | Typical use |
| --- | --- | --- |
| `InputData` | Yes | Request payloads, queue jobs, webhooks, CLI args. |
| `OutputData` | No | API responses, exported documents, serialized snapshots. |
| `Data` | No | Bidirectional value objects, neutral records, internal boundaries. |

You cannot change the base class without breaking callers, so pick the one whose default strictness matches the boundary's trust direction. Apply `#[Strict]` or `#[IgnoreUnknown]` only when the default does not fit.

## Immutable declarations

DTOKit expects public constructors with typed promoted properties. Final readonly classes are the recommended declaration style and the only style that preserves the boundary guarantee after construction.

```php
final readonly class CoordinatesData extends Data
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}
}
```

### Why readonly

Without `readonly`, a caller can mutate a property after mapping and break the contract that the constructor established. With `readonly`, the object's state is fixed at construction and cannot drift. The base classes themselves are declared `readonly`, so subclasses must be readonly-compatible.

### Why promoted properties

Promoted constructor parameters (`public string $name`) declare both the parameter and the property in one line and let DTOKit reflect them as a single unit. Non-promoted parameters are rejected at metadata time with a `MetadataException`, because they would produce a DTO with uninitialized properties.

### Why final

`final` prevents subclasses from adding behavior, mutating state, or shadowing properties in ways that break mapping. DTOKit does not require `final`, but it strongly recommends it. A non-final DTO is a footgun.

## Required, nullable, and optional

The single most important distinction in DTOKit:

```php
public string $name                 // required, cannot be null
public ?string $middleName          // required, may be null
public string $country = 'ZA'       // optional, default used only when missing
public ?string $company = null      // optional and nullable
```

- **Required** means the key must be present in the payload and the value must satisfy the type.
- **Nullable** (`?T`) means the value may be `null`. It does not mean the key may be missing.
- **Optional** means the key may be missing; the constructor default is used in that case.

Defaults never mask invalid provided values. If `country` is present with the wrong type, mapping fails rather than using `'ZA'`. This is deliberate: a malformed value is a contract violation, not a missing value.

| Declaration | `[]` | `['x' => null]` | `['x' => 'val']` | `['x' => []]` |
| --- | --- | --- | --- | --- |
| `string $x` | fail (missing) | fail (type) | ok | fail (type) |
| `?string $x` | fail (missing) | ok | ok | fail (type) |
| `string $x = 'd'` | ok, uses `'d'` | fail (type) | ok | fail (type) |
| `?string $x = null` | ok, uses `null` | ok | ok | fail (type) |

## Type-system philosophy

DTOKit's type system is narrow and predictable on purpose:

- **Coercion is minimal.** Integer strings (`'42'`) coerce to `int`; numeric strings coerce to `float`; recognized truthy strings coerce to `bool`. Everything else must already match the declared type. Objects are not stringified, arrays are not cast to objects, and floats are not truncated into integers.
- **Ambiguity is rejected.** Non-null union types (`int|string`) cannot be inferred — the engine refuses to pick a member. Add a `#[WithCast]` that implements a documented selection rule.
- **Intersections are unsupported.** PHP's intersection types do not give the engine a single class-string to map against, so they are rejected at metadata time.
- **`mixed` is an explicit opt-out.** A `mixed` parameter accepts any value the payload carries, including `null` when the declaration permits it. Use it sparingly and pair it with a cast when you need shape.

This philosophy trades convenience for explainability. The same payload always produces the same result, and every conversion the engine makes is one a reader could predict from the type.

## Deterministic metadata

DTOKit reflects constructor and attribute metadata once per process, then reuses the compiled result. Specifically:

1. The first time a data class is mapped, serialized, or explained, the engine reflects its constructor and parameters.
2. It collects each parameter's type, nullability, default, list/cast/transformer metadata, and output-affecting attributes into a `ClassMeta` structure.
3. The structure is cached in memory for the lifetime of the process. Subsequent calls reuse it without reflection.
4. Mapping does not consult a framework container, global configuration, environment variables, or service registry at any point.

Consequences:

- **No warmup cost after the first call.** Reflection is paid once per class per process.
- **No hidden global state.** The metadata cache is process-local and immutable after population.
- **No environment drift.** The same class always maps the same way, regardless of deployment or test setup.
- **No DI integration required.** Casts and transformers are instantiated with `new` and must be constructible without arguments.

### When the cache matters

Long-running processes (daemons, queue workers, roadrunner, FrankenPHP, Swoole) benefit most from the cache, because the one-time reflection cost is amortized over many requests. Short-lived PHP-FPM requests pay the reflection cost once per request, which is still cheap relative to typical I/O.

## Why constructor-only mapping

DTOKit maps exclusively through the constructor because:

- The constructor is the only place where a class's invariants can be enforced at construction time.
- Mapping through setters would allow partial construction and mutation, breaking immutability.
- The constructor signature is a single declarative contract that can be reflected, cached, and explained.

If you find yourself wanting to map a class without a constructor, or with non-promoted properties, that class is probably not a DTO — it is a struct or a builder, and DTOKit is the wrong tool.

## Where to go next

- [Mapping Input](/mapping) covers the full mapping rules, coercion edge cases, and lifecycle.
- [Serialization](/serialization) covers output contracts and redaction ordering.
- [Attributes](/attributes) covers every attribute in detail.
