<?php

namespace App\Domain\WorkOrders\Actions;

use App\Models\AuditLog;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteWorkOrderAction
{
    /**
     * Atomically mark a work order completed with signatures and audit logging.
     */
    public function execute(WorkOrder $workOrder, array $data, ?int $userId = null): WorkOrder
    {
        if ($workOrder->status === 'completed') {
            throw ValidationException::withMessages(['status' => 'This work order is already completed.']);
        }

        return DB::transaction(function () use ($workOrder, $data, $userId) {
            $oldStatus = $workOrder->status;

            $workOrder->update([
                'status'                  => 'completed',
                'completed_at'            => now(),
                'completion_notes'        => $data['completion_notes'] ?? $workOrder->completion_notes,
                'customer_signature_path' => $data['customer_signature_path'] ?? $workOrder->customer_signature_path,
                'customer_signature_name' => $data['customer_signature_name'] ?? $workOrder->customer_signature_name,
            ]);

            // Audit log
            AuditLog::create([
                'company_id'     => $workOrder->company_id,
                'user_id'        => $userId,
                'auditable_type' => WorkOrder::class,
                'auditable_id'   => $workOrder->id,
                'event'          => 'completed',
                'old_values'     => ['status' => $oldStatus],
                'new_values'     => ['status' => 'completed', 'completed_at' => now()],
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'created_at'     => now(),
            ]);

            return $workOrder->fresh()->load(['items', 'photos', 'checklists']);
        });
    }
}

