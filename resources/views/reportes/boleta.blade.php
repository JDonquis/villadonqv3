<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boleta de Calificaciones</title>
    <style>
        @page { margin: 30px 40px; }
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1a202c; margin: 0; }
        .header { text-align: center; border-bottom: 3px double #2b6cb0; padding-bottom: 10px; margin-bottom: 16px; }
        .school-name { font-size: 22px; font-weight: bold; color: #2b6cb0; text-transform: uppercase; letter-spacing: 1px; }
        .school-motto { font-size: 11px; color: #4a5568; font-style: italic; }
        .school-data { font-size: 10px; color: #718096; margin-top: 4px; }
        .title { text-align: center; font-size: 18px; font-weight: bold; text-transform: uppercase; margin: 6px 0 14px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 12px; }
        .info-table td { padding: 3px 6px; vertical-align: top; }
        .info-label { font-weight: bold; width: 120px; }
        .grades { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 22px; }
        .grades th { background: #2b6cb0; color: #fff; padding: 7px 6px; border: 1px solid #2b6cb0; font-size: 11px; text-transform: uppercase; }
        .grades td { padding: 6px; border: 1px solid #cbd5e0; text-align: center; }
        .grades td.name { text-align: left; font-weight: 600; }
        .grades tr:nth-child(even) td { background: #f7fafc; }
        .aprobada { color: #2f855a; font-weight: bold; }
        .reprobada { color: #c53030; font-weight: bold; }
        .en_curso { color: #b7791f; font-weight: bold; }
        .sin_nota { color: #a0aec0; }
        .signatures { width: 100%; margin-top: 40px; }
        .signature-col { width: 50%; text-align: center; font-size: 11px; color: #4a5568; }
        .signature-line { border-top: 1px solid #718096; width: 220px; margin: 0 auto 6px; padding-top: 6px; font-weight: bold; color: #1a202c; }
        .footer { margin-top: 30px; font-size: 10px; color: #a0aec0; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-name">{{ $config->name }}</div>
        @if ($config->motto)
            <div class="school-motto">"{{ $config->motto }}"</div>
        @endif
        <div class="school-data">
            RIF: {{ strtoupper($config->rif) }} · {{ $config->address }}
            @if ($config->phone_number) · Telf: {{ $config->phone_number }} @endif
            @if ($config->email) · {{ $config->email }} @endif
        </div>
    </div>

    <div class="title">Boleta de Calificaciones</div>

    <table class="info-table">
        <tr>
            <td class="info-label">Estudiante:</td>
            <td><strong>{{ $student['name'] }} {{ $student['last_name'] }}</strong></td>
            <td class="info-label">C.I:</td>
            <td>{{ $student['document_type'] }}-{{ $student['ci'] }}</td>
        </tr>
        <tr>
            <td class="info-label">Curso:</td>
            <td>{{ $student['course'] }}</td>
            <td class="info-label">Sección:</td>
            <td>{{ $student['section'] }}</td>
        </tr>
        <tr>
            <td class="info-label">Año escolar:</td>
            <td>{{ $period['label'] ?? '—' }}</td>
            <td class="info-label">Representante:</td>
            <td>{{ $student['rep_name'] }} {{ $student['rep_last_name'] }}</td>
        </tr>
        @if ($selected_lapse)
            <tr>
                <td class="info-label">Momento:</td>
                <td>{{ $selected_lapse['label'] }}</td>
            </tr>
        @endif
    </table>

    <table class="grades">
        <thead>
            <tr>
                <th style="text-align:left;">Materia</th>
                @if ($selected_lapse)
                    <th>Nota ({{ $selected_lapse['label'] }})</th>
                    <th>Estatus</th>
                @else
                    @foreach ($lapses as $lapse)
                        <th>{{ $lapse['label'] }}</th>
                    @endforeach
                    <th>Promedio</th>
                    <th>Estatus</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $subject)
                <tr>
                    <td class="name">{{ $subject['name'] }}</td>
                    @if ($selected_lapse)
                        @php $val = $subject['lapses'][$selected_lapse['number']]['definitive'] ?? null; @endphp
                        <td>{{ $val !== null ? $val : '—' }}</td>
                        <td class="{{ $subject['status']['value'] }}">{{ $subject['status']['label'] }}</td>
                    @else
                        @foreach ($lapses as $lapse)
                            @php $val = $subject['lapses'][$lapse['number']]['definitive'] ?? null; @endphp
                            <td>{{ $val !== null ? $val : '—' }}</td>
                        @endforeach
                        <td><strong>{{ $subject['annual'] !== null ? $subject['annual'] : '—' }}</strong></td>
                        <td class="{{ $subject['status']['value'] }}">{{ $subject['status']['label'] }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($subjects)
        <div style="font-size:11px; color:#4a5568; margin-bottom:30px;">
            Escala de calificaciones: 0 a 20 puntos. Aprobado con 10 o más.
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td class="signature-col">
                <div class="signature-line">Director(a)</div>
            </td>
            <td class="signature-col">
                <div class="signature-line">Representante</div>
            </td>
        </tr>
    </table>

    <div class="footer">Documento generado el {{ $current_date }} · {{ $config->name }}</div>
</body>
</html>
