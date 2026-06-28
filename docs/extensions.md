# Custom Casts and Transformers

Casts normalize incoming values during mapping. Transformers shape outgoing values during serialization. Both are explicit metadata attached to a field, both must be constructible without arguments, and both must be deterministic. This page covers patterns, composition, testing, and anti-patterns.

## The contracts

```php
interface Cast
{
    public function cast(mixed $value): mixed;
}

interface Transformer
{
    public function transform(mixed $value): mixed;
}
```

Both interfaces accept `mixed` and return `mixed`. There is no type constraint at the interface level because the engine still type-checks the result against the declared property type (for casts) or against serializable shapes (for transformers). Implementations should narrow types internally and document their contract with PHPDoc.

## Writing a cast

A cast runs **before** normal type conversion. Its job is to normalize or rewrite the raw payload value into something the engine's type system can accept.

```php
use DTOKit\Contract\Cast;

final class LowercaseEmailCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_string($value)
            ? strtolower(trim($value))
            : $value;
    }
}
```

```php
use DTOKit\Attribute\WithCast;

public function __construct(
    #[WithCast(LowercaseEmailCast::class)]
    public string $email,
) {}
```

The cast's return value is then type-checked against `string`. A non-string return raises `TypeMismatchException` after the cast runs. Cast failures (thrown exceptions) are wrapped in `CastException` with the field path.

### When to use a cast

- **Transport normalization shared by multiple boundaries.** Trimming, lowercasing, normalizing phone numbers, parsing ISO country codes.
- **Disambiguating a union type.** A `#[WithCast]` on an `int|string` parameter picks the member according to a documented rule.
- **Building a value object from a transport shape.** A cast that turns `['amount' => 1200, 'currency' => 'ZAR']` into a `Money` instance.
- **Coercing a format the engine does not accept.** Parsing `'1'`/`'0'` as a boolean when you specifically want that behavior, instead of relying on the engine's `FILTER_VALIDATE_BOOL` rules.

### When not to use a cast

- **Business validation.** "Email must be a real, deliverable address" is a business rule, not a transport-shape rule. Validate it after mapping.
- **Side effects.** A cast must not write to a database, call a service, mutate global state, or log. It is a pure function.
- **Anything that needs constructor arguments.** The engine instantiates with `new`. If you need configuration, define a separate cast class per configuration.

## Writing a transformer

A transformer runs **after** redaction and **before** recursive serialization. Its job is to turn a value the engine cannot serialize natively (a value object, a third-party class, a non-`DateTimeInterface` date) into something it can.

```php
use DTOKit\Contract\Transformer;

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

```php
use DTOKit\Attribute\WithTransformer;

public function __construct(
    #[WithTransformer(DateOnlyTransformer::class)]
    public DateTimeImmutable $birthday,
) {}
```

A transformer may return:

- A scalar or `null` (emitted directly).
- An array (recursed element by element).
- A nested `Data` object (serialized recursively).
- A `BackedEnum` (emits its value), `UnitEnum` (emits its name), or `DateTimeInterface` (emits ATOM).

Anything else raises `SerializationException` after the transformer runs. Transformer failures are wrapped in `TransformerException` with the output path and the original exception chained.

### When to use a transformer

- **Format customization.** Emitting `Y-m-d` instead of ATOM, or a Unix timestamp instead of an ISO string.
- **Value-object serialization.** Turning a `Money` value object into `['amount' => …, 'currency' => …]`.
- **Masking partial values.** Emitting the last four characters of a token instead of the full value, when full redaction is too aggressive.
- **Compatibility shims.** Converting a value from a newer internal representation to a legacy output format.

### Redaction precedes transformers

`#[Sensitive]` and `#[Redact]` are applied **before** the transformer runs. If a field is marked sensitive, the transformer receives the replacement string, not the raw value. If you need a transformer to operate on the real value, do not mark the field sensitive — have the transformer itself emit a safe representation.

## Combining cast and transformer

A property may carry both `#[WithCast]` and `#[WithTransformer]`. The cast runs during mapping; the transformer runs during serialization. They never interact, and the order is fixed by the lifecycle.

```php
#[WithCast(MoneyCast::class)]
#[WithTransformer(MoneyTransformer::class)]
public Money $price
```

This is the standard pattern for value objects: the cast reconstructs the value object from the transport shape on input; the transformer converts it back to a transport shape on output.

## Design rules

- **Keep extensions deterministic.** The same input must always produce the same output. No `mt_rand()`, no `time()`, no `microtime()`, no reading from `$_ENV`.
- **Keep extensions side-effect free.** No database, network, filesystem, cache, or global mutation. Pure functions only.
- **Do not depend on a service container.** The engine instantiates with `new`. Implementations cannot fetch dependencies.
- **Throw meaningful exceptions.** DTOKit preserves the original exception as the previous exception of `CastException` / `TransformerException`. Include the value's type (not the raw value) in the message.
- **Return transport-safe values from transformers.** Scalars, arrays, `Data` objects, enums, and `DateTimeInterface`. Anything else fails serialization.
- **Use a dedicated value object when conversion rules carry domain meaning.** A cast that turns a raw array into a `Money` value object documents the rule in its class name and tests. Inline coercion in the constructor hides it.

