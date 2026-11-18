<?php
namespace App\Models;
use App\Support\BelongsToCurrentCompany;
use Illuminate\Database\Eloquent\Model;
class Appointment extends Model {
    use BelongsToCurrentCompany;
    protected $fillable = ['company_id','service_request_id','technician_id','starts_at','ends_at','status','notes'];
    protected $casts = ['starts_at'=>'datetime','ends_at'=>'datetime'];
    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
    public function technician() { return $this->belongsTo(User::class, 'technician_id'); }
}
