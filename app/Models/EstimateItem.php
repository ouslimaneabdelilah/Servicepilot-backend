<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EstimateItem extends Model {
    protected $fillable = ['estimate_id','description','quantity','unit_price','line_total'];
    protected $casts = ['quantity'=>'decimal:2','unit_price'=>'decimal:2','line_total'=>'decimal:2'];
    public function estimate() { return $this->belongsTo(Estimate::class); }
}
