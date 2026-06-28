<?php

declare(strict_types=1);

use DTOKit\Data;
use DTOKit\Exception\AmbiguousMappingException;
use DTOKit\Exception\MetadataException;
use DTOKit\Internal\Engine;

it('rejects ambiguous unions and missing constructors', function (): void {
    expect(fn (): UnionData => UnionData::from(['value' => 1]))->toThrow(AmbiguousMappingException::class)
        ->and(fn (): InvalidMetadataData => InvalidMetadataData::from([]))->toThrow(MetadataException::class, 'public constructor');
});

it('validates constructor and attribute metadata', function (): void {
    eval('final readonly class CoverageNonPromotedData extends \\DTOKit\\InputData { public function __construct(string $value) {} }');
    eval('final readonly class CoverageDuplicateAttributeData extends \\DTOKit\\InputData { public function __construct(#[\\DTOKit\\Attribute\\MapInputName("first"), \\DTOKit\\Attribute\\MapInputName("second")] public string $value) {} }');
    $nonPromoted = 'CoverageNonPromotedData';
    $duplicateAttribute = 'CoverageDuplicateAttributeData';

    if (! is_a($nonPromoted, Data::class, true) || ! is_a($duplicateAttribute, Data::class, true)) {
        throw new RuntimeException('Invalid coverage fixture declaration.');
    }
    expect(fn (): Data => Engine::instance()->map($nonPromoted, ['value' => 'x']))->toThrow(MetadataException::class, 'promoted');

    $parameter = new ReflectionClass(UntypedFixture::class)->getConstructor()?->getParameters()[0];
    expect($parameter)->toBeInstanceOf(ReflectionParameter::class);
    $type = new ReflectionMethod(Engine::class, 'type');

    expect(fn (): mixed => $type->invoke(Engine::instance(), $parameter, ResponseData::class))->toThrow(MetadataException::class, 'must have a type')
        ->and(fn (): IntersectionData => IntersectionData::from(['value' => new ContractValue]))->toThrow(MetadataException::class, 'intersection type')
        ->and(fn (): NullableIntersectionData => NullableIntersectionData::from(['value' => new ContractValue]))->toThrow(MetadataException::class, 'intersection union')
        ->and(fn (): DuplicateInputData => DuplicateInputData::from(['value' => 'x']))->toThrow(MetadataException::class, 'Duplicate input name')
        ->and(fn (): Data => Engine::instance()->map($duplicateAttribute, ['first' => 'x']))->toThrow(MetadataException::class, 'duplicated');
});
