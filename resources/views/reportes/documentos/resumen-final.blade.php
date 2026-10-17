<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen Final</title>
    <style>
        @page { margin: 10px 12px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 7px; color: #111; margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #17223b; padding-bottom: 5px; margin-bottom: 6px; }
        .header td { vertical-align: middle; }
        .logo { width: 54px; }
        .logo img { max-width: 50px; max-height: 50px; }
        .school-name { font-size: 12px; font-weight: bold; text-transform: uppercase; color: #17223b; }
        .school-data { font-size: 6.5px; color: #444; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title .title { font-size: 12px; font-weight: bold; text-transform: uppercase; color: #17223b; }
        .doc-title .sub { font-size: 7px; margin-top: 2px; }
        .section-title { background: #17223b; color: #fff; font-size: 6.2px; font-weight: bold; text-transform: uppercase; padding: 1.5px 5px; margin: 4px 0 2px; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .info td { border: 1px solid #b9c1cc; padding: 2px 4px; font-size: 6.5px; }
        .info .label { background: #eef1f6; font-weight: bold; color: #333; text-transform: uppercase; font-size: 5.8px; }
        .grades { width: 100%; border-collapse: collapse; }
        .grades th { background: #17223b; color: #fff; border: 1px solid #17223b; padding: 1.5px 1px; font-size: 5.4px; text-align: center; text-transform: uppercase; }
        .grades th.name { text-align: left; }
        .grades td { border: 1px solid #aab3c0; padding: 1px; text-align: center; font-size: 6.1px; }
        .grades td.name { text-align: left; }
        .grades tbody tr:nth-child(even) td { background: #f5f7fa; }
        .fail { color: #b42318; font-weight: bold; }
        .totals td { background: #e8edf5; font-weight: bold; font-size: 6.2px; }
        .totals td.label { text-align: right; text-transform: uppercase; }
        .legend { margin-top: 4px; font-size: 5.8px; color: #444; line-height: 1.4; }
        .two { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .two > tbody > tr > td { vertical-align: top; }
        .prof { width: 100%; border-collapse: collapse; }
        .prof th { background: #17223b; color: #fff; border: 1px solid #17223b; padding: 2px; font-size: 5.8px; text-transform: uppercase; }
        .prof td { border: 1px solid #aab3c0; padding: 2px; font-size: 6.2px; }
        .course { width: 100%; border-collapse: collapse; }
        .course td { border: 1px solid #aab3c0; padding: 2px 4px; font-size: 6.4px; }
        .course .label { background: #eef1f6; font-weight: bold; text-transform: uppercase; font-size: 5.8px; }
        .signatures { width: 100%; margin-top: 6px; }
        .signatures td { width: 50%; text-align: center; font-size: 6.5px; padding-top: 10px; }
        .signature-line { border-top: 1px solid #444; width: 230px; margin: 0 auto; padding-top: 2px; font-weight: bold; }
        .footer { margin-top: 8px; border-top: 1px solid #ccd3dd; padding-top: 4px; text-align: center; font-size: 6px; color: #666; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if (!empty($data['institution']['logo']))
                <td class="logo"><img src="{{ $data['institution']['logo'] }}" alt="Logo"></td>
            @endif
            <td>
                <div class="school-name">{{ $data['institution']['name'] }}</div>
                <div class="school-data">
                    Código: {{ $data['institution']['code'] }}
                    @if (!empty($data['institution']['address'])) &middot; {{ $data['institution']['address'] }} @endif
                    @if (!empty($data['institution']['phone'])) &middot; Telf: {{ $data['institution']['phone'] }} @endif
                </div>
            </td>
            <td class="doc-title">
                <div class="title">Resumen Final del Rendimiento Estudiantil</div>
                <div class="sub">Año Escolar: {{ $data['meta']['school_lapse_label'] ?? '—' }} &middot; {{ $data['meta']['generated_at'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">I. Datos de la Institución Educativa</div>
    <table class="info">
        <tr>
            <td class="label">Municipio</td><td>{{ $data['institution']['municipality'] }}</td>
            <td class="label">Entidad Federal</td><td>{{ $data['institution']['federal_entity'] }}</td>
            <td class="label">CDCEE</td><td>{{ $data['institution']['cdcee'] }}</td>
            <td class="label">Director(a)</td><td>{{ $data['institution']['director_name'] }}</td>
            <td class="label">C.I.</td><td>{{ $data['institution']['director_ci'] }}</td>
        </tr>
    </table>

    <table class="two">
        <tr>
            <td style="width:72%; padding-right:6px;">
                <div class="section-title">II. Resumen Final del Rendimiento</div>
                <table class="grades">
                    <thead>
                        <tr>
                            <th style="width:16px;">N°</th>
                            <th style="width:56px;">Cédula</th>
                            <th class="name" style="width:120px;">Apellidos y Nombres</th>
                            <th style="width:20px;">Sexo</th>
                            <th style="width:46px;">Nacimiento</th>
                            @foreach ($data['matters'] as $matter)
                                <th title="{{ $matter['name'] }}">{{ $matter['short'] }}</th>
                            @endforeach
                            <th style="width:28px;">Prom.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['students'] as $student)
                            <tr>
                                <td>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $student['document_type'] }}-{{ $student['ci'] }}</td>
                                <td class="name">{{ $student['full_name'] }}</td>
                                <td>{{ $student['sex'] }}</td>
                                <td>{{ $student['birth_day'] }}/{{ $student['birth_month'] }}/{{ $student['birth_year'] }}</td>
                                @foreach ($data['matters'] as $matter)
                                    @php $score = $student['grades'][$matter['id']] ?? null; @endphp
                                    <td class="{{ $score !== null && $score < 10 ? 'fail' : '' }}">{{ $score !== null ? number_format($score, 1, ',', '') : '—' }}</td>
                                @endforeach
                                <td style="font-weight:bold;">{{ $student['average'] !== null ? number_format($student['average'], 1, ',', '') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="totals">
                            <td colspan="5" class="label">Inscritos</td>
                            @foreach ($data['matters'] as $matter)
                                <td>{{ $data['totals'][$matter['id']]['enrolled'] ?? 0 }}</td>
                            @endforeach
                            <td></td>
                        </tr>
                        <tr class="totals">
                            <td colspan="5" class="label">Inasistentes</td>
                            @foreach ($data['matters'] as $matter)
                                <td>{{ str_pad((string) ($data['totals'][$matter['id']]['absent'] ?? 0), 2, '0', STR_PAD_LEFT) }}</td>
                            @endforeach
                            <td></td>
                        </tr>
                        <tr class="totals">
                            <td colspan="5" class="label">Aprobados</td>
                            @foreach ($data['matters'] as $matter)
                                <td>{{ $data['totals'][$matter['id']]['approved'] ?? 0 }}</td>
                            @endforeach
                            <td></td>
                        </tr>
                        <tr class="totals">
                            <td colspan="5" class="label">No Aprobados</td>
                            @foreach ($data['matters'] as $matter)
                                <td>{{ $data['totals'][$matter['id']]['failed'] ?? 0 }}</td>
                            @endforeach
                            <td></td>
                        </tr>
                        <tr class="totals">
                            <td colspan="5" class="label">No Cursantes</td>
                            @foreach ($data['matters'] as $matter)
                                <td>{{ $data['totals'][$matter['id']]['not_taken'] ?? 0 }}</td>
                            @endforeach
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="legend">
                    @foreach ($data['matters'] as $matter)
                        <b>{{ $matter['short'] }}</b> = {{ $matter['name'] }}@if (!$loop->last) &middot; @endif
                    @endforeach
                </div>
            </td>
            <td style="width:28%;">
                <div class="section-title">III. Identificación del Curso</div>
                <table class="course">
                    <tr><td class="label">Plan de estudio</td><td>{{ $data['meta']['plan_name'] }}</td></tr>
                    <tr><td class="label">Código</td><td>{{ $data['meta']['plan_code'] ?: '—' }}</td></tr>
                    <tr><td class="label">Año cursado</td><td>{{ $data['meta']['course_name'] }}</td></tr>
                    <tr><td class="label">Sección</td><td>{{ $data['meta']['section_name'] ?: 'Todas' }}</td></tr>
                    <tr><td class="label">Estudiantes</td><td>{{ $data['meta']['total_students'] }}</td></tr>
                    <tr><td class="label">Fecha de emisión</td><td>{{ $data['meta']['generated_at'] }}</td></tr>
                </table>

                <div class="section-title" style="margin-top:8px;">IV. Profesores por Área</div>
                <table class="prof">
                    <thead><tr><th>Área</th><th>Profesor</th><th>C.I.</th></tr></thead>
                    <tbody>
                        @foreach ($data['matters'] as $index => $matter)
                            @php $professor = $data['professors'][$matter['id']] ?? null; @endphp
                            <tr>
                                <td>{{ $index + 1 }}. {{ $matter['short'] }}</td>
                                <td>{{ $professor['name'] ?? '—' }}</td>
                                <td>{{ $professor['ci'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">V. Observaciones</div>
    <div style="border:1px solid #aab3c0; height:18px;"></div>

    <table class="signatures">
        <tr>
            <td><div class="signature-line">Director(a)</div></td>
            <td><div class="signature-line">Sello de la Institución</div></td>
        </tr>
    </table>

    <div class="footer">Documento generado el {{ $data['meta']['generated_at'] }} &middot; {{ $data['institution']['name'] }}</div>
</body>
</html>
