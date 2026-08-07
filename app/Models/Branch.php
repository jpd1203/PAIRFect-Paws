<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'contact_number'];

    public function pets()
    {
        return $this->hasMany(Pet::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
