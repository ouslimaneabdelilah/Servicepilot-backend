<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\WorkOrders\Actions\CompleteWorkOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use App\Models\WorkOrderPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::query()->with(['serviceRequest.customer', 'technician', 'items', 'photos']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($techId = $request->input('technician_id')) {
            $query->where('technician_id', $techId);
        }

        $workOrders = $query->latest()->paginate($request->integer('per_page', 15));

        return $this->paginated($workOrders);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'service_request_id' => ['required', 'integer', Rule::exists('service_requests', 'id')->where('company_id', $companyId)],
            'estimate_id'        => ['nullable', 'integer', Rule::exists('estimates', 'id')->where('company_id', $companyId)],
            'technician_id'      => ['nullable', 'integer'],
            'status'             => ['sometimes', 'in:draft,assigned,scheduled,in_progress,on_hold,completed,cancelled'],
            'scheduled_start'    => ['nullable', 'date'],
            'completion_notes'   => ['nullable', 'string', 'max:5000'],
        ]);

        $data['company_id'] = $companyId;
        $data['status'] = $data['status'] ?? 'assigned';

        $workOrder = WorkOrder::create($data);

        return $this->created($workOrder->load(['serviceRequest.customer', 'technician']), 'Work order created.');
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        abort_if($workOrder->company_id !== app('currentCompanyId'), 404);

        return $this->success($workOrder->load(['serviceRequest.customer', 'technician', 'items', 'checklists', 'photos', 'estimate', 'invoice']));
    }

    public function update(Request $request, WorkOrder $workOrder): JsonResponse
    {
        abort_if($workOrder->company_id !== app('currentCompanyId'), 404);

        $data = $request->validate([
            'technician_id'    => ['nullable', 'integer'],
            'status'           => ['sometimes', 'in:draft,assigned,scheduled,in_progress,on_hold,completed,cancelled'],
            'scheduled_start'  => ['nullable', 'date'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $workOrder->update($data);

        return $this->success($workOrder->fresh()->load(['serviceRequest.customer', 'technician']), 'Work order updated.');
    }

    public function start(WorkOrder $workOrder): JsonResponse
    {
        abort_if($workOrder->company_id !== app('currentCompanyId'), 404);

        if ($workOrder->status === 'completed') {
            return $this->error('Completed work orders cannot be restarted.', 422);
        }

        $workOrder->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        return $this->success($workOrder->fresh(), 'Work order started (clock-in recorded).');
    }

    public function complete(Request $request, WorkOrder $workOrder, CompleteWorkOrderAction $completer): JsonResponse
    {
        abort_if($workOrder->company_id !== app('currentCompanyId'), 404);

        $data = $request->validate([
            'completion_notes'        => ['nullable', 'string', 'max:5000'],
            'customer_signature_path' => ['nullable', 'string', 'max:500'],
            'customer_signature_name' => ['nullable', 'string', 'max:120'],
        ]);

        $completed = $completer->execute($workOrder, $data, $request->user()?->id);

        return $this->success($completed, 'Work order completed successfully!');
    }

    public function addChecklist(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validate([
            'task' => ['required', 'string', 'max:255'],
        ]);

        $item = WorkOrderChecklist::create([
            'work_order_id' => $workOrder->id,
            'task'          => $data['task'],
            'is_completed'  => false,
        ]);

        return $this->created($item, 'Checklist task added.');
    }

    public function toggleChecklist(WorkOrder $workOrder, int $checklistId): JsonResponse
    {
        $item = $workOrder->checklists()->findOrFail($checklistId);
        $newStatus = !$item->is_completed;

        $item->update([
            'is_completed' => $newStatus,
            'completed_at' => $newStatus ? now() : null,
            'completed_by' => $newStatus ? auth()->id() : null,
        ]);

        return $this->success($item, 'Checklist updated.');
    }

    public function uploadPhoto(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validate([
            'photo'      => ['required', 'image', 'max:10240'], // max 10MB
            'photo_type' => ['sometimes', 'in:before,after,inspection'],
            'caption'    => ['nullable', 'string', 'max:255'],
        ]);

        $path = $request->file('photo')->store("work-orders/{$workOrder->id}", 'public');

        $photo = WorkOrderPhoto::create([
            'work_order_id' => $workOrder->id,
            'path'          => $path,
            'caption'       => $data['caption'] ?? null,
            'photo_type'    => $data['photo_type'] ?? 'after',
        ]);

        return $this->created($photo, 'Photo attached to work order.');
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->delete();

        return $this->success(null, 'Work order deleted.');
    }
}
