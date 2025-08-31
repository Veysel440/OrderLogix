<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model {
    protected $fillable = ['order_id','product_id','qty','status'];
    protected $casts = ['status'=>ReservationStatus::class];
    public function order(){ return $this->belongsTo(Order::class); }
    public function product(){ return $this->belongsTo(Product::class); }
}
