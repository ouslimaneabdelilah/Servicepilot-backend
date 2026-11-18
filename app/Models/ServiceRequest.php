<?php
namespace App\Models;
use App\Support\BelongsToCurrentCompany;
use Illuminate\Database\Eloquent\Model;
class ServiceRequest extends Model {
    use BelongsToCurrentCompany;
    protected $fillable = ['company_id','customer_id','title','description','service_address','status','priority','requested_at'];
    protected $casts = ['requested_at'=>'datetime'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function appointment() { return $this->hasOne(Appointment::class); }
    public function workOrder() { return $this->hasOne(WorkOrder::class); }
}
