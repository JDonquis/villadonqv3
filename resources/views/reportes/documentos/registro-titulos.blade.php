<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja de Registro de Título</title>
    <style>
        @page { margin: 18px 22px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 7.5px; color: #111; margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #17223b; padding-bottom: 6px; margin-bottom: 6px; }
        .header td { vertical-align: middle; }
        .logo { width: 58px; }
        .logo img { max-width: 54px; max-height: 54px; }
        .school-name { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #17223b; }
        .school-data { font-size: 7px; color: #444; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title .title { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #17223b; }
        .doc-title .sub { font-size: 7px; margin-top: 2px; }
        .section-title { background: #17223b; color: #fff; font-size: 6.8px; font-weight: bold; text-transform: uppercase; padding: 2px 5px; margin: 6px 0 3px; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
        .info td { border: 1px solid #b9c1cc; padding: 2px 4px; font-size: 6.8px; }
        .info .label { background: #eef1f6; font-weight: bold; color: #333; text-transform: uppercase; font-size: 6px; }
        .titles { width: 100%; border-collapse: collapse; }
        .titles th { background: #17223b; color: #fff; border: 1px solid #17223b; padding: 2px; font-size: 6.1px; text-transform: uppercase; text-align: center; }
        .titles th.name { text-align: left; }
        .titles td { border: 1px solid #aab3c0; padding: 2px 3px; font-size: 6.8px; text-align: center; }
        .titles td.name { text-align: left; }
        .titles tbody tr:nth-child(even) td { background: #f5f7fa; }
        .total { background: #e8edf5; font-weight: bold; font-size: 7px; }
        .authorities { width: 100%; border-collapse: collapse; margin-top: 3px; }
        .authorities td { border: 1px solid #aab3c0; padding: 3px 4px; font-size: 6.8px; }
        .authorities .label { background: #eef1f6; font-weight: bold; text-transform: uppercase; font-size: 6px; }
        .obs { border: 1px solid #aab3c0; min-height: 26px; margin-top: 2px; }
        .signatures { width: 100%; margin-top: 10px; }
        .signatures td { width: 50%; text-align: center; font-size: 6.6px; padding-top: 20px; }
        .signature-line { border-top: 1px solid #444; width: 230px; margin: 0 auto; padding-top: 2px; font-weight: bold; }
        .footer { margin-top: 6px; border-top: 1px solid #ccd3dd; padding-top: 3px; text-align: center; font-size: 6px; color: #666; }
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
                <div class="title">Hoja de Registro Título</div>
                <div class="sub">Código del Formato: EMG</div>
                <div class="sub">Lugar y Fecha de Expedición: {{ $data['institution']['federal_entity'] }} {{ $data['meta']['generated_at_long'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">I. Tipo de Registro: TÍTULO</div>

    <div class="section-title">II. Datos del Plantel</div>
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
            <td class="label">Zona Educativa</td><td>{{ $data['institution']['cdcee'] }}</td>
        </tr>
    </table>

    <div class="section-title">III. Identificación de la Evaluación: FINAL</div>
    <div class="section-title">IV. Datos del Título que Registra: BACHILLER</div>

    <div class="section-title">V. Cantidad de Estudiantes a los cuales se le otorgó el Título: {{ $data['meta']['total'] }}</div>
    <table class="titles">
        <thead>
            <tr>
                <th style="width:22px;">N°</th>
                <th style="width:74px;">Serial del Título</th>
                <th style="width:68px;">Cédula de Identidad</th>
                <th class="name">Nombres y Apellidos</th>
                <th style="width:32px;">E.F.</th>
                <th style="width:82px;">Municipio</th>
                <th style="width:64px;">Fecha de Nacimiento</th>
                <th style="width:70px;">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data['students'] as $student)
                <tr>
                    <td>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $student['serial'] ?: '*****' }}</td>
                    <td>{{ $student['document_type'] }}-{{ $student['ci'] }}</td>
                    <td class="name">{{ $student['full_name'] }}</td>
                    <td>{{ $student['birth_state_code'] }}</td>
                    <td>{{ $student['birth_municipality'] }}</td>
                    <td>{{ $student['birth_day'] }}/{{ $student['birth_month'] }}/{{ $student['birth_year'] }}</td>
                    <td>{{ $student['observations'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="padding:10px; color:#777;">No hay estudiantes egresados para los criterios seleccionados.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="3">TOTAL DE TÍTULOS EMITIDOS: {{ $data['meta']['total'] }}</td>
                <td colspan="3">AÑO: {{ $data['meta']['year_label'] }} &nbsp;&nbsp; SECCIÓN: {{ $data['meta']['section_name'] ?: 'Todas' }}</td>
                <td colspan="2">{{ $data['meta']['school_lapse_label'] }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">VI. Autoridades Educativas</div>
    <table class="authorities">
        <tr><td class="label" colspan="2">Director(a) del Plantel</td></tr>
        <tr><td>Apellidos y Nombres: {{ $data['authorities']['director_name'] }}</td><td style="width:28%;">C.I.: {{ $data['authorities']['director_ci'] }}</td></tr>
        <tr><td class="label" colspan="2">Coordinador(a) de Control de Estudios</td></tr>
        <tr><td>Apellidos y Nombres: {{ $data['authorities']['coordinator_name'] }}</td><td>C.I.: {{ $data['authorities']['coordinator_ci'] }}</td></tr>
        <tr><td class="label" colspan="2">Funcionario designado por el Ministerio del Poder Popular para la Educación</td></tr>
        <tr><td>Apellidos y Nombres: {{ $data['authorities']['official_name'] }}</td><td>C.I.: {{ $data['authorities']['official_ci'] }}</td></tr>
    </table>

    <div class="section-title">VII. Observaciones</div>
    <div class="obs"></div>

    <table class="signatures">
        <tr>
            <td><div class="signature-line">Director(a): {{ $data['authorities']['director_name'] }}</div><div style="margin-top:2px;">C.I. {{ $data['authorities']['director_ci'] }}</div></td>
            <td><div class="signature-line">Funcionario(a) Receptor / Sello</div><div style="margin-top:2px;">Fecha de Recepción: ____/____/______</div></td>
        </tr>
    </table>

    <div class="footer">Documento generado el {{ $data['meta']['generated_at'] }} &middot; {{ $data['institution']['name'] }}</div>
</body>
</html>
