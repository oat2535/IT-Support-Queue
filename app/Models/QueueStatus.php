<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueStatus extends Model
{
    protected $table = 'queue_app_queuestatus';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'code',
        'color',
    ];

    /**
     * Queue items currently assigned to this status.
     */
    public function queueItems(): HasMany
    {
        return $this->hasMany(QueueItem::class, 'status_id');
    }
}
