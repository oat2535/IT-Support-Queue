<?php

namespace App\Models;

use App\Models\Concerns\FormatsNaiveTimestamps;
use Illuminate\Database\Eloquent\Model;

class JobsBms extends Model
{
    use FormatsNaiveTimestamps;

    public const STATUS_LABELS = [
        '0' => 'รอรับซ่อม',
        '1' => 'กำลังดำเนินการ',
        '11' => 'รอจ่ายงาน',
        '12' => 'ตรวจรับงานแล้ว',
        '13' => 'รอใบเสนอราคา',
        '2' => 'ซ่อมเสร็จ',
        '3' => 'ยกเลิก',
        '5' => 'รออนุมัติ',
        '6' => 'รออะไหล่',
        '7' => 'ส่งซ่อมภายนอก',
    ];

    public const COMPLETED_STATUSES = ['2', '12'];

    protected $table = 'jobs_bms';

    public $timestamps = false;

    protected $fillable = [
        'jobno',
        'catagory',
        'description',
        'dept_tech',
        'name',
        'jobdate',
        'assign_date',
        'arrive_date',
        'req_date',
        'caller',
        'sap_code',
        'aname',
        'note',
        'act_dstart',
        'act_dfin',
        'job_status',
        'return_date',
        'enterdate',
        'enterby',
        'outsource_date',
        'abb_desc',
        'descriptions',
        'difficulty',
        'job_category_type',
    ];

    protected function casts(): array
    {
        return [
            'jobno' => 'integer',
            'difficulty' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $job): void {
            $job->normalizeModelTimestamps(
                [
                    'jobdate',
                    'assign_date',
                    'arrive_date',
                    'req_date',
                    'act_dstart',
                    'act_dfin',
                    'return_date',
                    'enterdate',
                    'outsource_date',
                ],
                ['outsource_date'],
            );
        });
    }

    public function getJobStatusDisplay(): string
    {
        $status = (string) ($this->job_status ?? '');

        return self::STATUS_LABELS[$status] ?? "Unknown ({$this->job_status})";
    }

    public function isCompletedInBms(): bool
    {
        return in_array((string) $this->job_status, self::COMPLETED_STATUSES, true);
    }
}
