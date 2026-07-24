# Errors and Explain Mode

DTOKit distinguishes three failure surfaces: thrown exceptions for "stop the operation" cases, result objects for "failure is control flow" cases, and explain traces for "show me what happened" cases. This page covers all three, plus the exception hierarchy, the explain event catalog, and integration patterns.

## Exception hierarchy

All package exceptions derive from `DTOKitException`, which extends `\RuntimeException`. Catch `DTOKitException` to handle any package failure as a single category.

```text
DTOKitException
├── MappingException
│   ├── UnknownFieldException
│   ├── MissingRequiredFieldException
│   ├── TypeMismatchException
│   ├── AmbiguousMappingException
│   └── CastException
├── MetadataException
└── SerializationException
    └── TransformerException
```

| Exception | When thrown |
| --- | --- |
| `MappingException` | Generic mapping failure (e.g. constructor threw, depth exceeded, non-string keys). |
| `UnknownFieldException` | A strict class received input keys it does not declare. |
| `MissingRequiredFieldException` | A required key is absent and has no default. |
| `TypeMismatchException` | A value does not satisfy the declared type. |
| `AmbiguousMappingException` | A non-null union type is encountered without a `#[WithCast]`. |
| `CastException` | A `#[WithCast]` implementation threw. |
| `MetadataException` | The class shape is unsupported (no public constructor, non-promoted parameter, untyped parameter, duplicate input name, duplicate attribute). |
| `SerializationException` | Serialization failure (unsupported value, depth exceeded, JSON encoding failure). |
| `TransformerException` | A `#[WithTransformer]` implementation threw. |

Mapping exceptions expose a safe `context` array with the DTO class, nested path, pipeline stage, and expected or received types where applicable. Context never contains raw sensitive values — only type names and debug types.

## Throwing mapping

Use `from()` when invalid input should stop the current operation. Catch the specific subclass to handle the failure, or catch `MappingException` to handle any mapping failure.

```php
try {
    $data = CustomerData::from($payload);
} catch (TypeMismatchException $error) {
    // Wrong type at a known path
    echo $error->context['path']; // e.g. 'address.city'
} catch (MissingRequiredFieldException $error) {
    // Required key missing
    echo $error->context['path']; // e.g. 'email'
} catch (UnknownFieldException $error) {
    // Strict mode rejected extra keys
    var_dump($error->context['fields']); // e.g. ['slug', 'meta']
} catch (MappingException $error) {
    // Any other mapping failure
    echo $error->getMessage();
}
```

### Reading error context

```php
try {
    CustomerData::from($payload);
} catch (MappingException $error) {
    $error->context;
}
```

A context payload may contain:

| Key | Meaning |
| --- | --- |
| `class` | DTO being mapped when the failure occurred. |
| `path` | Dot-separated location in the original payload. |
| `stage` | Mapping stage such as `type`, `cast`, `constructor`, `unknown_fields`. |
| `expected` | Expected runtime type when applicable. |
| `received` | Safe received type (`get_debug_type`), never the raw value. |
| `fields` | Unknown field paths for strict-input failures. |

`received` is always a type name (e.g. `'string'`, `'array'`, `'float'`), never the value. A password field that receives an integer is reported as `received: 'integer'`, not as the integer itself.

## Result-based mapping

Use `tryFrom()` when failure is expected control flow: batch imports, polyglot inputs, "try the strict DTO, fall back to the lenient one" patterns, or when building user-facing validation messages.

```php
$result = CustomerData::tryFrom($payload);

if ($result->succeeded()) {
    $customer = $result->data;
    // proceed
} else {
    $error = $result->error; // MappingException subclass
    // log, build a validation message, fall back, etc.
}
```

`MappingResult` is a final readonly value object. Exactly one of `data` and `error` is populated by the public factories. `succeeded()` and `failed()` are mutually exclusive.

### tryFrom vs from

| Use `from()` when | Use `tryFrom()` when |
| --- | --- |
| Invalid input is genuinely exceptional. | Invalid input is expected and recoverable. |
| You want to abort the current operation. | You want to collect failures or fall back. |
| You are inside a request handler that maps one payload. | You are importing a batch and want to skip bad rows. |
| You will translate the exception into an HTTP error response. | You want to build user-facing validation messages. |

`tryFrom()` catches `MappingException` only. `MetadataException` and `SerializationException` are still thrown, because they indicate programmer errors rather than bad input.

## Explain mode

`explain()` records the mapping pipeline without leaking values marked sensitive. It always returns an `ExplainResult` — it never throws for mapping failures.

```php
$explanation = CustomerData::explain($payload);

$explanation->toArray();
$explanation->toJson(JSON_PRETTY_PRINT);
$explanation->toText();
```

`ExplainResult` exposes:

- `events` — ordered list of staged events describing the mapping.
- `data` — the mapped object, or `null` on failure.
- `error` — the captured `MappingException`, or `null` on success.
- `succeeded()` — whether mapping succeeded.
- `toArray()`, `toJson()`, `toText()` — renderers.

### Event catalog

Each event is an `array` with `stage`, `message`, and a small context payload. Stages are stable strings you can assert on:

