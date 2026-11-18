<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Company $companyA;
    private string $tokenA;

    private User $userB;
    private Company $companyB;
    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Company A
        $this->companyA = Company::create(['name' => 'Company A', 'email' => 'a@corp.com', 'business_type' => 'hvac']);
        $this->userA = User::create([
            'name'               => 'User A',
            'email'              => 'usera@corp.com',
            'password'           => bcrypt('Secret123!'),
            'current_company_id' => $this->companyA->id,
        ]);
        $this->companyA->users()->attach($this->userA->id, ['role' => 'owner']);
        $this->tokenA = $this->userA->createToken('tokenA')->plainTextToken;

        // Setup Company B
        $this->companyB = Company::create(['name' => 'Company B', 'email' => 'b@corp.com', 'business_type' => 'plumbing']);
        $this->userB = User::create([
            'name'               => 'User B',
            'email'              => 'userb@corp.com',
            'password'           => bcrypt('Secret123!'),
            'current_company_id' => $this->companyB->id,
        ]);
        $this->companyB->users()->attach($this->userB->id, ['role' => 'owner']);

        // Customer in Company B
        $this->customerB = Customer::create([
            'company_id'      => $this->companyB->id,
            'customer_number' => 'CUST-B01',
            'name'            => 'Private Customer B',
            'email'           => 'private@customerb.com',
            'phone'           => '555-9999',
            'customer_type'   => 'residential',
            'status'          => 'active',
        ]);
    }

    public function test_user_from_company_a_cannot_view_company_b_customer(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
                         ->getJson("/api/v1/customers/{$this->customerB->id}");

        // Must return 404 because global scope BelongsToCurrentCompany filters it out
        $response->assertStatus(404);
    }

    public function test_user_from_company_a_cannot_attach_company_b_customer_to_service_request(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
                         ->postJson('/api/v1/service-requests', [
                             'customer_id'     => $this->customerB->id,
                             'title'           => 'Malicious Cross-Tenant Request',
                             'service_address' => '123 Fake Street',
                         ]);

        // FormRequest validation rule Rule::exists('customers', 'id')->where('company_id', $companyA->id)
        // must reject customerB's ID with 422 Unprocessable Entity
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_user_from_company_a_cannot_attach_company_b_customer_to_estimate(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
                         ->postJson('/api/v1/estimates', [
                             'customer_id' => $this->customerB->id,
                             'items'       => [
                                 [
                                     'description' => 'Test Item',
                                     'quantity'    => 1,
                                     'unit_price'  => 100,
                                 ],
                             ],
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertStatus(401);
    }
}

