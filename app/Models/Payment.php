<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'certificate_id',
        'collected_by',
        'approved_by',
        'or_number',
        'transaction_type',
        'revenue_source',
        'expense_type',
        'workflow_status',
        'request_reference',
        'voucher_number',
        'service_type',
        'description',
        'amount',
        'paid_at',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
