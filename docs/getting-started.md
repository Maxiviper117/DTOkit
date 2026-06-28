# Getting Started

## Requirements

- PHP 8.4 or newer
- Composer 2

## Installation

```bash
composer require maxiviper117/dtokit-core
```

## Define input data

Input data represents untrusted values entering the application. It rejects unknown fields by default.

```php
use DTOKit\InputData;

final readonly class CreatePostData extends InputData
{
    public function __construct(
        public string $title,
        public string $body,
        public ?int $categoryId = null,
    ) {}
}

$post = CreatePostData::from([
    'title' => 'Typed boundaries',
    'body' => 'Arrays in, typed object out.',
]);
```

Nullable and optional are different. `?int` permits explicit `null`; the default `= null` permits the key to be missing.

## Define output data

Output data describes values leaving the application.

```php
use DTOKit\OutputData;

final readonly class PostOutput extends OutputData
{
    public function __construct(
        public int $id,
        public string $title,
    ) {}
}

$output = new PostOutput(10, 'Typed boundaries');

$output->toArray(); // ['id' => 10, 'title' => 'Typed boundaries']
$output->toJson();  // {"id":10,"title":"Typed boundaries"}
```

## Handle mapping failure

`from()` throws a specific mapping exception. Use `tryFrom()` when failure should be represented as a value.

```php
$result = CreatePostData::tryFrom(['title' => 'Missing body']);

if ($result->failed()) {
    echo $result->error?->getMessage();
}
```

## Map external field names

External payloads do not need to follow PHP naming conventions.

```php
use DTOKit\Attribute\MapInputName;
use DTOKit\Attribute\MapOutputName;

final readonly class UserData extends InputData
{
    public function __construct(
        #[MapInputName('user_id')]
        #[MapOutputName('id')]
        public int $userId,
        public string $name,
    ) {}
}

$user = UserData::from([
    'user_id' => '42',
    'name' => 'Ada',
]);

$user->userId;  // 42
$user->toArray(); // ['id' => 42, 'name' => 'Ada']
```

Input and output names are separate decisions. This makes it possible to accept a legacy payload while exposing a cleaner output contract.

## Map nested data

```php
final readonly class AddressData extends InputData
{
    public function __construct(public string $city) {}
}

final readonly class CustomerData extends InputData
{
    public function __construct(
        public string $name,
        public AddressData $address,
    ) {}
}

$customer = CustomerData::from([
    'name' => 'Ada',
    'address' => ['city' => 'Cape Town'],
]);
```

Nested failures retain their path. An invalid city produces an error for `address.city`, not just `city`.

## Choose the right entry point

| Method | Use when |
| --- | --- |
| `from()` | Invalid input should throw and stop the current operation. |
| `tryFrom()` | Mapping failure is expected control flow. |
| `explain()` | You need a safe trace showing how mapping reached its result. |
| `toArray()` | You need a transport-ready PHP array. |
| `toJson()` | You need JSON generated from the same array contract. |

Next, read [Core Concepts](/concepts) and [Mapping Input](/mapping).
