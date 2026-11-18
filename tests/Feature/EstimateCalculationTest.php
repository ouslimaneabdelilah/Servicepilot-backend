<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;
    private ServiceRequest $request;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Calc Air Co', 'email' => 'calc@air.com', 'business_type' => 'hvac']);
        $this->user = User::create([
            'name'               => 'Calc User',
            'email'              => 'calc@air.com',
            'password'           => bcrypt('Secret123!'),
            'current_company_id' => $this->company->id,
        ]);
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);
        $this->token = $this->user->createToken('token')->plainTextToken;

        $this->customer = Customer::create([
            'company_id'      => $this->company->id,
            'customer_number' => 'CUST-01',
            'name'            => 'Alice Cooper',
            'email'           => 'alice@rock.com',
            'phone'           => '555-0199',
            'customer_type'   => 'residential',
            'status'          => 'active',
        ]);

        $this->request = ServiceRequest::create([
            'company_id'      => $this->company->id,
            'customer_id'     => $this->customer->id,
            'request_number'  => 'REQ-01',
            'title'           => 'Furnace checkup',
            'service_address' => '42 Rock Road',
            'status'          => 'new',
            'priority'        => 'normal',
        ]);
    }

    public function test_estimate_totals_are_server_calculated_and_client_cannot_tamper(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->postJson('/api/v1/estimates', [
                             'customer_id'        => $this->customer->id,
                             'service_request_id' => $this->request->id,
                             'discount_amount'    => 50.00,
                             'tax_rate'           => 0.10, // 10%
                             'total'              => 0.01, // Client attempts tampering!
                             'items'              => [
                                 [
                                     'description' => 'Motor Replacement',
                                     'quantity'    => 2,
                                     'unit_price'  => 150.00,
                                 ],
                                 [
                                     'description' => 'Diagnostic Fee',
                                     'quantity'    => 1,
                                     'unit_price'  => 50.00,
                                 ],
                             ],
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        // Expected calculation:
        // Item 1: 2 * 150 = 300.00
        // Item 2: 1 * 50 = 50.00
        // Subtotal = 350.00
        // Discount = 50.00 -> Taxable = 300.00
        // Tax (10%) = 30.00
        // Total = 330.00 (Tampered 0.01 is completely ignored!)
        $this->assertDatabaseHas('estimates', [
            'customer_id'     => $this->customer->id,
            'subtotal'        => 350.00,
            'discount_amount' => 50.00,
            'tax_amount'      => 30.00,
            'total'           => 330.00,
        ]);
    }

    public function test_approving_estimate_atomically_generates_work_order(): void
    {
        $estimate = Estimate::create([
            'company_id'         => $this->company->id,
            'customer_id'        => $this->customer->id,
            'service_request_id' => $this->request->id,
            'estimate_number'    => 'EST-TEST-01',
            'status'             => 'sent',
            'subtotal'           => 200.00,
            'total'              => 200.00,
        ]);

        $estimate->items()->create([
            'description' => 'AC Coil Cleaning',
            'quantity'    => 1,
            'unit_price'  => 200.00,
            'line_total'  => 200.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->postJson("/api/v1/estimates/{$estimate->id}/approve", [
                             'approved_by_name' => 'Alice Cooper',
                             'signature_path'   => 'signatures/alice.png',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.estimate.status', 'approved');

        // Verify Work Order was generated
        $this->assertDatabaseHas('work_orders', [
            'company_id'  => $this->company->id,
            'estimate_id' => $estimate->id,
            'status'      => 'assigned',
        ]);

        // Verify Work Order Items copied
        $this->assertDatabaseHas('work_order_items', [
            'description' => 'AC Coil Cleaning',
            'unit_price'  => 200.00,
        ]);
    }
}

