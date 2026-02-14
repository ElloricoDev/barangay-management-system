<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'fiscal_year',
        'budget_type',
        'category',
        'program_name',
        'resolution_no',
        'allocated_amount',
        'revised_amount',
        'remarks',
        'status',
        'submitted_at',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'revised_amount' => 'decimal:2',
        'fiscal_year' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disbursementRequests()
    {
        return $this->hasMany(DisbursementRequest::class);
    }
}
