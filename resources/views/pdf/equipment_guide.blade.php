<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Guía de Remisión {{ $guide_number }}</title>
    <style>
        @page { margin: 1.5cm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1a202c;
            margin: 0; padding: 0;
            line-height: 1.5;
        }

        /* ── Header ──────────────────────────────────── */
        .header-outer { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .header-outer td { vertical-align: middle; }
        .logo-cell { width: 30%; padding-right: 12px; }
        .logo-box {
            border: 1px solid #cbd5e0; padding: 10px 14px; text-align: center;
        }
        .logo-company { font-size: 14px; font-weight: 900; color: #1a202c; text-transform: uppercase; letter-spacing: 0.5px; }
        .logo-sub { font-size: 7.5px; color: #718096; margin-top: 2px; }
        .guide-box {
            border: 2px solid #1a202c; padding: 10px 16px; text-align: center; width: 35%;
        }
        .guide-box-title { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; }
        .guide-box-number { font-size: 13px; font-weight: 900; margin-top: 4px; letter-spacing: 1px; }
        .meta-cell { width: 35%; padding-left: 12px; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .meta-label { font-size: 8px; color: #718096; font-weight: 700; text-transform: uppercase; }
        .meta-value { font-size: 8.5px; font-weight: 600; color: #1a202c; }

        /* ── Section headers ─────────────────────────── */
        .section { margin-bottom: 10px; }
        .section-title {
            font-size: 8px; font-weight: 900; text-transform: uppercase;
            letter-spacing: 0.6px; color: #4a5568;
            border-bottom: 1.5px solid #2d3748; padding-bottom: 3px; margin-bottom: 6px;
        }

        /* ── Two-column info grid ────────────────────── */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-table td { border: 1px solid #e2e8f0; padding: 6px 10px; vertical-align: top; }
        .info-label { font-size: 7.5px; font-weight: 700; color: #718096; text-transform: uppercase; display: block; margin-bottom: 1px; }
        .info-value { font-size: 9.5px; font-weight: 600; color: #1a202c; }

        /* ── Route row ───────────────────────────────── */
        .route-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .route-table td { border: 1px solid #e2e8f0; padding: 8px 12px; vertical-align: middle; }
        .route-label { font-size: 7.5px; font-weight: 700; color: #718096; text-transform: uppercase; margin-bottom: 2px; }
        .route-value { font-size: 11px; font-weight: 900; color: #1a202c; }
        .route-sub   { font-size: 7.5px; color: #a0aec0; margin-top: 1px; }
        .route-arrow { text-align: center; width: 48px; font-size: 20px; font-weight: 900; color: #e53e3e; border-left: none !important; border-right: none !important; }

        /* ── Items table ─────────────────────────────── */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .items-table th {
            background: #2d3748; color: #fff;
            font-size: 7.5px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.4px; padding: 6px 8px; text-align: center;
            border: 1px solid #2d3748;
        }
        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            font-size: 9px;
            vertical-align: middle;
        }
        .items-table tr:nth-child(even) td { background: #f7fafc; }
        .td-center { text-align: center; }
        .td-desc { font-weight: 600; color: #1a202c; }
        .td-meta { font-size: 7.5px; color: #718096; margin-top: 1px; }
        .badge-type {
            display: inline-block; padding: 1px 6px; border-radius: 999px;
            font-size: 7px; font-weight: 800; text-transform: uppercase;
        }
        .badge-it     { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .badge-kitchen { background: #fffbeb; color: #92400e; border: 1px solid #fef3c7; }

        /* ── Transport data ──────────────────────────── */
        .transport-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .transport-table td { border: 1px solid #e2e8f0; padding: 6px 10px; font-size: 9px; }

        /* ── Footer ──────────────────────────────────── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
        .sig-table td { width: 45%; text-align: center; padding: 0 20px; border: none; }
        .sig-line { border-top: 1.5px solid #2d3748; margin: 0 auto 5px; width: 80%; }
        .sig-name { font-size: 8.5px; font-weight: 800; color: #1a202c; text-transform: uppercase; }
        .sig-role { font-size: 7px; color: #718096; margin-top: 1px; }

        .note-box {
            margin-top: 14px; padding: 7px 12px;
            background: #fef5e7; border-left: 3px solid #ed8936;
            font-size: 7.5px; color: #744210;
        }

        .totals-row td { background: #edf2f7 !important; font-weight: 800; }
    </style>
</head>
<body>

    {{-- ── HEADER ────────────────────────────────────────── --}}
    <table class="header-outer">
        <tr>
            {{-- Logo / Empresa --}}
            <td class="logo-cell">
                <div class="logo-box">
                    <div class="logo-company">ESSAM</div>
                    <div class="logo-sub">Gestión de Equipos y Logística</div>
                </div>
            </td>

            {{-- Guide number box --}}
            <td style="text-align:center; padding: 0 8px;">
                <div class="guide-box" style="margin: 0 auto; display: inline-block; min-width: 200px;">
                    <div class="guide-box-title">Guía de Remisión Interna</div>
                    <div style="font-size:7.5px; color:#718096; margin: 2px 0;">Control de Activos · Despacho de Equipos</div>
                    <div class="guide-box-number">N° {{ $guide_number }}</div>
                </div>
            </td>

            {{-- Meta --}}
            <td class="meta-cell">
                <table style="width:100%; border-collapse: collapse;">
                    <tr>
                        <td style="border: 1px solid #e2e8f0; padding: 4px 8px;">
                            <span class="info-label">Fecha y Hora de Emisión</span>
                            <span class="info-value">{{ $dispatched_at }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #e2e8f0; padding: 4px 8px;">
                            <span class="info-label">Emitido por</span>
                            <span class="info-value">{{ $dispatched_by }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #e2e8f0; padding: 4px 8px;">
                            <span class="info-label">Total de ítems</span>
                            <span class="info-value">{{ count($items) }} equipo(s)</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── RUTA ──────────────────────────────────────────── --}}
    <div class="section-title">Ruta de Traslado</div>
    <table class="route-table">
        <tr>
            <td style="width:44%">
                <div class="route-label">Punto de Partida — Almacén Origen</div>
                <div class="route-value">{{ $origin_name }}</div>
                <div class="route-sub">Almacén / Sede de despacho</div>
            </td>
            <td class="route-arrow">&raquo;&raquo;</td>
            <td style="width:44%">
                <div class="route-label">Punto de Llegada — {{ strtoupper($destination_label) }}</div>
                <div class="route-value">{{ $destination_name }}</div>
                <div class="route-sub">{{ $destination_label }}</div>
            </td>
        </tr>
    </table>

    {{-- ── ENCARGADO ─────────────────────────────────────── --}}
    <div class="section-title">Datos del Receptor y Motivo</div>
    <table class="info-table">
        <tr>
            <td style="width:50%">
                <span class="info-label">Encargado de Recepción</span>
                <span class="info-value">{{ $staff_name ?? 'No especificado' }}</span>
            </td>
            <td style="width:50%">
                <span class="info-label">Motivo del Traslado</span>
                <span class="info-value">Traslado de activos entre establecimientos</span>
            </td>
        </tr>
        @if($description)
        <tr>
            <td colspan="2">
                <span class="info-label">Observaciones</span>
                <span class="info-value" style="font-style:italic;">{{ $description }}</span>
            </td>
        </tr>
        @endif
    </table>

    {{-- ── BIENES A TRANSPORTAR ──────────────────────────── --}}
    <div class="section-title">Bienes por Transportar</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">N°</th>
                <th style="width:8%">Tipo</th>
                <th style="width:38%">Descripción Detallada</th>
                <th style="width:12%">Código Interno</th>
                <th style="width:16%">N° Serie</th>
                <th style="width:10%">Unidad</th>
                <th style="width:8%">Cant.</th>
                <th style="width:4%">N° Desp.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td class="td-center">{{ $i + 1 }}</td>
                <td class="td-center">
                    <span class="badge-type {{ $item['equipable_type'] === 'computer' ? 'badge-it' : 'badge-kitchen' }}">
                        {{ $item['equipable_type'] === 'computer' ? 'IT' : 'Cocina' }}
                    </span>
                </td>
                <td>
                    <div class="td-desc">
                        @php
                            $desc = strtoupper($item['equipment_name']);
                            if ($item['equipment_brand']) $desc .= ' MARCA ' . strtoupper($item['equipment_brand']);
                            if ($item['equipment_model']) $desc .= ' MODELO ' . strtoupper($item['equipment_model']);
                        @endphp
                        {{ $desc }}
                    </div>
                    @if($item['equipment_code'])
                    <div class="td-meta">Cód. interno: {{ $item['equipment_code'] }}</div>
                    @endif
                </td>
                <td class="td-center" style="font-family: monospace; font-size:8px;">
                    {{ $item['equipment_code'] ?: '—' }}
                </td>
                <td class="td-center" style="font-family: monospace; font-size:8px;">
                    {{ $item['equipment_series'] ?: '—' }}
                </td>
                <td class="td-center">UNIDAD (NIU)</td>
                <td class="td-center" style="font-weight: 800;">{{ number_format($item['quantity'], 2) }}</td>
                <td class="td-center" style="font-size: 7.5px; font-family: monospace;">{{ $item['dispatch_number'] }}</td>
            </tr>
            @endforeach
            {{-- Totals row --}}
            <tr class="totals-row">
                <td colspan="6" style="text-align:right; font-size:8.5px; padding-right:10px;">TOTAL DE UNIDADES:</td>
                <td class="td-center" style="font-size:10px;">{{ number_format($items->sum('quantity'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- ── DATOS DEL TRASLADO ─────────────────────────────── --}}
    <div class="section-title">Datos del Traslado</div>
    <table class="transport-table">
        <tr>
            <td style="width:33%">
                <span class="info-label">Modalidad de Traslado</span>
                <span class="info-value">Privado</span>
            </td>
            <td style="width:33%">
                <span class="info-label">Indicador de Transbordo</span>
                <span class="info-value">NO</span>
            </td>
            <td style="width:33%">
                <span class="info-label">Unidad de Medida del Peso</span>
                <span class="info-value">KGM</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="info-label">Fecha de Inicio de Traslado</span>
                <span class="info-value">{{ $dispatched_at }}</span>
            </td>
            <td>
                <span class="info-label">Referencia Interna</span>
                <span class="info-value" style="font-family:monospace;">{{ $guide_number }}</span>
            </td>
            <td>
                <span class="info-label">Total de Ítems Despachados</span>
                <span class="info-value">{{ count($items) }}</span>
            </td>
        </tr>
    </table>

    {{-- ── FIRMAS ─────────────────────────────────────────── --}}
    <table class="sig-table">
        <tr>
            <td>
                <div style="height:55px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ strtoupper($dispatched_by) }}</div>
                <div class="sig-role">Responsable de Despacho</div>
            </td>
            <td style="width:10%;"></td>
            <td>
                <div style="height:55px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ strtoupper($staff_name ?? '___________________________') }}</div>
                <div class="sig-role">Firma y Huella del Receptor</div>
            </td>
        </tr>
    </table>

    <div class="note-box">
        <strong>NOTA:</strong> La presente Guía de Remisión Interna N° {{ $guide_number }} acredita el traslado de los bienes detallados.
        El receptor es responsable de verificar la cantidad y estado de los equipos al momento de la recepción y deberá firmar en señal de conformidad.
        Ante cualquier discrepancia, pérdida o daño, informar de inmediato al área de Gestión de Equipos.
    </div>

</body>
</html>
