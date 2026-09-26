<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $route = $request->user()->role === UserRole::Manager
            ? 'manager.dashboard'
            : 'professional.dashboard';

        return redirect()->route($route);
    }
}
