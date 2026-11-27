<?php

namespace App\Models;

use App\Models\Concerns\FormatsNaiveTimestamps;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueItem extends Model
{
    use FormatsNaiveTimestamps;

    protected $table = 'queue_app_queueitem';

    public $timestamps = false;

    protected $fillable = [
        'queue_number',
        'user_name',
        'user_department',
        'issue_description',
        'comment',
        'is_urgent',
        'created_at',
        'linked_job_no',
        'status_id',
        'call_queue_date',
        'is_adhoc',
    ];

    protected function casts(): array
    {
        return [
            'is_urgent' => 'integer',
            'is_adhoc' => 'integer',
            'linked_job_no' => 'integer',
            'status_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if (empty($item->attributes['created_at'])) {
                $item->attributes['created_at'] = Carbon::now('Asia/Bangkok')->format('Y-m-d H:i:s');
            }
            $item->normalizeModelTimestamps(['created_at', 'call_queue_date']);
        });
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(QueueStatus::class, 'status_id');
    }

    public function bmsJob(): BelongsTo
    {
        return $this->belongsTo(JobsBms::class, 'linked_job_no', 'jobno');
    }
}
