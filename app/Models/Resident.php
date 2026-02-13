<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resident extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'birthdate',
        'gender',
        'contact_number',
    ];

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}
