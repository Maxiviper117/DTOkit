# Recipes

## Accept snake case and expose camel case

```php
final readonly class ProductData extends InputData
{
    public function __construct(
        #[MapInputName('product_id')]
        public int $productId,
    ) {}
}
```

Because no output mapping is supplied, serialization uses `productId`.

## Accept one name and expose another

```php
public function __construct(
    #[MapInputName('legacy_customer_number')]
    #[MapOutputName('customerId')]
    public int $customerId,
) {}
```

## Model a webhook

```php
final readonly class PaymentWebhookData extends InputData
{
    public function __construct(
        #[MapInputName('event_id')] public string $eventId,
        public PaymentState $state,
        public DateTimeImmutable $occurredAt,
        public PaymentDetailsData $details,
    ) {}
}
```

Keep webhook signature verification outside the DTO. Verify authenticity first, then map the verified payload.

## Produce a public response

```php
final readonly class UserOutput extends OutputData
{
    public function __construct(
        public int $id,
        public string $name,
        #[Hidden] public string $internalReference,
    ) {}
}
```

Prefer omitting sensitive fields from the constructor entirely. Use visibility attributes when the value is needed internally by the boundary object.

## Handle partial updates today

The Core MVP does not yet include explicit missing/null/present field wrappers. Use a dedicated DTO for each supported patch shape or keep partial-update detection outside DTOKit until field-state support is released. Do not infer “missing” from `null`.

## Normalize a value object

Use a cast to create the value object and a transformer to convert it back into a transport-safe shape. Both attributes may be applied to the same property.
