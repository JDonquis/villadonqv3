<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Sábana Académica</title>
    <style>
        @page { margin: 10px 14px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8px; color: #111; margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #17223b; padding-bottom: 5px; margin-bottom: 7px; }
        .header td { vertical-align: middle; }
        .logo { width: 56px; }
        .logo img { max-width: 52px; max-height: 52px; }
        .school-name { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #17223b; }
        .school-data { font-size: 7px; color: #444; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title .title { font-size: 14px; font-weight: bold; color: #17223b; text-transform: uppercase; }
        .doc-title .sub { font-size: 8px; margin-top: 2px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .meta td { border: 1px solid #b9c1cc; padding: 3px 5px; font-size: 7.5px; }
        .meta .label { background: #e9edf3; font-weight: bold; text-transform: uppercase; font-size: 6.5px; color: #333; }
        .grades { width: 100%; border-collapse: collapse; }
        .grades th { background: #17223b; color: #fff; border: 1px solid #17223b; padding: 3px 2px; font-size: 6.5px; text-align: center; text-transform: uppercase; }
        .grades th.name { text-align: left; }
        .grades td { border: 1px solid #aab3c0; padding: 2px; text-align: center; font-size: 7.5px; }
        .grades td.name { text-align: left; }
        .grades tbody tr:nth-child(even) td { background: #f5f7fa; }
        .fail { color: #b42318; font-weight: bold; }
        .stats { background: #eef2f8; font-weight: bold; }
        .tfoot td { background: #e4eaf3; font-weight: bold; color: #17223b; }
        .legend { margin-top: 5px; font-size: 6.5px; color: #444; line-height: 1.5; }
        .footer { margin-top: 8px; border-top: 1px solid #ccd3dd; padding-top: 4px; text-align: center; font-size: 6.5px; color: #666; }
        .signatures { width: 100%; margin-top: 18px; }
        .signatures td { width: 50%; text-align: center; font-size: 7px; padding-top: 22px; }
        .signature-line { border-top: 1px solid #444; width: 210px; margin: 0 auto; padding-top: 3px; font-weight: bold; }
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
                    @if (!empty($data['institution']['address'])) {{ $data['institution']['address'] }} @endif
                    @if (!empty($data['institution']['phone'])) &middot; Telf: {{ $data['institution']['phone'] }} @endif
                    @if (!empty($data['institution']['email'])) &middot; {{ $data['institution']['email'] }} @endif
                </div>
            </td>
            <td class="doc-title">
                <div class="title">Sábana Académica</div>
                <div class="sub">Resumen de las Evaluaciones</div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">Año escolar</td>
            <td>{{ $data['meta']['school_lapse_label'] ?? '—' }}</td>
            <td class="label">Año / Grado</td>
            <td>{{ $data['meta']['course_name'] ?? '—' }}</td>
            <td class="label">Sección</td>
            <td>{{ $data['meta']['section_name'] ?: 'Todas' }}</td>
            <td class="label">Momento</td>
            <td>{{ $data['meta']['lapse_label'] ?? '—' }}</td>
            <td class="label">Fecha</td>
            <td>{{ $data['meta']['generated_at'] }}</td>
        </tr>
    </table>

    <table class="grades">
        <thead>
            <tr>
                <th style="width:20px;">Nro.</th>
                <th class="name" style="width:150px;">Alumno</th>
                @foreach ($data['matters'] as $matter)
                    <th title="{{ $matter['name'] }}">{{ $matter['short'] }}</th>
                @endforeach
                <th style="width:34px;">Pro.</th>
                <th style="width:28px;">Pos.</th>
                <th style="width:28px;">Apr.</th>
                <th style="width:28px;">Apl.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['students'] as $student)
                <tr>
                    <td>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="name">{{ $student['full_name'] }}</td>
                    @foreach ($data['matters'] as $matter)
                        @php $grade = $student['grades'][$matter['id']] ?? null; @endphp
                        <td class="{{ $grade && $grade['score'] !== null && !$grade['approved'] ? 'fail' : '' }}">
                            {{ $grade && $grade['score'] !== null ? number_format($grade['score'], 1, ',', '') : '—' }}
                        </td>
                    @endforeach
                    <td class="stats">{{ $student['average'] !== null ? number_format($student['average'], 1, ',', '') : '—' }}</td>
                    <td>{{ $student['position'] ?? '—' }}</td>
                    <td>{{ $student['approved_count'] }}</td>
                    <td class="{{ $student['failed_count'] ? 'fail' : '' }}">{{ $student['failed_count'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($data['matters']) + 6 }}" style="padding:10px; color:#777;">No hay estudiantes para los criterios seleccionados.</td></tr>
            @endforelse
        </tbody>
        @if (count($data['students']) && count($data['matters']))
            <tfoot>
                <tr class="tfoot">
                    <td colspan="2" style="text-align:right; text-transform:uppercase; font-size:6.5px;">Promedio por área</td>
                    @foreach ($data['matters'] as $matter)
                        @php $stat = $data['matter_stats'][$matter['id']] ?? null; @endphp
                        <td>{{ $stat && $stat['average'] !== null ? number_format($stat['average'], 1, ',', '') : '—' }}</td>
                    @endforeach
                    <td>{{ $data['stats']['average'] !== null ? number_format($data['stats']['average'], 1, ',', '') : '—' }}</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="legend">
        <b>Áreas:</b>
        @foreach ($data['matters'] as $matter)
            <b>{{ $matter['short'] }}</b> = {{ $matter['name'] }}@if (!$loop->last) &middot; @endif
        @endforeach
        &nbsp;|&nbsp; Pro. = promedio, Pos. = posición, Apr. = áreas aprobadas, Apl. = áreas aplazadas. Escala 0 a 20; aprueba con 10.
    </div>

    <div class="footer">Documento generado el {{ $data['meta']['generated_at'] }} &middot; {{ $data['institution']['name'] }}</div>
</body>
</html>
