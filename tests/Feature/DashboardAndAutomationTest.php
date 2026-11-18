<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_aggregated_metrics_and_alerts(): void
    {
        $company = Company::create([
            'name'          => 'Apex HVAC LLC',
            'business_type' => 'hvac',
            'email'         => 'apex@test.com',
            'currency'      => 'USD',
        ]);

        $user = User::create([
            'name'               => 'Owner User',
            'email'              => 'owner@apex.com',
            'password'           => bcrypt('Secret12345!'),
            'current_company_id' => $company->id,
        ]);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name'       => 'Alice Smith',
            'first_name' => 'Alice',
            'last_name'  => 'Smith',
            'email'      => 'alice@test.com',
        ]);

        ServiceRequest::create([
            'company_id'      => $company->id,
            'customer_id'     => $customer->id,
            'title'           => 'AC Blowing Warm',
            'description'     => 'Freon leak suspected',
            'service_address' => '123 Main St, Miami, FL',
            'status'          => 'new',
            'priority'        => 'urgent',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.metrics.new_requests', 1)
                 ->assertJsonStructure([
                     'data' => [
                         'metrics' => [
                             'today_appointments',
                             'new_requests',
                             'active_jobs',
                             'completed_jobs',
                             'pending_estimates',
                             'unpaid_invoices',
                             'overdue_invoices',
                             'revenue_this_month',
                         ],
                         'upcoming_appointments',
                         'urgent_requests',
                         'automation_alerts',
                     ],
                 ]);
    }

    public function test_automations_can_be_retrieved_and_toggled(): void
    {
        $company = Company::create([
            'name'          => 'Elite Plumbing LLC',
            'business_type' => 'plumbing',
            'email'         => 'elite@test.com',
            'currency'      => 'USD',
        ]);

        $user = User::create([
            'name'               => 'Plumber Boss',
            'email'              => 'boss@elite.com',
            'password'           => bcrypt('Secret12345!'),
            'current_company_id' => $company->id,
        ]);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->getJson('/api/v1/automations');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['rules', 'recent_executions']]);

        // Toggle rule
        $toggleResponse = $this->withHeader('Authorization', "Bearer $token")
                               ->postJson('/api/v1/automations/auto_invoice_on_job_complete/toggle');

        $toggleResponse->assertStatus(200)
                       ->assertJsonPath('success', true);
    }
}
