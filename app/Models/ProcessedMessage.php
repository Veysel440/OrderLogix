<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedMessage extends Model {
    public $timestamps = false;
    protected $fillable = ['message_id','consumer','processed_at'];
    protected $casts = ['processed_at'=>'datetime'];
}
