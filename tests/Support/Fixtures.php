<?php

declare(strict_types=1);

use DTOKit\Attribute\Hidden;
use DTOKit\Attribute\IgnoreUnknown;
use DTOKit\Attribute\ListOf;
use DTOKit\Attribute\MapInputName;
use DTOKit\Attribute\MapOutputName;
use DTOKit\Attribute\Redact;
use DTOKit\Attribute\Sensitive;
use DTOKit\Attribute\Strict;
use DTOKit\Attribute\WithCast;
use DTOKit\Attribute\WithTransformer;
use DTOKit\Contract\Cast;
use DTOKit\Contract\Transformer;
use DTOKit\InputData;
use DTOKit\OutputData;

enum Status: string
{
    case Active = 'active';
}

enum UnitStatus
{
    case Ready;
}

interface FirstContract {}
interface SecondContract {}
final class ContractValue implements FirstContract, SecondContract {}

final class UntypedFixture
{
    /** @param mixed $value */
    public function __construct($value)
    {
        if ($value === self::class) {
            throw new RuntimeException('Unused fixture guard.');
        }
    }
}

final class TrimCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}
final class UpperTransformer implements Transformer
{
    public function transform(mixed $value): mixed
    {
        return is_string($value) ? strtoupper($value) : $value;
    }
}

final class FailingCast implements Cast
{
    public function cast(mixed $value): mixed
    {
        throw new RuntimeException('unsafe raw value');
    }
}

final class FailingTransformer implements Transformer
{
    public function transform(mixed $value): mixed
    {
        throw new RuntimeException('failed');
    }
}

final readonly class AddressData extends InputData
{
    public function __construct(public string $city) {}
}

final readonly class UserData extends InputData
{
    public function __construct(
        #[MapInputName('user_id'), MapOutputName('id')] public int $userId,
        #[WithCast(TrimCast::class), WithTransformer(UpperTransformer::class)] public string $name,
        public ?string $nickname,
        public AddressData $address,
        /** @var list<AddressData> */
        #[ListOf(AddressData::class)] public array $offices,
        public Status $status,
        public DateTimeImmutable $createdAt,
        #[Sensitive] public string $password,
        #[Redact('***')] public string $token,
        #[Hidden] public string $internal,
        public string $role = 'user',
    ) {}
}

#[IgnoreUnknown]
final readonly class LooseData extends InputData
{
    public function __construct(public string $name) {}
}

final readonly class ResponseData extends OutputData
{
    public function __construct(public int $id) {}
}

final readonly class UnionData extends InputData
{
    public function __construct(public string|int $value) {}
}

final readonly class CastFailureData extends InputData
{
    public function __construct(#[WithCast(FailingCast::class)] public string $value) {}
}

final readonly class TransformerFailureData extends OutputData
{
    public function __construct(#[WithTransformer(FailingTransformer::class)] public string $value) {}
}

final readonly class UnsupportedOutputData extends OutputData
{
    public function __construct(public object $value) {}
}

final readonly class FloatData extends OutputData
{
    public function __construct(public float $value) {}
}

final readonly class InvalidMetadataData extends InputData {}

final readonly class IntersectionData extends InputData
{
    public function __construct(public FirstContract&SecondContract $value) {}
}

final readonly class NullableIntersectionData extends InputData
{
    public function __construct(public (FirstContract&SecondContract)|null $value) {}
}

final readonly class DuplicateInputData extends InputData
{
    public function __construct(
        #[MapInputName('value')] public string $first,
        #[MapInputName('value')] public string $second,
    ) {}
}

#[Strict]
final readonly class StrictOutputData extends OutputData
{
    public function __construct(public int $id) {}
}

final readonly class ThrowingConstructorData extends InputData
{
    public function __construct(public string $value)
    {
        throw new RuntimeException('constructor failed');
    }
}

final readonly class ScalarData extends InputData
{
    /** @param list<string> $items */
    public function __construct(
        public mixed $anything,
        public float $ratio,
        public bool $enabled,
        public array $items,
        public object $context,
        public ContractValue $contract,
    ) {}
}

final readonly class RecursiveData extends InputData
{
    public function __construct(public ?self $child = null) {}
}

final readonly class ParentTypedData extends InputData
{
    public function __construct(public parent $value) {}
}

final readonly class UnitEnumOutput extends OutputData
{
    public function __construct(public UnitStatus $status) {}
}

/** @return array<string, mixed> */
function validPayload(): array
{
    return [
        'user_id' => '42', 'name' => ' Ada ', 'nickname' => null,
        'address' => ['city' => 'Cape Town'], 'offices' => [['city' => 'Durban']],
        'status' => 'active', 'createdAt' => '2026-06-28T10:00:00+02:00',
        'password' => 'secret', 'token' => 'abc', 'internal' => 'private',
    ];
}
