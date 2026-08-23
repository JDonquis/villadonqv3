<?php

namespace App\Services;

use App\Models\SchoolCharge;

class SchoolChargeService
{
    public function summary()
    {
        return SchoolCharge::with(['student', 'schoolLapse'])
            ->where('status', 'pending')
            ->orWhere('status', 'overdue')
            ->latest()
            ->get()
            ->map(function (SchoolCharge $charge) {
                $student = $charge->student;

                return [
                    'id' => $charge->id,
                    'student' => $student ? trim($student->name.' '.$student->last_name) : null,
                    'ci' => $student?->ci,
                    'school_lapse' => $charge->schoolLapse
                        ? $charge->schoolLapse->start.' - '.$charge->schoolLapse->end
                        : null,
                    'amount' => (float) $charge->amount,
                ];
            })
            ->values();
    }

    public function totalAccumulated()
    {
        return (float) SchoolCharge::sum('amount');
    }

    public function byLapse()
    {
        return SchoolCharge::with('schoolLapse')
            ->selectRaw('school_lapse_id, COUNT(*) as students, SUM(amount) as total')
            ->groupBy('school_lapse_id')
            ->get()
            ->map(function ($row) {
                return [
                    'school_lapse' => $row->schoolLapse
                        ? $row->schoolLapse->start.' - '.$row->schoolLapse->end
                        : null,
                    'students' => (int) $row->students,
                    'total' => (float) $row->total,
                ];
            })
            ->values();
    }
}
