<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['name','key_hash','abilities','user_id','last_used_at'];
    protected $casts = ['abilities' => 'array', 'last_used_at'=>'datetime'];
}
