<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Invoices\Actions\CalculateInvoiceTotalsAction;
use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['customer', 'items', 'payments']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $invoices = $query->latest('issue_date')->paginate($request->integer('per_page', 15));

        return $this->paginated($invoices);
    }

    public function store(Request $request, CalculateInvoiceTotalsAction $calculator): JsonResponse
    {
        $companyId = app('currentCompanyId');

        $data = $request->validate([
            'customer_id'     => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'work_order_id'   => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('company_id', $companyId)],
            'estimate_id'     => ['nullable', 'integer', Rule::exists('estimates', 'id')->where('company_id', $companyId)],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_rate'        => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'issue_date'      => ['nullable', 'date'],
            'due_date'        => ['nullable', 'date'],
            'payment_terms'   => ['nullable', 'string', 'max:100'],
            'customer_notes'  => ['nullable', 'string', 'max:2000'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity'    => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
        ]);

        $invoice = DB::transaction(function () use ($data, $companyId, $calculator) {
            $calculated = $calculator->execute(
                $data['items'],
                (float) ($data['discount_amount'] ?? 0.0),
                (float) ($data['tax_rate'] ?? 0.08)
            );

            $count = Invoice::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'company_id'      => $companyId,
                'customer_id'     => $data['customer_id'],
                'work_order_id'   => $data['work_order_id'] ?? null,
                'estimate_id'     => $data['estimate_id'] ?? null,
                'invoice_number'  => $invoiceNumber,
                'status'          => 'draft',
                'subtotal'        => $calculated['subtotal'],
                'discount_amount' => $calculated['discount_amount'],
                'tax_amount'      => $calculated['tax_amount'],
                'total'           => $calculated['total'],
                'amount_paid'     => 0.00,
                'balance_due'     => $calculated['total'],
                'issue_date'      => $data['issue_date'] ?? now(),
                'due_date'        => $data['due_date'] ?? now()->addDays(14),
                'payment_terms'   => $data['payment_terms'] ?? 'Due on receipt',
                'customer_notes'  => $data['customer_notes'] ?? null,
            ]);

            foreach ($data['items'] as $index => $item) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'line_total'  => $calculated['item_totals'][$index],
                ]);
            }

            return $invoice;
        });

        return $this->created($invoice->load(['customer', 'items']), 'Invoice created successfully.');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        abort_if($invoice->company_id !== app('currentCompanyId'), 404);

        return $this->success($invoice->load(['customer', 'items', 'payments', 'workOrder', 'estimate']));
    }

    public function send(Invoice $invoice): JsonResponse
    {
        abort_if($invoice->company_id !== app('currentCompanyId'), 404);

        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        return $this->success($invoice, 'Invoice marked as sent to customer.');
    }

    public function void(Invoice $invoice): JsonResponse
    {
        abort_if($invoice->company_id !== app('currentCompanyId'), 404);

        if ($invoice->amount_paid > 0) {
            return $this->error('Cannot void an invoice with recorded payments. Issue a refund first.', 422);
        }

        $invoice->update(['status' => 'void']);

        return $this->success($invoice, 'Invoice has been voided.');
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        abort_if($invoice->company_id !== app('currentCompanyId'), 404);

        if ($invoice->amount_paid > 0) {
            return $this->error('Cannot delete an invoice with recorded payments.', 422);
        }

        $invoice->delete();

        return $this->success(null, 'Invoice deleted.');
    }
}
