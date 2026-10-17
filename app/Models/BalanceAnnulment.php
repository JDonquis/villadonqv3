<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceAnnulment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_lapse_id',
        'up_to_month',
        'include_inscription',
        'filters',
        'snapshot',
        'affected_count',
        'total_annulled',
        'created_by',
        'undone_at',
    ];

    protected $casts = [
        'include_inscription' => 'boolean',
        'filters' => 'array',
        'snapshot' => 'array',
        'total_annulled' => 'decimal:2',
        'undone_at' => 'datetime',
    ];

    public function schoolLapse()
    {
        return $this->belongsTo(SchoolLapse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }
}
