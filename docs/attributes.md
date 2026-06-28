# Attributes

Attributes are the metadata that shapes mapping and serialization. This page covers every attribute, where it can be placed, how conflicts are resolved, and how to combine attributes safely.

## Placement rules

DTOKit attributes may be placed on promoted constructor parameters. PHP also exposes promoted attributes on their corresponding properties, and DTOKit resolves them once during metadata compilation.

### Parameter vs property placement

For a promoted constructor parameter `public int $userId`, you may place an attribute on the parameter, the property, or both. DTOKit checks the parameter first, then the property. The two locations are interchangeable for promoted parameters; choose one and stay consistent within a codebase.

```php
// On the parameter (recommended for promoted properties)
public function __construct(
    #[MapInputName('user_id')]
    public int $userId,
) {}

// On the property (also valid for promoted properties)
public int $userId;

#[MapInputName('user_id')]
public int $userId;
```

For non-promoted parameters, attribute resolution does not apply — DTOKit rejects non-promoted parameters at metadata time with `MetadataException`.

### Duplicate attributes

Each attribute may be declared at most once per parameter (or property). A duplicate raises `MetadataException` at metadata time, before any mapping happens:

> Attribute DTOKit\Attribute\MapInputName is duplicated on …

This is a fail-loud guard: silent "last wins" behavior would make contracts unstable.

## Naming

### `MapInputName(string $name)`

Selects the external key used during mapping. The constructor property keeps its PHP name; the payload key is what changes.

```php
#[MapInputName('user_id')]
public int $userId
```

Now `UserData::from(['user_id' => 1])` maps to `$userId`. The PHP name `userId` is not accepted as an input key unless you also accept it through a cast.

### `MapOutputName(string $name)`

Selects the external key used during serialization.

```php
#[MapOutputName('user_id')]
public int $userId
```

Now `$user->toArray()` emits `['user_id' => …]` instead of `['userId' => …]`.

### Independent input and output names

Input and output names are independent decisions. A single property can accept one external name on input and emit a different one on output:

```php
#[MapInputName('legacy_customer_number')]
#[MapOutputName('customerId')]
public int $customerId
```

This decouples inbound payloads (often legacy, snake_case, or vendor-named) from outbound contracts (often newer, camelCase, or client-facing).

### Duplicate input names

Two parameters that resolve to the same input name raise `MetadataException('Duplicate input name …')`. Output names may collide intentionally with input names; only input names must be unique (because the engine has to route each payload key to exactly one parameter).

## Type behavior

### `ListOf(string $type)`

Declares the item type for a runtime array list. The parameter must be typed `array`; the attribute supplies the item type PHP cannot express.

```php
#[ListOf(MemberData::class)]
public array $members
```

The `type` argument may be:

- A `Data` subclass class-string (items are mapped recursively).
- A backed enum class-string (items are resolved via `from()`).
- A `DateTimeInterface` class-string (items are parsed).
- A built-in scalar type name (`'string'`, `'int'`, `'float'`, `'bool'`).

The input must be a list (`array_is_list()`). Associative arrays and arrays with non-sequential integer keys are rejected with a `TypeMismatchException` expecting `list<Type>`.

`#[ListOf]` is mapping metadata only — it is not consulted during serialization. When you construct an output object directly, the property's runtime value is serialized as a plain array of whatever items it holds.

### `WithCast(class-string<Cast> $cast)`

References a zero-argument class implementing `DTOKit\Contract\Cast`. The cast is instantiated with `new` and runs before normal type conversion.

```php
#[WithCast(TrimCast::class)]
public string $name
```

The cast may rewrite the value, change its type, or pass it through. If the cast's return value still does not satisfy the declared type, mapping fails with `TypeMismatchException` after the cast runs. If the cast throws, the failure is wrapped in `CastException` with the field path and the original exception chained.

### `WithTransformer(class-string<Transformer> $transformer)`

References a zero-argument class implementing `DTOKit\Contract\Transformer`. The transformer is instantiated with `new` and runs during serialization, after redaction and before recursive serialization.

```php
#[WithTransformer(MoneyTransformer::class)]
public Money $price
```

The transformer may return a scalar, `null`, an array, or a nested `Data` object. Anything the engine cannot serialize after the transformer runs raises `SerializationException`. Transformer failures are wrapped in `TransformerException`.

### Combining `WithCast` and `WithTransformer`

A property may carry both `#[WithCast]` and `#[WithTransformer]`. The cast shapes the inbound value during mapping; the transformer shapes the outbound value during serialization. They run at different times and never interact.

```php
#[WithCast(MoneyCast::class)]
#[WithTransformer(MoneyTransformer::class)]
public Money $price
```

This is the standard pattern for value objects that need to be reconstructed from a transport shape on input and re-serialized on output.

## Input policy

### `Strict` (class-level)

Rejects keys that do not map to constructor parameters.

```php
#[Strict]
final readonly class ExportRow extends Data
{
    public function __construct(public string $id) {}
}
```

`Strict` flips a `Data` or `OutputData` class to strict behavior. On an `InputData` class (which is already strict) it is a no-op but useful as explicit documentation.

