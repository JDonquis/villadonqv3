<?php

namespace App\Services;

use App\Models\MainConfig;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

    public function export(Collection $payments, string $filename = 'libro_ventas'): StreamedResponse
    {
        $nature = PaymentNature::indexFor($payments);
        $days = $this->groupByDay($payments);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Libro de Ventas');

        $this->prepareColumns($sheet);

        $row = $this->writeHeader($sheet);
        $totalRow = $this->writeTotal($sheet, $row, null);
        $row++;

        $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValue($this->cell($index, $row), $header);
        }
        $row++;

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
            $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
            $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
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

    private function writeHeader($sheet): int
    {
        $institution = MainConfig::first()?->name;
        $row = 1;

        if ($institution) {
            $sheet->setCellValue('A'.$row, $institution);
            $sheet->mergeCells('A'.$row.':F'.$row);
            $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true)->setSize(14);
            $row++;
        }

        $sheet->setCellValue('A'.$row, 'LIBRO DE VENTAS');
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true)->setSize(12);
        $row++;

        $sheet->setCellValue('A'.$row, 'Generado el '.Carbon::now()->translatedFormat('d/m/Y H:i'));
        $sheet->mergeCells('A'.$row.':F'.$row);
        $row++;

        return $row;
    }

    private function writeTotal($sheet, int $row, ?string $formula): int
    {
        $sheet->setCellValue('A'.$row, 'TOTAL GENERAL');
        $sheet->setCellValue('D'.$row, $formula ?? 0);
        $sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);

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
        $sheet->getStyle('D'.$row)->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
    }

    private function prepareColumns($sheet): void
    {
        $widths = ['A' => 38, 'B' => 16, 'C' => 46, 'D' => 16, 'E' => 22, 'F' => 20];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function cell(int $index, int $row): string
    {
        return chr(65 + $index).$row;
    }
}