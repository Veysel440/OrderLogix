<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    protected $fillable = ['user_id','status','total','currency'];
    protected $casts = ['status' => OrderStatus::class, 'total' => 'decimal:2'];
    public function items(){ return $this->hasMany(OrderItem::class); }
    public function payments(){ return $this->hasMany(Payment::class); }
    public function shipment(){ return $this->hasOne(Shipment::class); }
    public function user(){ return $this->belongsTo(User::class); }
}
