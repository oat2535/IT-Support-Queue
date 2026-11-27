<?php

namespace App\Services;

use App\Contracts\BmsJobSource;
use Illuminate\Support\Facades\DB;

class SqlsrvBmsJobSource implements BmsJobSource
{
    private const BASE_SELECT_SQL = <<<'SQL'
        SELECT
            jobs.jobno,
            jobs.catagory,
            jobs.description,
            jobs.dept_tech,
            e.name,
            jobs.jobdate,
            jobs.assign_date,
            jobs.arrive_date,
            jobs.req_date,
            jobs.caller,
            jobs.sap_code,
            jobs.aname,
            jobs.note,
            jobs.act_dstart,
            jobs.act_dfin,
            jobs.job_status,
            jobs.return_date,
            jobs.enterdate,
            jobs.enterby,
            jobs.outsource_date,
            CASE
                WHEN description LIKE N'%ระบบล่ม%' OR description LIKE N'%กู้ระบบ%' OR description LIKE N'%security%' OR
                     description LIKE N'%server%' OR description LIKE N'%firewall%' OR description LIKE N'%database%' OR
                     description LIKE N'%ความปลอดภัย%'
                THEN 5
                WHEN description LIKE N'%ติดตั้งระบบใหม่%' OR description LIKE N'%เปลี่ยนอะไหล่%' OR description LIKE N'%ซ่อมบอร์ด%' OR
                     description LIKE N'%ไวรัส%' OR description LIKE N'%กู้ข้อมูล%' OR description LIKE N'%คอมพิวเตอร์(ชำรุด)%'
                THEN 4
                WHEN description LIKE N'%เพิ่มสายแลน%' OR description LIKE N'%ย้ายโทรศัพท์%' OR description LIKE N'%อินเทอร์เน็ต หลุด%' OR
                     description LIKE N'%อินเทอร์เน็ต ช้า%' OR description LIKE N'%ไฟดับ%'
                THEN 2
                WHEN description LIKE N'%ปรินเตอร์%' OR description LIKE N'%พิมพ์ ไม่ได้%' OR description LIKE N'%ตั้งค่า%' OR
                     description LIKE N'%เปลี่ยนรหัส%' OR description LIKE N'%เมาส์%' OR description LIKE N'%คีย์บอร์ด%'
                THEN 1
                ELSE 3
            END AS difficulty,
            CASE
                WHEN description LIKE N'%server%' OR description LIKE N'%database%' OR description LIKE N'%firewall%' OR
                     description LIKE N'%network core%' OR description LIKE N'%ระบบล่ม%' OR description LIKE N'%กู้ระบบ%'
                THEN 'SERVER'
                WHEN description LIKE N'%excel%' OR description LIKE N'%word%' OR description LIKE N'%windows%' OR
                     description LIKE N'%ลงโปรแกรม%' OR description LIKE N'%ตั้งค่า%' OR description LIKE N'%รหัส%'
                THEN 'APP'
                WHEN description LIKE N'%คอม%' OR description LIKE N'%computer%' OR description LIKE N'%จอ%' OR
                     description LIKE N'%ปรินเตอร์%' OR description LIKE N'%พิมพ์%' OR description LIKE N'%เมาส์%' OR
                     description LIKE N'%คีย์บอร์ด%' OR description LIKE N'%โทรศัพท์%' OR description LIKE N'%สายแลน%'
                THEN 'ENDPOINT'
                ELSE 'OTHER'
            END AS job_category_type,
            md.abb_desc,
            md.descriptions
        FROM jobs
        LEFT JOIN employee e ON e.emp_id = jobs.emp_id
        LEFT JOIN m_dept md ON jobs.dept = md.dept
    SQL;

    public function fetchNewActiveJobs(): array
    {
        $sql = self::BASE_SELECT_SQL." WHERE jobs.job_status IN ('11', '1') AND jobs.dept_control = '2' ORDER BY jobs.req_date";

        return array_map(
            fn (object $row): array => (array) $row,
            DB::connection('sqlsrv')->select($sql)
        );
    }

    public function fetchJobsByNumbers(array $jobNumbers): array
    {
        if ($jobNumbers === []) {
            return [];
        }

        $results = [];
        foreach (array_chunk(array_values($jobNumbers), 50) as $batch) {
            $placeholders = implode(', ', array_fill(0, count($batch), '?'));
            $sql = self::BASE_SELECT_SQL." WHERE jobs.jobno IN ({$placeholders})";

            foreach (DB::connection('sqlsrv')->select($sql, $batch) as $row) {
                $results[] = (array) $row;
            }
        }

        return $results;
    }
}
