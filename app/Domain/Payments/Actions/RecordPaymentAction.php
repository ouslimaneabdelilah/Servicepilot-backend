<?php

namespace App\Domain\Payments\Actions;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPaymentAction
{
    /**
     * Atomically record a payment and reconcile the target invoice.
     */
    public function execute(Invoice $invoice, array $paymentData, ?int $userId = null): Payment
    {
        if ($invoice->status === 'void') {
            throw ValidationException::withMessages(['invoice_id' => 'Cannot record payments against a void invoice.']);
        }

        $amount = (float) $paymentData['amount'];
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($invoice, $paymentData, $amount, $userId) {
            // 1. Create Payment record
            $payment = Payment::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'amount'     => $amount,
                'currency'   => $paymentData['currency'] ?? 'USD',
                'method'     => $paymentData['method'],
                'status'     => 'completed',
                'reference'  => $paymentData['reference'] ?? null,
                'paid_at'    => $paymentData['paid_at'] ?? now(),
                'notes'      => $paymentData['notes'] ?? null,
            ]);

            // 2. Re-calculate invoice paid & balance
            $totalPaid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
            $balanceDue = max(0.0, round(((float) $invoice->total) - $totalPaid, 2));

            $newStatus = $balanceDue <= 0.00 ? 'paid' : ($totalPaid > 0 ? 'partially_paid' : $invoice->status);

            $invoice->update([
                'amount_paid' => $totalPaid,
                'balance_due' => $balanceDue,
                'status'      => $newStatus,
            ]);

            // 3. Log audit event
            AuditLog::create([
                'company_id'     => $invoice->company_id,
                'user_id'        => $userId,
                'auditable_type' => Payment::class,
                'auditable_id'   => $payment->id,
                'event'          => 'recorded',
                'old_values'     => ['balance_due' => $invoice->balance_due],
                'new_values'     => ['amount' => $amount, 'new_balance' => $balanceDue, 'status' => $newStatus],
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'created_at'     => now(),
            ]);

            return $payment->load('invoice');
        });
    }
}

