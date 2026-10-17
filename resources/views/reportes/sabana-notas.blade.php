<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Sábana de Notas</title>
    <style>
        @page { margin: 22px 26px; }
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1a202c; margin: 0; }

        .header { width: 100%; border-bottom: 3px double #1f4287; padding-bottom: 8px; margin-bottom: 10px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { width: 70px; }
        .logo img { max-width: 62px; max-height: 62px; }
        .school-name { font-size: 16px; font-weight: bold; color: #1f4287; text-transform: uppercase; letter-spacing: .5px; }
        .school-motto { font-size: 9px; color: #4a5568; font-style: italic; margin-top: 1px; }
        .school-data { font-size: 8px; color: #718096; margin-top: 3px; line-height: 1.35; }
        .doc-title { text-align: right; }
        .doc-title .title { font-size: 17px; font-weight: bold; color: #1f4287; letter-spacing: 1px; }
        .doc-title .sub { font-size: 8px; color: #718096; margin-top: 3px; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta td { padding: 4px 6px; font-size: 9px; border: 1px solid #e2e8f0; }
        .meta .label { background: #edf2f7; font-weight: bold; color: #4a5568; text-transform: uppercase; font-size: 7.5px; letter-spacing: .3px; }

        .grades { width: 100%; border-collapse: collapse; }
        .grades th { background: #17223b; color: #fff; border: 1px solid #17223b; padding: 4px 3px; font-size: 7.5px; text-transform: uppercase; text-align: center; letter-spacing: .3px; }
        .grades th.name { text-align: left; }
        .grades td { border: 1px solid #cbd5e0; padding: 3px; text-align: center; font-size: 9px; }
        .grades td.name { text-align: left; }
        .grades tbody tr:nth-child(even) td { background: #f7fafc; }
        .fail { color: #c53030; font-weight: bold; }
        .avg-col { background: #e6f0ff; font-weight: bold; color: #1f4287; }
        .tfoot td { background: #edf2f7; font-weight: bold; color: #1f4287; }
        .ci { font-family: "DejaVu Sans Mono", Consolas, monospace; }
        .section-sub { color: #718096; font-size: 7.5px; }
        .sub { font-size: 6.5px; color: #718096; margin-top: 1px; }

        .legend { margin-top: 8px; font-size: 7.5px; color: #718096; line-height: 1.5; }
        .legend b { color: #17223b; }
        .footer { margin-top: 14px; font-size: 8px; color: #a0aec0; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                @if (!empty($institution['logo']))
                    <td class="logo"><img src="{{ $institution['logo'] }}" alt="Logo"></td>
                @endif
                <td>
                    <div class="school-name">{{ $institution['name'] }}</div>
                    @if (!empty($institution['motto']))
                        <div class="school-motto">"{{ $institution['motto'] }}"</div>
                    @endif
                    <div class="school-data">
                        @if (!empty($institution['rif'])) RIF: {{ $institution['rif'] }}@endif
                        @if (!empty($institution['address'])) &middot; {{ $institution['address'] }}@endif
                        @if (!empty($institution['phone'])) &middot; Telf: {{ $institution['phone'] }}@endif
                        @if (!empty($institution['email'])) &middot; {{ $institution['email'] }}@endif
                    </div>
                </td>
                <td class="doc-title">
                    <div class="title">SÁBANA DE NOTAS</div>
                    <div class="sub">{{ $institution['name'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Período escolar</td>
            <td>{{ $sheet['school_lapse']['label'] ?? '—' }}</td>
            <td class="label">Año / Grado</td>
            <td>{{ $sheet['course']['name'] ?? '—' }}</td>
            <td class="label">Sección</td>
            <td>{{ $section ?: 'Todas' }}</td>
            <td class="label">Momento</td>
            <td>{{ $selected_lapse['label'] ?? '—' }}</td>
        </tr>
    </table>

    <table class="grades">
        <thead>
            <tr>
                <th style="width:22px;">Nº</th>
                <th style="width:62px;">Cédula</th>
                <th class="name" style="width:160px;">Estudiante</th>
                @foreach ($matters as $matter)
                    <th title="{{ $matter['name'] }}">{{ mb_strtoupper(mb_substr($matter['name'], 0, 3)) }}</th>
                @endforeach
                <th style="width:46px;">Prom.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $i => $student)
                <tr>
                    <td>{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="ci">{{ $student['ci'] ?? '—' }}</td>
                    <td class="name">
                        {{ $student['last_name'] }}, {{ $student['name'] }}
                        @if (!empty($student['section_name']))
                            <br><span class="section-sub">Regular &middot; {{ $student['section_name'] }}</span>
                        @endif
                    </td>
                    @foreach ($matters as $matter)
                        @php
                            $grade = $student['grades'][$matter['id']] ?? null;
                            $final = $grade['final'] ?? null;
                            $def = $grade['definitive'] ?? null;
                            $pts = (int) ($grade['points'] ?? 0);
                        @endphp
                        <td class="{{ $final !== null && $final < $passing_score ? 'fail' : '' }}">
                            @if ($final === null)
                                —
                            @else
                                <div>{{ number_format($final, 2, ',', '.') }}</div>
                                @if ($pts > 0)
                                    <div class="sub">antes {{ number_format($def, 2, ',', '.') }} +{{ $pts }}</div>
                                @endif
                            @endif
                        </td>
                    @endforeach
                    <td class="avg-col">
                        @if ($student['average'] !== null)
                            <div>{{ number_format($student['average'], 2, ',', '.') }}</div>
                            @if ($student['base_average'] !== null && $student['base_average'] != $student['average'])
                                <div class="sub">antes {{ number_format($student['base_average'], 2, ',', '.') }}</div>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($matters) + 4 }}" style="text-align:center; color:#a0aec0; padding:12px;">
                        No hay estudiantes con los criterios seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (count($students) && count($matters))
            <tfoot class="tfoot">
                <tr>
                    <td colspan="3" style="text-align:right; text-transform:uppercase; font-size:7.5px;">Promedio por asignatura</td>
                    @foreach ($matters as $matter)
                        <td>{{ $matter_averages[$matter['id']] !== null ? number_format($matter_averages[$matter['id']], 2, ',', '.') : '—' }}</td>
                    @endforeach
                    <td class="avg-col">{{ $overall_average !== null ? number_format($overall_average, 2, ',', '.') : '—' }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if (count($matters))
        <div class="legend">
            <b>Materias:</b>
            @foreach ($matters as $matter)
                <b>{{ mb_strtoupper(mb_substr($matter['name'], 0, 3)) }}</b> = {{ $matter['name'] }}@if (!$loop->last) &middot; @endif
            @endforeach
        </div>
    @endif

    <div class="footer">
        Escala de calificaciones de 0 a {{ $max_score }} puntos. Aprobado con {{ $passing_score }} o más.
        &middot; «antes» = nota definitiva publicada antes de sumar los puntos de la sábana (base + puntos = nota final).
        &middot; Documento generado el {{ $generated_at }} &middot; {{ $institution['name'] }}
    </div>
</body>
</html>
