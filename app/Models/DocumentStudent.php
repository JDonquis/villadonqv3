<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentStudent extends Model
{
    use HasFactory;

    protected $table = 'document_students';

    protected $fillable = [
        'student_id',
        'inscription_id',
        'type_document_id',
        'document',
    ];

    public $timestamps = false;

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }

    public function typeDocument()
    {
        return $this->belongsTo(TypeDocument::class);
    }
}
