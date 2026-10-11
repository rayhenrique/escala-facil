<?php

namespace App\Http\Middleware;

use App\Http\Requests\AuditHistoryRequest;
use App\Http\Requests\OperationalNavigationRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateOperationalQuery
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->routeIs('manager.dashboard', 'manager.shifts', 'manager.swaps', 'manager.calendar', 'manager.reports', 'professional.calendar', 'professional.reports', 'professional.dashboard', 'manager.audit')) {
            abort_if(Validator::make($request->query(), (new OperationalNavigationRequest)->rules())->fails(), 404);
        }

        if ($request->routeIs('manager.audit')) {
            $filters = new AuditHistoryRequest;
            $filters->replace($request->query());
            abort_if(Validator::make($request->query(), $filters->rules())->fails(), 404);
        }

        return $next($request);
    }
}
