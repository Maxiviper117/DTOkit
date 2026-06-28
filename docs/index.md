# DTOKit

DTOKit is a framework-agnostic PHP toolkit for defining, mapping, serializing, and inspecting typed data boundaries.

Use it where loose data enters or leaves an application: HTTP payloads, API responses, queue messages, webhooks, CLI input, CSV rows, and external services.

## Why DTOKit?

Raw arrays hide their shape and defer mistakes until runtime. DTOKit turns those arrays into immutable, typed objects with explicit mapping and output rules.

- **Framework neutral:** core mapping works in plain PHP without a container.
- **Strict at trust boundaries:** input objects reject unknown fields by default.
- **Safe output:** hide or redact sensitive fields during serialization.
- **Nested and typed:** map nested objects, lists, enums, and dates recursively.
- **Explainable:** inspect mapping decisions and path-aware failures.

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

The constructor is the contract: `name` and `email` are required, `company` may be missing because it has a default, and unknown fields are rejected.

## Current scope

The Core MVP targets PHP 8.4 and includes mapping, serialization, attributes, custom casts and transformers, structured errors, and explain mode. Framework adapters, validation integrations, contract snapshots, and schema generation are intentionally outside the current package.

Continue with [Getting Started](/getting-started).
