<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FreelancerSkill extends Model
{
    use HasFactory;
    protected $fillable = [
        'freelancer_profile_id',
        'skill_id'
    ];
}
