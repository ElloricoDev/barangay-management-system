<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $fillable = [
        'barangay_name',
        'barangay_city',
        'barangay_province',
        'contact_number',
        'contact_email',
        'receipt_prefix',
        'timezone',
        'maintenance_mode',
        'login_theme',
        'footer_note',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('system_settings')) {
            return new self([
                'barangay_name' => 'Barangay Management System',
                'timezone' => 'Asia/Manila',
                'receipt_prefix' => 'OR',
                'maintenance_mode' => false,
                'login_theme' => 'emerald',
            ]);
        }

        try {
            return self::query()->firstOrCreate(
                ['id' => 1],
                [
                    'barangay_name' => 'Barangay Management System',
                    'timezone' => 'Asia/Manila',
                    'receipt_prefix' => 'OR',
                    'maintenance_mode' => false,
                    'login_theme' => 'emerald',
                ]
            );
        } catch (QueryException) {
            return new self([
                'barangay_name' => 'Barangay Management System',
                'timezone' => 'Asia/Manila',
                'receipt_prefix' => 'OR',
                'maintenance_mode' => false,
                'login_theme' => 'emerald',
            ]);
        }
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
