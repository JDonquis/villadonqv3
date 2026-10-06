<?php

namespace App\Services;

use App\Models\AccountPayment;
use App\Models\MainConfig;
use App\Models\Payment;
use App\Models\PaymentConcept;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesBookService
{
    private const HEADERS = [
        'Estudiante(s)',
        'Año',
        'Concepto',
        'Pagado (Bs)',
        'Referencia',
        'Método de pago',
    ];

    private const MONEY_FORMAT = '#,##0.00';

    public function export(Collection $payments, string $filename = 'libro_ventas', array $filters = []): StreamedResponse
    {
        $nature = PaymentNature::indexFor($payments);
        $days = $this->groupByDay($payments);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Libro de Ventas');

        $this->prepareColumns($sheet);
        $this->styleBaseSheet($sheet);

        $row = $this->writeHeader($sheet, $filters);
        $totalRow = $this->writeTotal($sheet, $row, null);
        $row++;

        $this->styleTableHeader($sheet, $row);
        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValue($this->cell($index, $row), $header);
        }
        $sheet->setAutoFilter('A'.$row.':F'.$row);
        $row++;

        $sheet->freezePane('A'.$row);

        $subtotalRows = [];

        foreach ($days as $date => $dayPayments) {
            $sheet->setCellValue('A'.$row, $this->dayTitle($date));
            $sheet->mergeCells('A'.$row.':F'.$row);
            $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
            $row++;

            $firstPaymentRow = $row;

            foreach ($dayPayments as $payment) {
                $this->writePaymentRow($sheet, $row, $payment, $nature);
                $row++;
            }

            $lastPaymentRow = max($firstPaymentRow, $row - 1);

            $sheet->setCellValue('A'.$row, 'Subtotal del día');
            $sheet->setCellValue('D'.$row, '=SUM(D'.$firstPaymentRow.':D'.$lastPaymentRow.')');
            $this->styleSubtotalRow($sheet, $row);
            $subtotalRows[] = $row;
            $row += 2;
        }

        if ($subtotalRows) {
            $formula = '=SUM('.implode(',', array_map(fn ($r) => 'D'.$r, $subtotalRows)).')';
            $this->writeTotal($sheet, $row, $formula);
            $this->writeTotal($sheet, $totalRow, $formula);
        } else {
            $this->writeTotal($sheet, $row, null);
        }

        $writer = new Xlsx($spreadsheet);
        $name = str_ends_with($filename, '.xlsx') ? $filename : $filename.'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function groupByDay(Collection $payments): Collection
    {
        return $payments
            ->filter(fn (Payment $payment) => $payment->raw_date)
            ->groupBy(fn (Payment $payment) => Carbon::parse($payment->raw_date)->format('Y-m-d'))
            ->sortByDesc(fn ($day) => $day->first()->raw_date);
    }

    private function dayTitle(string $date): string
    {
        $carbon = Carbon::parse($date);

        return $carbon->translatedFormat('l j \d\e F \d\e Y');
    }

    private function writeHeader($sheet, array $filters = []): int
    {
        $institution = MainConfig::first()?->name;
        $row = 1;

        if ($institution) {
            $sheet->setCellValue('A'.$row, $institution);
            $sheet->mergeCells('A'.$row.':F'.$row);
            $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => Color::COLOR_DARKGREEN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'D9F2E6']],
            ]);
            $row++;
        }

        $sheet->setCellValue('A'.$row, 'LIBRO DE VENTAS');
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => Color::COLOR_WHITE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
        ]);
        $row++;

        $generatedText = 'Generado el '.Carbon::now()->translatedFormat('d/m/Y H:i');
        $filterSummary = $this->formatFiltersForExport($filters);
        if ($filterSummary !== '') {
            $generatedText .= ' | Filtros: '.$filterSummary;
        }

        $sheet->setCellValue('A'.$row, $generatedText);
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => '4B5563']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'F3F4F6']],
        ]);
        $row++;

        return $row;
    }

    private function writeTotal($sheet, int $row, ?string $formula): int
    {
        $sheet->setCellValue('A'.$row, 'TOTAL GENERAL');
        $sheet->setCellValue('D'.$row, $formula ?? 0);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '0F766E']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'D1D5DB']],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A'.$row)->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getStyle('D'.$row)->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
        ]);

        return $row;
    }

    private function writePaymentRow($sheet, int $row, Payment $payment, PaymentNature $nature): void
    {
        $students = $payment->students;

        $names = $students
            ->map(fn ($student) => trim($student->name.' '.$student->last_name))
            ->filter()
            ->unique()
            ->values();

        $courses = $students
            ->map(fn ($student) => $student->course?->name)
            ->filter()
            ->unique()
            ->values();

        $methods = $payment->accountPayment?->method?->name;

        $sheet->setCellValue('A'.$row, $names->implode(', '));
        $sheet->setCellValue('B'.$row, $courses->implode(', '));
        $sheet->setCellValue('C'.$row, $nature->conceptFor($payment));
        $sheet->setCellValue('D'.$row, (float) $payment->total_in_bs);
        $sheet->setCellValueExplicit('E'.$row, (string) $payment->reference, DataType::TYPE_STRING);
        $sheet->setCellValue('F'.$row, $methods ?: 'Efectivo');

        $fill = ($row % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fill]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'E5E7EB']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
        $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
    }

    private function prepareColumns($sheet): void
    {
        $widths = ['A' => 38, 'B' => 16, 'C' => 46, 'D' => 16, 'E' => 22, 'F' => 20];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('A:F')->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function formatFiltersForExport(array $filters): string
    {
        $parts = [];

        if (! empty($filters['search'])) {
            $parts[] = 'búsqueda: "'.trim((string) $filters['search']).'"';
        }

        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $start = $this->normalizeExportDate($filters['start_date'] ?? null);
            $end = $this->normalizeExportDate($filters['end_date'] ?? null);

            if ($start && $end) {
                $parts[] = 'fecha: '.$start.' a '.$end;
            } elseif ($start || $end) {
                $parts[] = 'fecha: '.($start ?? $end);
            }
        }

        if (! empty($filters['account_payment_id'])) {
            $accountIds = is_array($filters['account_payment_id'])
                ? $filters['account_payment_id']
                : [$filters['account_payment_id']];

            $accountNames = array_map(function ($id) {
                $account = AccountPayment::with('method')->find($id);

                if (! $account) {
                    return 'ID '.$id;
                }

                $labelParts = [
                    $account->method?->name,
                    $account->bank,
                    $account->cash_currency,
                    $account->username,
                    $account->person_name,
                ];

                $label = implode(' ', array_filter($labelParts, fn ($part) => $part !== null && $part !== ''));

                return $label !== '' ? $label : 'ID '.$id;
            }, $accountIds);

            $parts[] = 'cuenta: '.implode(', ', $accountNames);
        }

        if (! empty($filters['payment_concept_id'])) {
            $conceptIds = is_array($filters['payment_concept_id'])
                ? $filters['payment_concept_id']
                : [$filters['payment_concept_id']];

            $conceptNames = array_map(function ($id) {
                if (in_array((string) $id, ['regular', '0'], true)) {
                    return 'regular';
                }

                $label = PaymentConcept::find($id)?->name;

                return $label ?: 'ID '.$id;
            }, $conceptIds);

            $parts[] = 'concepto: '.implode(', ', $conceptNames);
        }

        if (! empty($filters['month'])) {
            $monthLabels = [
                'inscription' => 'inscripción',
                'september' => 'septiembre',
                'october' => 'octubre',
                'november' => 'noviembre',
                'december' => 'diciembre',
                'january' => 'enero',
                'february' => 'febrero',
                'march' => 'marzo',
                'april' => 'abril',
                'may' => 'mayo',
                'june' => 'junio',
                'july' => 'julio',
                'august' => 'agosto',
            ];

            $monthValue = is_array($filters['month']) ? $filters['month'] : [$filters['month']];
            $labels = array_map(fn ($value) => $monthLabels[$value] ?? (string) $value, $monthValue);
            $parts[] = 'mes: '.implode(', ', $labels);
        }

        return implode(' | ', $parts);
    }

    private function normalizeExportDate($date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        if (is_numeric($date)) {
            $date = (float) $date / 1000;
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }

    private function styleBaseSheet($sheet): void
    {
        $sheet->getDefaultRowDimension()->setRowHeight(22);
        $sheet->getStyle('A1:F1000')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function styleTableHeader($sheet, int $row): void
    {
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '1F4E78']],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function styleSubtotalRow($sheet, int $row): void
    {
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => '1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E5E7EB']],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'D1D5DB']],
            ],
        ]);
        $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function cell(int $index, int $row): string
    {
        return chr(65 + $index).$row;
    }
}