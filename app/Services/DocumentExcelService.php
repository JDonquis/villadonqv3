<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentExcelService
{
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const TEMPLATE_PATH = 'resources/templates/documentos/';

    public function sabanaAcademica(array $data): StreamedResponse
    {
        $sheet = $this->loadTemplate('sabana_academica.xlsx')->getActiveSheet();
        $meta = $data['meta'];
        $matters = array_slice($data['matters'], 0, 14);
        $students = $data['students'];

        $templateRows = 41;
        $extra = max(0, count($students) - $templateRows);

        if ($extra > 0) {
            $this->insertStyledRows($sheet, 46, $extra, 45, 'A', 'U');
        }

        $this->clearRange($sheet, 'A5:U'.(45 + $extra));

        $sheet->setCellValue('B1', $data['institution']['name'].'  Año Escolar: '.($meta['school_lapse_label'] ?? '').'    Fecha: '.$meta['generated_at']);
        $sheet->setCellValue('B2', 'Resumen de las Evaluaciones - '.(str_replace(' Momento', ' Lap.', $meta['lapse_label'] ?? 'Final')));
        $sheet->setCellValue('B3', 'Sección '.($meta['section_name'] ?: 'Todas'));

        $headerColumns = ['A' => 'Nro.', 'B' => 'Alumno'];
        $columnIndex = 3;

        foreach ($matters as $matter) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $headerColumns[$column] = $matter['short'];
            if ($column !== 'C') {
                $sheet->duplicateStyle($sheet->getStyle('C4'), $column.'4');
            }
            $columnIndex++;
        }

        $statsColumns = ['Q' => 'E.T.', 'R' => 'Pro.', 'S' => 'Pos.', 'T' => 'Apr.', 'U' => 'Apl.'];

        foreach ($headerColumns as $column => $label) {
            if ($column === 'A' || $column === 'B') {
                $sheet->setCellValue($column.'4', $label);
            } else {
                $sheet->setCellValue($column.'4', $label);
            }
        }

        foreach ($statsColumns as $column => $label) {
            $sheet->setCellValue($column.'4', $label);
        }

        $row = 5;

        foreach ($students as $student) {
            $sheet->setCellValue('A'.$row, $student['number'] ?? ($row - 4));
            $sheet->setCellValue('B'.$row, $student['full_name']);

            $columnIndex = 3;
            foreach ($matters as $matter) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $score = $student['grades'][$matter['id']]['score'] ?? null;
                $sheet->setCellValue($column.$row, $score !== null ? $score : '');
                if ($column !== 'C') {
                    $sheet->duplicateStyle($sheet->getStyle('C5'), $column.$row);
                }
                $columnIndex++;
            }

            $sheet->setCellValue('R'.$row, $student['average'] ?? '');
            $sheet->setCellValue('S'.$row, $student['position'] ?? '');
            $sheet->setCellValue('T'.$row, $student['approved_count'] ?? 0);
            $sheet->setCellValue('U'.$row, $student['failed_count'] ?? 0);
            $row++;
        }

        $lastRow = max(4, $row - 1);

        $sheet->getPageSetup()->setPrintArea('A1:U'.$lastRow);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->setShowGridlines(false);

        return $this->save($this->spreadsheetOf($sheet), 'sabana-academica-'.$this->slug($meta['course_name'] ?? 'curso').'-'.$this->slug($meta['section_name'] ?? 'todas').'.xlsx');
    }

    public function resumenFinal(array $data): StreamedResponse
    {
        $spreadsheet = $this->loadTemplate('resumen_final.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $meta = $data['meta'];
        $matters = array_slice($data['matters'], 0, 12);
        $students = $data['students'];

        $sheet->setCellValue('AM3', 'I. Año Escolar: '.($meta['school_lapse_label'] ?? ''));
        $sheet->setCellValue('AU4', 'FINAL');
        $sheet->setCellValue('BK4', ' '.mb_strtoupper($meta['generated_at_long'] ?? $meta['generated_at']));
        $sheet->setCellValue('A6', 'Código de la Institución Educativa: '.$data['institution']['code']);
        $sheet->setCellValue('AD6', 'Denominación o Epónimo: '.mb_strtoupper($data['institution']['name']));
        $sheet->setCellValue('A7', 'Dirección: '.$data['institution']['address']);
        $sheet->setCellValue('BK7', $data['institution']['phone']);
        $sheet->setCellValue('A8', 'Municipio: '.mb_strtoupper($data['institution']['municipality']));
        $sheet->setCellValue('AE8', mb_strtoupper($data['institution']['federal_entity']));
        $sheet->setCellValue('AP8', 'CDCEE:                                    '.mb_strtoupper($data['institution']['cdcee']));
        $sheet->setCellValue('A9', 'Director(a): '.mb_strtoupper($data['institution']['director_name'] ?? ''));
        $sheet->setCellValue('AP9', 'Cédula de Identidad:  '.($data['institution']['director_ci'] ?? ''));

        $subjectColumns = ['AX', 'AZ', 'BB', 'BD', 'BF', 'BH', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BP'];

        foreach ($subjectColumns as $index => $column) {
            $sheet->setCellValue($column.'16', isset($matters[$index]) ? $matters[$index]['short'] : '');

            if (! isset($matters[$index])) {
                $sheet->setCellValue($column.'15', '');
            }
        }

        $templateRows = 35;
        $studentCount = count($students);
        $extra = max(0, $studentCount - $templateRows);

        if ($extra > 0) {
            $this->insertStyledRows($sheet, 52, $extra, 51, 'A', 'BQ');
        }

        $this->clearRange($sheet, 'A17:BQ'.(51 + $extra));

        $row = 17;

        foreach ($students as $student) {
            $sheet->setCellValue('A'.$row, str_pad((string) ($row - 16), 2, '0', STR_PAD_LEFT));
            $sheet->setCellValueExplicit('B'.$row, $student['document_type'].'-'.$student['ci'], DataType::TYPE_STRING);
            $sheet->setCellValue('K'.$row, $student['last_name']);
            $sheet->setCellValue('X'.$row, $student['name']);
            $sheet->setCellValue('AI'.$row, $student['birth_place']);
            $sheet->setCellValue('AP'.$row, '');
            $sheet->setCellValue('AQ'.$row, $student['sex']);
            $sheet->setCellValue('AR'.$row, $student['birth_day']);
            $sheet->setCellValue('AT'.$row, $student['birth_month']);
            $sheet->setCellValue('AV'.$row, $student['birth_year']);

            foreach ($subjectColumns as $index => $column) {
                if (! isset($matters[$index])) {
                    $sheet->setCellValue($column.$row, '');

                    continue;
                }

                $score = $student['grades'][$matters[$index]['id']] ?? null;
                $sheet->setCellValue($column.$row, $score !== null ? $score : '');
            }

            $sheet->setCellValue('BQ'.$row, '');
            $row++;
        }

        $totalsRow = 52 + $extra;
        $totalLabels = ['Inscritos', 'Inasistentes', 'Aprobados', 'No Aprobados', 'No Cursantes'];
        $totalKeys = ['enrolled', 'absent', 'approved', 'failed', 'not_taken'];

        foreach ($totalLabels as $index => $label) {
            $currentRow = $totalsRow + $index;
            $sheet->setCellValue('X'.$currentRow, $label);

            foreach ($subjectColumns as $subjectIndex => $column) {
                if (! isset($matters[$subjectIndex])) {
                    $sheet->setCellValue($column.$currentRow, '');

                    continue;
                }

                $value = $data['totals'][$matters[$subjectIndex]['id']][$totalKeys[$index]] ?? 0;
                $sheet->setCellValue($column.$currentRow, $totalKeys[$index] === 'absent' ? str_pad((string) $value, 2, '0', STR_PAD_LEFT) : $value);
            }

            $sheet->setCellValue('BQ'.$currentRow, '*');
        }

        $profStart = 57 + $extra;
        $sheet->setCellValue('A'.$profStart, 'V. Profesores por Áreas:');
        $sheet->setCellValue('U'.$profStart, 'Apellidos y Nombres ');
        $sheet->setCellValue('AK'.$profStart, 'Cédula de Identidad');
        $sheet->setCellValue('AX'.$profStart, 'Firma:');
        $sheet->setCellValue('BH'.$profStart, 'VI. Identificación del Curso:');

        $sheet->setCellValue('A'.($profStart + 1), 'N°');
        $sheet->setCellValue('B'.($profStart + 1), 'Áreas de Formación');
        $sheet->setCellValue('BH'.($profStart + 1), 'PLAN DE ESTUDIO:');

        $profRows = 12;
        for ($index = 0; $index < $profRows; $index++) {
            $currentRow = $profStart + 2 + $index;
            $matter = $matters[$index] ?? null;
            $professor = $matter ? ($data['professors'][$matter['id']] ?? []) : [];

            $sheet->setCellValue('A'.$currentRow, $index + 1);
            $sheet->setCellValue('B'.$currentRow, $matter['short'] ?? '');
            $sheet->setCellValue('C'.$currentRow, $matter['name'] ?? '');
            $sheet->setCellValue('U'.$currentRow, $professor['name'] ?? '');
            $sheet->setCellValue('AK'.$currentRow, $professor['ci'] ?? '');
        }

        $courseInfo = [
            2 => $meta['plan_name'] ?? '',
            3 => 'CÓDIGO:',
            4 => $meta['plan_code'] ?? '',
            5 => 'AÑO CURSADO',
            6 => mb_strtoupper($meta['course_name'] ?? ''),
            7 => 'SECCIÓN',
            8 => $meta['section_name'] ?? '',
            9 => 'N° DE ESTUDIANTES POR SECCIÓN',
        ];

        foreach ($courseInfo as $offset => $value) {
            $sheet->setCellValue('BH'.($profStart + $offset), $value);
        }

        $sheet->setCellValue('BH'.($profStart + 13), $data['meta']['total_students']);
        $sheet->setCellValue('BP'.($profStart + 9), 'N° DE ESTUDIANTES EN ESTA PÁGINA');
        $sheet->setCellValue('BP'.($profStart + 13), $data['meta']['total_students']);

        $obsRow = 71 + $extra;
        $sheet->setCellValue('A'.$obsRow, 'VII. Observaciones: * * * ');
        $sheet->setCellValue('A'.($obsRow + 1), '* ** ');

        $signRow = 73 + $extra;
        $sheet->setCellValue('A'.$signRow, 'VIII. Fecha de Remisión:           ');
        $sheet->setCellValue('W'.$signRow, 'SELLO DE LA INSTITUCIÓN EDUCATIVA');
        $sheet->setCellValue('AN'.$signRow, 'IX. Fecha de Recepción:');
        $sheet->setCellValue('BK'.$signRow, "SELLO DEL CENTRO DE DESARROLLO DE LA CALIDAD EDUCATIVA ESTADAL \n");
        $sheet->setCellValue('A'.($signRow + 1), 'Director(a)');
        $sheet->setCellValue('AN'.($signRow + 1), 'Funcionario Receptor');
        $sheet->setCellValue('A'.($signRow + 2), 'Apellidos y Nombres:');
        $sheet->setCellValue('AN'.($signRow + 2), 'Apellidos y Nombres:');
        $sheet->setCellValue('A'.($signRow + 3), $data['institution']['director_name'] ?? '');
        $sheet->setCellValue('A'.($signRow + 4), 'Cédula de Identidad:');
        $sheet->setCellValue('AN'.($signRow + 4), 'Cédula de Identidad.:');
        $sheet->setCellValue('A'.($signRow + 5), $data['institution']['director_ci'] ?? '');
        $sheet->setCellValue('A'.($signRow + 6), 'Firma:');
        $sheet->setCellValue('AN'.($signRow + 6), 'Firma:');

        $lastRow = $signRow + 6;
        $sheet->getPageSetup()->setPrintArea('A1:BQ'.$lastRow);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->setShowGridlines(false);

        return $this->save($spreadsheet, 'resumen-final-'.$this->slug($meta['course_name'] ?? 'curso').'-'.$this->slug($meta['section_name'] ?? 'todas').'.xlsx');
    }

    public function certificadoNotas(array $data): StreamedResponse
    {
        $spreadsheet = $this->loadTemplate('notas_certificadas.xlsx');

        foreach (array_reverse($spreadsheet->getSheetNames()) as $name) {
            if (strcasecmp($name, 'Hoja1') !== 0) {
                $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($spreadsheet->getSheetByName($name)));
            }
        }

        $sheet = $spreadsheet->getActiveSheet();
        $institution = $data['institution'];
        $student = $data['student'];
        $program = $data['program'];

        $sheet->setCellValue('L2', 'I. Plan de Estudio:  '.$program['label']);
        $sheet->setCellValue('S2', ' Código '.($program['code'] ?? ''));
        $sheet->setCellValue('L3', 'Lugar y Fecha de Expedición: '.mb_strtoupper($institution['federal_entity']).' '.mb_strtoupper($data['generated_at_long']));
        $sheet->setCellValue('C6', $institution['code']);
        $sheet->setCellValue('J6', mb_strtoupper($institution['name']));
        $sheet->setCellValue('C7', $institution['address']);
        $sheet->setCellValue('Q7', $institution['phone']);
        $sheet->setCellValue('C8', mb_strtoupper($institution['municipality']));
        $sheet->setCellValue('I8', mb_strtoupper($institution['federal_entity']));
        $sheet->setCellValue('R8', ' '.mb_strtoupper($institution['cdcee']));
        $sheet->setCellValueExplicit('D10', $student['document_type'].' '.$student['ci'], DataType::TYPE_STRING);
        $sheet->setCellValue('N10', $student['birth_date_label']);
        $sheet->setCellValue('C11', $student['last_name']);
        $sheet->setCellValue('N11', $student['name']);
        $sheet->setCellValue('E12', $student['birth_country']);
        $sheet->setCellValue('J12', 'Estado: '.mb_strtoupper($student['birth_state']));
        $sheet->setCellValue('P12', 'Municipio:  '.mb_strtoupper($student['birth_municipality']));

        $this->clearRange($sheet, 'A14:U16');
        $this->fillCertificateInstitutions($sheet, $data['institutions']);

        $blocks = [
            1 => ['start' => 21, 'max' => 7, 'name' => 'A', 'score' => 'D', 'letters' => 'E', 'te' => 'G', 'month' => 'H', 'year' => 'I', 'plantel' => 'J'],
            2 => ['start' => 21, 'max' => 7, 'name' => 'M', 'score' => 'O', 'letters' => 'P', 'te' => 'R', 'month' => 'S', 'year' => 'T', 'plantel' => 'U'],
            3 => ['start' => 31, 'max' => 8, 'name' => 'A', 'score' => 'D', 'letters' => 'E', 'te' => 'G', 'month' => 'H', 'year' => 'I', 'plantel' => 'J'],
            4 => ['start' => 31, 'max' => 8, 'name' => 'M', 'score' => 'O', 'letters' => 'P', 'te' => 'R', 'month' => 'S', 'year' => 'T', 'plantel' => 'U'],
            5 => ['start' => 43, 'max' => 10, 'name' => 'A', 'score' => 'D', 'letters' => 'E', 'te' => 'G', 'month' => 'H', 'year' => 'I', 'plantel' => 'J'],
        ];

        $blockClearRanges = [
            1 => 'A21:J27',
            2 => 'M21:U27',
            3 => 'A31:J38',
            4 => 'M31:U38',
            5 => 'A43:J52',
        ];

        foreach ($data['years'] as $year) {
            $block = $blocks[$year['number']] ?? null;

            if (! $block) {
                continue;
            }

            $this->clearRange($sheet, $blockClearRanges[$year['number']]);

            $subjects = array_slice($year['subjects'], 0, $block['max']);

            for ($index = 0; $index < $block['max']; $index++) {
                $currentRow = $block['start'] + $index;
                $subject = $subjects[$index] ?? null;

                if (! $subject) {
                    continue;
                }

                $hasScore = $subject['score'] !== null;

                $sheet->setCellValue($block['name'].$currentRow, $subject['name']);
                $sheet->setCellValue($block['score'].$currentRow, $hasScore ? (int) round($subject['score']) : '******');
                $sheet->setCellValue($block['letters'].$currentRow, $hasScore ? ($subject['letters'] ?? '') : '*************************');
                $sheet->setCellValue($block['te'].$currentRow, $hasScore ? 'F' : '****');
                $sheet->setCellValue($block['month'].$currentRow, $hasScore ? '07' : '****');
                $sheet->setCellValue($block['year'].$currentRow, $hasScore ? ($year['year'] ?? now()->year) : '****');
                $sheet->setCellValue($block['plantel'].$currentRow, $hasScore ? 1 : '****');
            }
        }

        $sheet->setCellValue('A59', $data['director']['name']);
        $sheet->setCellValue('A61', $data['director']['ci']);

        $sheet->getPageSetup()->setPrintArea('A1:U63');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->setShowGridlines(false);

        return $this->save($spreadsheet, 'notas-certificadas-'.$this->slug($student['ci']).'.xlsx');
    }

    public function registroTitulos(array $data): StreamedResponse
    {
        $spreadsheet = $this->loadTemplate('hoja_registro_titulo.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $meta = $data['meta'];
        $institution = $data['institution'];
        $students = $data['students'];
        $authorities = $data['authorities'];

        $sheet->setCellValue('AC5', 'Lugar y Fecha de Expedición:  '.mb_strtoupper($institution['federal_entity']).' '.mb_strtoupper($meta['generated_at_long']));
        $sheet->setCellValue('C7', '  '.$institution['code']);
        $sheet->setCellValue('R7', mb_strtoupper($institution['name']));
        $sheet->setCellValue('A8', 'Dirección: '.$institution['address']);
        $sheet->setCellValue('A9', 'Municipio: '.mb_strtoupper($institution['municipality']));
        $sheet->setCellValue('M9', 'Entidad Federal: '.mb_strtoupper($institution['federal_entity']));
        $sheet->setCellValue('AK9', 'Zona Educativa: '.mb_strtoupper($institution['cdcee']));
        $sheet->setCellValue('A13', 'Nombre del Documento: BACHILLER.');

        $templateRows = 25;
        $extra = max(0, count($students) - $templateRows);

        if ($extra > 0) {
            $this->insertStyledRows($sheet, 43, $extra, 42, 'A', 'AP');
        }

        $this->clearRange($sheet, 'A18:AP'.(42 + $extra));

        $row = 18;

        foreach ($students as $student) {
            $sheet->setCellValueExplicit('A'.$row, str_pad((string) ($row - 17), 2, '0', STR_PAD_LEFT), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, $student['serial'] ?: '*****', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$row, $student['ci'], DataType::TYPE_STRING);
            $sheet->setCellValue('J'.$row, mb_strtoupper($student['full_name']));
            $sheet->setCellValue('AD'.$row, $student['birth_state_code']);
            $sheet->setCellValue('AF'.$row, mb_strtoupper($student['birth_municipality']));
            $sheet->setCellValue('AM'.$row, $student['birth_day']);
            $sheet->setCellValue('AN'.$row, $student['birth_month']);
            $sheet->setCellValue('AO'.$row, $student['birth_year']);
            $sheet->setCellValue('AP'.$row, $student['observations']);
            $row++;
        }

        $totalRow = 43 + $extra;
        $sheet->setCellValue('A'.$totalRow, 'TOTAL DE TITULOS  EMITIDOS: '.$meta['total']);
        $sheet->setCellValue('AD'.$totalRow, 'AÑO:');
        $sheet->setCellValue('AH'.$totalRow, $meta['year_label'] ?: '5°');
        $sheet->setCellValue('AO'.$totalRow, 'SECCION: "'.($meta['section_name'] ?: 'U').'"');

        $authRow = 44 + $extra;
        $sheet->setCellValue('A'.$authRow, 'VI. Autoridades Educativas:');
        $sheet->setCellValue('A'.($authRow + 1), 'DIRECTOR(A) DEL PLANTEL: ');
        $sheet->setCellValue('A'.($authRow + 2), 'Apellidos y Nombres: '.mb_strtoupper($authorities['director_name']));
        $sheet->setCellValue('AF'.($authRow + 2), 'C.I.: '.$authorities['director_ci']);
        $sheet->setCellValue('A'.($authRow + 3), 'COORDINADOR(A) DE CONTROL DE ESTUDIOS:');
        $sheet->setCellValue('A'.($authRow + 4), 'Apellidos y Nombres: '.mb_strtoupper($authorities['coordinator_name']));
        $sheet->setCellValue('AF'.($authRow + 4), 'C.I.: '.$authorities['coordinator_ci']);
        $sheet->setCellValue('A'.($authRow + 5), 'FUNCIONARIO DESIGNADO POR EL MINISTERIO DEL PODER POPULAR PARA LA EDUCACION:');
        $sheet->setCellValue('A'.($authRow + 6), 'Apellidos y Nombres: '.mb_strtoupper($authorities['official_name']));
        $sheet->setCellValue('AF'.($authRow + 6), 'C.I.: '.$authorities['official_ci']);
        $sheet->setCellValue('A'.($authRow + 7), 'VII. Observaciones:');

        $signRow = 53 + $extra;
        $sheet->setCellValue('A'.$signRow, 'VIII. Fecha de Remisión:');
        $sheet->setCellValue('AC'.$signRow, 'IX. Fecha de Recepción:');
        $sheet->setCellValue('A'.($signRow + 1), 'Director (a)');
        $sheet->setCellValue('O'.($signRow + 1), 'SELLO DEL PLANTEL');
        $sheet->setCellValue('AC'.($signRow + 1), 'Funcionario(a) Receptor');
        $sheet->setCellValue('A'.($signRow + 2), 'Apellidos y Nombres:');
        $sheet->setCellValue('AC'.($signRow + 2), 'Apellidos y Nombres:');
        $sheet->setCellValue('A'.($signRow + 3), mb_strtoupper($authorities['director_name']));
        $sheet->setCellValue('A'.($signRow + 4), 'Cédula de Identidad');
        $sheet->setCellValue('AC'.($signRow + 4), 'Cédula de Identidad');
        $sheet->setCellValue('A'.($signRow + 5), $authorities['director_ci']);
        $sheet->setCellValue('A'.($signRow + 6), 'Firma:');
        $sheet->setCellValue('AC'.($signRow + 6), 'Firma:');

        $lastRow = $signRow + 6;
        $highestRow = $sheet->getHighestRow();

        if ($highestRow > $lastRow) {
            $sheet->removeRow($lastRow + 1, $highestRow - $lastRow);
        }

        $sheet->getPageSetup()->setPrintArea('A1:AP'.$lastRow);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->setShowGridlines(false);

        return $this->save($spreadsheet, 'registro-titulos-'.$this->slug($meta['course_name'] ?? 'curso').'-'.$this->slug($meta['section_name'] ?? 'todas').'.xlsx');
    }

    private function fillCertificateInstitutions(Worksheet $sheet, array $institutions): void
    {
        $left = array_slice($institutions, 0, 2);
        $right = array_slice($institutions, 2, 3);

        foreach ($left as $index => $institution) {
            $row = 15 + $index;
            $sheet->setCellValue('A'.$row, $index + 1);
            $sheet->setCellValue('B'.$row, mb_strtoupper($institution['name']));
            $sheet->setCellValue('F'.$row, mb_strtoupper($institution['locality']));
            $sheet->setCellValue('J'.$row, $institution['state_code']);
        }

        foreach ($right as $index => $institution) {
            $row = 14 + $index;
            $sheet->setCellValue('M'.$row, $index + 3);
            $sheet->setCellValue('N'.$row, mb_strtoupper($institution['name']));
            $sheet->setCellValue('Q'.$row, mb_strtoupper($institution['locality']));
            $sheet->setCellValue('U'.$row, $institution['state_code']);
        }
    }

    private function loadTemplate(string $file): Spreadsheet
    {
        $path = base_path(self::TEMPLATE_PATH.$file);

        if (! is_file($path)) {
            throw new \RuntimeException('No se encontró la plantilla '.$file.'.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);

        return $reader->load($path);
    }

    private function save(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => self::MIME_XLSX]);
    }

    private function spreadsheetOf(Worksheet $sheet): Spreadsheet
    {
        return $sheet->getParent();
    }

    private function clearRange(Worksheet $sheet, string $range): void
    {
        [$start, $end] = Coordinate::rangeBoundaries($range);
        [$startColumn, $startRow] = $start;
        [$endColumn, $endRow] = $end;

        $mergeSlaves = [];

        foreach (array_keys($sheet->getMergeCells()) as $merge) {
            [$mergeStart, $mergeEnd] = Coordinate::rangeBoundaries($merge);

            for ($row = $mergeStart[1]; $row <= $mergeEnd[1]; $row++) {
                for ($column = $mergeStart[0]; $column <= $mergeEnd[0]; $column++) {
                    if ($column === $mergeStart[0] && $row === $mergeStart[1]) {
                        continue;
                    }

                    $mergeSlaves[$column.'_'.$row] = true;
                }
            }
        }

        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($column = $startColumn; $column <= $endColumn; $column++) {
                if (isset($mergeSlaves[$column.'_'.$row])) {
                    continue;
                }

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($column).$row, null);
            }
        }
    }

    private function insertStyledRows(Worksheet $sheet, int $beforeRow, int $count, int $styleRow, string $startColumn, string $endColumn): void
    {
        if ($count <= 0) {
            return;
        }

        $merges = [];
        $startColumnIndex = Coordinate::columnIndexFromString($startColumn);
        $endColumnIndex = Coordinate::columnIndexFromString($endColumn);

        foreach (array_keys($sheet->getMergeCells()) as $merge) {
            [$mergeStart, $mergeEnd] = Coordinate::rangeBoundaries($merge);

            if ($mergeStart[1] !== $styleRow) {
                continue;
            }

            if ($mergeStart[0] < $startColumnIndex || $mergeEnd[0] > $endColumnIndex) {
                continue;
            }

            $merges[] = [$mergeStart[0], $mergeEnd[0]];
        }

        $rowHeight = $sheet->getRowDimension($styleRow)->getRowHeight();
        $sheet->insertNewRowBefore($beforeRow, $count);

        for ($index = 0; $index < $count; $index++) {
            $row = $beforeRow + $index;
            $sheet->duplicateStyle($sheet->getStyle($startColumn.$styleRow.':'.$endColumn.$styleRow), $startColumn.$row.':'.$endColumn.$row);
            $sheet->getRowDimension($row)->setRowHeight($rowHeight);

            foreach ($merges as [$mergeStartColumn, $mergeEndColumn]) {
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($mergeStartColumn).$row.':'
                    .Coordinate::stringFromColumnIndex($mergeEndColumn).$row
                );
            }
        }
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?: 'documento';

        return strtolower(trim($value, '-'));
    }
}
