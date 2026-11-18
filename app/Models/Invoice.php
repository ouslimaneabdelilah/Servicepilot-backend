<?php

namespace App\Models;

use App\Support\BelongsToCurrentCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, BelongsToCurrentCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'estimate_id',
        'work_order_id',
        'invoice_number',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'amount_paid',
        'balance_due',
        'issue_date',
        'due_date',
        'issued_at',
        'payment_terms',
        'customer_notes',
        'notes',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
        'amount_paid'     => 'decimal:2',
        'balance_due'     => 'decimal:2',
        'issue_date'      => 'date',
        'due_date'        => 'date',
        'issued_at'       => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
