<?php

namespace Webkul\Bagisto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Bagisto\Contracts\CategoryFieldMapping as CategoryFieldMappingContract;
use Webkul\Bagisto\Presenters\JsonDataPresenter;
use Webkul\HistoryControl\Contracts\HistoryAuditable as HistoryContract;
use Webkul\HistoryControl\Interfaces\PresentableHistoryInterface;
use Webkul\HistoryControl\Traits\HistoryTrait;

class CategoryFieldMapping extends Model implements CategoryFieldMappingContract, HistoryContract, PresentableHistoryInterface
{
    use HistoryTrait;

    protected $historyTags = ['bagitsto_credentials'];

    protected $table = 'wk_bagisto_category_field_config_mapping';

    protected $fillable = [
        'credential_id',
        'section',
        'mapped_value',
        'fixed_value',
    ];

    protected $casts = [
        'mapped_value' => 'json',
        'fixed_value'  => 'json',
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
            'mapped_value' => JsonDataPresenter::class,
            'fixed_value'  => JsonDataPresenter::class,
        ];
    }
}
