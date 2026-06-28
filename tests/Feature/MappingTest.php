<?php

declare(strict_types=1);

use DTOKit\Data;
use DTOKit\Exception\MappingException;
use DTOKit\Exception\MissingRequiredFieldException;
use DTOKit\Exception\TypeMismatchException;
use DTOKit\Exception\UnknownFieldException;
use DTOKit\Internal\Engine;

it('maps supported values', function (): void {
    $data = UserData::from(validPayload());

    expect($data->userId)->toBe(42)
        ->and($data->name)->toBe('Ada')
        ->and($data->role)->toBe('user')
        ->and($data->address->city)->toBe('Cape Town')
        ->and($data->offices[0])->toBeInstanceOf(AddressData::class)
        ->and($data->status)->toBe(Status::Active);
});

it('normalizes public object properties', function (): void {
    expect(ResponseData::from((object) ['id' => 7, 'ignored' => true])->id)->toBe(7);
});

it('applies strict and permissive unknown-field policies', function (): void {
    expect(fn (): UserData => UserData::from(validPayload() + ['admin' => true]))
        ->toThrow(UnknownFieldException::class, 'admin')
        ->and(LooseData::from(['name' => 'Ada', 'extra' => 1])->name)->toBe('Ada')
        ->and(fn (): StrictOutputData => StrictOutputData::from(['id' => 1, 'extra' => true]))
        ->toThrow(UnknownFieldException::class, 'extra');
});

it('reports missing and nested type failures with paths', function (): void {
    expect(fn (): AddressData => AddressData::from([]))->toThrow(MissingRequiredFieldException::class, 'city');
    $payload = validPayload();
    $payload['address'] = ['city' => []];
    expect(fn (): UserData => UserData::from($payload))->toThrow(TypeMismatchException::class, 'address.city');
});

it('rejects invalid scalar, list, enum, and date input', function (): void {
    expect(fn (): ResponseData => ResponseData::from(['id' => '1.2']))->toThrow(TypeMismatchException::class, 'id');
    foreach ([
        ['offices', ['office' => ['city' => 'Durban']], 'list'],
        ['status', 'disabled', 'status'],
        ['createdAt', [], 'createdAt'],
        ['address', 10, 'address'],
        ['status', [], 'status'],
        ['createdAt', 'not a real date %%%', 'createdAt'],
    ] as [$field, $value, $message]) {
        $payload = validPayload();
        $payload[$field] = $value;
        expect(fn (): UserData => UserData::from($payload))->toThrow(TypeMismatchException::class, $message);
    }
});

it('converts scalars and preserves compatible objects', function (): void {
    $address = new AddressData('Pretoria');
    $date = new DateTimeImmutable('2026-06-28T10:00:00+02:00');
    $payload = validPayload();
    $payload['address'] = $address;
    $payload['status'] = Status::Active;
    $payload['createdAt'] = $date;
    $user = UserData::from($payload);

    $context = new stdClass;
    $contract = new ContractValue;
    $scalars = ScalarData::from([
        'anything' => 'anything', 'ratio' => '1.5', 'enabled' => 'true',
        'items' => ['one'], 'context' => $context, 'contract' => $contract,
    ]);

    expect($user->address)->toBe($address)
        ->and($user->status)->toBe(Status::Active)
        ->and($user->createdAt)->toBe($date)
        ->and($scalars->ratio)->toBe(1.5)
        ->and($scalars->enabled)->toBeTrue()
        ->and($scalars->items)->toBe(['one'])
        ->and($scalars->context)->toBe($context)
        ->and($scalars->contract)->toBe($contract);
});

it('wraps constructor and source normalization failures', function (): void {
    expect(fn (): ResponseData => ResponseData::from(['id' => null]))->toThrow(TypeMismatchException::class, 'id')
        ->and(fn (): ThrowingConstructorData => ThrowingConstructorData::from(['value' => 'x']))->toThrow(MappingException::class, 'Could not construct')
        ->and(fn (): Data => Engine::instance()->map(ResponseData::class, [0 => 1]))->toThrow(MappingException::class, 'strings');
});

it('resolves parent property types', function (): void {
    $value = new AddressData('Polokwane');
    expect(ParentTypedData::from(['value' => $value])->value)->toBe($value);
});
