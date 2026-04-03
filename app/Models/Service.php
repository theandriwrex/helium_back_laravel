<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'freelancer_id',
        'category_id',
        'title',
        'description',
        'price',
        'delivery_time',
        'revisions',
        'requirements',
        'is_active',
        'deactivation_reason',
        'photo'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'delivery_time' => 'integer',
        'revisions' => 'integer',
        'is_active' => 'boolean',
        'deactivation_reason' => 'string',
        'photo'
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

    public function reviews()
    {
        return $this->hasManyThrough(
            Review::class,
            Order::class,
            'service_id',
            'order_id'
        );
    }
}
