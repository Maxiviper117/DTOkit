<?php

declare(strict_types=1);

use DTOKit\Exception\CastException;
use DTOKit\Exception\SerializationException;
use DTOKit\Exception\TransformerException;

it('serializes mapped values safely', function (): void {
    $serialized = UserData::from(validPayload())->toArray();

    expect($serialized)->toMatchArray([
        'id' => 42, 'name' => 'ADA', 'nickname' => null,
        'address' => ['city' => 'Cape Town'], 'offices' => [['city' => 'Durban']],
        'status' => 'active', 'password' => '[redacted]', 'token' => '***', 'role' => 'user',
    ])->and(new UnitEnumOutput(UnitStatus::Ready)->toArray())->toBe(['status' => 'Ready']);
    expect($serialized)->not->toHaveKey('internal');
});

it('wraps cast, transformer, and encoding failures', function (): void {
    expect(fn (): CastFailureData => CastFailureData::from(['value' => 'secret']))->toThrow(CastException::class, 'value')
        ->and(fn (): array => new TransformerFailureData('x')->toArray())->toThrow(TransformerException::class)
        ->and(fn (): array => new UnsupportedOutputData(new stdClass)->toArray())->toThrow(SerializationException::class, 'Unsupported')
        ->and(fn (): string => new FloatData(NAN)->toJson())->toThrow(SerializationException::class, 'JSON encoding');
});
