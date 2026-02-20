<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public function services()
{
    return $this->hasMany(Service::class);
}

}
