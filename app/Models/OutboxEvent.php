<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model {
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['id','type','payload','occurred_at','published_at','aggregate_type','aggregate_id'];
    protected $casts = ['payload'=>'array','occurred_at'=>'datetime','published_at'=>'datetime'];
}
