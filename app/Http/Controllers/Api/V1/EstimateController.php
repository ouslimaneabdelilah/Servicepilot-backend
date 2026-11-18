<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Estimates\Actions\ApproveEstimateAction;
use App\Domain\Estimates\Actions\CalculateEstimateTotalsAction;
use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Estimate;
use App\Models\EstimateItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EstimateController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Estimate::query()->with(['customer', 'items', 'workOrder']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $estimates = $query->latest()->paginate($request->integer('per_page', 15));

        return $this->paginated($estimates);
    }

    public function store(Request $request, CalculateEstimateTotalsAction $calculator): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'customer_id'        => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'service_request_id' => ['nullable', 'integer', Rule::exists('service_requests', 'id')->where('company_id', $companyId)],
            'discount_amount'    => ['sometimes', 'numeric', 'min:0'],
            'tax_rate'           => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'valid_until'        => ['nullable', 'date'],
            'customer_message'   => ['nullable', 'string', 'max:2000'],
            'internal_notes'     => ['nullable', 'string', 'max:2000'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['nullable', 'integer'],
            'items.*.description'=> ['required', 'string', 'max:255'],
            'items.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $estimate = DB::transaction(function () use ($data, $companyId, $calculator) {
            // Server-authoritative totals calculation
            $calculated = $calculator->execute(
                $data['items'],
                (float) ($data['discount_amount'] ?? 0.0),
                (float) ($data['tax_rate'] ?? 0.08) // Default 8%
            );

            // Generate estimate number
            $count = Estimate::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
            $estimateNumber = 'EST-' . date('Y') . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

            $estimate = Estimate::create([
                'company_id'         => $companyId,
                'customer_id'        => $data['customer_id'],
                'service_request_id' => $data['service_request_id'] ?? null,
                'estimate_number'    => $estimateNumber,
                'status'             => 'draft',
                'subtotal'           => $calculated['subtotal'],
                'discount_amount'    => $calculated['discount_amount'],
                'tax_rate'           => $calculated['tax_rate'],
                'tax_amount'         => $calculated['tax_amount'],
                'total'              => $calculated['total'],
                'valid_until'        => $data['valid_until'] ?? now()->addDays(30),
                'customer_message'   => $data['customer_message'] ?? null,
                'internal_notes'     => $data['internal_notes'] ?? null,
            ]);

            // Save line items
            foreach ($data['items'] as $index => $item) {
                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'service_id'  => $item['service_id'] ?? null,
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'line_total'  => $calculated['item_totals'][$index],
                ]);
            }

            return $estimate;
        });

        return $this->created($estimate->load(['customer', 'items']), 'Estimate created successfully.');
    }

    public function show(Estimate $estimate): JsonResponse
    {
        abort_if($estimate->company_id !== app('currentCompanyId'), 404);

        return $this->success($estimate->load(['customer', 'items', 'serviceRequest', 'approvals', 'workOrder']));
    }

    public function update(Request $request, Estimate $estimate, CalculateEstimateTotalsAction $calculator): JsonResponse
    {
        abort_if($estimate->company_id !== app('currentCompanyId'), 404);

        if ($estimate->status === 'approved') {
            return $this->error('Approved estimates are locked and cannot be modified.', 422);
        }

        $data = $request->validate([
            'discount_amount'  => ['sometimes', 'numeric', 'min:0'],
            'tax_rate'         => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'valid_until'      => ['nullable', 'date'],
            'customer_message' => ['nullable', 'string', 'max:2000'],
            'internal_notes'   => ['nullable', 'string', 'max:2000'],
            'items'            => ['sometimes', 'array', 'min:1'],
            'items.*.service_id'=> ['nullable', 'integer'],
            'items.*.description'=>['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price'=>['required_with:items', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($estimate, $data, $calculator) {
            if (isset($data['items'])) {
                $calculated = $calculator->execute(
                    $data['items'],
                    (float) ($data['discount_amount'] ?? $estimate->discount_amount),
                    (float) ($data['tax_rate'] ?? $estimate->tax_rate)
                );

                $estimate->update([
                    'subtotal'        => $calculated['subtotal'],
                    'discount_amount' => $calculated['discount_amount'],
                    'tax_rate'        => $calculated['tax_rate'],
                    'tax_amount'      => $calculated['tax_amount'],
                    'total'           => $calculated['total'],
                    'valid_until'     => $data['valid_until'] ?? $estimate->valid_until,
                    'customer_message'=> $data['customer_message'] ?? $estimate->customer_message,
                    'internal_notes'  => $data['internal_notes'] ?? $estimate->internal_notes,
                ]);

                // Replace items
                $estimate->items()->delete();
                foreach ($data['items'] as $index => $item) {
                    EstimateItem::create([
                        'estimate_id' => $estimate->id,
                        'service_id'  => $item['service_id'] ?? null,
                        'description' => $item['description'],
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['unit_price'],
                        'line_total'  => $calculated['item_totals'][$index],
                    ]);
                }
            } else {
                $estimate->update($data);
            }
        });

        return $this->success($estimate->fresh()->load(['customer', 'items']), 'Estimate updated successfully.');
    }

    public function send(Estimate $estimate): JsonResponse
    {
        abort_if($estimate->company_id !== app('currentCompanyId'), 404);
        $estimate->update(['status' => 'sent']);

        return $this->success($estimate, 'Estimate marked as sent to customer.');
    }

    public function approve(Request $request, Estimate $estimate, ApproveEstimateAction $approver): JsonResponse
    {
        abort_if($estimate->company_id !== app('currentCompanyId'), 404);
        $data = $request->validate([
            'approved_by_name'  => ['required', 'string', 'max:120'],
            'approved_by_email' => ['nullable', 'email', 'max:190'],
            'signature_path'    => ['nullable', 'string', 'max:500'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ]);

        $workOrder = $approver->execute($estimate, $data, $request->user()?->id);

        return $this->success([
            'estimate'   => $estimate->fresh(),
            'work_order' => $workOrder,
        ], 'Estimate approved! Work order has been automatically created.');
    }

    public function reject(Request $request, Estimate $estimate): JsonResponse
    {
        abort_if($estimate->company_id !== app('currentCompanyId'), 404);
        $estimate->update(['status' => 'rejected']);

        return $this->success($estimate, 'Estimate marked as rejected.');
    }

    public function destroy(Estimate $estimate): JsonResponse
    {
        abort_if($estimate->company_id !== app('currentCompanyId'), 404);
        if ($estimate->status === 'approved') {
            return $this->error('Cannot delete an approved estimate.', 422);
        }

        $estimate->delete();

        return $this->success(null, 'Estimate deleted.');
    }
}
