<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AttendanceRecordController extends Controller
{
    public function index(Request $request): View
    {
        $date = $this->resolveDate($request->query('date'));

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('date', $date->toDateString())
            ->get();

        $users = User::whereIn('id', $attendanceRecords->pluck('user_id'))
            ->orderBy('id')
            ->get();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->toDateString(),
            'nextDay' => $date->copy()->addDay()->toDateString(),
            'users' => $users,
            'attendanceRecords' => $attendanceRecords,
        ]);
    }

    // ?date=YYYY-mm-dd をCarbonにする。不正な値は今日に戻す
    private function resolveDate(?string $date): Carbon
    {
        $isValid = Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->passes();

        return $isValid
            ? Carbon::createFromFormat('!Y-m-d', $date)
            : now()->startOfDay();
    }
}
