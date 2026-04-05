<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'revision_number',
        'freelancer_note',
        'client_feedback',
        'submitted_at',
        'feedback_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'feedback_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function files()
    {
        return $this->hasMany(OrderRevisionFile::class);
    }
}

