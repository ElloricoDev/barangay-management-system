<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangayProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'committee',
        'status',
        'start_date',
        'end_date',
        'budget',
        'participants',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
