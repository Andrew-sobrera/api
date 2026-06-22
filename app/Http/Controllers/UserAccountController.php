<?php

namespace App\Http\Controllers;

use App\Services\UserDashboardService;
use Illuminate\Http\Request;

class UserAccountController extends Controller
{
    public function __construct(protected UserDashboardService $dashboardService)
    {
    }

    public function dashboard(Request $request)
    {
        return response()->json($this->dashboardService->stats($request->user()));
    }
}
