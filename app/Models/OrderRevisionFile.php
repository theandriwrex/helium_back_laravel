<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRevisionFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_revision_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function revision()
    {
        return $this->belongsTo(OrderRevision::class, 'order_revision_id');
    }
}

