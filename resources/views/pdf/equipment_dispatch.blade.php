<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Despacho {{ $dispatch['dispatch_number'] }}</title>
    <style>
        @page { margin: 1.5cm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1a202c;
            margin: 0; padding: 0;
            line-height: 1.5;
        }

        /* ── Header ─────────────────────────────────────── */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .header-table td { border: 1px solid #cbd5e0; padding: 10px; vertical-align: middle; }
        .logo-box { width: 25%; text-align: center; }
        .title-box { width: 50%; text-align: center; }
        .code-box  { width: 25%; font-size: 8px; color: #718096; line-height: 1.8; }
        .main-title {
            font-size: 13px; font-weight: 900;
            text-transform: uppercase; color: #2d3748; margin: 0;
        }
        .sub-title { font-size: 9px; font-weight: 600; color: #718096; margin-top: 3px; }

        /* ── Sections ────────────────────────────────────── */
        .section { margin-bottom: 14px; }
        .section-header {
            background: #2d3748; color: #fff;
            padding: 5px 10px; font-weight: 800;
            text-transform: uppercase; font-size: 8px;
            letter-spacing: 0.5px;
        }
        .data-grid { width: 100%; border-collapse: collapse; }
        .data-grid td { border: 1px solid #cbd5e0; padding: 7px 10px; vertical-align: top; }
        .label {
            font-weight: 700; color: #718096; font-size: 7.5px;
            text-transform: uppercase; display: block; margin-bottom: 2px;
        }
        .value { font-weight: 600; font-size: 10px; color: #1a202c; }

        /* ── Status badges ───────────────────────────────── */
        .badge {
            border-radius: 999px; padding: 2px 8px;
            font-weight: 800; font-size: 7px;
            text-transform: uppercase; border: 1px solid transparent;
            display: inline-block;
        }
        .badge-blue   { background:#ebf8ff; color:#2b6cb0; border-color:#bee3f8; }
        .badge-green  { background:#f0fff4; color:#276749; border-color:#c6f6d5; }
        .badge-yellow { background:#fffff0; color:#975a16; border-color:#fefcbf; }
        .badge-red    { background:#fff5f5; color:#9b2c2c; border-color:#fed7d7; }
        .badge-gray   { background:#f7fafc; color:#4a5568; border-color:#e2e8f0; }

        /* ── Route arrow ─────────────────────────────────── */
        .route-table { width: 100%; border-collapse: collapse; }
        .route-table td { border: 1px solid #cbd5e0; padding: 12px 16px; vertical-align: middle; }
        .route-arrow { text-align: center; font-size: 22px; font-weight: 900; color: #e53e3e; width: 60px; border: none !important; letter-spacing: -2px; }
        .route-box-label { font-size: 7.5px; font-weight: 700; color: #718096; text-transform: uppercase; margin-bottom: 3px; }
        .route-box-value { font-size: 12px; font-weight: 900; color: #2d3748; }
        .route-box-sub   { font-size: 8px; color: #a0aec0; margin-top: 2px; }

        /* ── Description box ─────────────────────────────── */
        .desc-box {
            border: 1px solid #cbd5e0; padding: 10px 14px;
            min-height: 50px; font-size: 10px; color: #2d3748;
            background: #f7fafc;
        }
        .desc-empty { color: #a0aec0; font-style: italic; }

        /* ── Footer signatures ───────────────────────────── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 50px; }
        .sig-table td { width: 45%; text-align: center; padding: 0 20px; border: none; vertical-align: bottom; }
        .sig-line { border-top: 1.5px solid #2d3748; margin: 0 auto 6px; width: 80%; }
        .sig-name { font-size: 9px; font-weight: 800; color: #2d3748; text-transform: uppercase; }
        .sig-role { font-size: 7px; color: #718096; margin-top: 2px; }

        .text-center { text-align: center; }
        .bold { font-weight: 800; }
        .mt-1 { margin-top: 6px; }
        .watermark-returned {
            position: fixed; top: 40%; left: 10%;
            font-size: 60px; font-weight: 900;
            color: rgba(0,0,0,0.06); transform: rotate(-30deg);
            text-transform: uppercase; letter-spacing: 4px;
        }
    </style>
</head>
<body>

    @if($dispatch['status'] === 'returned')
    <div class="watermark-returned">RETORNADO</div>
    @endif

    {{-- ── HEADER ──────────────────────────────────────────── --}}
    <table class="header-table">
        <tr>
            <td class="logo-box">
                <h1 style="color:#e53e3e;margin:0;font-size:18px;font-weight:900;">SANTA MONICA</h1>
                <div style="font-size:7px;font-weight:700;letter-spacing:1px;color:#4a5568;">SERVICIOS ALIMENTICIOS</div>
            </td>
            <td class="title-box">
                <p class="main-title">Orden de Despacho de Equipo</p>
                <p class="sub-title">Control de Activos · Gestión de Equipos</p>
            </td>
            <td class="code-box">
                <div><strong>N° ORDEN:</strong> {{ $dispatch['dispatch_number'] }}</div>
                <div><strong>FECHA:</strong> {{ $dispatch['dispatched_at'] }}</div>
                <div><strong>TIPO:</strong> {{ $dispatch['equipable_type'] === 'computer' ? 'TECNOLÓGICO' : 'MENAJE / COCINA' }}</div>
                <div style="margin-top:6px;">
                    @php
                        $statusLabels = ['active' => 'ACTIVO', 'returned' => 'RETORNADO'];
                        $statusColors = ['active' => 'badge-green', 'returned' => 'badge-gray'];
                    @endphp
                    <span class="badge {{ $statusColors[$dispatch['status']] ?? 'badge-gray' }}">
                        {{ $statusLabels[$dispatch['status']] ?? strtoupper($dispatch['status']) }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- ── EQUIPO ───────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-header">Datos del Equipo</div>
        <table class="data-grid">
            <tr>
                <td style="width:40%">
                    <span class="label">Nombre / Descripción</span>
                    <span class="value" style="font-size:11px;">{{ $dispatch['equipment_name'] }}</span>
                </td>
                <td style="width:30%">
                    <span class="label">Marca · Modelo</span>
                    <span class="value">
                        {{ collect([$dispatch['equipment_brand'], $dispatch['equipment_model']])->filter()->join(' · ') ?: '—' }}
                    </span>
                </td>
                <td style="width:15%">
                    <span class="label">Código Interno</span>
                    <span class="value" style="font-family:monospace;">{{ $dispatch['equipment_code'] ?: '—' }}</span>
                </td>
                <td style="width:15%">
                    <span class="label">N° Serie</span>
                    <span class="value" style="font-family:monospace;">{{ $dispatch['equipment_series'] ?: '—' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Estado del Equipo</span>
                    @php
                        $statuses = [0=>'Nuevo',1=>'Bueno',2=>'Regular',3=>'Dañado',4=>'Baja'];
                        $statusBadge = [0=>'badge-blue',1=>'badge-green',2=>'badge-yellow',3=>'badge-red',4=>'badge-gray'];
                        $st = $dispatch['equipment_status'] ?? 0;
                    @endphp
                    <span class="badge {{ $statusBadge[$st] ?? 'badge-gray' }}">{{ $statuses[$st] ?? '—' }}</span>
                </td>
                <td colspan="2">
                    <span class="label">Tipo</span>
                    <span class="value">{{ $dispatch['equipable_type'] === 'computer' ? 'Equipo Tecnológico (IT)' : 'Equipo de Menaje / Cocina' }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── RUTA DE DESPACHO ─────────────────────────────────── --}}
    <div class="section">
        <div class="section-header">Ruta del Despacho</div>
        <table class="route-table">
            <tr>
                <td style="width:40%">
                    <div class="route-box-label">ORIGEN &mdash; ALMAC&Eacute;N</div>
                    <div class="route-box-value">{{ $dispatch['origin_name'] }}</div>
                    <div class="route-box-sub">Almac&eacute;n de despacho</div>
                </td>
                <td class="route-arrow">&raquo;&raquo;</td>
                <td style="width:40%">
                    <div class="route-box-label">DESTINO &mdash; {{ strtoupper($dispatch['destination_label']) }}</div>
                    <div class="route-box-value">{{ $dispatch['destination_name'] }}</div>
                    <div class="route-box-sub">{{ $dispatch['destination_label'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── ENCARGADO ────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-header">Datos del Encargado y Despacho</div>
        <table class="data-grid">
            <tr>
                <td style="width:40%">
                    <span class="label">Encargado / Receptor</span>
                    <span class="value" style="font-size:11px;">{{ $dispatch['staff_name'] ?? 'No especificado' }}</span>
                </td>
                <td style="width:35%">
                    <span class="label">Despachado por</span>
                    <span class="value">{{ $dispatch['dispatched_by'] }}</span>
                </td>
                <td style="width:25%">
                    <span class="label">Fecha y Hora</span>
                    <span class="value">{{ $dispatch['dispatched_at'] }}</span>
                </td>
            </tr>
            @if($dispatch['returned_at'])
            <tr>
                <td colspan="3">
                    <span class="label">Fecha de Retorno al Almacén</span>
                    <span class="value">{{ $dispatch['returned_at'] }}</span>
                </td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ── DESCRIPCIÓN ──────────────────────────────────────── --}}
    <div class="section">
        <div class="section-header">Descripción / Motivo del Despacho</div>
        <div class="desc-box">
            @if($dispatch['description'])
                {{ $dispatch['description'] }}
            @else
                <span class="desc-empty">Sin descripción adicional.</span>
            @endif
        </div>
    </div>

    {{-- ── FIRMAS ───────────────────────────────────────────── --}}
    <table class="sig-table">
        <tr>
            <td>
                <div style="height:55px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ strtoupper($dispatch['dispatched_by']) }}</div>
                <div class="sig-role">Responsable de Despacho</div>
            </td>
            <td></td>
            <td>
                <div style="height:55px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ strtoupper($dispatch['staff_name'] ?? '___________________________') }}</div>
                <div class="sig-role">Firma y Huella del Receptor</div>
            </td>
        </tr>
    </table>

    <div style="margin-top:20px; padding:8px 12px; background:#fef5e7; border-left:3px solid #ed8936; font-size:8px; color:#744210;">
        <strong>NOTA:</strong> Este documento acredita el despacho del equipo detallado. El receptor es responsable del buen uso y conservación del equipo durante el tiempo asignado. Ante cualquier daño, pérdida o novedad, informar de inmediato al área de Gestión de Equipos.
    </div>

</body>
</html>
