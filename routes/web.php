<?php

use App\Http\Controllers\Admin\AttendanceRecordController as AdminAttendanceRecordController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminStampCorrectionRequestController;
use App\Http\Controllers\AdminAuthenticatedSessionController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\StampCorrectionRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// 管理者
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthenticatedSessionController::class, 'store']);
    });

    Route::middleware('admin')->group(function () {
        Route::post('logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('attendance/list', [AdminAttendanceRecordController::class, 'index'])->name('attendance.list');
        Route::get('attendance/{id}', [AdminAttendanceRecordController::class, 'show'])->whereNumber('id')->name('attendance.show');
        Route::get('staff/list', [StaffController::class, 'index'])->name('staff.list');
        Route::get('attendance/staff/{id}', [StaffController::class, 'attendance'])->whereNumber('id')->name('attendance.staff');
    });
});

// 一般ユーザー
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [AttendanceRecordController::class, 'create']);
    Route::post('/attendance', [AttendanceRecordController::class, 'store']);
    Route::get('/attendance/list', [AttendanceRecordController::class, 'index']);
    Route::get('/attendance/detail/{id}', [AttendanceRecordController::class, 'show'])->whereNumber('id');
    Route::get('/attendance/{id}', [AttendanceRecordController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/{id}', [AttendanceRecordController::class, 'update'])->whereNumber('id');
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index']);
    Route::get('/application/{id}', [StampCorrectionRequestController::class, 'show'])->whereNumber('id');
});

// 管理者（申請の詳細確認・承認）
Route::middleware('admin')->group(function () {
    Route::get('/stamp_correction_request/approve/{id}', [AdminStampCorrectionRequestController::class, 'show'])->whereNumber('id');
    Route::post('/stamp_correction_request/approve/{id}', [AdminStampCorrectionRequestController::class, 'approve'])->whereNumber('id');
});
