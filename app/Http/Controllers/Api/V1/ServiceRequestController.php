<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Customer;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceRequestController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = ServiceRequest::query()->with(['customer', 'appointment.technician', 'workOrder']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('service_address', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $requests = $query->latest()->paginate($request->integer('per_page', 15));

        return $this->paginated($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'customer_id'     => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'title'           => ['required', 'string', 'max:180'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'service_address' => ['required', 'string', 'max:500'],
            'status'          => ['sometimes', 'in:new,pending,quoted,scheduled,in_progress,completed,cancelled'],
            'priority'        => ['sometimes', 'in:low,normal,high,urgent'],
            'requested_at'    => ['nullable', 'date'],
        ]);

        $count = ServiceRequest::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
        $data['request_number'] = 'REQ-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        $data['company_id'] = $companyId;
        $data['created_by'] = $request->user()->id;

        $record = ServiceRequest::create($data);

        return $this->created($record->load(['customer', 'appointment']), 'Service request created successfully.');
    }

    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        abort_if($serviceRequest->company_id !== app('currentCompanyId'), 404);

        return $this->success($serviceRequest->load(['customer', 'appointment.technician', 'workOrder']));
    }

    public function update(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        abort_if($serviceRequest->company_id !== app('currentCompanyId'), 404);

        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'customer_id'     => ['sometimes', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'title'           => ['sometimes', 'string', 'max:180'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'service_address' => ['sometimes', 'string', 'max:500'],
            'status'          => ['sometimes', 'in:new,pending,quoted,scheduled,in_progress,completed,cancelled'],
            'priority'        => ['sometimes', 'in:low,normal,high,urgent'],
            'requested_at'    => ['nullable', 'date'],
        ]);

        $serviceRequest->update($data);

        return $this->success($serviceRequest->fresh()->load(['customer', 'appointment.technician']), 'Service request updated successfully.');
    }

    public function destroy(ServiceRequest $serviceRequest): JsonResponse
    {
        $serviceRequest->delete();

        return $this->success(null, 'Service request deleted successfully.');
    }
}
