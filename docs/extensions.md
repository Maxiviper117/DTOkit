# Custom Casts and Transformers

Casts normalize incoming values. Transformers shape outgoing values. Both are explicit metadata attached to a field and must be constructible without dependencies.

## Writing a cast

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

The cast runs before normal type conversion. Returning a value incompatible with the property type still produces a type mismatch.

## Writing a transformer

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

## Design rules

- Keep extensions deterministic and side-effect free.
- Do not perform database or network calls.
- Do not read framework containers or global mutable configuration.
- Throw meaningful exceptions; DTOKit preserves them as the previous exception.
- Return transport-safe transformer values or nested DTOKit objects.
- Use a dedicated value object when conversion rules carry domain meaning.

## Cast versus constructor logic

Use a cast for transport normalization shared by boundary objects. Keep constructors declarative. Business validation belongs in the application or domain layer after successful mapping.
