<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    public function index(): View
    {
        $applications = Application::with(['user', 'attendanceRecord'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.admin-application-list', ['applications' => $applications]);
    }
}
