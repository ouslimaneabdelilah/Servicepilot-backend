<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Invoice Air', 'email' => 'inv@air.com', 'business_type' => 'hvac']);
        $this->user = User::create([
            'name'               => 'Accountant User',
            'email'              => 'inv@air.com',
            'password'           => bcrypt('Secret123!'),
            'current_company_id' => $this->company->id,
        ]);
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);
        $this->token = $this->user->createToken('token')->plainTextToken;

        $this->customer = Customer::create([
            'company_id'      => $this->company->id,
            'customer_number' => 'CUST-INV-01',
            'name'            => 'Bob Dylan',
            'email'           => 'bob@dylan.com',
            'phone'           => '555-4321',
            'customer_type'   => 'residential',
            'status'          => 'active',
        ]);
    }

    public function test_payment_recording_reconciles_invoice_balance_and_status(): void
    {
        // 1. Create Invoice for $500.00
        $invoice = Invoice::create([
            'company_id'     => $this->company->id,
            'customer_id'    => $this->customer->id,
            'invoice_number' => 'INV-TEST-500',
            'status'         => 'sent',
            'subtotal'       => 500.00,
            'total'          => 500.00,
            'amount_paid'    => 0.00,
            'balance_due'    => 500.00,
            'issue_date'     => now(),
            'due_date'       => now()->addDays(14),
        ]);

        // 2. Record Partial Payment of $200.00
        $response1 = $this->withHeader('Authorization', "Bearer {$this->token}")
                          ->postJson('/api/v1/payments', [
                              'invoice_id' => $invoice->id,
                              'amount'     => 200.00,
                              'method'     => 'card',
                              'reference'  => 'ch_partial_200',
                          ]);

        $response1->assertStatus(201);

        $invoice->refresh();
        $this->assertEquals('200.00', $invoice->amount_paid);
        $this->assertEquals('300.00', $invoice->balance_due);
        $this->assertEquals('partially_paid', $invoice->status);

        // 3. Record Final Payment of $300.00
        $response2 = $this->withHeader('Authorization', "Bearer {$this->token}")
                          ->postJson('/api/v1/payments', [
                              'invoice_id' => $invoice->id,
                              'amount'     => 300.00,
                              'method'     => 'cash',
                          ]);

        $response2->assertStatus(201);

        $invoice->refresh();
        $this->assertEquals('500.00', $invoice->amount_paid);
        $this->assertEquals('0.00', $invoice->balance_due);
        $this->assertEquals('paid', $invoice->status);
    }
}

