# Serialization

## Arrays and JSON

`toArray()` is the serialization source of truth. `toJson()` encodes that result with `JSON_THROW_ON_ERROR` and wraps encoding failures in `SerializationException`.

Supported values include scalars, null, backed and unit enums, dates, nested data objects, and arrays containing supported values.

## Output names

Use `#[MapOutputName]` when the public key differs from the PHP property name.

```php
public function __construct(
    #[MapOutputName('created_at')]
    public DateTimeImmutable $createdAt,
) {}
```

Dates serialize using ISO 8601. Backed enums serialize to their backing values; unit enums serialize to their names.

## Hidden and sensitive fields

- `#[Hidden]` removes a field entirely.
- `#[Sensitive]` keeps the key but replaces its value with `[redacted]`.
- `#[Redact('***')]` emits a chosen replacement.

```php
public function __construct(
    #[Hidden] public string $internalId,
    #[Sensitive] public string $password,
    #[Redact('****')] public string $token,
) {}
```

Do not treat output filtering as a substitute for selecting a deliberate output DTO.

## Custom transformers

Implement `DTOKit\Contract\Transformer` and use `#[WithTransformer]`. The transformer runs before recursive serialization.

```php
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

Transformer failures are wrapped in `TransformerException` with the output path.

## Serialization order

For each field DTOKit applies the following order:

1. Skip `#[Hidden]` fields.
2. Replace `#[Sensitive]` or `#[Redact]` values.
3. Apply `#[WithTransformer]` when present.
4. Recursively serialize the resulting value.
5. Write it under the configured output name.

Redaction takes precedence over transformers so sensitive input is never passed into output transformation accidentally.

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

`ListOf` is mapping metadata and is not needed when directly constructing output objects. PHPDoc remains useful for static analysis.

## Unsupported values

Arbitrary objects are not serialized automatically. Convert value objects through a transformer or represent them as a nested `Data` object. Silent public-property serialization would make output contracts unstable and could expose private information.
