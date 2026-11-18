<?php

namespace App\Models;

use App\Support\BelongsToCurrentCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory, BelongsToCurrentCompany;

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'description',
        'base_price',
        'estimated_duration',
        'pricing_type',
        'is_active',
    ];

    protected $casts = [
        'base_price'         => 'decimal:2',
        'estimated_duration' => 'integer',
        'is_active'          => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

