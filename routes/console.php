<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bms:sync-jobs')
    ->everyTwentySeconds()
    ->withoutOverlapping();

Schedule::command('shift:auto-control --mode=close')
    ->cron('*/5 21-23,0-5 * * *')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping();

Schedule::command('shift:auto-control --mode=open')
    ->dailyAt('08:00')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping();
