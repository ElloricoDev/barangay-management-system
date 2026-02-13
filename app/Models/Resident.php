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
        'archived_at',
        'archived_by',
        'archive_reason',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function archiver()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
