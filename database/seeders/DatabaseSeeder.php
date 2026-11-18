<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use App\Models\WorkOrderItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Global Permissions
        $permissionNames = [
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'services.view', 'services.manage',
            'requests.view', 'requests.create', 'requests.update', 'requests.assign',
            'appointments.view', 'appointments.create', 'appointments.update',
            'estimates.view', 'estimates.create', 'estimates.update', 'estimates.approve', 'estimates.send',
            'work_orders.view', 'work_orders.create', 'work_orders.update', 'work_orders.start', 'work_orders.complete',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.send', 'invoices.void',
            'payments.view', 'payments.record', 'payments.refund',
            'team.manage',
        ];

        $permissions = [];
        foreach ($permissionNames as $name) {
            $permissions[$name] = Permission::firstOrCreate(['name' => $name], ['description' => "Permission to $name"]);
        }

        // 2. Company 1: Precision HVAC (New York)
        $company1 = Company::create([
            'name'          => 'Precision Air & Heating',
            'business_type' => 'hvac',
            'phone'         => '+1 (212) 555-0199',
            'email'         => 'contact@precisionair.com',
            'address'       => '350 5th Ave, New York, NY 10118',
            'timezone'      => 'America/New_York',
            'currency'      => 'USD',
        ]);

        // Roles for Company 1
        $ownerRole1 = Role::create(['company_id' => $company1->id, 'name' => 'owner', 'description' => 'Business Owner']);
        $ownerRole1->permissions()->sync(array_values(array_map(fn($p) => $p->id, $permissions)));

        $techRole1 = Role::create(['company_id' => $company1->id, 'name' => 'technician', 'description' => 'Field Technician']);
        $techRole1->permissions()->sync([
            $permissions['customers.view']->id,
            $permissions['requests.view']->id,
            $permissions['appointments.view']->id,
            $permissions['work_orders.view']->id,
            $permissions['work_orders.start']->id,
            $permissions['work_orders.complete']->id,
        ]);

        // Users for Company 1
        $ownerUser1 = User::create([
            'name'               => 'Alex Miller',
            'email'              => 'alex@precisionair.com',
            'password'           => Hash::make('Password123!'),
            'current_company_id' => $company1->id,
            'phone'              => '+1 (212) 555-0101',
        ]);
        $company1->users()->attach($ownerUser1->id, ['role' => 'owner']);

        $techUser1 = User::create([
            'name'               => 'Mike Rodriguez',
            'email'              => 'mike.tech@precisionair.com',
            'password'           => Hash::make('Password123!'),
            'current_company_id' => $company1->id,
            'phone'              => '+1 (212) 555-0102',
        ]);
        $company1->users()->attach($techUser1->id, ['role' => 'technician']);

        // Bind tenant context to Company 1 for seeding tenant models
        app()->instance('currentCompanyId', $company1->id);

        // Service Categories & Services for Company 1
        $catHVAC = ServiceCategory::create([
            'company_id'  => $company1->id,
            'name'        => 'Cooling & Heating',
            'description' => 'Air conditioning, heat pumps, and furnace services',
        ]);

        $serviceDiagnostic = Service::create([
            'company_id'         => $company1->id,
            'category_id'        => $catHVAC->id,
            'name'               => 'System Diagnostic & Inspection',
            'description'        => 'Full multi-point HVAC inspection and troubleshooting',
            'base_price'         => 129.00,
            'estimated_duration' => 60,
            'pricing_type'       => 'fixed',
        ]);

        $serviceTuneup = Service::create([
            'company_id'         => $company1->id,
            'category_id'        => $catHVAC->id,
            'name'               => 'Seasonal AC Maintenance Tune-Up',
            'description'        => 'Coil cleaning, refrigerant level check, filter replacement, electrical tests',
            'base_price'         => 189.00,
            'estimated_duration' => 90,
            'pricing_type'       => 'fixed',
        ]);

        $serviceCapacitor = Service::create([
            'company_id'         => $company1->id,
            'category_id'        => $catHVAC->id,
            'name'               => 'Dual Run Capacitor Replacement',
            'description'        => 'Replacement of motor start/run capacitor including parts and labor',
            'base_price'         => 245.00,
            'estimated_duration' => 45,
            'pricing_type'       => 'fixed',
        ]);

        // Customer 1 for Company 1
        $cust1 = Customer::create([
            'company_id'      => $company1->id,
            'customer_number' => 'CUST-1001',
            'first_name'      => 'Robert',
            'last_name'       => 'Johnson',
            'name'            => 'Robert Johnson',
            'email'           => 'robert.j@gmail.com',
            'phone'           => '+1 (646) 555-7821',
            'customer_type'   => 'residential',
            'status'          => 'active',
            'notes'           => 'Gate code #4412. Prefers morning appointments.',
        ]);

        $addr1 = CustomerAddress::create([
            'company_id'     => $company1->id,
            'customer_id'    => $cust1->id,
            'label'          => 'Home',
            'street_address' => '742 Evergreen Terrace',
            'apartment'      => 'Apt 4B',
            'city'           => 'Brooklyn',
            'state'          => 'NY',
            'postal_code'    => '11201',
            'is_default'     => true,
        ]);

        // Customer 2 for Company 1
        $cust2 = Customer::create([
            'company_id'      => $company1->id,
            'customer_number' => 'CUST-1002',
            'first_name'      => 'Sarah',
            'last_name'       => 'Connor',
            'company_name'    => 'Cyberdyne Systems Cafe',
            'name'            => 'Sarah Connor',
            'email'           => 'sconnor@cyberdyne.org',
            'phone'           => '+1 (212) 555-9080',
            'customer_type'   => 'commercial',
            'status'          => 'active',
            'notes'           => 'Commercial kitchen rooftop AC unit.',
        ]);

        CustomerAddress::create([
            'company_id'     => $company1->id,
            'customer_id'    => $cust2->id,
            'label'          => 'Storefront',
            'street_address' => '101 Broadway',
            'city'           => 'New York',
            'state'          => 'NY',
            'postal_code'    => '10006',
            'is_default'     => true,
        ]);

        // Service Request 1 -> Estimate -> Work Order -> Invoice -> Payment
        $req1 = ServiceRequest::create([
            'company_id'      => $company1->id,
            'customer_id'     => $cust1->id,
            'title'           => 'AC blowing warm air in living room',
            'description'     => 'Unit is humming but no cold air is coming out of the vents. Thermostat set to 68F.',
            'service_address' => '742 Evergreen Terrace, Apt 4B, Brooklyn, NY 11201',
            'status'          => 'completed',
            'priority'        => 'high',
            'requested_at'    => Carbon::now()->subDays(2),
        ]);

        $est1 = Estimate::create([
            'company_id'         => $company1->id,
            'customer_id'        => $cust1->id,
            'service_request_id' => $req1->id,
            'estimate_number'    => 'EST-2026-001',
            'status'             => 'approved',
            'subtotal'           => 374.00,
            'discount_amount'    => 20.00,
            'tax_rate'           => 0.08875, // NY Sales Tax
            'tax_amount'         => 31.42,
            'total'              => 385.42,
            'valid_until'        => Carbon::now()->addDays(30),
            'customer_message'   => 'Thank you for choosing Precision Air. Estimate includes diagnostic fee credit.',
            'notes'              => 'Customer approved on site with technician.',
        ]);

        EstimateItem::create([
            'estimate_id' => $est1->id,
            'service_id'  => $serviceDiagnostic->id,
            'description' => 'System Diagnostic & Safety Inspection',
            'quantity'    => 1.00,
            'unit_price'  => 129.00,
            'line_total'  => 129.00,
        ]);

        EstimateItem::create([
            'estimate_id' => $est1->id,
            'service_id'  => $serviceCapacitor->id,
            'description' => 'Dual Run 45/5 MFD Capacitor Replacement',
            'quantity'    => 1.00,
            'unit_price'  => 245.00,
            'line_total'  => 245.00,
        ]);

        $wo1 = WorkOrder::create([
            'company_id'              => $company1->id,
            'service_request_id'      => $req1->id,
            'estimate_id'             => $est1->id,
            'technician_id'           => $techUser1->id,
            'status'                  => 'completed',
            'started_at'              => Carbon::now()->subDay()->setHour(9)->setMinute(0),
            'completed_at'            => Carbon::now()->subDay()->setHour(10)->setMinute(30),
            'completion_notes'        => 'Diagnosed faulty capacitor. Replaced and tested system. Delta T measured at 18 degrees across coil. Operating normally.',
            'customer_signature_path' => 'signatures/wo-1001-signature.png',
        ]);

        WorkOrderChecklist::create([
            'work_order_id' => $wo1->id,
            'task'          => 'Inspect outdoor condenser coil and electrical contacts',
            'is_completed'  => true,
            'completed_at'  => Carbon::now()->subDay(),
            'completed_by'  => $techUser1->id,
        ]);

        WorkOrderChecklist::create([
            'work_order_id' => $wo1->id,
            'task'          => 'Replace capacitor and discharge safely',
            'is_completed'  => true,
            'completed_at'  => Carbon::now()->subDay(),
            'completed_by'  => $techUser1->id,
        ]);

        WorkOrderChecklist::create([
            'work_order_id' => $wo1->id,
            'task'          => 'Test temperature drop and amp draw',
            'is_completed'  => true,
            'completed_at'  => Carbon::now()->subDay(),
            'completed_by'  => $techUser1->id,
        ]);

        WorkOrderItem::create([
            'work_order_id' => $wo1->id,
            'service_id'    => $serviceCapacitor->id,
            'description'   => 'Capacitor Replacement',
            'quantity'      => 1.00,
            'unit_price'    => 245.00,
            'total_price'   => 245.00,
        ]);

        $inv1 = Invoice::create([
            'company_id'      => $company1->id,
            'customer_id'     => $cust1->id,
            'estimate_id'     => $est1->id,
            'invoice_number'  => 'INV-2026-001',
            'status'          => 'paid',
            'issue_date'      => Carbon::now()->subDay(),
            'due_date'        => Carbon::now()->addDays(14),
            'subtotal'        => 374.00,
            'tax_amount'      => 31.42,
            'total'           => 385.42,
            'amount_paid'     => 385.42,
            'customer_notes'  => 'Payment received via credit card. Thank you for your business!',
            'payment_terms'   => 'Due on receipt',
        ]);

        InvoiceItem::create([
            'invoice_id'  => $inv1->id,
            'description' => 'System Diagnostic & Inspection',
            'quantity'    => 1.00,
            'unit_price'  => 129.00,
            'line_total'  => 129.00,
        ]);

        InvoiceItem::create([
            'invoice_id'  => $inv1->id,
            'description' => 'Dual Run Capacitor Replacement',
            'quantity'    => 1.00,
            'unit_price'  => 245.00,
            'line_total'  => 245.00,
        ]);

        Payment::create([
            'company_id' => $company1->id,
            'invoice_id' => $inv1->id,
            'amount'     => 385.42,
            'method'     => 'card',
            'reference'  => 'ch_3Nzk2026demo_approved',
            'paid_at'    => Carbon::now()->subDay(),
            'notes'      => 'Paid on site with technician card reader.',
        ]);

        // Appointment for Customer 2 (Scheduled)
        $req2 = ServiceRequest::create([
            'company_id'      => $company1->id,
            'customer_id'     => $cust2->id,
            'title'           => 'Spring Rooftop Unit Maintenance',
            'description'     => 'Quarterly rooftop unit filter replacement and belt inspection.',
            'service_address' => '101 Broadway, New York, NY 10006',
            'status'          => 'scheduled',
            'priority'        => 'normal',
            'requested_at'    => Carbon::now(),
        ]);

        Appointment::create([
            'company_id'         => $company1->id,
            'service_request_id' => $req2->id,
            'technician_id'      => $techUser1->id,
            'starts_at'          => Carbon::now()->addDay()->setHour(13)->setMinute(0),
            'ends_at'            => Carbon::now()->addDay()->setHour(15)->setMinute(0),
            'status'             => 'confirmed',
            'notes'              => 'Access key available at security front desk.',
        ]);

        // 3. Company 2: Apex Master Plumbing (Austin, TX) — For Multi-Tenant Isolation Testing
        $company2 = Company::create([
            'name'          => 'Apex Master Plumbing',
            'business_type' => 'plumbing',
            'phone'         => '+1 (512) 555-0811',
            'email'         => 'service@apexplumbing.com',
            'address'       => '500 E 4th St, Austin, TX 78701',
            'timezone'      => 'America/Chicago',
            'currency'      => 'USD',
        ]);

        $ownerUser2 = User::create([
            'name'               => 'Carlos Vance',
            'email'              => 'carlos@apexplumbing.com',
            'password'           => Hash::make('Password123!'),
            'current_company_id' => $company2->id,
            'phone'              => '+1 (512) 555-0812',
        ]);
        $company2->users()->attach($ownerUser2->id, ['role' => 'owner']);

        // Bind Company 2 for its data
        app()->instance('currentCompanyId', $company2->id);

        $custCompany2 = Customer::create([
            'company_id'      => $company2->id,
            'customer_number' => 'CUST-2001',
            'first_name'      => 'David',
            'last_name'       => 'Miller',
            'name'            => 'David Miller',
            'email'           => 'dmiller.austin@gmail.com',
            'phone'           => '+1 (512) 555-4321',
            'customer_type'   => 'residential',
            'status'          => 'active',
            'notes'           => 'Water heater leaking in garage.',
        ]);

        CustomerAddress::create([
            'company_id'     => $company2->id,
            'customer_id'    => $custCompany2->id,
            'label'          => 'Residence',
            'street_address' => '2201 Congress Ave',
            'city'           => 'Austin',
            'state'          => 'TX',
            'postal_code'    => '78704',
            'is_default'     => true,
        ]);
    }
}