## Patterns

### Trim and normalize

```php
final class TrimCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
```

### Coerce a boolean from `1`/`0`

The engine accepts `'1'`/`'0'` (string) but rejects `1`/`0` (int). If your payload carries integer flags, use a cast:

```php
final class IntBoolCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_int($value) ? (bool) $value : $value;
    }
}
```

### Disambiguate a union

```php
final class IntOrStringCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_int($value) ? $value : (string) $value;
    }
}
```

```php
#[WithCast(IntOrStringCast::class)]
public int|string $identifier
```

### Build a value object

```php
final class MoneyCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        if (! is_array($value) || ! isset($value['amount'], $value['currency'])) {
            return $value; // let the engine reject it
        }

        return new Money((int) $value['amount'], (string) $value['currency']);
    }
}
```

### Mask partially instead of full redaction

```php
final class TokenMaskTransformer implements Transformer
{
    public function transform(mixed $value): mixed
    {
        if (! is_string($value) || strlen($value) < 4) {
            return '[redacted]';
        }

        return '…' . substr($value, -4);
    }
}
```

```php
#[WithTransformer(TokenMaskTransformer::class)]
public string $apiKey
```

### Format a date differently

```php
final class UnixTimestampTransformer implements Transformer
{
    public function transform(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface
            ? $value->getTimestamp()
            : $value;
    }
}
```

## Composition

Casts and transformers are not composable through the engine — you cannot chain two `#[WithCast]` attributes on the same field (the metadata step would reject the duplicate). To compose, write a single cast or transformer that calls the others internally:

```php
final class NormalizeEmailCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        $value = (new TrimCast())->cast($value);
        $value = (new LowercaseEmailCast())->cast($value);

        return $value;
    }
}
```

Keep composed extensions small and unit-test each part independently.

## Sharing extensions across DTOs

Casts and transformers are referenced by class-string, so a single implementation can be reused across any number of DTOs:

```php
#[WithCast(TrimCast::class)]
public string $title;

#[WithCast(TrimCast::class)]
public string $body;
```

This is encouraged. A small library of well-tested casts (trim, lowercase, normalize-phone, parse-money) can serve an entire application's boundaries.

## Testing extensions

Treat each cast and transformer as a pure function and unit-test it directly, without going through the engine. Then add an integration test that maps and serializes a DTO using the extension.

```php
it('trims string input', function (): void {
    expect((new TrimCast())->cast('  ada  '))->toBe('ada');
});

it('passes non-string input through unchanged', function (): void {
    expect((new TrimCast())->cast(42))->toBe(42);
});

it('applies the trim cast during mapping', function (): void {
    $data = CreatePostData::from(['title' => '  Hello  ', 'body' => 'World']);

    expect($data->title)->toBe('Hello');
});
```

Add failure-path tests for casts that may throw:

```php
it('wraps cast failures in CastException', function (): void {
    expect(fn () => RiskyData::from(['value' => 'bad']))
        ->toThrow(CastException::class);
});
```

## Anti-patterns

- **A cast that reads `$_ENV` or a config file.** The engine must remain framework-neutral and deterministic. Pass configuration through a dedicated cast class per configuration, not through globals.
- **A transformer that performs I/O.** Serialization happens on the hot path; database or network calls there will destroy performance and break determinism.
- **A cast that returns a value the property type does not accept.** The engine will catch this and raise `TypeMismatchException`, but the failure is confusing — the cast "succeeded" yet mapping failed. Make the cast's return type match the property type.
- **A transformer that returns an unsupported value (e.g. a closure or a resource).** Serialization will raise `SerializationException` immediately. Return a scalar, array, `Data` object, enum, or `DateTimeInterface`.
- **A cast that throws a generic `Exception`.** Throw a specific exception type with a clear message; DTOKit preserves it as the previous exception of `CastException` so callers can inspect the root cause.
- **Marking a field `#[Sensitive]` and expecting the transformer to see the real value.** The transformer sees the replacement string. Use a transformer that masks the value itself, and do not mark the field sensitive.

## Cast versus constructor logic

Use a cast for transport normalization shared by boundary objects. Keep constructors declarative. Business validation belongs in the application or domain layer after successful mapping.

A DTO constructor should do three things: assign its parameters, assign its parameters, and assign its parameters. Anything else — computing derived state, validating business rules, calling services — belongs elsewhere.

## Where to go next

- [Attributes](/attributes) covers `#[WithCast]` and `#[WithTransformer]` placement and resolution.
- [Mapping Input](/mapping) covers where casts fit in the lifecycle.
- [Serialization](/serialization) covers where transformers fit in the lifecycle.
- [Testing Data Boundaries](/testing) covers testing extensions in context.
