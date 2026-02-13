<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'certificate_id',
        'blotter_id',
        'uploaded_by',
        'title',
        'module',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'notes',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function blotter()
    {
        return $this->belongsTo(Blotter::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
