# Serialization

Serialization is the reverse of mapping: turning a typed data object back into a transport-ready array. `toArray()` is the source of truth; `toJson()` encodes that result. This page covers output contracts, the serialization order, redaction precedence, recursion, and unsupported-value strategies.

## Arrays and JSON

`toArray()` is the serialization source of truth. `toJson()` encodes its result with `JSON_THROW_ON_ERROR` and wraps encoding failures in `SerializationException`.

```php
$output = new PostOutput(10, 'Typed boundaries');

$output->toArray(); // ['id' => 10, 'title' => 'Typed boundaries']
$output->toJson();  // {"id":10,"title":"Typed boundaries"}
$output->toJson(JSON_PRETTY_PRINT);
```

Supported values include scalars, `null`, backed and unit enums, `DateTimeInterface`, nested `Data` objects, and arrays containing supported values. Anything else raises `SerializationException` unless a `#[WithTransformer]` converts it.

### JSON flags

`toJson()` always adds `JSON_THROW_ON_ERROR` to the flags you pass. Common additions:

- `JSON_PRETTY_PRINT` — readable output for logs and debugging.
- `JSON_UNESCAPED_SLASHES` — keep `/` unescaped in URLs.
- `JSON_UNESCAPED_UNICODE` — keep multibyte characters readable.
- `JSON_THROW_ON_ERROR` — already applied; passing it again is harmless.

Avoid `JSON_PARTIAL_OUTPUT_ON_ERROR` — it silently drops invalid values, which contradicts DTOKit's fail-loud philosophy. Use a transformer to convert unsupported values instead.

## Output contracts

A serialized DTO is a deliberate contract. Design it the way a public API is designed:

- **Expose only what callers need.** Every field in an `OutputData` class is a public commitment. Adding fields is a backwards-compatible change; removing or renaming them is a breaking change.
- **Use a dedicated output DTO per response shape.** A list response, a detail response, and a search response are three different shapes — three different `OutputData` classes, not one class with conditional fields.
- **Do not serialize persistence models directly.** Domain entities often carry internal state, foreign keys, and audit fields that should never leave the process. Build an output DTO from each entity.
- **Use `#[MapOutputName]` to decouple PHP names from external names.** PHP property names follow PSR conventions (`camelCase`); external payloads often follow JSON conventions (`snake_case`). Keep them separate.

## Output names

Use `#[MapOutputName]` when the public key differs from the PHP property name.

```php
public function __construct(
    #[MapOutputName('created_at')]
    public DateTimeImmutable $createdAt,
) {}
```

`#[MapInputName]` and `#[MapOutputName]` are independent. A property can accept one external name on input and emit a different external name on output, which is useful when a payload is renamed but a response contract must keep the old name (or vice versa).

## Hidden and sensitive fields

Three attributes control what leaves the object:

- `#[Hidden]` removes the field and its key entirely from the serialized output.
- `#[Sensitive]` keeps the key but replaces its value with `[redacted]`, and prevents the raw value from appearing in explain events.
- `#[Redact('***')]` keeps the key and emits a configured replacement string. Default is `[redacted]`.

```php
public function __construct(
    #[Hidden] public string $internalId,
    #[Sensitive] public string $password,
    #[Redact('****')] public string $token,
) {}
```

| Attribute | Key in output | Value in output | Raw value in explain |
| --- | --- | --- | --- |
| `#[Hidden]` | No | — | Not recorded as `match` |
| `#[Sensitive]` | Yes | `[redacted]` | Replaced with `[redacted]` |
| `#[Redact('***')]` | Yes | `***` | Replaced with `***` |

### Output filtering is not a substitute for a deliberate output DTO

The DTO shape is the primary exposure boundary. `#[Hidden]` and friends are safety nets for cases where a value is needed internally by the boundary object but should not be emitted. When a value is never needed by the boundary object at all, do not include it in the constructor — omit it entirely from the output DTO.

## Custom transformers

Implement `DTOKit\Contract\Transformer` and use `#[WithTransformer]`. The transformer runs after redaction and before recursive serialization.

```php
use DTOKit\Contract\Transformer;

final class MoneyTransformer implements Transformer
{
    public function transform(mixed $value): mixed
    {
        return $value instanceof Money
            ? ['amount' => $value->amount, 'currency' => $value->currency]
            : $value;
    }
}
```

```php
use DTOKit\Attribute\WithTransformer;

public function __construct(
    #[WithTransformer(MoneyTransformer::class)]
    public Money $price,
) {}
```

A transformer may return:

- A scalar or `null` (emitted directly).
- An array (recursed element by element).
- A nested `Data` object (serialized recursively).
- Anything else the engine can serialize.

