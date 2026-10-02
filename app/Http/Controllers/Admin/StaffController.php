<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        return view('admin.staff-list', ['users' => $users]);
    }
}
