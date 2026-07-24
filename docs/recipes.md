# Recipes

Common boundary patterns, worked end-to-end. Each recipe shows the DTOs, the mapping call, and the rationale.

## Naming

### Accept snake_case and expose camelCase

```php
final readonly class ProductData extends InputData
{
    public function __construct(
        #[MapInputName('product_id')]
        public int $productId,
    ) {}
}
```

Because no output mapping is supplied, serialization uses `productId`. Use this when the inbound payload follows JSON conventions but your PHP code follows PSR conventions.

### Accept one name and expose another

```php
public function __construct(
    #[MapInputName('legacy_customer_number')]
    #[MapOutputName('customerId')]
    public int $customerId,
) {}
```

Decouples inbound legacy names from outbound response names — useful when an API field is renamed but backwards compatibility with existing clients must be preserved on the output side, or vice versa.

## Webhooks

### Model a webhook

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

Keep webhook signature verification outside the DTO. Verify authenticity first, then map the verified payload. The DTO is a typed view of the payload; it is not an authenticator.

### Accept forward-compatible webhooks

```php
#[IgnoreUnknown]
final readonly class StripeWebhookData extends InputData
{
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
    ) {}
}
```

Webhook providers add keys over time. `#[IgnoreUnknown]` keeps the boundary stable across provider versions. Be aware that ignored keys are dropped — if you need to forward the raw payload, capture it before mapping.

## Responses

### Produce a public response

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

Prefer omitting sensitive fields from the constructor entirely. Use visibility attributes when the value is needed internally by the boundary object (for example, for an audit log) but should not be emitted.

### Mask a token instead of redacting fully

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

final readonly class ApiKeyOutput extends OutputData
{
    public function __construct(
        #[WithTransformer(TokenMaskTransformer::class)]
        public string $apiKey,
    ) {}
}
```

`#[Sensitive]` would replace the whole value with `[redacted]`. A masking transformer lets callers confirm which key is in use without exposing the full secret.

### Paginated list response

```php
final readonly class UserSummaryOutput extends OutputData
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

final readonly class PaginatedUsersOutput extends OutputData
{
    /** @param list<UserSummaryOutput> $items */
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
        public array $items,
    ) {}
}

$response = new PaginatedUsersOutput(1, 20, 53, [
    new UserSummaryOutput(1, 'Ada'),
    new UserSummaryOutput(2, 'Grace'),
]);

$response->toArray();
// ['page' => 1, 'perPage' => 20, 'total' => 53, 'items' => […]]
```

Build the output DTO directly from domain objects. `#[ListOf]` is not needed on output DTOs because you are constructing them in PHP, not mapping from a payload.

## Value objects

### Round-trip a value object

```php
final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}
}

final class MoneyCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        if (! is_array($value) || ! isset($value['amount'], $value['currency'])) {
            return $value;
        }

        return new Money((int) $value['amount'], (string) $value['currency']);
    }
}

final class MoneyTransformer implements Transformer
{
    public function transform(mixed $value): mixed
    {
        return $value instanceof Money
            ? ['amount' => $value->amount, 'currency' => $value->currency]
            : $value;
    }
}

final readonly class OrderInput extends InputData
{
    public function __construct(
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public Money $total,
    ) {}
}

$input = OrderInput::from(['total' => ['amount' => 1200, 'currency' => 'ZAR']]);
$input->total->amount;    // 1200
$input->toArray();        // ['total' => ['amount' => 1200, 'currency' => 'ZAR']]
```

This is the canonical pattern for domain value objects at a boundary: a cast reconstructs the value object on input; a transformer serializes it back on output. The DTO carries the value object, not the raw array.

## CSV imports

### Map a CSV row

CSV rows arrive as numerically-indexed arrays. Convert them to associative arrays first, then map:

```php
use DTOKit\InputData;

final readonly class CustomerImportRow extends InputData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $company = null,
    ) {}
}

$header = ['name', 'email', 'company'];

foreach ($csvRecords as $row) {
    $payload = array_combine($header, $row);
    $result  = CustomerImportRow::tryFrom($payload);

    if ($result->failed()) {
        $this->report->reject($row, $result->error?->context['path']);
        continue;
    }

    $this->importer->import($result->data);
}
```

Use `tryFrom()` for batch imports so one bad row does not abort the whole file. Log the path from the error context so operators can find the offending column.

## Queue jobs

