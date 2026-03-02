<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category_id',
    ];

    public function freelancerProfiles()
    {
        return $this->belongsToMany(
            FreelancerProfile::class,
            'freelancer_skills',
            'skill_id',
            'freelancer_profile_id'
        );
    }

    public function category()
    
    {
        return $this->belongsTo(Category::class);
    }
}
