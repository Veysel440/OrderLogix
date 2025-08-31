<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    protected $fillable = ['sku','name','price','stock_qty','reserved_qty'];
    public function orderItems(){ return $this->hasMany(OrderItem::class); }
    public function reservations(){ return $this->hasMany(Reservation::class); }
}