### Map a queued payload

```php
final readonly class SendWelcomeEmail extends InputData
{
    public function __construct(
        public int $userId,
        public string $email,
        public ?string $displayName = null,
    ) {}
}

// Producer
$payload = json_encode([
    'userId'      => $user->id,
    'email'       => $user->email,
    'displayName' => $user->name,
]);

$queue->push('welcome', $payload);

// Consumer
$payload = json_decode($queue->pop('welcome'), true, flags: JSON_THROW_ON_ERROR);
$job     = SendWelcomeEmail::from($payload);

$this->mailer->welcome($job->userId, $job->email, $job->displayName ?? $job->email);
```

Mapping the queued payload at the consumer boundary catches producer bugs immediately — a missing field, a renamed key, or a wrong type fails before the worker starts doing work, instead of producing a half-sent email.

## CLI input

### Map CLI arguments

```php
final readonly class ImportCommandInput extends InputData
{
    public function __construct(
        public string $file,
        public ?string $format = null,
        public bool $dryRun = false,
    ) {}
}

// $argv parsed into an associative array by your console library
$input = ImportCommandInput::from([
    'file'   => $argv['file'] ?? '',
    'format' => $argv['format'] ?? null,
    'dryRun' => $argv['dry-run'] ?? false,
]);
```

For boolean flags, your console library should produce `true`/`false` rather than the string `'1'`/`'0'`. If you cannot avoid integer `1`/`0`, use a `#[WithCast]` that coerces integers to booleans.

## Versioned APIs

### Version an output contract

```php
final readonly class UserOutputV1 extends OutputData
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

final readonly class UserOutputV2 extends OutputData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

// V1 response
echo (new UserOutputV1($user->id, $user->name))->toJson();

// V2 response
echo (new UserOutputV2($user->id, $user->name, $user->email))->toJson();
```

One output DTO per API version. Never reuse a single DTO with conditional fields — the contract becomes unclear and tests become impossible. Adding a field is a new version; do not mutate an existing version's DTO.

### Renaming a field while keeping compatibility

```php
final readonly class UserOutputV2 extends OutputData
{
    public function __construct(
        public int $id,
        #[MapOutputName('name')] public string $displayName,
    ) {}
}
```

The internal property is `displayName`, but clients still see `name` in the JSON. Use this when refactoring internally without breaking the wire contract.

## Polymorphic payloads

### Dispatch by discriminator

DTOKit does not natively select a class by a discriminator field. Use a cast on a wrapper DTO that inspects the discriminator and returns the right nested instance:

```php
final class EventCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        if (! is_array($value) || ! isset($value['type'])) {
            return $value;
        }

        return match ($value['type']) {
            'payment.paid'    => PaymentPaidEvent::from($value),
            'payment.failed'  => PaymentFailedEvent::from($value),
            default           => $value, // let the engine reject it
        };
    }
}

final readonly class WebhookEnvelope extends InputData
{
    public function __construct(
        public string $eventId,
        #[WithCast(EventCast::class)]
        public PaymentPaidEvent|PaymentFailedEvent $event,
    ) {}
}
```

The cast selects the class; the union type on the property documents the result. The union is non-null, so without the cast it would raise `AmbiguousMappingException` — the cast is what disambiguates.

## Partial updates today

The Core MVP does not yet include explicit missing/null/present field wrappers. Use a dedicated DTO for each supported patch shape, or keep partial-update detection outside DTOKit until field-state support is released. Do not infer "missing" from `null` — they are different concepts.

```php
final readonly class UpdateUserNameInput extends InputData
{
    public function __construct(
        public ?string $name = null,
    ) {}
}

final readonly class UpdateUserEmailInput extends InputData
{
    public function __construct(
        public ?string $email = null,
    ) {}
}
```

One DTO per supported patch operation. The application can then apply only the fields the DTO carries, without guessing whether a `null` means "set to null" or "leave unchanged".

## Normalize a value object

Use a cast to create the value object and a transformer to convert it back into a transport-safe shape. Both attributes may be applied to the same property. See [Round-trip a value object](#round-trip-a-value-object) above.

## Where to go next

- [Attributes](/attributes) covers every attribute used in these recipes.
- [Custom Casts and Transformers](/extensions) covers writing the casts and transformers.
- [Security](/security) covers the safety reasoning behind `#[Hidden]` and `#[Sensitive]`.
