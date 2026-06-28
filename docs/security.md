# Security

DTOKit operates at trust boundaries, so safe defaults matter more than permissive convenience. This page covers the threat model, the controls DTOKit provides, the controls it deliberately does not provide, and how to layer defense in depth.

## Threat model

The threats DTOKit is designed to mitigate:

- **Mass assignment.** An attacker submits keys the application did not intend to accept, hoping one of them sets a privileged field. `InputData` rejects unknown fields by default.
- **Type confusion.** An attacker submits a value of the wrong type (a string where an int is expected, an array where a string is expected) hoping it slips through and triggers downstream bugs. DTOKit's narrow coercion and explicit type checks reject mismatches with the full path.
- **Sensitive-data leakage.** A field the application needs internally (a password, a token, an internal identifier) is accidentally included in serialized output. `#[Hidden]`, `#[Sensitive]`, and `#[Redact]` prevent this.
- **Diagnostic leakage.** A mapping error or explain trace includes the raw sensitive value. Error context records only type names; explain events replace sensitive values with `[redacted]`.
- **Resource exhaustion.** A deeply nested payload exhausts the process stack. The 64-level depth guard stops recursion before this happens.

The threats DTOKit does **not** mitigate:

- **Authentication and authorization.** DTOKit does not know who the caller is.
- **Business-rule validation.** "Is this email deliverable?" is a business question.
- **Injection.** SQL, HTML, command, and LDAP injection are application concerns; DTOKit does not escape values.
- **Request-size limits.** A 10 GB payload will still exhaust memory before mapping begins.
- **Rate limiting.** DTOKit does not throttle.
- **Secret storage.** DTOKit does not encrypt or store secrets.
- **Side channels.** Timing, cache-neighborhood, and similar attacks are out of scope.

Treat DTOKit as one boundary-control layer, not a complete application security system.

## Reject undeclared input

`InputData` rejects unknown fields by default. This reduces accidental mass assignment and makes newly supplied attacker-controlled keys visible immediately — a typo in a client payload, or a new key from a probing scanner, fails loudly instead of silently being accepted.

```php
final readonly class CreateUserData extends InputData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}

CreateUserData::from(['name' => 'Ada', 'email' => 'a@b.c', 'is_admin' => true]);
// throws UnknownFieldException with fields: ['is_admin']
```

### When to use `#[IgnoreUnknown]`

Use `#[IgnoreUnknown]` only for boundaries that explicitly require forward compatibility — typically webhook payloads that may grow new keys between provider versions. Even then, never let ignored values flow into the application unchecked. Ignored values are dropped at the boundary; they are not passed to the constructor.

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

The webhook maps `event_id` and silently drops anything else. If a new event type adds a `signature` key, your code does not break — but you also do not receive the signature, so verify authenticity separately.

## Deliberate output contracts

Do not serialize persistence models directly. Create an output DTO containing only approved fields. The DTO shape is the primary exposure boundary; `#[Hidden]`, `#[Sensitive]`, and `#[Redact]` are safety nets, not the boundary itself.

```php
// Bad: serializing an Eloquent model directly leaks whatever fields it has.
echo json_encode($userModel);

// Good: a dedicated output DTO exposes only approved fields.
final readonly class UserOutput extends OutputData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}
```

### When to use `#[Hidden]` vs omitting the field

If a field is never needed by the boundary object, omit it from the constructor entirely. The DTO is smaller, the contract is clearer, and there is no chance the value leaks.

If a field is needed internally by the boundary object (for example, a CSRF token that must be present on the input side but never emitted), use `#[Hidden]`:

```php
final readonly class FormInput extends InputData
{
    public function __construct(
        public string $name,
        #[Hidden]
        public string $csrfToken,
    ) {}
}
```

The input DTO carries the token for verification, but `toArray()` does not emit it.

## Sensitive diagnostics

Mapping errors record received types rather than raw values. Explain events redact fields marked `#[Sensitive]`. The redaction applies before any transformer runs, so a transformer cannot accidentally leak the raw value through output.

```php
final readonly class LoginInput extends InputData
{
    public function __construct(
        public string $email,
        #[Sensitive]
        public string $password,
    ) {}
}

try {
    LoginInput::from(['email' => 'ada@example.com', 'password' => 42]);
} catch (TypeMismatchException $error) {
    echo $error->context['received']; // 'integer', not 42
}
```

