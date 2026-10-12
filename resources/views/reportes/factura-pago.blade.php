<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura N° {{ $payment['id'] }}</title>
    <style>
        @page { margin: 28px 36px; }
        * { box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1a202c; margin: 0; }

        .header { width: 100%; border-bottom: 3px double #1f4287; padding-bottom: 10px; margin-bottom: 14px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .logo { width: 74px; }
        .logo img { max-width: 70px; max-height: 70px; }
        .school-name { font-size: 18px; font-weight: bold; color: #1f4287; text-transform: uppercase; letter-spacing: .5px; }
        .school-motto { font-size: 10px; color: #4a5568; font-style: italic; margin-top: 2px; }
        .school-data { font-size: 9.5px; color: #718096; margin-top: 4px; line-height: 1.35; }
        .doc-title { text-align: right; }
        .doc-title .factura { font-size: 22px; font-weight: bold; color: #1f4287; letter-spacing: 2px; }
        .doc-title .num { font-size: 12px; color: #c53030; font-weight: bold; margin-top: 4px; }
        .doc-title .date { font-size: 10px; color: #4a5568; margin-top: 6px; }

        .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #fff; background: #1f4287; padding: 5px 8px; margin: 0 0 8px; }

        .rep-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .rep-table td { padding: 3px 6px; vertical-align: top; font-size: 11px; }
        .rep-label { font-weight: bold; width: 96px; color: #4a5568; }

        .concept { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .concept th { background: #1f4287; color: #fff; padding: 7px 8px; border: 1px solid #1f4287; font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; }
        .concept th.monto { text-align: right; width: 130px; }
        .concept td { padding: 5px 8px; border: 1px solid #cbd5e0; vertical-align: top; }
        .concept td.monto { text-align: right; font-family: "DejaVu Sans Mono", Consolas, monospace; }
        .student-row td { background: #edf2f7; font-weight: bold; color: #17223b; font-size: 11px; }
        .student-meta { font-weight: normal; color: #718096; font-size: 9.5px; }
        .subtotal-row td { background: #f7fafc; font-weight: bold; color: #4a5568; }

        .total-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .total-table td { padding: 8px; }
        .total-label { text-align: right; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; width: 78%; }
        .total-amount { text-align: right; font-family: "DejaVu Sans Mono", Consolas, monospace; font-size: 14px; font-weight: bold; color: #fff; background: #17223b; width: 22%; }

        .footer { margin-top: 26px; font-size: 9px; color: #a0aec0; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
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
                        @if (!empty($institution['rif'])) RIF: {{ $institution['rif'] }}<br>@endif
                        @if (!empty($institution['address'])) {{ $institution['address'] }}<br>@endif
                        @if (!empty($institution['phone'])) Telf: {{ $institution['phone'] }} @endif
                        @if (!empty($institution['email'])) · {{ $institution['email'] }}@endif
                    </div>
                </td>
                <td class="doc-title">
                    <div class="factura">FACTURA</div>
                    <div class="num">N° {{ str_pad($payment['id'], 6, '0', STR_PAD_LEFT) }}</div>
                    <div class="date">
                        <strong>Fecha de Transacción:</strong> {{ $payment['transaction_date'] }}
                        @if (!empty($payment['report_date']))
                            <br><strong>Fecha de Reporte:</strong> {{ $payment['report_date'] }}
                        @endif
                        @if (!empty($payment['reference']))
                            <br><strong>Referencia:</strong> {{ $payment['reference'] }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Datos del Representante</div>
    <table class="rep-table">
        <tr>
            <td class="rep-label">Nombre y Apellido:</td>
            <td>{{ $representative['name'] ?: '—' }}</td>
            <td class="rep-label">C.I / RIF:</td>
            <td>{{ $representative['document'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="rep-label">Teléfono:</td>
            <td>{{ $representative['phone'] ?: '—' }}</td>
            <td class="rep-label">Correo:</td>
            <td>{{ $representative['email'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="rep-label">Parentesco:</td>
            <td>{{ $representative['relationship'] ?: '—' }}</td>
            <td class="rep-label">Domicilio:</td>
            <td>{{ $representative['address'] ?: '—' }}</td>
        </tr>
    </table>

    <table class="concept">
        <thead>
            <tr>
                <th>Concepto o Descripción</th>
                <th class="monto">Monto (Bs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($students as $student)
                @php
                    $meta = trim(($student['course'] ?? '').($student['section'] ? ' - '.$student['section'] : ''));
                @endphp
                <tr class="student-row">
                    <td colspan="2">
                        {{ $student['name'] }}
                        @if ($meta !== '')
                            <span class="student-meta">· {{ $meta }}</span>
                        @endif
                    </td>
                </tr>
                @foreach ($student['lines'] as $line)
                    <tr>
                        <td>{{ $line['label'] }}</td>
                        <td class="monto">{{ number_format($line['amount_bs'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @if (count($students) > 1)
                    <tr class="subtotal-row">
                        <td>Subtotal {{ $student['name'] }}</td>
                        <td class="monto">{{ number_format($student['subtotal_bs'], 2, ',', '.') }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <table class="total-table">
        <tr>
            <td class="total-label">Total</td>
            <td class="total-amount">{{ number_format($total_bs, 2, ',', '.') }} Bs</td>
        </tr>
    </table>

    <div class="footer">Documento generado el {{ $generated_at }} · {{ $institution['name'] }}</div>
</body>
</html>
