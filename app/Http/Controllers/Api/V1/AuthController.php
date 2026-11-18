<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:120'],
            'email'                 => ['required', 'email', 'max:190', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:10', 'confirmed'],
            'company_name'          => ['required', 'string', 'max:160'],
            'business_type'         => ['nullable', 'in:hvac,plumbing,electrical,appliance_repair,general'],
            'phone'                 => ['nullable', 'string', 'max:30'],
        ]);

        [$user, $company] = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'phone'    => $data['phone'] ?? null,
            ]);

            $company = Company::create([
                'name'          => $data['company_name'],
                'business_type' => $data['business_type'] ?? 'general',
                'phone'         => $data['phone'] ?? null,
                'email'         => strtolower($data['email']),
                'currency'      => 'USD',
                'timezone'      => 'America/New_York',
            ]);

            // Create Owner Role for company
            $ownerRole = Role::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'owner'],
                ['description' => 'Business Owner']
            );
            $allPermissions = Permission::all();
            if ($allPermissions->isNotEmpty()) {
                $ownerRole->permissions()->sync($allPermissions->pluck('id'));
            }

            // Create default Technician & Dispatcher roles
            Role::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'technician'],
                ['description' => 'Field Technician']
            );
            Role::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'dispatcher'],
                ['description' => 'Dispatcher & Service Coordinator']
            );

            // Attach user as Owner
            $company->users()->attach($user->id, ['role' => 'owner']);
            $user->forceFill(['current_company_id' => $company->id])->save();

            return [$user, $company];
        });

        $token = $user->createToken('servicepilot-mobile')->plainTextToken;

        return $this->created([
            'token' => $token,
            'user'  => $user->fresh()->load('currentCompany'),
        ], 'Company and account registered successfully.');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        // Validate active company context
        if (!$user->current_company_id || !$user->companies()->whereKey($user->current_company_id)->exists()) {
            $company = $user->companies()->first();
            if ($company) {
                $user->forceFill(['current_company_id' => $company->id])->save();
            }
        }

        $deviceName = $data['device_name'] ?? 'servicepilot-mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->success([
            'token' => $token,
            'user'  => $user->fresh()->load('currentCompany', 'companies'),
        ], 'Signed in successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('currentCompany', 'companies');

        return $this->success([
            'user' => $user,
        ]);
    }

    public function switchCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $targetCompany = $user->companies()->whereKey($data['company_id'])->first();

        if (!$targetCompany) {
            return $this->error('You are not an authorized member of this company.', 403);
        }

        $user->forceFill(['current_company_id' => $targetCompany->id])->save();

        return $this->success([
            'user'            => $user->fresh()->load('currentCompany', 'companies'),
            'current_company' => $targetCompany,
        ], 'Switched active company successfully.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:120'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password'     => ['nullable', 'string', 'min:10', 'confirmed'],
        ]);

        if (!empty($data['new_password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password does not match.'],
                ]);
            }
            $user->password = Hash::make($data['new_password']);
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }

        $user->save();

        return $this->success($user->fresh(), 'Profile updated successfully.');
    }

    public function listInvitations(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');
        $invitations = Invitation::where('company_id', $companyId)->latest()->get();

        return $this->success($invitations);
    }

    public function inviteTeamMember(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role'  => ['required', 'in:owner,admin,dispatcher,technician,accountant'],
        ]);

        // Check if user already in company
        $existingUser = User::where('email', strtolower($data['email']))->first();
        if ($existingUser && $existingUser->companies()->whereKey($companyId)->exists()) {
            return $this->error('This user is already a member of this company.', 422);
        }

        $invitation = Invitation::create([
            'company_id' => $companyId,
            'invited_by' => $request->user()->id,
            'email'      => strtolower($data['email']),
            'role'       => $data['role'],
            'token'      => Str::random(40),
            'expires_at' => now()->addDays(7),
        ]);

        return $this->created($invitation, 'Invitation sent successfully.');
    }

    public function revokeInvitation(Invitation $invitation): JsonResponse
    {
        if ($invitation->company_id !== app('currentCompanyId')) {
            return $this->error('Unauthorized.', 403);
        }

        $invitation->delete();

        return $this->success(null, 'Invitation revoked successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'All active sessions revoked.');
    }
}