| Stage | When emitted | Useful context keys |
| --- | --- | --- |
| `source` | After source normalization, before mapping begins. | `type` (debug type of the source). |
| `match` | When a payload key matches a declared input name. | `path`, `value` (`[redacted]` for sensitive fields). |
| `default` | When a missing key uses a constructor default. | `path`. |
| `cast` | When a `#[WithCast]` is applied. | `path`, `cast` (class-string). |
| `unknown` | When unknown fields are detected. | `paths` (list of dotted paths). |
| `result` | Final outcome. | `class` on success; the exception's context on failure. |

Sensitive fields appear in `match` events with `value: '[redacted]'` — never with the raw value. Cast and unknown events never carry values, only paths and class strings.

### Reading a trace

```php
$explanation = CustomerData::explain($payload);

foreach ($explanation->events as $event) {
    echo "[{$event['stage']}] {$event['message']}\n";
}
```

A successful trace looks like:

```text
[source] Normalized input source
[match] Matched input field
[match] Matched input field
[default] Used constructor default
[result] Mapping succeeded
```

A failed trace looks like:

```text
[source] Normalized input source
[match] Matched input field
[result] Mapping failed
```

The `[result]` event on failure carries the exception's context, so the trace alone is enough to diagnose the failure without re-running mapping.

### Diffing successful and failed traces

Explain traces are deterministic for a given class and payload. This makes them useful for assertions:

```php
expect($explanation->succeeded())->toBeTrue()
    ->and(count($explanation->events))->toBeGreaterThan(1)
    ->and($explanation->events[0]['stage'])->toBe('source');
```

For failures, assert on the error context rather than message wording, which may evolve:

```php
expect($explanation->succeeded())->toBeFalse()
    ->and($explanation->error?->context['path'])->toBe('address.city')
    ->and($explanation->error?->context['stage'])->toBe('type');
```

## Integrating explain with logging

Explain output is designed to be safe to log. `toText()` produces a compact, single-line-per-event trace with no raw sensitive values; `toJson()` produces structured JSON for log aggregation.

```php
$explanation = OrderData::explain($payload);

if ($explanation->failed()) {
    $this->logger->warning('Order payload rejected', [
        'path'    => $explanation->error?->context['path'],
        'stage'   => $explanation->error?->context['stage'],
        'trace'   => $explanation->toText(),
    ]);
}
```

Never log the raw `$payload` alongside the trace — it may contain secrets. The trace is intentionally safe; the payload is not.

### PSR-3 integration pattern

```php
try {
    $order = OrderData::from($payload);
} catch (MappingException $error) {
    $this->logger->info('Mapping rejected payload', [
        'class' => $error->context['class'] ?? null,
        'path'  => $error->context['path']  ?? null,
        'stage' => $error->context['stage'] ?? null,
    ]);

    throw $error;
}
```

Use explain when you need the full trace; use caught-exception context when you need only the failure point.

## When to use explain vs tryFrom

| Use `tryFrom()` when | Use `explain()` when |
| --- | --- |
| You need the mapped object or the error. | You need the full decision trace. |
| You are building validation messages. | You are debugging "why did this map?" |
| You are implementing fall-back logic. | You are writing test assertions on stages. |
| Performance matters — `tryFrom()` does not record events. | You can afford the small overhead of event recording. |

`explain()` re-runs mapping with tracing enabled. It is slightly slower than `tryFrom()` and should not be used on hot paths in production. Use it for debugging, testing, and one-off diagnostics.

## Common failures

### Missing is not nullable

If a nullable parameter has no default, callers must still provide the key. Add a constructor default only when omission is part of the contract.

```php
public ?string $middleName // required key, value may be null
public ?string $middleName = null // optional key, value may be null
```

### Unknown field rejected

Check for a typo first — a misspelled `emal` instead of `email` is the most common cause. Use `#[IgnoreUnknown]` only when the boundary intentionally accepts forward-compatible extra fields.

### Ambiguous union

DTOKit does not guess between non-null union members. Add a custom cast that implements a documented selection rule, or replace the union with a dedicated value type whose constructor documents the rule.

### Constructor failure

Constructor exceptions are wrapped as `MappingException('Could not construct …')` with the previous exception chained. DTO constructors should remain side-effect free and should not contain business validation or service calls. If your constructor throws, treat it as a programmer error, not a mapping failure.

### Cast or transformer threw

`CastException` and `TransformerException` chain the original throwable via `getPrevious()`. Inspect the previous exception to find the root cause:

```php
try {
    $data = OrderData::from($payload);
} catch (CastException $error) {
    $original = $error->getPrevious(); // the cast's exception
}
```

## What explain is not

Explain output is designed for debugging and assertions. It is **not**:

- A validation-message localization system. Build user-facing messages in your application layer.
- A stable wire format. The exact event set and message wording may evolve between minor versions. Assert on `stage` and `context` keys, not on prose.
- A substitute for logging the actual payload. The trace is safe; the payload is not.

## Where to go next

- [Testing Data Boundaries](/testing) covers asserting on explain output.
- [API Reference](/api) covers the exact `MappingResult` and `ExplainResult` shapes.
