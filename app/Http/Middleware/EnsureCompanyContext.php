<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $companyId = $user->current_company_id;

        // If user has no current_company_id set, pick their first membership
        if (!$companyId) {
            $firstCompany = $user->companies()->first();
            if ($firstCompany) {
                $companyId = $firstCompany->id;
                $user->forceFill(['current_company_id' => $companyId])->save();
            }
        }

        // Verify that user holds an active membership in this company
        $membership = $user->companies()->whereKey($companyId)->first();
        abort_unless($membership, 403, 'No authorized company selected.');

        // Bind current tenant ID in the service container
        app()->instance('currentCompanyId', (int) $companyId);

        // Resolve user's role and permissions for this active company
        $roleName = $membership->pivot->role ?? 'technician';
        $user->setAttribute('active_role', $roleName);

        // Dynamic Gate resolution: owners have full access, others checked against role permissions
        Gate::before(function ($user, $ability) use ($companyId, $roleName) {
            if ($roleName === 'owner') {
                return true;
            }

            // Look up permissions assigned to this role in this company
            $role = Role::where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })->where('name', $roleName)->with('permissions')->first();

            if ($role && $role->permissions->contains('name', $ability)) {
                return true;
            }

            return null;
        });

        return $next($request);
    }
}
