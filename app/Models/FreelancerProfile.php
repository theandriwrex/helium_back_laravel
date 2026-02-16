<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreelancerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'description',
        'hourly_rate'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
