<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Actions\RecordPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with(['invoice.customer']);

        if ($invoiceId = $request->input('invoice_id')) {
            $query->where('invoice_id', $invoiceId);
        }

        if ($method = $request->input('method')) {
            $query->where('method', $method);
        }

        $payments = $query->latest('paid_at')->paginate($request->integer('per_page', 15));

        return $this->paginated($payments);
    }

    public function store(Request $request, RecordPaymentAction $recorder): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')->where('company_id', $companyId)],
            'amount'     => ['required', 'numeric', 'gt:0'],
            'method'     => ['required', 'in:card,cash,check,bank_transfer,other'],
            'reference'  => ['nullable', 'string', 'max:120'],
            'paid_at'    => ['sometimes', 'date'],
            'notes'      => ['nullable', 'string', 'max:3000'],
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        $payment = $recorder->execute($invoice, $data, $request->user()?->id);

        return $this->created($payment, 'Payment recorded and invoice reconciled.');
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->success($payment->load(['invoice.customer']));
    }
}
