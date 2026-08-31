<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelTemplateService
{
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const HEADER_BG_COLOR = '176B6B';

    private const HEADER_FONT_COLOR = 'FFFFFF';

    public function student(): StreamedResponse
    {
        $columns = [
            'Nombre del estudiante' => 'María',
            'Apellido del estudiante' => 'González',
            'Cédula del estudiante' => '30123456',
            'Tipo documento estudiante' => 'V',
            'Fecha de nacimiento (AAAA-MM-DD)' => '2015-03-12',
            'Email del estudiante' => 'maria.gonzalez@example.com',
            'Teléfono del estudiante' => '0412-1234567',
            'Sexo (Masculino/Femenino)' => 'Femenino',
            'Colegio anterior' => 'Colegio San José',
            'Año escolar' => '1er Año',
            'Sección' => 'A',
            'Nombre representante' => 'Pedro',
            'Apellido representante' => 'González',
            'Cédula representante' => '10234567',
            'Tipo doc representante' => 'V',
            'Teléfono representante' => '0414-7654321',
            'Teléfono 2 representante' => '0424-5551212',
            'Email representante' => 'pedro.gonzalez@example.com',
            'Profesión representante' => 'Ingeniero',
            'Lugar de trabajo representante' => 'PDVSA',
            'Parentesco representante' => 'Padre',
            'Dirección' => 'Av. Principal, Sector El Caujaro',
            'Estado' => 'Falcón',
            'Ciudad' => 'Coro',
            'Nombre 2do representante' => 'Ana',
            'Apellido 2do representante' => 'González',
            'Cédula 2do representante' => '11223344',
            'Tipo doc 2do representante' => 'V',
            'Teléfono 2do representante' => '0416-2223344',
            'Teléfono 2 (2do representante)' => '',
            'Email 2do representante' => 'ana.gonzalez@example.com',
            'Profesión 2do representante' => 'Docente',
            'Lugar de trabajo 2do representante' => 'Escuela Bolivariana',
            'Parentesco 2do representante' => 'Madre',
            'Exonerado (0/1)' => 0,
            'Porcentaje exoneración' => '',
            'Observaciones exoneración' => '',
        ];

        return $this->buildTemplate('plantilla_estudiantes.xlsx', $columns);
    }

    public function teacher(): StreamedResponse
    {
        $columns = [
            'Cédula' => '15566778',
            'Nombre' => 'Luis',
            'Apellido' => 'Ramírez',
            'Email' => 'luis.ramirez@example.com',
            'Teléfono' => '0412-5558899',
            'Dirección' => 'Calle 7, Coro',
            'Materias (separadas por coma)' => 'Matemática, Física',
        ];

        return $this->buildTemplate('plantilla_profesores.xlsx', $columns);
    }

    public function user(): StreamedResponse
    {
        $columns = [
            'Cédula' => '12033445',
            'Nombre' => 'Carlos',
            'Apellido' => 'Pérez',
            'Email' => 'carlos.perez@example.com',
            'Teléfono' => '0416-1112233',
            'Dirección' => 'Av. Los Andes, Coro',
            'Es administrador (0/1)' => 1,
        ];

        return $this->buildTemplate('plantilla_personal.xlsx', $columns);
    }

    public function readRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray();
        if (empty($rows)) {
            return [];
        }

        $headers = array_map(function ($header) {
            return $this->normalizeHeader($header);
        }, $rows[0]);

        $result = [];
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            if ($this->isRowEmpty($row)) {
                continue;
            }

            $data = [];
            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }

                $data[$header] = trim((string) ($row[$columnIndex] ?? ''));
            }

            $result[] = [
                'row' => $index + 1,
                'data' => $data,
            ];
        }

        return $result;
    }

    public function normalizeHeader($header): string
    {
        $header = (string) $header;

        return preg_replace('/\s+/', ' ', strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $header))));
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function buildTemplate(string $filename, array $columns): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla');

        $headers = array_keys($columns);
        $example = array_values($columns);

        foreach ($headers as $index => $header) {
            $column = $this->columnLetter($index + 1);

            $sheet->setCellValue($column.'1', $header);
            $sheet->setCellValue($column.'2', $example[$index]);

            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $lastColumn = $this->columnLetter(count($headers));
        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::HEADER_FONT_COLOR],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::HEADER_BG_COLOR],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A2:'.$lastColumn.'2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '808080']],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => self::MIME_XLSX,
        ]);
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - $mod, 26);
        }

        return $letter;
    }
}
