<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model {
    protected $fillable = ['order_id','amount','currency','status','provider','provider_ref','meta'];
    protected $casts = ['amount'=>'decimal:2','status'=>PaymentStatus::class,'meta'=>'array'];
    public function order(){ return $this->belongsTo(Order::class); }
}
