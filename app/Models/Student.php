<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'representative_id',
        'course_id',
        'section_id',
        'name',
        'last_name',
        'date_birth',
        'email',
        'ci',
        'phone_number',
        'sex',
        'previous_school',
        'photo',
        'search',
        'status',
        'graduate',
        'is_exempt',
        'exemption_percentage',
        'exemption_observations',
        'apply_to_past_debts',
        'document_type',
    ];

    public $timestamps = false;

    public function representative()
    {
        return $this->belongsTo(Representative::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_students')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function balances()
    {
        return $this->hasMany(BalanceStudent::class);
    }

    public static function saveDocs($document, $current, $documentName)
    {

        if ($current) {
            Storage::disk('public')->delete('request/' . $documentName . '/' . $current);
        }

        $doc_name = Str::random(25) . '.' . $document->extension();

        $document->storeAs('request/' . $documentName, $doc_name, 'public');

        return $doc_name;
    }

    protected function search(): Attribute
    {
        return Attribute::get(function () {
            $repName = $this->representative?->user?->name ?? '';
            $repLastName = $this->representative?->user?->last_name ?? '';
            $courseName = $this->course?->name ?? '';
            $sectionName = $this->section?->name ?? '';

            return trim($repName . ' ' . $repLastName . ' ' . $courseName . ' ' . $sectionName . ' '
                . $this->name . ' ' . $this->last_name . ' ' . $this->date_birth . ' '
                . $this->email . ' ' . $this->ci . ' ' . $this->phone_number . ' '
                . $this->sex . ' ' . $this->previous_school . ' '
                . $this->exemption_percentage . ' ' . $this->exemption_observations);
        });
    }
}
