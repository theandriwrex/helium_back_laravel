<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreelancerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'description',
        'experience',
        'profession',
        'education_level',
        'strikes'
    ];

    public function user()
{
    return $this->belongsTo(User::class);
}

public function services()
{
    return $this->hasMany(Service::class, 'freelancer_id');
}


}
