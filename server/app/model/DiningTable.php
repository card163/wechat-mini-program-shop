<?php

declare(strict_types=1);

namespace app\model;

/**
 * 桌号（大桌 / 小桌 / 一楼）
 */
class DiningTable extends BaseModel
{
    public const int STATUS_ON  = 1;
    public const int STATUS_OFF = 0;

    protected $table = 'nf_table';

    protected $casts = [
        'zone_id'    => 'integer',
        'sort'       => 'integer',
        'status'     => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $appends = ['zone_name'];

    public function zone(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TableZone::class, 'zone_id');
    }

    public function getZoneNameAttribute(): string
    {
        return (string)($this->zone?->name ?? '');
    }
}
