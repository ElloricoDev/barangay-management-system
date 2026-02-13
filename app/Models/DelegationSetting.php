<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegationSetting extends Model
{
    protected $fillable = [
        'staff_can_approve',
        'enabled_by',
        'enabled_at',
    ];

    protected function casts(): array
    {
        return [
            'staff_can_approve' => 'boolean',
            'enabled_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => 1],
            ['staff_can_approve' => false]
        );
    }
}

