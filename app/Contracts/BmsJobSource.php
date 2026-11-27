<?php

namespace App\Contracts;

interface BmsJobSource
{
    /**
     * Round 1: Fetch active/waiting jobs from BMS (job_status IN ('11', '1') AND dept_control = '2').
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchNewActiveJobs(): array;

    /**
     * Round 2: Fetch fresh BMS details for specific local job numbers.
     *
     * @param  array<int, int>  $jobNumbers
     * @return array<int, array<string, mixed>>
     */
    public function fetchJobsByNumbers(array $jobNumbers): array;
}
