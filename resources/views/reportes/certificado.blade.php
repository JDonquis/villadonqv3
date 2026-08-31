<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado de Notas</title>
    <style>
        @page { margin: 30px 40px; }
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #1a202c; margin: 0; }
        .letterhead { border: 3px double #2b6cb0; padding: 14px 20px; text-align: center; margin-bottom: 22px; }
        .school-name { font-size: 24px; font-weight: bold; color: #2b6cb0; text-transform: uppercase; letter-spacing: 1px; }
        .school-motto { font-size: 11px; color: #4a5568; font-style: italic; margin-top: 2px; }
        .school-data { font-size: 10px; color: #718096; margin-top: 4px; }
        .title { text-align: center; font-size: 18px; font-weight: bold; text-transform: uppercase; margin: 6px 0 18px; letter-spacing: 1px; }
        .text { text-align: justify; line-height: 1.6; margin-bottom: 14px; }
        .grades { width: 100%; border-collapse: collapse; font-size: 12px; margin: 14px 0 24px; }
        .grades th { background: #2b6cb0; color: #fff; padding: 7px 6px; border: 1px solid #2b6cb0; font-size: 11px; text-transform: uppercase; }
        .grades td { padding: 6px; border: 1px solid #cbd5e0; text-align: center; }
        .grades td.name { text-align: left; font-weight: 600; }
        .grades tr:nth-child(even) td { background: #f7fafc; }
        .aprobada { color: #2f855a; font-weight: bold; }
        .reprobada { color: #c53030; font-weight: bold; }
        .en_curso { color: #b7791f; font-weight: bold; }
        .sin_nota { color: #a0aec0; }
        .signatures { width: 100%; margin-top: 50px; }
        .signature-col { width: 50%; text-align: center; font-size: 11px; color: #4a5568; }
        .signature-line { border-top: 1px solid #718096; width: 220px; margin: 0 auto 6px; padding-top: 6px; font-weight: bold; color: #1a202c; }
        .footer { margin-top: 30px; font-size: 10px; color: #a0aec0; text-align: center; }
        .date-line { text-align: right; font-size: 11px; color: #4a5568; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="letterhead">
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

    <div class="title">Certificado de Notas</div>

    <div class="date-line">
        {{ $config->name }}, a los {{ $current_date }}
    </div>

    <div class="text">
        Quien suscribe, <strong>Dirección de {{ $config->name }}</strong>, hace constar que
        el/la estudiante <strong>{{ $student['name'] }} {{ $student['last_name'] }}</strong>,
        titular de la cédula de identidad N° <strong>{{ $student['document_type'] }}-{{ $student['ci'] }}</strong>,
        cursó el <strong>{{ $student['course'] }}</strong>, sección <strong>{{ $student['section'] }}</strong>,
        durante el año escolar <strong>{{ $period['label'] ?? '—' }}</strong>,
        @if ($selected_lapse)
            en el <strong>{{ $selected_lapse['label'] }}</strong>,
        @endif
        obteniendo las siguientes calificaciones:
    </div>

    <table class="grades">
        <thead>
            <tr>
                <th style="text-align:left;">Materia</th>
                <th>{{ $selected_lapse ? 'Nota ('.$selected_lapse['label'].')' : 'Promedio Anual' }}</th>
                <th>Estatus</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $subject)
                <tr>
                    <td class="name">{{ $subject['name'] }}</td>
                    <td>{{ $subject['annual'] !== null ? $subject['annual'] : '—' }}</td>
                    <td class="{{ $subject['status']['value'] }}">{{ $subject['status']['label'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="text">
        Se expide el presente certificado a solicitud de la parte interesada.
        Escala de calificaciones: 0 a 20 puntos, aprobado con 10 o más.
    </div>

    <table class="signatures">
        <tr>
            <td class="signature-col">
                <div class="signature-line">Director(a)</div>
            </td>
            <td class="signature-col">
                <div class="signature-line">Secretaría / Coordinación</div>
            </td>
        </tr>
    </table>

    <div class="footer">Documento generado el {{ $current_date }} · {{ $config->name }}</div>
</body>
</html>
