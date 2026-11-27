<?php

namespace App\Models;

use App\Models\Concerns\FormatsNaiveTimestamps;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ShiftClosure extends Model
{
    use FormatsNaiveTimestamps;

    protected $table = 'queue_app_shiftclosure';

    public $timestamps = false;

    protected $fillable = [
        'closed_at',
        'closed_by',
        'opened_at',
        'opened_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $closure): void {
            if (empty($closure->attributes['closed_at'])) {
                $closure->attributes['closed_at'] = Carbon::now('Asia/Bangkok')->format('Y-m-d H:i:s');
            }
            $closure->normalizeModelTimestamps(['closed_at', 'opened_at']);
        });
    }
}