Transformer failures are wrapped in `TransformerException` with the output path. The original throwable is chained so callers can inspect the root cause.

See [Custom Casts and Transformers](/extensions) for patterns and testing.

## Serialization order

For each field, DTOKit applies the following order:

1. **Skip `#[Hidden]` fields.** The key is never emitted.
2. **Replace `#[Sensitive]` or `#[Redact]` values.** The replacement string is used as the value; the raw value is discarded.
3. **Apply `#[WithTransformer]` when present.** The transformer receives the value (or the replacement, if redacted) and returns the value to serialize.
4. **Recursively serialize** the resulting value:
   - `null` and scalars pass through.
   - `Data` objects are serialized via the same pipeline.
   - Backed enums emit their backing value.
   - Unit enums emit their name.
   - `DateTimeInterface` emits an ISO-8601 (`DateTimeInterface::ATOM`) string.
   - Arrays are walked element by element.
   - Anything else raises `SerializationException`.
5. **Write** the serialized value under the configured output name.

### Why redaction precedes transformers

Redaction is applied **before** transformers so sensitive input is never passed into output transformation accidentally. If a transformer needs the real value, do not mark the field `#[Sensitive]` or `#[Redact]` — instead, have the transformer itself emit a safe representation (for example, a token's last four characters).

### Why hidden precedes everything

`#[Hidden]` skips the field entirely, including its transformer. This is intentional: a hidden field is not part of the output contract at all, so running its transformer would be wasted work and could observe side effects.

## Dates

`DateTimeInterface` values serialize using `DateTimeInterface::ATOM`, which is ISO 8601 with seconds and timezone:

```
2026-06-28T12:00:00+00:00
```

If you need a different format, use a `#[WithTransformer]`:

```php
final class DateOnlyTransformer implements Transformer
{
    public function transform(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : $value;
    }
}
```

Timezones are preserved. A `DateTime` constructed in `Africa/Johannesburg` serializes with `+02:00`.

## Enums

- **Backed enums** serialize to their backing value (`'pending'`, `42`).
- **Unit enums** serialize to their case name (`'Pending'`).

This matches the round-trip: a backed enum that maps from `'pending'` also serializes to `'pending'`.

## Nested output example

```php
final readonly class LineOutput extends OutputData
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {}
}

final readonly class OrderOutput extends OutputData
{
    /** @param list<LineOutput> $lines */
    public function __construct(
        public int $id,
        public array $lines,
    ) {}
}

$order = new OrderOutput(10, [new LineOutput('ABC-1', 2)]);

$order->toArray();
// ['id' => 10, 'lines' => [['sku' => 'ABC-1', 'quantity' => 2]]]
```

`ListOf` is mapping metadata and is not needed when directly constructing output objects. PHPDoc (`@param list<LineOutput> $lines`) remains useful for static analysis and for human readers.

### Recursion model

Nested `Data` objects are serialized via the same pipeline, so `#[Hidden]`, `#[Sensitive]`, `#[Redact]`, `#[WithTransformer]`, and `#[MapOutputName]` apply at every depth. Arrays are walked element by element, so a `list<LineOutput>` produces a list of serialized `LineOutput` objects.

The same 64-depth guard that bounds mapping bounds serialization. A circular object graph (which `readonly` makes impossible to construct through DTOKit, but possible to assemble manually) raises `SerializationException('Maximum serialization depth exceeded.')`.

## Unsupported values

Arbitrary objects are not serialized automatically. Convert value objects through a transformer or represent them as a nested `Data` object:

| Value type | Strategy |
| --- | --- |
| Domain value object (`Money`, `Coordinates`) | `#[WithTransformer]` that emits an array or scalar. |
| Third-party class with public properties | Wrap it in a `Data` subclass, or write a transformer. |
| Resource, closure | Convert to a serializable form before constructing the DTO. |
| Circular reference | Not supported; restructure as a tree. |

Silent public-property serialization would make output contracts unstable and could expose private information, so the engine refuses to guess.

## Performance notes

- **Metadata is cached.** Reflection is paid once per class per process; subsequent serializations reuse the cached `ClassMeta`.
- **Serialization is O(n) in declared fields.** Each field is read once via property access. Transformers and redaction are constant-time per field.
- **JSON encoding is delegated to `json_encode()`.** DTOKit does not implement its own encoder; the standard library is faster and well-tested.
- **No reflection during serialization.** Property access on a `readonly` class is direct; no reflection overhead is paid on the hot path.

## Where to go next

- [Errors and Explain Mode](/diagnostics) covers diagnosing serialization failures.
- [Custom Casts and Transformers](/extensions) covers writing transformers.
- [Recipes](/recipes) covers common output shapes.
