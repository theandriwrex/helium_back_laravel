<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderFinalDeliveryFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_final_delivery_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function finalDelivery()
    {
        return $this->belongsTo(OrderFinalDelivery::class, 'order_final_delivery_id');
    }
}