### What to keep out of DTOs entirely

Even with redaction, some values should not be in a DTO at all:

- **Full credentials you do not need to keep.** If you only need to verify a password, do so before constructing the DTO and do not store the password on it.
- **PII you do not need to ship.** If a response does not need a national ID number, do not include it on the output DTO — redaction is a safety net, not a substitute for not having the field.
- **Secrets in field names.** DTOKit does not redact key names. A field named `aws_secret_access_key` will appear in error paths and explain traces by name.

### What custom extensions must do

Custom casts and transformers are responsible for not including raw secrets in exceptions they throw. The engine wraps their exceptions in `CastException` / `TransformerException` and chains the original, but it does not redact the original exception's message.

```php
// Bad: the raw value ends up in the exception message.
final class BadCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException("Bad value: $value");
        }

        return $value;
    }
}

// Good: include the type, not the value.
final class SafeCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException(
                'Expected string, received ' . get_debug_type($value),
            );
        }

        return $value;
    }
}
```

## Object input

Only public object properties are normalized. Private and protected state is not bypassed through reflection. This prevents a malicious or buggy object from smuggling state through visibility barriers.

If you need to map a class that exposes data through methods instead of public properties, write a cast that calls the methods and returns an array, or convert the object to an array before passing it to `from()`.

## Resource limits

Recursive mapping and serialization stop after 64 levels. This prevents malformed deeply nested payloads from causing unbounded recursion. The depth guard counts nested `Data` mapping calls and `#[ListOf]` item mapping calls, not scalar conversions.

Applications should still enforce request-size and collection-size limits **before** mapping. A 10 GB JSON payload with `[[[[…]]]]` 60 levels deep passes the depth guard but exhausts memory during `json_decode()`. Set `post_max_size`, validate `Content-Length`, and cap collection sizes in your HTTP layer.

### Defense in depth

A single control is not enough. Layer them:

1. **Edge:** rate limit by IP, reject oversized bodies at the reverse proxy.
2. **HTTP layer:** enforce `Content-Length`, set `post_max_size`, validate the JSON is parseable.
3. **Application boundary:** map through DTOKit — strict input, typed fields, unknown-field rejection.
4. **Application:** business-rule validation on the typed object.
5. **Domain:** enforce invariants in entities and value objects.
6. **Persistence:** parameterized queries, least-privilege database users.

DTOKit is layer 3. It works best when the surrounding layers also do their jobs.

## Logging safety

Never log raw payloads at any boundary. Logs are often aggregated, archived, and viewed by people who should not see secrets. Log explain traces (which are safe) and error context (which is safe), but not the original payload.

```php
// Bad
$this->logger->debug('Payload received', ['payload' => $payload]);

// Good
$this->logger->debug('Mapping started', ['class' => CreateOrderInput::class]);
// …and on failure:
$this->logger->info('Mapping rejected', $error->context);
```

If you must log the payload for debugging, do so only in non-production environments with explicit redaction of known-sensitive keys, and never log credentials regardless of environment.

## Versioning and breaking changes

Output DTOs are public contracts. Removing a field, renaming a field, or changing a field's type is a breaking change for clients. Use `#[MapOutputName]` to keep old names when renaming internally, and add new fields rather than repurposing old ones.

Input DTOs are also contracts, but the contract is with the caller. Tightening an input contract (rejecting something that used to be accepted) is a breaking change. Loosening it (accepting something that used to be rejected) is usually safe.

The package itself follows semantic versioning. See [Release Guide](/releasing) for the versioning policy.

## What DTOKit does not provide

- Authentication or authorization.
- Business-rule validation.
- HTML, SQL, command, or LDAP escaping.
- Request body size limits.
- Rate limiting.
- Encryption or secret storage.
- CSRF protection.
- Audit logging.
- Session management.

Treat DTOKit as one boundary-control layer, not a complete application security system. Pair it with a framework's authentication and authorization, an HTTP layer's body limits, and an application's business validation.

## Where to go next

- [Testing Data Boundaries](/testing) covers sensitive-data safety tests.
- [Errors and Explain Mode](/diagnostics) covers safe logging.
- [Recipes](/recipes) covers common secure-boundary patterns.
