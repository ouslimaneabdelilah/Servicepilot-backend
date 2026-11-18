<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_company_and_account(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'John Contractor',
            'email'                 => 'john@coolairservices.com',
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'company_name'          => 'Cool Air Services LLC',
            'business_type'         => 'hvac',
            'phone'                 => '+15550001111',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure([
                     'data' => [
                         'token',
                         'user' => ['id', 'name', 'email', 'current_company'],
                     ],
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'john@coolairservices.com']);
        $this->assertDatabaseHas('companies', ['name' => 'Cool Air Services LLC']);
    }

    public function test_user_can_login_and_retrieve_current_context(): void
    {
        $company = Company::create([
            'name'          => 'Elite HVAC',
            'business_type' => 'hvac',
            'email'         => 'admin@elitehvac.com',
            'currency'      => 'USD',
            'timezone'      => 'America/New_York',
        ]);

        $user = User::create([
            'name'               => 'Admin User',
            'email'              => 'admin@elitehvac.com',
            'password'           => bcrypt('Password123!'),
            'current_company_id' => $company->id,
        ]);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin@elitehvac.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_user_can_switch_active_company(): void
    {
        $c1 = Company::create(['name' => 'Company One', 'email' => 'c1@test.com', 'business_type' => 'general']);
        $c2 = Company::create(['name' => 'Company Two', 'email' => 'c2@test.com', 'business_type' => 'general']);

        $user = User::create([
            'name'               => 'Multi-Tenant Boss',
            'email'              => 'boss@multitenant.com',
            'password'           => bcrypt('Password123!'),
            'current_company_id' => $c1->id,
        ]);
        $c1->users()->attach($user->id, ['role' => 'owner']);
        $c2->users()->attach($user->id, ['role' => 'owner']);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/v1/auth/switch-company', [
                             'company_id' => $c2->id,
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.current_company.id', $c2->id);

        $this->assertEquals($c2->id, $user->fresh()->current_company_id);
    }
}

