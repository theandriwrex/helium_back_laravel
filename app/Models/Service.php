<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'freelancer_id',
        'category_id',
        'title',
        'description',
        'price',
        'is_active'
    ];

    public function freelancerProfile()
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

