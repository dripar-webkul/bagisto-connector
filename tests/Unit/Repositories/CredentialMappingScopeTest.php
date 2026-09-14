<?php

use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Bagisto\Models\AttributeMapping;
use Webkul\Bagisto\Models\CategoryFieldMapping;
use Webkul\Bagisto\Models\Credential;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\CategoryFieldMappingRepository;

uses(TestCase::class);

function bagistoCredential(string $suffix): Credential
{
    return Credential::create([
        'shop_url' => 'https://store-'.$suffix.'.test',
        'email'    => $suffix.'@example.com',
        'password' => 'secret',
    ]);
}

it('resolves the mapping of the credential it was asked for', function () {
    $first = bagistoCredential('one');
    $second = bagistoCredential('two');

    AttributeMapping::create([
        'credential_id' => $first->id,
        'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
        'mapped_value'  => ['sku' => 'sku'],
    ]);

    AttributeMapping::create([
        'credential_id' => $second->id,
        'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
        'mapped_value'  => ['sku' => 'product_number'],
    ]);

    $repository = resolve(AttributeMappingRepository::class);

    expect($repository->forCredential($first->id, MappingSection::STANDARD_ATTRIBUTE)->mapped_value)
        ->toBe(['sku' => 'sku'])
        ->and($repository->forCredential($second->id, MappingSection::STANDARD_ATTRIBUTE)->mapped_value)
        ->toBe(['sku' => 'product_number']);
});

it('returns nothing when the credential has no mapping for that section', function () {
    $credential = bagistoCredential('empty');

    expect(resolve(AttributeMappingRepository::class)->forCredential($credential->id, MappingSection::IMAGE_ATTRIBUTE))
        ->toBeNull();
});

it('returns nothing rather than a stray row when no credential is given', function () {
    $credential = bagistoCredential('anon');

    AttributeMapping::create([
        'credential_id' => $credential->id,
        'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
        'mapped_value'  => ['sku' => 'sku'],
    ]);

    expect(resolve(AttributeMappingRepository::class)->forCredential(null, MappingSection::STANDARD_ATTRIBUTE))
        ->toBeNull();
});

it('creates a section on first save and updates it afterwards', function () {
    $credential = bagistoCredential('save');
    $repository = resolve(CategoryFieldMappingRepository::class);

    $repository->saveSection($credential->id, MappingSection::STANDARD_FIELD, ['mapped_value' => ['name' => 'name']]);
    $repository->saveSection($credential->id, MappingSection::STANDARD_FIELD, ['mapped_value' => ['name' => 'slug']]);

    expect(CategoryFieldMapping::where('credential_id', $credential->id)->count())->toBe(1)
        ->and($repository->forCredential($credential->id, MappingSection::STANDARD_FIELD)->mapped_value)
        ->toBe(['name' => 'slug']);
});

it('drops a credential mappings along with the credential', function () {
    $credential = bagistoCredential('cascade');

    AttributeMapping::create([
        'credential_id' => $credential->id,
        'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
        'mapped_value'  => ['sku' => 'sku'],
    ]);

    CategoryFieldMapping::create([
        'credential_id' => $credential->id,
        'section'       => MappingSection::STANDARD_FIELD->value,
        'mapped_value'  => ['name' => 'name'],
    ]);

    $credentialId = $credential->id;
    $credential->delete();

    expect(AttributeMapping::where('credential_id', $credentialId)->count())->toBe(0)
        ->and(CategoryFieldMapping::where('credential_id', $credentialId)->count())->toBe(0);
});

it('keeps one credential cached data away from another', function () {
    expect(CacheType::ATTRIBUTE_MAPPING->forCredential(1))
        ->not->toBe(CacheType::ATTRIBUTE_MAPPING->forCredential(2))
        ->and(CacheType::BAGISTO_API_HTTP->forCredential(1))
        ->not->toBe(CacheType::BAGISTO_API_HTTP->forCredential(2));
});

it('records mapping history against the owning credential', function () {
    $credential = bagistoCredential('history');

    $mapping = AttributeMapping::create([
        'credential_id' => $credential->id,
        'section'       => MappingSection::STANDARD_ATTRIBUTE->value,
        'mapped_value'  => ['sku' => 'sku'],
    ]);

    expect($mapping->getPrimaryModelIdForHistory())->toBe($credential->id)
        ->and($mapping->generateTags())->toBe(['bagitsto_credentials']);
});
