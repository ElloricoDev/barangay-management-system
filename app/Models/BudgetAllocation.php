<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_year',
        'category',
        'program_name',
        'allocated_amount',
        'revised_amount',
        'remarks',
        'status',
        'created_by',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'revised_amount' => 'decimal:2',
        'fiscal_year' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
