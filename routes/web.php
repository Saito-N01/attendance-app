<?php

use App\Http\Controllers\AdminAuthenticatedSessionController;
use App\Http\Controllers\AttendanceRecordController;
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
    });
});

// 一般ユーザー
Route::middleware('auth')->group(function () {
    Route::get('/attendance', [AttendanceRecordController::class, 'create']);
    Route::post('/attendance', [AttendanceRecordController::class, 'store']);
    Route::get('/attendance/list', [AttendanceRecordController::class, 'index']);
    Route::get('/attendance/detail/{id}', [AttendanceRecordController::class, 'show'])->whereNumber('id');
    Route::get('/attendance/{id}', [AttendanceRecordController::class, 'show'])->whereNumber('id');
    Route::post('/attendance/{id}', [AttendanceRecordController::class, 'update'])->whereNumber('id');
});
