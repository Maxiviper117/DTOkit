<?php

declare(strict_types=1);

use DTOKit\Attribute\Hidden;
use DTOKit\Attribute\IgnoreUnknown;
use DTOKit\Attribute\ListOf;
use DTOKit\Attribute\MapInputName;
use DTOKit\Attribute\MapOutputName;
use DTOKit\Attribute\Redact;
use DTOKit\Attribute\Sensitive;
use DTOKit\Attribute\WithCast;
use DTOKit\Attribute\WithTransformer;
use DTOKit\Contract\Cast;
use DTOKit\Contract\Transformer;
use DTOKit\Exception\AmbiguousMappingException;
use DTOKit\Exception\CastException;
use DTOKit\Exception\MetadataException;
use DTOKit\Exception\MissingRequiredFieldException;
use DTOKit\Exception\SerializationException;
use DTOKit\Exception\TransformerException;
use DTOKit\Exception\TypeMismatchException;
use DTOKit\Exception\UnknownFieldException;
use DTOKit\InputData;
use DTOKit\OutputData;

enum Status: string
{
    case Active = 'active';
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

it('maps supported values and serializes safely', function (): void {
    $data = UserData::from(validPayload());

    expect($data->userId)->toBe(42)
        ->and($data->name)->toBe('Ada')
        ->and($data->role)->toBe('user')
        ->and($data->address->city)->toBe('Cape Town')
        ->and($data->offices[0])->toBeInstanceOf(AddressData::class)
        ->and($data->status)->toBe(Status::Active)
        ->and($data->toArray())->toMatchArray([
            'id' => 42, 'name' => 'ADA', 'nickname' => null,
            'address' => ['city' => 'Cape Town'], 'offices' => [['city' => 'Durban']],
            'status' => 'active', 'password' => '[redacted]', 'token' => '***', 'role' => 'user',
        ])
        ->and($data->toArray())->not->toHaveKey('internal');
});

it('normalizes public object properties', function (): void {
    expect(ResponseData::from((object) ['id' => 7, 'ignored' => true])->id)->toBe(7);
});

it('rejects unknown input fields in InputData', function (): void {
    UserData::from(validPayload() + ['admin' => true]);
})->throws(UnknownFieldException::class, 'admin');

it('allows class-level permissive input', function (): void {
    expect(LooseData::from(['name' => 'Ada', 'extra' => 1])->name)->toBe('Ada');
});

it('reports missing and nested type failures with paths', function (): void {
    expect(fn (): AddressData => AddressData::from([]))->toThrow(MissingRequiredFieldException::class, 'city');
    $payload = validPayload();
    $payload['address'] = ['city' => []];
    expect(fn (): UserData => UserData::from($payload))->toThrow(TypeMismatchException::class, 'address.city');
});

it('returns non-throwing mapping and explain results', function (): void {
    $failed = AddressData::tryFrom([]);
    $explain = UserData::explain(validPayload());

    expect($failed->failed())->toBeTrue()
        ->and($explain->succeeded())->toBeTrue()
        ->and($explain->toArray()['events'])->not->toBeEmpty()
        ->and($explain->toText())->toContain('Mapping succeeded')
        ->and($explain->toJson())->toBeJson()
        ->and($explain->toJson())->not->toContain('secret');
});

it('rejects invalid scalar, list, enum, and date input', function (): void {
    expect(fn (): ResponseData => ResponseData::from(['id' => '1.2']))->toThrow(TypeMismatchException::class, 'id');

    $payload = validPayload();
    $payload['offices'] = ['office' => ['city' => 'Durban']];
    expect(fn (): UserData => UserData::from($payload))->toThrow(TypeMismatchException::class, 'list');

    $payload = validPayload();
    $payload['status'] = 'disabled';
    expect(fn (): UserData => UserData::from($payload))->toThrow(TypeMismatchException::class, 'status');

    $payload = validPayload();
    $payload['createdAt'] = [];
    expect(fn (): UserData => UserData::from($payload))->toThrow(TypeMismatchException::class, 'createdAt');
});

it('rejects ambiguous unions and invalid metadata', function (): void {
    expect(fn (): UnionData => UnionData::from(['value' => 1]))->toThrow(AmbiguousMappingException::class);
    expect(fn (): InvalidMetadataData => InvalidMetadataData::from([]))->toThrow(MetadataException::class, 'public constructor');
});

it('wraps extension and serialization failures', function (): void {
    expect(fn (): CastFailureData => CastFailureData::from(['value' => 'secret']))->toThrow(CastException::class, 'value');
    expect(fn (): array => new TransformerFailureData('x')->toArray())->toThrow(TransformerException::class);
    expect(fn (): array => new UnsupportedOutputData(new stdClass)->toArray())->toThrow(SerializationException::class, 'Unsupported');
    expect(fn (): string => new FloatData(NAN)->toJson())->toThrow(SerializationException::class, 'JSON encoding');
});
