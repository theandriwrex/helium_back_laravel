<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    public function freelancerProfiles()
    {
        return $this->belongsToMany(
            FreelancerProfile::class,
            'freelancer_skills',
            'skill_id',
            'freelancer_profile_id'
        );
    }
}
