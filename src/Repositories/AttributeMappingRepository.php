<?php

namespace Webkul\Bagisto\Repositories;

use Illuminate\Database\Eloquent\Model;
use Webkul\Bagisto\Enums\Export\MappingSection;
use Webkul\Core\Eloquent\Repository;

class AttributeMappingRepository extends Repository
{
    public function model(): string
    {
        return 'Webkul\Bagisto\Contracts\AttributeMapping';
    }

    public function forCredential(int|string|null $credentialId, MappingSection $section): ?Model
    {
        if ($credentialId === null) {
            return null;
        }

        return $this->findWhere([
            'credential_id' => $credentialId,
            'section'       => $section->value,
        ])->first();
    }

    public function saveSection(int|string $credentialId, MappingSection $section, array $attributes): Model
    {
        $existing = $this->forCredential($credentialId, $section);

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return $this->create($attributes + [
            'credential_id' => $credentialId,
            'section'       => $section->value,
        ]);
    }
}
