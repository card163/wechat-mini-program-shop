<?php

declare(strict_types=1);

namespace app\model;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $created_at
 * @property string $updated_at
 */
abstract class BaseModel extends Model
{
    protected $connection = 'mysql';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 统一按业务时区输出，避免 Eloquent 默认序列化成 UTC
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)
            ->setTimezone((string)config('app.default_timezone', 'Asia/Shanghai'))
            ->format('Y-m-d H:i:s');
    }
}
