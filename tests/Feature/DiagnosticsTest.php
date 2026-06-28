<?php

declare(strict_types=1);

use DTOKit\Exception\MappingException;
use DTOKit\Exception\MissingRequiredFieldException;
use DTOKit\Exception\SerializationException;
use DTOKit\Result\MappingResult;

it('returns non-throwing mapping and safe explanations', function (): void {
    $failed = AddressData::tryFrom([]);
    $explain = UserData::explain(validPayload());

    expect($failed->failed())->toBeTrue()
        ->and($explain->succeeded())->toBeTrue()
        ->and($explain->toArray()['events'])->not->toBeEmpty()
        ->and($explain->toText())->toContain('Mapping succeeded')
        ->and($explain->toJson())->toBeJson();
    expect($explain->toJson())->not->toContain('secret');
});

it('represents failed explanations and both result states', function (): void {
    $explain = AddressData::explain([]);
    $success = MappingResult::success(new AddressData('Gqeberha'));
    $failure = MappingResult::failure(new MappingException('failed'));

    expect($explain->succeeded())->toBeFalse()
        ->and($explain->error)->toBeInstanceOf(MissingRequiredFieldException::class)
        ->and($explain->toText())->toContain('Mapping failed')
        ->and($success->succeeded())->toBeTrue()->and($success->failed())->toBeFalse()
        ->and($failure->succeeded())->toBeFalse()->and($failure->failed())->toBeTrue();
});

it('guards mapping and serialization recursion depth', function (): void {
    $payload = [];
    for ($index = 0; $index < 66; $index++) {
        $payload = ['child' => $payload];
    }
    expect(fn (): RecursiveData => RecursiveData::from($payload))->toThrow(MappingException::class, 'Maximum mapping depth');

    $data = new RecursiveData;
    for ($index = 0; $index < 66; $index++) {
        $data = new RecursiveData($data);
    }
    expect(fn (): array => $data->toArray())->toThrow(SerializationException::class, 'Maximum serialization depth');
});