### `IgnoreUnknown` (class-level)

Ignores undeclared keys. Explain mode records them as `[unknown] Ignored unknown fields` events with their dotted paths.

```php
#[IgnoreUnknown]
final readonly class WebhookData extends InputData
{
    public function __construct(public string $eventId) {}
}
```

`IgnoreUnknown` flips an `InputData` class to lenient behavior. Use it only when the boundary intentionally accepts forward-compatible extra fields (e.g. a webhook payload that may grow new keys between provider versions).

### Strictness resolution

Strictness is resolved per class in this order:

1. Default: strict for `InputData`, lenient for `Data` and `OutputData`.
2. If `#[Strict]` is present, the class is strict.
3. If `#[IgnoreUnknown]` is present, the class is lenient.

If both `#[Strict]` and `#[IgnoreUnknown]` are present, `#[IgnoreUnknown]` wins (it is checked second). This is a footgun — do not declare both. Future versions may raise `MetadataException` for the conflict.

## Output safety

### `Hidden`

Omits the property and its key from serialization. The field is still required (or optional, depending on its default) during mapping — `Hidden` only affects output.

```php
#[Hidden]
public string $internalId
```

Use `Hidden` for fields the boundary object needs internally but the response contract does not include. For fields the boundary object does not need at all, omit them from the constructor entirely.

### `Sensitive`

Emits `[redacted]` on serialization and prevents raw values from entering explain `match` events.

```php
#[Sensitive]
public string $password
```

`Sensitive` is the default redaction: the key remains in the output so callers know a value exists, but the value is replaced with `[redacted]`. Use it for credentials, tokens, and other secrets whose presence is part of the contract but whose value must never leak.

### `Redact(string $replacement = '[redacted]')`

Emits a configured replacement, defaulting to `[redacted]`. Like `Sensitive`, but with a custom replacement.

```php
#[Redact('****')]
public string $token
```

Use `Redact` when the contract calls for a specific placeholder (e.g. a fixed-length mask) or when you want to distinguish several redacted fields by replacement string.

### `Sensitive` vs `Redact`

`#[Sensitive]` is equivalent to `#[Redact('[redacted]')]` for serialization, and both suppress raw values in explain events. The only difference is the replacement string. Choose `Sensitive` for the default placeholder; choose `Redact` when you need a custom one. You do not need both on the same field.

## Resolution order

DTOKit resolves metadata in a deterministic order during the first mapping, serialization, or explain call for each class:

1. Reflect the constructor and parameters.
2. Validate that the constructor is public and that every parameter is promoted and typed.
3. Resolve strictness from the base class and `#[Strict]` / `#[IgnoreUnknown]`.
4. For each parameter, resolve:
   - The PHP name and (via `#[MapInputName]`) the input name.
   - The output name (via `#[MapOutputName]`, defaults to the PHP name).
   - The type, nullability, and union-ness.
   - The default availability.
   - `#[ListOf]` element type.
   - `#[WithCast]` class-string.
   - `#[WithTransformer]` class-string.
   - `#[Hidden]`, `#[Sensitive]`, `#[Redact]` flags.
5. Detect duplicate input names and duplicate attributes and raise `MetadataException` for any conflict.

Conflicting or duplicate metadata fails during compilation instead of silently choosing a winner. The cached `ClassMeta` is then reused for the lifetime of the process.

## Practical patterns

### Accept snake_case, emit camelCase

```php
#[MapInputName('created_at')]
public DateTimeImmutable $createdAt
```

### Hide a field from output but require it on input

```php
#[Hidden]
public string $rawSignature // verified before mapping, never emitted
```

### Redact with a custom placeholder

```php
#[Redact('••••••')]
public string $apiKey
```

### Map a list of value objects

```php
/** @param list<MoneyData> $prices */
#[ListOf(MoneyData::class)]
public array $prices
```

### Accept forward-compatible webhooks

```php
#[IgnoreUnknown]
final readonly class WebhookData extends InputData
{
    public function __construct(
        #[MapInputName('event_id')]
        public string $eventId,
    ) {}
}
```

## Anti-patterns

- **`#[Strict]` and `#[IgnoreUnknown]` on the same class.** `IgnoreUnknown` wins silently. Pick one.
- **`#[Hidden]` on a field the response contract requires.** The field will not appear in output. Re-check the contract.
- **`#[Sensitive]` on a field that callers need the value of.** The value is replaced with `[redacted]`. Use a transformer that masks partially instead.
- **`#[WithCast]` whose class requires constructor arguments.** The engine instantiates with `new` and will throw. Casts must be constructible without arguments.
- **Placing an attribute on a non-promoted parameter.** Resolution happens against the promoted property; non-promoted parameters are rejected at metadata time.

## Where to go next

- [Custom Casts and Transformers](/extensions) covers writing the classes referenced by `#[WithCast]` and `#[WithTransformer]`.
- [Mapping Input](/mapping) and [Serialization](/serialization) cover where each attribute takes effect.
- [API Reference](/api) lists every attribute signature.
