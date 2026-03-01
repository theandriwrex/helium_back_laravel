<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FreelancerProfile extends Model
{
    use HasFactory;
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
