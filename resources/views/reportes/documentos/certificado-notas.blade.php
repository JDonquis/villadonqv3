<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificación de Calificaciones</title>
    <style>
        @page { margin: 18px 24px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 7.5px; color: #111; margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #17223b; padding-bottom: 6px; margin-bottom: 6px; }
        .header td { vertical-align: middle; }
        .logo { width: 60px; }
        .logo img { max-width: 56px; max-height: 56px; }
        .school-name { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #17223b; }
        .school-data { font-size: 7px; color: #444; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title .title { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #17223b; }
        .doc-title .sub { font-size: 7px; margin-top: 2px; }
        .section-title { background: #17223b; color: #fff; font-size: 6.8px; font-weight: bold; text-transform: uppercase; padding: 2px 5px; margin: 6px 0 3px; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
        .info td { border: 1px solid #b9c1cc; padding: 2px 4px; font-size: 6.8px; }
        .info .label { background: #eef1f6; font-weight: bold; color: #333; text-transform: uppercase; font-size: 6px; }
        .planteles { width: 100%; border-collapse: collapse; }
        .planteles th { background: #17223b; color: #fff; border: 1px solid #17223b; padding: 2px; font-size: 6.2px; text-transform: uppercase; }
        .planteles td { border: 1px solid #aab3c0; padding: 2px 3px; font-size: 6.8px; }
        .years { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-top: 2px; }
        .years > tbody > tr > td { width: 50%; vertical-align: top; }
        .year-title { background: #e8edf5; border: 1px solid #aab3c0; border-bottom: none; text-align: center; font-weight: bold; font-size: 6.8px; padding: 2px; text-transform: uppercase; }
        .year-table { width: 100%; border-collapse: collapse; }
        .year-table th { background: #17223b; color: #fff; border: 1px solid #17223b; padding: 1.5px; font-size: 5.6px; text-transform: uppercase; }
        .year-table td { border: 1px solid #aab3c0; padding: 1.5px 2px; font-size: 6.2px; }
        .year-table td.subject { text-align: left; }
        .year-table td.center { text-align: center; }
        .stars { letter-spacing: 0.5px; color: #333; }
        .obs { border: 1px solid #aab3c0; min-height: 24px; margin-top: 2px; }
        .signatures { width: 100%; margin-top: 10px; }
        .signatures td { width: 50%; text-align: center; font-size: 6.6px; padding-top: 20px; }
        .signature-line { border-top: 1px solid #444; width: 220px; margin: 0 auto; padding-top: 2px; font-weight: bold; }
        .footer { margin-top: 6px; border-top: 1px solid #ccd3dd; padding-top: 3px; text-align: center; font-size: 6px; color: #666; }
        .muted { color: #777; }
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
                <div class="title">Certificación de Calificaciones EMG</div>
                <div class="sub">I. Plan de Estudio: {{ $data['program']['label'] }} @if ($data['program']['code']) &middot; Código {{ $data['program']['code'] }} @endif</div>
                <div class="sub">Lugar y Fecha de Expedición: {{ $data['institution']['federal_entity'] }} {{ $data['generated_at_long'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">II. Datos del Plantel o Zona Educativa que Emite la Certificación</div>
    <table class="info">
        <tr>
            <td class="label">Código</td><td>{{ $data['institution']['code'] }}</td>
            <td class="label">Nombre</td><td colspan="3">{{ $data['institution']['name'] }}</td>
        </tr>
        <tr>
            <td class="label">Dirección</td><td colspan="3">{{ $data['institution']['address'] }}</td>
            <td class="label">Teléfono</td><td>{{ $data['institution']['phone'] }}</td>
        </tr>
        <tr>
            <td class="label">Municipio</td><td>{{ $data['institution']['municipality'] }}</td>
            <td class="label">Entidad Federal</td><td>{{ $data['institution']['federal_entity'] }}</td>
            <td class="label">CDCEE</td><td>{{ $data['institution']['cdcee'] }}</td>
        </tr>
    </table>

    <div class="section-title">III. Datos de Identificación del Estudiante</div>
    <table class="info">
        <tr>
            <td class="label">Cédula de Identidad</td><td>{{ $data['student']['document_type'] }} {{ $data['student']['ci'] }}</td>
            <td class="label">Fecha de Nacimiento</td><td>{{ $data['student']['birth_date_label'] }}</td>
        </tr>
        <tr>
            <td class="label">Apellidos</td><td>{{ $data['student']['last_name'] }}</td>
            <td class="label">Nombres</td><td>{{ $data['student']['name'] }}</td>
        </tr>
        <tr>
            <td class="label">Lugar de Nacimiento</td><td>{{ $data['student']['birth_country'] }}</td>
            <td class="label">Estado / Municipio</td><td>{{ $data['student']['birth_state'] }} / {{ $data['student']['birth_municipality'] }}</td>
        </tr>
    </table>

    <div class="section-title">IV. Planteles donde Cursó Estudios</div>
    <table class="planteles">
        <thead><tr><th style="width:22px;">N°</th><th>Nombre del Plantel</th><th style="width:100px;">Localidad</th><th style="width:32px;">E.F.</th></tr></thead>
        <tbody>
            @foreach ($data['institutions'] as $institution)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $institution['name'] }}</td>
                    <td>{{ $institution['locality'] }}</td>
                    <td>{{ $institution['state_code'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">V. Plan de Estudio</div>
    <table class="years">
        @foreach ([[0, 1], [2, 3], [4, null]] as $pair)
            <tr>
                @foreach ($pair as $yearIndex)
                    <td>
                        @if ($yearIndex !== null && isset($data['years'][$yearIndex]))
                            @php $year = $data['years'][$yearIndex]; @endphp
                            <div class="year-title">{{ $year['label'] }} @if (!empty($year['year'])) ({{ $year['year'] }}) @endif</div>
                            <table class="year-table">
                                <thead>
                                    <tr>
                                        <th style="width:45%;">Áreas de Formación</th>
                                        <th style="width:14%;">N°</th>
                                        <th style="width:22%;">Letras</th>
                                        <th style="width:8%;">T-E</th>
                                        <th style="width:11%;">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($year['subjects'] as $subject)
                                        <tr>
                                            <td class="subject">{{ $subject['name'] }}</td>
                                            <td class="center">{{ $subject['score'] !== null ? number_format($subject['score'], 0, ',', '') : '******' }}</td>
                                            <td class="center stars">{{ $subject['letters'] ?? '****************' }}</td>
                                            <td class="center">{{ $subject['score'] !== null ? 'F' : '****' }}</td>
                                            <td class="center">{{ !empty($year['year']) ? '07/'.$year['year'] : '****' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="center muted">Sin calificaciones registradas</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <div style="height:8px;"></div>
    <table class="years">
        <tr>
            <td style="width:50%;">
                <div class="year-title">Orientación y Convivencia</div>
                <table class="year-table">
                    <thead><tr><th>Año</th><th>Literal</th></tr></thead>
                    <tbody>
                        @foreach (['1°', '2°', '3°', '4°', '5°'] as $index => $label)
                            <tr><td class="center">{{ $label }}</td><td class="center">{{ $index < 3 ? 'B' : '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td style="width:50%;">
                <div class="year-title">Participación en Grupos de Creación, Recreación y Producción</div>
                <table class="year-table">
                    <thead><tr><th>Año</th><th>Grupo</th><th>Literal</th></tr></thead>
                    <tbody>
                        @foreach (['PERIODISMO ESCOLAR', 'MANUALIDADES', 'POESÍA Y ALGO MÁS', '—', '—'] as $index => $group)
                            <tr><td class="center">{{ $index + 1 }}°</td><td class="subject">{{ $group }}</td><td class="center">{{ $index < 3 ? 'B' : '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">VI. Observaciones</div>
    <div class="obs"></div>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">Director(a): {{ $data['director']['name'] }}</div>
                <div style="margin-top:2px;">C.I. {{ $data['director']['ci'] }}</div>
            </td>
            <td>
                <div class="signature-line">Sello del Plantel / Zona Educativa</div>
                <div style="margin-top:2px;">Validez Nacional e Internacional</div>
            </td>
        </tr>
    </table>

    <div class="footer">Documento generado el {{ $data['generated_at'] }} &middot; {{ $data['institution']['name'] }}</div>
</body>
</html>
