<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'nit',
        'address',
        'website'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

