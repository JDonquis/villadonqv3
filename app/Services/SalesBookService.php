<?php

namespace App\Services;

use App\Models\AccountPayment;
use App\Models\MainConfig;
use App\Models\Payment;
use App\Models\PaymentConcept;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesBookService
{
    /**
     * Catálogo de columnas disponibles.
     * scope: 'student' => una línea por estudiante dentro de la celda.
     *        'payment' => un único valor por pago.
     * type:  'money' | 'rate' | 'int' | 'text'
     */
    public const COLUMNS = [
        'student_name' => ['label' => 'Estudiante(s)', 'scope' => 'student', 'type' => 'text'],
        'student_ci' => ['label' => 'Cédula', 'scope' => 'student', 'type' => 'text'],
        'student_grade' => ['label' => 'Año / Grado', 'scope' => 'student', 'type' => 'text'],
        'student_section' => ['label' => 'Sección', 'scope' => 'student', 'type' => 'text'],
        'legal_rep' => ['label' => 'Representante Legal', 'scope' => 'student', 'type' => 'text'],
        'report_date' => ['label' => 'Fecha de Reporte', 'scope' => 'payment', 'type' => 'text'],
        'concept' => ['label' => 'Concepto', 'scope' => 'student', 'type' => 'text'],
        'amount_bs' => ['label' => 'Pagado (Bs)', 'scope' => 'payment', 'type' => 'money'],
        'payment_method' => ['label' => 'Método de Pago', 'scope' => 'payment', 'type' => 'text'],
        'amount_usd' => ['label' => 'Monto USD ($)', 'scope' => 'payment', 'type' => 'money'],
        'bcv_rate' => ['label' => 'Tasa de Cambio', 'scope' => 'payment', 'type' => 'rate'],
        'receiving_bank' => ['label' => 'Banco Receptor', 'scope' => 'payment', 'type' => 'text'],
        'reference' => ['label' => 'N° de Referencia', 'scope' => 'payment', 'type' => 'text'],
        'observations' => ['label' => 'Observación', 'scope' => 'payment', 'type' => 'text'],
        'correlative_id' => ['label' => 'ID Correlativo', 'scope' => 'payment', 'type' => 'int'],
        'operator_user' => ['label' => 'Usuario Operador', 'scope' => 'payment', 'type' => 'text'],
    ];

    public const REQUIRED_COLUMNS = ['student_name', 'amount_bs'];

    private const MONEY_FORMAT = '#,##0.00';

    private const COLOR_PRIMARY_DARK = '0F172A';
    private const COLOR_PRIMARY_LIGHT = 'F8FAFC';
    private const COLOR_HEADER_BG = '1E293B';
    private const COLOR_DAY_HEADER_BG = 'F1F5F9';
    private const COLOR_DAY_HEADER_TXT = '334155';
    private const COLOR_ZEBRA = 'FBFCFD';
    private const COLOR_BORDER_LIGHT = 'E2E8F0';
    private const COLOR_SUBTOTAL_BG = 'F8FAFC';
    private const COLOR_MUTED_TXT = '64748B';

    private const COLUMN_WIDTHS = [
        'student_name' => 34,
        'student_ci' => 16,
        'student_grade' => 16,
        'student_section' => 12,
        'legal_rep' => 30,
        'report_date' => 14,
        'amount_bs' => 16,
        'payment_method' => 24,
        'amount_usd' => 14,
        'bcv_rate' => 12,
        'receiving_bank' => 16,
        'concept' => 40,
        'reference' => 16,
        'observations' => 30,
        'correlative_id' => 10,
        'operator_user' => 22,
    ];

    /**
     * @param  array  $columns  IDs de columnas solicitadas (se validan y se fuerza el orden del catálogo).
     * @param  string  $format  xlsx | csv | pdf
     */
    public function export(
        Collection $payments,
        string $filename = 'libro_ventas',
        array $filters = [],
        array $columns = [],
        string $format = 'xlsx'
    ) {
        $nature = PaymentNature::indexFor($payments);
        $report = $this->buildReport($payments, $this->resolveColumns($columns), $nature);
        $report['filters'] = $filters;

        $format = in_array($format, ['xlsx', 'csv', 'pdf'], true) ? $format : 'xlsx';
        $name = preg_replace('/\.(xlsx|csv|pdf)$/', '', $filename).'.'.$format;

        return match ($format) {
            'csv' => $this->exportCsv($report, $name),
            'pdf' => $this->exportPdf($report, $name),
            default => $this->exportXlsx($report, $name),
        };
    }

    /**
     * Normaliza y ordena los IDs de columnas según el catálogo, forzando las obligatorias.
     */
    private function resolveColumns(array $columns): array
    {
        $requested = array_values(array_filter(
            (array) $columns,
            fn ($id) => is_string($id) && isset(self::COLUMNS[$id])
        ));

        $selected = array_values(array_unique(array_merge(self::REQUIRED_COLUMNS, $requested)));

        return array_values(array_filter(
            array_keys(self::COLUMNS),
            fn ($id) => in_array($id, $selected, true)
        ));
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

    /**
     * Construye la estructura de datos del reporte:
     * columns + días (con filas ya resueltas) + total.
     */
    private function buildReport(Collection $payments, array $columnIds, PaymentNature $nature): array
    {
        $columns = array_map(fn ($id) => ['id' => $id] + self::COLUMNS[$id], $columnIds);
        $days = $this->groupByDay($payments);

        $total = 0.0;
        $dayBlocks = [];

        foreach ($days as $date => $dayPayments) {
            $rows = [];
            $subtotal = 0.0;

            foreach ($dayPayments as $payment) {
                $studentLines = [];

                foreach ($payment->students as $student) {
                    foreach ($columns as $col) {
                        if ($col['scope'] === 'student') {
                            $studentLines[$col['id']][] = $this->studentValue($col['id'], $payment, $student, $nature);
                        }
                    }
                }

                $values = [];

                foreach ($columns as $col) {
                    if ($col['scope'] === 'student') {
                        $lines = array_map(
                            fn ($value) => ($value === null || $value === '') ? '—' : (string) $value,
                            $studentLines[$col['id']] ?? []
                        );
                        $values[$col['id']] = $lines ? implode("\n", $lines) : '—';
                    } else {
                        $values[$col['id']] = $this->paymentValue($col['id'], $payment);
                    }
                }

                $amountBs = (float) $payment->total_in_bs;
                $subtotal += $amountBs;

                $rows[] = [
                    'values' => $values,
                    'studentCount' => max(1, $payment->students->count()),
                ];
            }

            $total += $subtotal;
            $dayBlocks[] = [
                'date' => $date,
                'title' => $this->dayTitle($date),
                'rows' => $rows,
                'subtotal' => $subtotal,
            ];
        }

        return [
            'columns' => $columns,
            'days' => $dayBlocks,
            'total' => $total,
        ];
    }

    private function studentValue(string $id, Payment $payment, $student, PaymentNature $nature)
    {
        return match ($id) {
            'student_name' => trim($student->name.' '.$student->last_name),
            'student_ci' => trim(($student->document_type ? $student->document_type.'-' : '').$student->ci),
            'student_grade' => trim($student->course?->name ?? ''),
            'student_section' => trim($student->section?->name ?? ''),
            'legal_rep' => trim(
                ($student->representative?->user?->name ?? '').' '.($student->representative?->user?->last_name ?? '')
            ),
            'concept' => $nature->conceptForStudent($payment, $student),
            default => null,
        };
    }

    private function paymentValue(string $id, Payment $payment)
    {
        return match ($id) {
            'amount_bs' => (float) $payment->total_in_bs,
            'amount_usd' => (float) $payment->total_in_dolars,
            'bcv_rate' => (float) $payment->exchange_rate,
            'payment_method' => $this->paymentMethodLabel($payment->accountPayment),
            'receiving_bank' => $payment->accountPayment?->bank,
            'report_date' => $payment->reported_date?->format('d/m/Y') ?? '',
            'reference' => (string) ($payment->reference ?: 'S/R'),
            'observations' => (string) ($payment->observations ?? ''),
            'correlative_id' => (int) $payment->id,
            'operator_user' => trim(($payment->user?->name ?? '').' '.($payment->user?->last_name ?? '')),
            default => null,
        };
    }

    /**
     * Etiqueta del método de pago: método + moneda efectivo + usuario (sólo los
     * presentes). El banco va en su propia columna "Banco Receptor".
     */
    private function paymentMethodLabel(?AccountPayment $account): string
    {
        $parts = array_filter([
            $account?->method?->name,
            $account?->cash_currency,
            $account?->username,
        ], fn ($part) => $part !== null && $part !== '');

        return $parts ? implode(' ', $parts) : 'Efectivo';
    }

    // ----------------------------------------------------------------------
    // XLSX
    // ----------------------------------------------------------------------

    private function exportXlsx(array $report, string $filename): StreamedResponse
    {
        $columns = $report['columns'];
        $columnCount = count($columns);
        $lastCol = Coordinate::stringFromColumnIndex($columnCount);
        $moneyCol = $this->columnLetter($columns, 'amount_bs');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Libro de Ventas');
        $sheet->setShowGridLines(true);

        $this->prepareColumns($sheet, $columns);
        $sheet->getDefaultRowDimension()->setRowHeight(22);
        $sheet->getStyle('A1:'.$lastCol.'800')->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $row = $this->writeHeader($sheet, $report['filters'], $lastCol);
        $totalRow = $this->writeTotalCard($sheet, $row, $moneyCol);
        $row += 2;

        $this->styleTableHeader($sheet, $row, $columns, $lastCol);
        $row++;

        $sheet->freezePane('A'.$row);

        $subtotalRows = [];

        foreach ($report['days'] as $day) {
            $this->writeDayHeader($sheet, $row, $day['title'], $lastCol);
            $row++;

            $firstPaymentRow = $row;

            foreach ($day['rows'] as $paymentRow) {
                $this->writePaymentRow($sheet, $row, $paymentRow, $columns);
                $row++;
            }

            $lastPaymentRow = max($firstPaymentRow, $row - 1);

            $sheet->setCellValue('A'.$row, 'SUBTOTAL DEL DÍA');
            $sheet->setCellValue($moneyCol.$row, '=SUM('.$moneyCol.$firstPaymentRow.':'.$moneyCol.$lastPaymentRow.')');
            $this->styleSubtotalRow($sheet, $row, $lastCol, $moneyCol);
            $subtotalRows[] = $row;
            $row += 2;
        }

        if ($subtotalRows) {
            $formula = '=SUM('.implode(',', array_map(fn ($r) => $moneyCol.$r, $subtotalRows)).')';
            $this->writeFinalTotal($sheet, $row, $formula, $lastCol, $moneyCol);
            $sheet->setCellValue($moneyCol.$totalRow, $formula);
        } else {
            $this->writeFinalTotal($sheet, $row, '0.00', $lastCol, $moneyCol);
            $sheet->setCellValue($moneyCol.$totalRow, 0.00);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeHeader($sheet, array $filters, string $lastCol): int
    {
        $institution = MainConfig::first()?->name ?? 'INSTITUCIÓN EDUCATIVA';
        $row = 1;

        $sheet->getRowDimension($row)->setRowHeight(28);
        $sheet->setCellValue('A'.$row, $institution);
        $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 14, 'color' => ['argb' => self::COLOR_PRIMARY_DARK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PRIMARY_LIGHT]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]]],
        ]);
        $row++;

        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->setCellValue('A'.$row, 'LIBRO DE VENTAS E INGRESOS');
        $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 11, 'color' => ['argb' => '0284C7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PRIMARY_LIGHT]],
        ]);
        $row++;

        $sheet->getRowDimension($row)->setRowHeight(20);
        $generatedText = 'Emitido el '.Carbon::now()->translatedFormat('d/m/Y \a \l\a\s H:i');
        $filterSummary = $this->formatFiltersForExport($filters);
        if ($filterSummary !== '') {
            $generatedText .= '  •  Criterios aplicados: '.$filterSummary;
        }

        $sheet->setCellValue('A'.$row, $generatedText);
        $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'size' => 9, 'color' => ['argb' => self::COLOR_MUTED_TXT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PRIMARY_LIGHT]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'CBD5E1']]],
        ]);
        $row += 2;

        return $row;
    }

    private function writeTotalCard($sheet, int $row, string $moneyCol): int
    {
        $sheet->getRowDimension($row)->setRowHeight(32);

        $moneyIndex = Coordinate::columnIndexFromString($moneyCol);
        $labelEnd = Coordinate::stringFromColumnIndex(max(1, $moneyIndex - 1));

        $sheet->setCellValue('A'.$row, 'TOTAL GENERAL FACTURADO');
        $sheet->mergeCells('A'.$row.':'.$labelEnd.$row);
        $sheet->getStyle('A'.$row.':'.$labelEnd.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 11, 'color' => ['argb' => '065F46']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'ECFDF5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'A7F3D0']]],
        ]);

        $sheet->setCellValue($moneyCol.$row, 0.00);
        $sheet->getStyle($moneyCol.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'bold' => true, 'size' => 13, 'color' => ['argb' => '047857']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'ECFDF5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'A7F3D0']]],
        ]);

        return $row;
    }

    private function styleTableHeader($sheet, int $row, array $columns, string $lastCol): void
    {
        $sheet->getRowDimension($row)->setRowHeight(26);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 10, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADER_BG]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '334155']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        foreach ($columns as $index => $col) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($letter.$row, $col['label']);
        }
    }

    private function writeDayHeader($sheet, int $row, string $title, string $lastCol): void
    {
        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->setCellValue('A'.$row, '📅  '.$title);
        $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 9.5, 'color' => ['argb' => self::COLOR_DAY_HEADER_TXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_DAY_HEADER_BG]],
            'borders' => [
                'left' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => '0284C7']],
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'right' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
    }

    private function writePaymentRow($sheet, int $row, array $paymentRow, array $columns): void
    {
        $lastCol = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getRowDimension($row)->setRowHeight($paymentRow['studentCount'] * 15 + 6);

        foreach ($columns as $index => $col) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $value = $paymentRow['values'][$col['id']] ?? '';

            if (in_array($col['type'], ['money', 'rate', 'int'], true) && is_numeric($value)) {
                $sheet->setCellValue($letter.$row, (float) $value);
            } else {
                $sheet->setCellValueExplicit($letter.$row, (string) $value, DataType::TYPE_STRING);
            }
        }

        $fill = ($row % 2 === 0) ? self::COLOR_ZEBRA : 'FFFFFF';
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'size' => 9.5, 'color' => ['argb' => '1E293B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fill]],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'left' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
                'right' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        foreach ($columns as $index => $col) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $align = match ($col['type']) {
                'money', 'rate' => Alignment::HORIZONTAL_RIGHT,
                'int' => Alignment::HORIZONTAL_CENTER,
                default => Alignment::HORIZONTAL_LEFT,
            };
            $sheet->getStyle($letter.$row)->getAlignment()->setHorizontal($align);

            if (in_array($col['type'], ['money', 'rate'], true)) {
                $sheet->getStyle($letter.$row)->applyFromArray([
                    'font' => ['name' => 'Consolas', 'size' => 9.5, 'color' => ['argb' => '0F172A']],
                    'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
                ]);
            } elseif ($col['type'] === 'int') {
                $sheet->getStyle($letter.$row)->applyFromArray([
                    'font' => ['name' => 'Consolas', 'size' => 9, 'color' => ['argb' => '475569']],
                ]);
            }
        }
    }

    private function styleSubtotalRow($sheet, int $row, string $lastCol, string $moneyCol): void
    {
        $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 9.5, 'color' => ['argb' => '334155']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_SUBTOTAL_BG]],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '94A3B8']],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '94A3B8']],
            ],
        ]);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($moneyCol.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'bold' => true, 'size' => 10, 'color' => ['argb' => '0F172A']],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
    }

    private function writeFinalTotal($sheet, int $row, string $formula, string $lastCol, string $moneyCol): void
    {
        $sheet->getRowDimension($row)->setRowHeight(30);
        $sheet->setCellValue('A'.$row, 'TOTAL CONSOLIDADO GENERAL');
        $sheet->mergeCells('A'.$row.':'.Coordinate::stringFromColumnIndex(max(1, Coordinate::columnIndexFromString($moneyCol) - 1)).$row);
        $sheet->setCellValue($moneyCol.$row, $formula);

        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['name' => 'Segoe UI', 'bold' => true, 'size' => 11, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PRIMARY_DARK]],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => '0284C7']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => '0284C7']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
        $sheet->getStyle($moneyCol.$row)->applyFromArray([
            'font' => ['name' => 'Consolas', 'bold' => true, 'size' => 12, 'color' => ['argb' => '38BDF8']],
            'numberFormat' => ['formatCode' => self::MONEY_FORMAT],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
    }

    private function prepareColumns($sheet, array $columns): void
    {
        foreach ($columns as $index => $col) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth(self::COLUMN_WIDTHS[$col['id']] ?? 18);
        }
    }

    private function columnLetter(array $columns, string $id): string
    {
        foreach ($columns as $index => $col) {
            if ($col['id'] === $id) {
                return Coordinate::stringFromColumnIndex($index + 1);
            }
        }

        return 'A';
    }

    // ----------------------------------------------------------------------
    // CSV
    // ----------------------------------------------------------------------

    private function exportCsv(array $report, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;

        // Encabezados
        foreach ($report['columns'] as $index => $col) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$row, $col['label']);
        }
        $row++;

        foreach ($report['days'] as $day) {
            $sheet->setCellValue('A'.$row, $day['title']);
            $row++;

            foreach ($day['rows'] as $paymentRow) {
                foreach ($report['columns'] as $index => $col) {
                    $value = $paymentRow['values'][$col['id']] ?? '';
                    $sheet->setCellValueExplicit(
                        Coordinate::stringFromColumnIndex($index + 1).$row,
                        $this->formatValueForText($value, $col['type']),
                        DataType::TYPE_STRING
                    );
                }
                $row++;
            }

            $sheet->setCellValue('A'.$row, 'SUBTOTAL DEL DÍA');
            $sheet->setCellValue($this->columnLetter($report['columns'], 'amount_bs').$row, number_format($day['subtotal'], 2, ',', '.'));
            $row += 2;
        }

        $sheet->setCellValue('A'.$row, 'TOTAL GENERAL');
        $sheet->setCellValue($this->columnLetter($report['columns'], 'amount_bs').$row, number_format($report['total'], 2, ',', '.'));

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ----------------------------------------------------------------------
    // PDF
    // ----------------------------------------------------------------------

    private function exportPdf(array $report, string $filename): Response
    {
        $institution = MainConfig::first()?->name ?? 'INSTITUCIÓN EDUCATIVA';
        $filterSummary = $this->formatFiltersForExport($report['filters']);

        $html = $this->renderPdfHtml($report, $institution, $filterSummary);

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function renderPdfHtml(array $report, string $institution, string $filterSummary): string
    {
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $headCells = '';
        foreach ($report['columns'] as $col) {
            $headCells .= '<th>'.$esc($col['label']).'</th>';
        }

        $body = '';
        $moneyCol = 'amount_bs';

        foreach ($report['days'] as $day) {
            $body .= '<tr class="day"><td colspan="'.count($report['columns']).'">'.$esc($day['title']).'</td></tr>';

            foreach ($day['rows'] as $paymentRow) {
                $body .= '<tr>';
                foreach ($report['columns'] as $col) {
                    $value = $paymentRow['values'][$col['id']] ?? '';
                    $body .= '<td class="'.($col['type'] === 'money' || $col['type'] === 'rate' ? 'num' : '').'">'
                        .$esc($this->formatValueForText($value, $col['type'], true)).'</td>';
                }
                $body .= '</tr>';
            }

            $body .= '<tr class="subtotal"><td colspan="'.(count($report['columns']) - 1).'">SUBTOTAL DEL DÍA</td>'
                .'<td class="num">'.number_format($day['subtotal'], 2, ',', '.').'</td></tr>';
        }

        $body .= '<tr class="total"><td colspan="'.(count($report['columns']) - 1).'">TOTAL GENERAL</td>'
            .'<td class="num">'.number_format($report['total'], 2, ',', '.').'</td></tr>';

        $generated = Carbon::now()->translatedFormat('d/m/Y \a \l\a\s H:i');
        $meta = $filterSummary !== '' ? 'Criterios aplicados: '.$esc($filterSummary) : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
                * { font-family: DejaVu Sans, sans-serif; }
                body { font-size: 9px; color: #1e293b; margin: 0; }
                h1 { font-size: 15px; margin: 0 0 2px; color: #0f172a; }
                h2 { font-size: 11px; margin: 0 0 8px; color: #0284c7; font-weight: normal; }
                .meta { font-size: 8px; color: #64748b; margin-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #1e293b; color: #fff; padding: 5px; border: 1px solid #334155; text-align: center; }
                td { padding: 4px 5px; border: 1px solid #e2e8f0; vertical-align: top; }
                tr.day td { background: #f1f5f9; font-weight: bold; color: #334155; }
                tr.subtotal td { background: #f8fafc; font-weight: bold; }
                tr.total td { background: #0f172a; color: #fff; font-weight: bold; }
                .num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
            </style></head><body>'
            .'<h1>'.$esc($institution).'</h1>'
            .'<h2>LIBRO DE VENTAS E INGRESOS</h2>'
            .'<div class="meta">Emitido el '.$generated.($meta !== '' ? ' &bull; '.$meta : '').'</div>'
            .'<table><thead><tr>'.$headCells.'</tr></thead><tbody>'.$body.'</tbody></table>'
            .'</body></html>';
    }

    private function formatValueForText($value, string $type, bool $multilineToBreak = false): string
    {
        if ($value === null) {
            return '';
        }

        if (in_array($type, ['money', 'rate'], true) && is_numeric($value)) {
            return number_format((float) $value, 2, ',', '.');
        }

        $text = (string) $value;

        return $multilineToBreak ? str_replace("\n", '<br>', $text) : $text;
    }

    // ----------------------------------------------------------------------
    // Filtros
    // ----------------------------------------------------------------------

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
