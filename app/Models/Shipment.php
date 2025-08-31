<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model {
    protected $fillable = ['order_id','status','carrier','tracking_no','scheduled_at','shipped_at','delivered_at'];
    protected $casts = [
        'status'=>ShipmentStatus::class,
        'scheduled_at'=>'datetime',
        'shipped_at'=>'datetime',
        'delivered_at'=>'datetime',
    ];
    public function order(){ return $this->belongsTo(Order::class); }
}
