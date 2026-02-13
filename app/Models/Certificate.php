<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'type',
        'purpose',
        'status',
        'issue_date'
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }
}
