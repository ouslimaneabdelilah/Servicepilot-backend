<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->with('addresses');

        // Search
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('customer_number', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by customer type
        if ($type = $request->input('customer_type')) {
            $query->where('customer_type', $type);
        }

        $sortField = in_array($request->input('sort_by'), ['name', 'created_at', 'customer_number']) ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('order') === 'asc' ? 'asc' : 'desc';

        $customers = $query->orderBy($sortField, $sortOrder)->paginate($request->integer('per_page', 15));

        return $this->paginated($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'     => ['required_without:name', 'nullable', 'string', 'max:80'],
            'last_name'      => ['required_without:name', 'nullable', 'string', 'max:80'],
            'name'           => ['sometimes', 'nullable', 'string', 'max:160'],
            'company_name'   => ['nullable', 'string', 'max:160'],
            'email'          => ['nullable', 'email', 'max:190'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'customer_type'  => ['sometimes', 'in:residential,commercial'],
            'status'         => ['sometimes', 'in:active,inactive,lead'],
            'notes'          => ['nullable', 'string', 'max:5000'],

            // Optional initial address
            'street_address' => ['nullable', 'string', 'max:255'],
            'apartment'      => ['nullable', 'string', 'max:60'],
            'city'           => ['required_with:street_address', 'nullable', 'string', 'max:100'],
            'state'          => ['required_with:street_address', 'nullable', 'string', 'max:2'],
            'postal_code'    => ['required_with:street_address', 'nullable', 'string', 'max:20'],
        ]);

        $companyId = app('currentCompanyId');

        $customer = DB::transaction(function () use ($data, $companyId) {
            $firstName = $data['first_name'] ?? null;
            $lastName = $data['last_name'] ?? null;
            $fullName = $data['name'] ?? trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

            if (empty($fullName)) {
                $fullName = $data['company_name'] ?? 'Customer';
            }

            // Generate company-scoped customer number
            $count = Customer::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
            $customerNumber = 'CUST-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

            $cust = Customer::create([
                'company_id'      => $companyId,
                'customer_number' => $customerNumber,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'name'            => $fullName,
                'company_name'    => $data['company_name'] ?? null,
                'email'           => !empty($data['email']) ? strtolower($data['email']) : null,
                'phone'           => $data['phone'] ?? null,
                'customer_type'   => $data['customer_type'] ?? 'residential',
                'status'          => $data['status'] ?? 'active',
                'notes'           => $data['notes'] ?? null,
            ]);

            // Save primary address if provided
            if (!empty($data['street_address'])) {
                CustomerAddress::create([
                    'company_id'     => $companyId,
                    'customer_id'    => $cust->id,
                    'label'          => 'Primary',
                    'street_address' => $data['street_address'],
                    'apartment'      => $data['apartment'] ?? null,
                    'city'           => $data['city'] ?? '',
                    'state'          => strtoupper($data['state'] ?? 'NY'),
                    'postal_code'    => $data['postal_code'] ?? '',
                    'is_default'     => true,
                ]);
            }

            return $cust;
        });

        return $this->created($customer->load('addresses'), 'Customer created successfully.');
    }

    public function show(Customer $customer): JsonResponse
    {
        abort_if($customer->company_id !== app('currentCompanyId'), 404);

        return $this->success(
            $customer->load(['addresses', 'serviceRequests', 'estimates', 'invoices'])
        );
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        abort_if($customer->company_id !== app('currentCompanyId'), 404);

        $data = $request->validate([
            'first_name'    => ['sometimes', 'string', 'max:80'],
            'last_name'     => ['sometimes', 'string', 'max:80'],
            'name'          => ['sometimes', 'string', 'max:160'],
            'company_name'  => ['nullable', 'string', 'max:160'],
            'email'         => ['nullable', 'email', 'max:190'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'customer_type' => ['sometimes', 'in:residential,commercial'],
            'status'        => ['sometimes', 'in:active,inactive,lead'],
            'notes'         => ['nullable', 'string', 'max:5000'],
        ]);

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $data['first_name'] ?? $customer->first_name;
            $last = $data['last_name'] ?? $customer->last_name;
            $data['name'] = trim("$first $last");
        }

        $customer->update($data);

        return $this->success($customer->fresh()->load('addresses'), 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        abort_if($customer->company_id !== app('currentCompanyId'), 404);

        $customer->delete();

        return $this->success(null, 'Customer deleted successfully.');
    }
}
