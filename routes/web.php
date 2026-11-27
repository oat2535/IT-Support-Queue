<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::match(['get', 'post'], '/add-queue', [QueueController::class, 'addQueue'])->name('add_queue');

Route::post('/login-member', [AuthController::class, 'login'])->middleware('throttle:15,1')->name('login_member');
Route::post('/logout-member', [AuthController::class, 'logout'])->name('logout_member');

Route::middleware('staff')->group(function (): void {
    Route::post('/call-next', [QueueController::class, 'callNext'])->name('call_next');
    Route::post('/finish-queue', [QueueController::class, 'finishQueue'])->name('finish_queue');
    Route::post('/update-job-desc', [QueueController::class, 'updateDescription'])->name('update_job_desc');
    Route::post('/toggle-urgent', [QueueController::class, 'toggleUrgent'])->name('toggle_urgent');
    Route::post('/insert-queue', [QueueController::class, 'insertAdhoc'])->name('insert_queue');
    Route::post('/finish-adhoc', [QueueController::class, 'finishAdhoc'])->name('finish_adhoc');
    Route::post('/close-queue', [QueueController::class, 'closeQueue'])->name('close_queue');
    Route::post('/toggle-shift-status', [ShiftController::class, 'toggle'])->name('toggle_shift_status');
});
