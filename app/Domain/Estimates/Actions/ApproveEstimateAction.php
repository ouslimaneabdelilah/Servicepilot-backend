<?php

namespace App\Domain\Estimates\Actions;

use App\Models\AuditLog;
use App\Models\Estimate;
use App\Models\EstimateApproval;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveEstimateAction
{
    /**
     * Atomically approve an estimate, log audit trail, and initialize the linked Work Order.
     */
    public function execute(Estimate $estimate, array $approvalData, ?int $userId = null): WorkOrder
    {
        if ($estimate->status === 'approved') {
            throw ValidationException::withMessages(['status' => 'This estimate is already approved.']);
        }

        return DB::transaction(function () use ($estimate, $approvalData, $userId) {
            // 1. Update estimate status
            $oldStatus = $estimate->status;
            $estimate->update(['status' => 'approved']);

            // 2. Record approval metadata
            EstimateApproval::create([
                'estimate_id'       => $estimate->id,
                'approved_by_name'  => $approvalData['approved_by_name'],
                'approved_by_email' => $approvalData['approved_by_email'] ?? null,
                'ip_address'        => $approvalData['ip_address'] ?? request()->ip(),
                'signature_path'    => $approvalData['signature_path'] ?? null,
                'notes'             => $approvalData['notes'] ?? 'Customer approved estimate.',
                'approved_at'       => now(),
            ]);

            // 3. Create or find linked Work Order
            $workOrder = WorkOrder::firstOrCreate(
                [
                    'company_id'  => $estimate->company_id,
                    'estimate_id' => $estimate->id,
                ],
                [
                    'service_request_id' => $estimate->service_request_id,
                    'status'             => 'assigned',
                    'completion_notes'   => null,
                ]
            );

            // Copy estimate line items to work order items if empty
            if ($workOrder->items()->count() === 0) {
                foreach ($estimate->items as $estItem) {
                    WorkOrderItem::create([
                        'work_order_id' => $workOrder->id,
                        'service_id'    => $estItem->service_id,
                        'description'   => $estItem->description,
                        'quantity'      => $estItem->quantity,
                        'unit_price'    => $estItem->unit_price,
                        'total_price'   => $estItem->line_total,
                    ]);
                }
            }

            // 4. Record audit log
            AuditLog::create([
                'company_id'     => $estimate->company_id,
                'user_id'        => $userId,
                'auditable_type' => Estimate::class,
                'auditable_id'   => $estimate->id,
                'event'          => 'approved',
                'old_values'     => ['status' => $oldStatus],
                'new_values'     => ['status' => 'approved', 'work_order_id' => $workOrder->id],
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'created_at'     => now(),
            ]);

            return $workOrder->load(['items', 'serviceRequest', 'customer']);
        });
    }
}

