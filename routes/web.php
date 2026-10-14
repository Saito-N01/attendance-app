<?php

use App\Http\Controllers\Admin\AttendanceRecordController as AdminAttendanceRecordController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminStampCorrectionRequestController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\StampCorrectionRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

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

// ヘッダーのロゴ（/）：ログイン状態に応じて最初の画面へ振り分ける
Route::get('/', HomeController::class)->name('home');

// 管理者
Route::prefix('admin')->name('admin.')->group(function () {
    // ログイン・ログアウトは一般ユーザーと同じ Fortify のコントローラーを使う。
    // 管理者かどうかの判定・画面・遷移先は FortifyServiceProvider で /admin/* かどうかにより切り替える
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('admin')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
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
    Route::get('/attendance/report', [AttendanceReportController::class, 'index']);
});

// 管理者（申請の詳細確認・承認・エクスポート）
Route::middleware('admin')->group(function () {
    Route::get('/stamp_correction_request/approve/{id}', [AdminStampCorrectionRequestController::class, 'show'])->whereNumber('id');
    Route::post('/stamp_correction_request/approve/{id}', [AdminStampCorrectionRequestController::class, 'approve'])->whereNumber('id');
    Route::post('/export', [StaffController::class, 'export']);
});
