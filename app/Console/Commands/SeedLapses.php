<?php

namespace App\Console\Commands;

use App\Models\Lapse;
use App\Models\SchoolLapse;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SeedLapses extends Command
{
    protected $signature = 'lapses:seed';

    protected $description = 'Garantiza los 3 momentos escolares (lapsos) para cada período escolar.';

    public function handle()
    {
        $schoolLapses = SchoolLapse::orderBy('start')->get();

        if ($schoolLapses->isEmpty()) {
            $this->warn('No hay períodos escolares registrados.');

            return 0;
        }

        $created = 0;
        foreach ($schoolLapses as $schoolLapse) {
            foreach ($this->buildLapseRanges($schoolLapse) as $number => $range) {
                $exists = Lapse::where('school_lapse_id', $schoolLapse->id)
                    ->where('number', $number)
                    ->exists();

                if ($exists) {
                    continue;
                }

                Lapse::create([
                    'start' => $range['start'],
                    'end' => $range['end'],
                    'number' => $number,
                    'school_lapse_id' => $schoolLapse->id,
                ]);
                $created++;
            }
        }

        $this->info("Lapsos garantizados. Se crearon {$created} lapsos.");

        return 0;
    }

    public function buildLapseRanges(SchoolLapse $schoolLapse): array
    {
        $start = Carbon::parse($schoolLapse->start);
        $end = Carbon::parse($schoolLapse->end);

        $l1Start = $start->copy();
        $l1End = $start->copy()->addMonths(3)->subDay();

        $l2Start = $l1End->copy()->addDay();
        $l2End = $l2Start->copy()->addMonths(3)->subDay();

        $l3Start = $l2End->copy()->addDay();
        $l3End = $end->copy();

        return [
            1 => ['start' => $l1Start->toDateString(), 'end' => $l1End->toDateString()],
            2 => ['start' => $l2Start->toDateString(), 'end' => $l2End->toDateString()],
            3 => ['start' => $l3Start->toDateString(), 'end' => $l3End->toDateString()],
        ];
    }
}
