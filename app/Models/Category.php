<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function services()  
    {
        return $this->hasMany(Service::class);
    }

    public function skills()
    {
        return $this->hasMany(Skill::class);
    }

}
