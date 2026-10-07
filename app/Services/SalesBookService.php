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
        'Año / Grado',
        'Concepto de Pago',
        'Pagado (Bs)',
        'Referencia',
        'Método de Pago',
    ];

    private const MONEY_FORMAT = '#,##0.00';

    // 🎨 Paleta de colores ejecutiva (Slate & Navy Modern)
    private const COLOR_PRIMARY_DARK  = '0F172A'; // Slate 900 (Cabecera principal)
    private const COLOR_PRIMARY_LIGHT = 'F8FAFC'; // Slate 50
    private const COLOR_HEADER_BG     = '1E293B'; // Slate 800 (Columnas de la tabla)
    private const COLOR_DAY_HEADER_BG = 'F1F5F9'; // Slate 100 (Banda de día)
    private const COLOR_DAY_HEADER_TXT= '334155'; // Slate 700
    private const COLOR_ZEBRA         = 'FBFCFD'; // Gris ultra suave
    private const COLOR_BORDER_LIGHT  = 'E2E8F0'; // Slate 200 (Borde tenue)
    private const COLOR_SUBTOTAL_BG   = 'F8FAFC'; // Subtotales
    private const COLOR_TOTAL_BG      = '0F766E'; // Teal 700 elegante para el gran total
    private const COLOR_MUTED_TXT     = '64748B'; // Slate 500

    public function export(Collection $payments, string $filename = 'libro_ventas', array $filters = []): StreamedResponse
    {
        $nature = PaymentNature::indexFor($payments);
        $days = $this->groupByDay($payments);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Libro de Ventas');

        // Mostrar líneas de cuadrícula para acabado pulcro
        $sheet->setShowGridLines(true);

        $this->prepareColumns($sheet);
        $this->styleBaseSheet($sheet);

        // Encabezado del reporte
        $row = $this->writeHeader($sheet, $filters);
        
        // Fila de resumen superior (KPI Total)
        $totalRow = $this->writeTotalCard($sheet, $row);
        $row += 2;

        // Cabecera de columnas de la tabla
        $this->styleTableHeader($sheet, $row);
        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValue($this->cell($index, $row), $header);
        }
        $sheet->setAutoFilter('A'.$row.':F'.$row);
        $row++;

        $sheet->freezePane('A'.$row);

        $subtotalRows = [];

        foreach ($days as $date => $dayPayments) {
            // Banda divisoria del día
            $this->writeDayHeader($sheet, $row, $this->dayTitle($date));
            $row++;

            $firstPaymentRow = $row;

            foreach ($dayPayments as $payment) {
                $this->writePaymentRow($sheet, $row, $payment, $nature);
                $row++;
            }

            $lastPaymentRow = max($firstPaymentRow, $row - 1);

            // Fila de subtotal del día
            $sheet->setCellValue('A'.$row, 'SUBTOTAL DEL DÍA');
            $sheet->setCellValue('D'.$row, '=SUM(D'.$firstPaymentRow.':D'.$lastPaymentRow.')');
            $this->styleSubtotalRow($sheet, $row);
            $subtotalRows[] = $row;
            $row += 2; // Espacio entre grupos de días
        }

        // Gran Total al final
        if ($subtotalRows) {
            $formula = '=SUM('.implode(',', array_map(fn ($r) => 'D'.$r, $subtotalRows)).')';
            $this->writeFinalTotal($sheet, $row, $formula);
            $sheet->setCellValue('D'.$totalRow, $formula); // Actualizar KPI superior
        } else {
            $this->writeFinalTotal($sheet, $row, '0.00');
            $sheet->setCellValue('D'.$totalRow, 0.00);
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
        return mb_strtoupper($carbon->translatedFormat('l, d \d\e F \d\e Y'), 'UTF-8');
    }

    private function writeHeader($sheet, array $filters = []): int
    {
        $institution = MainConfig::first()?->name ?? 'INSTITUCIÓN EDUCATIVA';
        $row = 1;

        // Fila 1: Nombre de la Institución
        $sheet->getRowDimension($row)->setRowHeight(28);
        $sheet->setCellValue('A'.$row, $institution);
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => [
                'name' => 'Segoe UI',
                'bold' => true,
                'size' => 14,
                'color' => ['argb' => self::COLOR_PRIMARY_DARK],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_PRIMARY_LIGHT],
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
            ],
        ]);
        $row++;

        // Fila 2: Título del Documento
        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->setCellValue('A'.$row, 'LIBRO DE VENTAS E INGRESOS');
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => [
                'name' => 'Segoe UI',
                'bold' => true,
                'size' => 11,
                'color' => ['argb' => '0284C7'], // Cyan/Blue corporativo
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_PRIMARY_LIGHT],
            ],
        ]);
        $row++;

        // Fila 3: Metadatos y Filtros
        $sheet->getRowDimension($row)->setRowHeight(20);
        $generatedText = 'Emitido el '.Carbon::now()->translatedFormat('d/m/Y \a \l\a\s H:i');
        $filterSummary = $this->formatFiltersForExport($filters);
        if ($filterSummary !== '') {
            $generatedText .= '  •  Criterios aplicados: '.$filterSummary;
        }

        $sheet->setCellValue('A'.$row, $generatedText);
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => [
                'name' => 'Segoe UI',
                'size' => 9,
                'color' => ['argb' => self::COLOR_MUTED_TXT],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_PRIMARY_LIGHT],
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'CBD5E1']],
            ],
        ]);
        $row += 2; // Espacio respirador

        return $row;
    }

    private function writeTotalCard($sheet, int $row): int
    {
        // Tarjeta resumen moderna de TOTAL GENERAL en el encabezado
        $sheet->getRowDimension($row)->setRowHeight(32);
        
        $sheet->setCellValue('A'.$row, 'TOTAL GENERAL FACTURADO');
        $sheet->mergeCells('A'.$row.':C'.$row);
        $sheet->getStyle('A'.$row.':C'.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 11, 'color' => ['argb' => '065F46']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'ECFDF5']], // Emerald 50
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'A7F3D0']],
            ],
        ]);

        $sheet->setCellValue('D'.$row, 0.00);
        $sheet->getStyle('D'.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'bold' => true, 'size' => 13, 'color' => ['argb' => '047857']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'ECFDF5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'A7F3D0']],
            ],
        ]);

        $sheet->mergeCells('E'.$row.':F'.$row);
        $sheet->setCellValue('E'.$row, 'Bs. VES');
        $sheet->getStyle('E'.$row.':F'.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 10, 'color' => ['argb' => '065F46']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'ECFDF5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'A7F3D0']],
            ],
        ]);

        return $row;
    }

    private function writeDayHeader($sheet, int $row, string $title): void
    {
        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->setCellValue('A'.$row, '📅  '.$title);
        $sheet->mergeCells('A'.$row.':F'.$row);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => [
                'name' => 'Segoe UI',
                'bold' => true,
                'size' => 9.5,
                'color' => ['argb' => self::COLOR_DAY_HEADER_TXT],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_DAY_HEADER_BG],
            ],
            'borders' => [
                'left' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => '0284C7']], // Acento lateral izquierdo
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ]);
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

        $sheet->getRowDimension($row)->setRowHeight(24);

        $sheet->setCellValue('A'.$row, $names->implode(', '));
        $sheet->setCellValue('B'.$row, $courses->implode(', ') ?: '—');
        $sheet->setCellValue('C'.$row, $nature->conceptFor($payment));
        $sheet->setCellValue('D'.$row, (float) $payment->total_in_bs);
        $sheet->setCellValueExplicit('E'.$row, (string) ($payment->reference ?: 'S/R'), DataType::TYPE_STRING);
        $sheet->setCellValue('F'.$row, $methods ?: 'Efectivo');

        // Alternancia suave (Zebra striping)
        $fill = ($row % 2 === 0) ? self::COLOR_ZEBRA : 'FFFFFF';
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'size' => 9.5, 'color' => ['argb' => '1E293B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fill]],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'left' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'right' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Formatos específicos por columna
        $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
        $sheet->getStyle('B'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C'.$row)->getAlignment()->setWrapText(true);
        
        // Columna Monto en fuente monoespaciada limpia
        $sheet->getStyle('D'.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'size' => 9.5, 'color' => ['argb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
        ]);

        // Referencia centrada y monoespaciada
        $sheet->getStyle('E'.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'size' => 9, 'color' => ['argb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('F'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function styleSubtotalRow($sheet, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 9.5, 'color' => ['argb' => '334155']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_SUBTOTAL_BG]],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '94A3B8']],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '94A3B8']],
            ],
        ]);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('A'.$row.':C'.$row)->getAlignment()->setIndent(1);

        $sheet->getStyle('D'.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'bold' => true, 'size' => 10, 'color' => ['argb' => '0F172A']],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
    }

    private function writeFinalTotal($sheet, int $row, string $formula): void
    {
        $sheet->getRowDimension($row)->setRowHeight(30);
        $sheet->setCellValue('A'.$row, 'TOTAL CONSOLIDADO GENERAL');
        $sheet->mergeCells('A'.$row.':C'.$row);
        $sheet->setCellValue('D'.$row, $formula);

        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 11, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PRIMARY_DARK]],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => '0284C7']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => '0284C7']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
        $sheet->getStyle('D'.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'bold' => true, 'size' => 12, 'color' => ['argb' => '38BDF8']],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
    }

    private function prepareColumns($sheet): void
    {
        // Proporciones ergonómicas para evitar cortes de texto
        $widths = [
            'A' => 36, // Estudiantes
            'B' => 16, // Año / Grado
            'C' => 44, // Concepto
            'D' => 18, // Pagado (Bs)
            'E' => 18, // Referencia
            'F' => 18, // Método de pago
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function styleBaseSheet($sheet): void
    {
        $sheet->getDefaultRowDimension()->setRowHeight(22);
        $sheet->getStyle('A1:F500')->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function styleTableHeader($sheet, int $row): void
    {
        $sheet->getRowDimension($row)->setRowHeight(26);
        $sheet->getStyle('A'.$row.':F'.$row)->applyFromArray([
            'font' => [
                'name' => 'Segoe UI',
                'bold' => true,
                'size' => 10,
                'color' => ['argb' => Color::COLOR_WHITE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_HEADER_BG],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '334155']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    private function cell(int $index, int $row): string
    {
        return chr(65 + $index).$row;
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
}