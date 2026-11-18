<?php

namespace App\Models;

use App\Support\BelongsToCurrentCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estimate extends Model
{
    use HasFactory, BelongsToCurrentCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'service_request_id',
        'estimate_number',
        'status',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total',
        'valid_until',
        'customer_message',
        'internal_notes',
        'notes',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate'        => 'decimal:4',
        'tax_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
        'valid_until'     => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(EstimateApproval::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function workOrder()
    {
        return $this->hasOne(WorkOrder::class);
    }
}
