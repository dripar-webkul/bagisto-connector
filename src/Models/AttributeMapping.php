<?php

namespace Webkul\Bagisto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Bagisto\Contracts\AttributeMapping as AttributeMappingContract;
use Webkul\Bagisto\Presenters\JsonDataPresenter;
use Webkul\HistoryControl\Contracts\HistoryAuditable as HistoryContract;
use Webkul\HistoryControl\Interfaces\PresentableHistoryInterface;
use Webkul\HistoryControl\Traits\HistoryTrait;

class AttributeMapping extends Model implements AttributeMappingContract, HistoryContract, PresentableHistoryInterface
{
    use HistoryTrait;

    protected $historyTags = ['bagitsto_credentials'];

    protected $table = 'wk_bagisto_attribute_config_mapping';

    protected $fillable = [
        'credential_id',
        'section',
        'mapped_value',
        'fixed_value',
        'additional_info',
    ];

    protected $casts = [
        'mapped_value'    => 'json',
        'fixed_value'     => 'json',
        'additional_info' => 'json',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class, 'credential_id');
    }

    public function getPrimaryModelIdForHistory(): int
    {
        return (int) $this->credential_id;
    }

    public static function getPresenters(): array
    {
        return [
            'mapped_value'    => JsonDataPresenter::class,
            'fixed_value'     => JsonDataPresenter::class,
            'additional_info' => JsonDataPresenter::class,
        ];
    }
}
