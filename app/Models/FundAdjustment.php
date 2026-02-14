<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_type',
        'amount',
        'reason',
        'reference_no',
        'remarks',
        'adjusted_by',
        'adjusted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'adjusted_at' => 'datetime',
    ];

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
