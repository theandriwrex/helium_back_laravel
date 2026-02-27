<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreelancerSkill extends Model
{
    use HasFactory;
    protected $fillable = [
        'freelancer_profile_id',
        'skill_id'
    ];
}
