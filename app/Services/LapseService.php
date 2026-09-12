<?php

namespace App\Services;

use App\Models\Lapse;
use App\Models\SchoolLapse;
use Carbon\Carbon;

class LapseService
{
    public function forPeriod(SchoolLapse $period): array
    {
        return $period->lapses
            ->sortBy('number')
            ->values()
            ->map(fn ($l) => $this->format($l))
            ->all();
    }

    public function currentByDate(SchoolLapse $period): ?Lapse
    {
        if ($period->lapses->isEmpty()) {
            return null;
        }

        $today = Carbon::now()->toDateString();

        return $period->lapses
            ->sortBy('number')
            ->first(fn ($l) => $today >= $l->start && $today <= $l->end);
    }

    /**
     * Actualiza start/end de los momentos enviados (por id) validando orden y
     * rango. Lanza excepciones con mensajes en español.
     */
    public function saveMoments(int $schoolLapseId, array $rows): void
    {
        $period = SchoolLapse::findOrFail($schoolLapseId);
        $byId = collect($rows)->keyBy('id');

        $lapses = $period->lapses()->orderBy('number')->get();

        $periodStart = $period->start;
        $periodEnd = $period->end;

        foreach ($lapses as $lapse) {
            if (! isset($byId[$lapse->id])) {
                continue;
            }

            $row = $byId[$lapse->id];
            $start = $this->validDate($row['start'] ?? null, 'La fecha de inicio');
            $end = $this->validDate($row['end'] ?? null, 'La fecha de fin');

            if ($start > $end) {
                throw new \Exception($this->momentLabel((int) $lapse->number).': la fecha de inicio no puede ser posterior a la de fin.');
            }

            if ($start < $periodStart || $end > $periodEnd) {
                throw new \Exception($this->momentLabel((int) $lapse->number).': las fechas deben estar dentro del periodo escolar ('.$periodStart.' al '.$periodEnd.').');
            }

            $lapse->update(['start' => $start, 'end' => $end]);
        }

        $sorted = $period->lapses()->orderBy('number')->get()->values();

        foreach ($sorted as $i => $lapse) {
            $next = $sorted[$i + 1] ?? null;
            if ($next && $lapse->end >= $next->start) {
                throw new \Exception('Los momentos se solapan: el '.$this->momentLabel((int) $lapse->number).' termina el '.$lapse->end.' y el '.$this->momentLabel((int) $next->number).' empieza el '.$next->start.'. Ajusta las fechas.');
            }
        }
    }

    /**
     * Cierra el momento vigente hoy y abre el siguiente desde hoy.
     * Devuelve ['closed'|'next'|'message'] sin lanzar excepción en casos esperados.
     */
    public function closeAndAdvance(SchoolLapse $period): array
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();

        $current = $this->currentByDate($period);

        if (! $current) {
            return ['message' => 'Hoy no corresponde a ningún momento escolar. Revisa las fechas antes de cerrar uno.'];
        }

        $currentNumber = (int) $current->number;
        $next = $period->lapses
            ->sortBy('number')
            ->first(fn ($l) => (int) $l->number === $currentNumber + 1);

        if (! $next) {
            return ['message' => 'El '.$this->momentLabel($currentNumber).' es el último del periodo. Usa "Iniciar próximo periodo" para pasar al siguiente ciclo.'];
        }

        $newCurrentEnd = $today->copy()->subDay()->toDateString();

        if ($newCurrentEnd < $current->start) {
            return ['message' => 'El '.$this->momentLabel($currentNumber).' empezó hoy; aún no se puede cerrar.'];
        }

        if ($todayStr > $next->end) {
            return ['message' => 'No se puede abrir el siguiente momento: su fecha de fin ('.$next->end.') es anterior a hoy. Ajusta primero sus fechas.'];
        }

        $current->update(['end' => $newCurrentEnd]);
        $next->update(['start' => $todayStr]);

        return [
            'closed' => $current->id,
            'next' => $next->id,
            'message' => $this->momentLabel($currentNumber).' cerrado. El '.$this->momentLabel((int) $next->number).' queda vigente desde hoy.',
        ];
    }

    private function format(Lapse $lapse): array
    {
        return [
            'id' => $lapse->id,
            'number' => (int) $lapse->number,
            'label' => $this->momentLabel((int) $lapse->number),
            'start' => $lapse->start,
            'end' => $lapse->end,
        ];
    }

    private function validDate($value, string $label): string
    {
        $date = Carbon::createFromFormat('Y-m-d', (string) $value);
        if (! $date) {
            throw new \Exception($label.' no es una fecha válida.');
        }

        return $date->toDateString();
    }

    private function momentLabel(int $number): string
    {
        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$number] ?? $number.'º').' Momento';
    }
}
