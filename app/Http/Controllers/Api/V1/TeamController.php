<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');
        $company = Company::findOrFail($companyId);

        $members = $company->users()
            ->with(['roles' => function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            }])
            ->get()
            ->map(function ($user) {
                $pivotRole = $user->pivot->role ?? 'technician';
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $pivotRole,
                    'joined_at' => $user->pivot->created_at?->toIso8601String(),
                ];
            });

        return $this->success($members, 'Team members retrieved successfully.');
    }

    public function invite(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role'  => ['required', 'in:admin,dispatcher,technician'],
        ]);

        $invitation = Invitation::updateOrCreate(
            [
                'company_id' => $companyId,
                'email'      => strtolower($data['email']),
            ],
            [
                'role'       => $data['role'],
                'token'      => Str::random(40),
                'expires_at' => now()->addDays(7),
            ]
        );

        return $this->created($invitation, 'Invitation sent successfully.');
    }
}
