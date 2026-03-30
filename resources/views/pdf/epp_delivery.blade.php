<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato de Entrega de EPP - {{ $staff->name }}</title>
    <style>
        @page {
            margin: 1.5cm;
        }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 10px; 
            color: #1a202c; 
            margin: 0; 
            padding: 0; 
            line-height: 1.4; 
        }
        .header { 
            width: 100%; 
            border: 1px solid #e2e8f0;
            margin-bottom: 20px; 
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: 1px solid #cbd5e0; padding: 10px; vertical-align: middle; }
        
        .logo-box { width: 25%; text-align: center; }
        .title-box { width: 50%; text-align: center; }
        .code-box { width: 25%; font-size: 8px; color: #718096; line-height: 1.6; }
        
        .main-title { 
            font-size: 14px; 
            font-weight: 800; 
            text-transform: uppercase; 
            color: #2d3748;
            margin: 0;
        }
        
        .section { margin-bottom: 15px; }
        .section-header { 
            background: #f7fafc; 
            padding: 4px 10px; 
            font-weight: 800; 
            text-transform: uppercase; 
            border: 1px solid #cbd5e0; 
            border-bottom: none;
            font-size: 9px;
            color: #4a5568;
        }
        
        .data-grid { width: 100%; border-collapse: collapse; }
        .data-grid td { border: 1px solid #cbd5e0; padding: 6px 10px; vertical-align: top; }
        .label { 
            font-weight: 700; 
            color: #718096; 
            font-size: 8px; 
            text-transform: uppercase; 
            display: block; 
            margin-bottom: 2px; 
        }
        .value { font-weight: 600; font-size: 10px; color: #1a202c; }
        
        table.items-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table.items-table th { 
            background: #2d3748; 
            color: white; 
            border: 1px solid #2d3748; 
            padding: 8px; 
            text-align: left; 
            text-transform: uppercase; 
            font-size: 8px;
            font-weight: 800;
        }
        table.items-table td { border: 1px solid #cbd5e0; padding: 8px; vertical-align: middle; }
        
        .footer { margin-top: 40px; width: 100%; }
        .signature-container { width: 100%; margin-top: 30px; }
        .signature-box { 
            width: 40%; 
            display: inline-block;
            text-align: center; 
        }
        .signature-line {
            width: 80%;
            margin: 0 auto 5px;
            border-top: 1px solid #2d3748;
        }
        .signature-text { font-size: 9px; font-weight: 700; color: #4a5568; }
        
        .legend { 
            font-size: 8px; 
            color: #718096; 
            margin-top: 20px; 
            padding: 10px; 
            background: #fdf2f2;
            border-left: 3px solid #f56565;
        }
        
        .condition-badge { 
            border-radius: 999px; 
            padding: 1px 8px; 
            font-weight: 800; 
            font-size: 7px; 
            text-transform: uppercase;
            border: 1px solid transparent;
        }
        .nuevo { background: #f0fff4; color: #276749; border-color: #c6f6d5; }
        .almacen { background: #fffaf0; color: #9c4221; border-color: #feebc8; }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: 800; }
        
        .mt-4 { margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-box">
                    <h1 style="color: #e53e3e; margin: 0; font-size: 18px; font-weight: 900;">SANTA MONICA</h1>
                    <div style="font-size: 7px; font-weight: 700; letter-spacing: 1px; color: #4a5568;">SERVICIOS ALIMENTICIOS</div>
                </td>
                <td class="title-box">
                    <h2 class="main-title">FORMATO DE ENTREGA DE EPP</h2>
                    <div style="font-size: 9px; font-weight: 600; margin-top: 4px; color: #718096;">SISTEMA DE GESTIÓN DE SEGURIDAD Y SALUD</div>
                </td>
                <td class="code-box">
                    <div><strong>CÓDIGO:</strong> SM-SS-RE-29</div>
                    <div><strong>FECHA:</strong> 21/01/26</div>
                    <div><strong>VERSIÓN:</strong> 04</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-header">DATOS DEL EMPLEADOR</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%"><span class="label">Razón Social</span><span class="value">Empresa de Servicios Santa Monica SR.</span></td>
                <td style="width: 50%"><span class="label">RUC</span><span class="value">20319127845</span></td>
            </tr>
            <tr>
                <td><span class="label">Actividad Económica</span><span class="value">Servicio de alimentación colectiva</span></td>
                <td><span class="label">Domicilio Fiscal</span><span class="value">Cal Ric Marañon Mza. I Lote. 9, Urb. Valle de la Molina, Lima</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-header">DATOS DEL TRABAJADOR</div>
        <table class="data-grid">
            <tr>
                <td style="width: 40%"><span class="label">Apellidos y Nombres</span><span class="value" style="font-size: 11px;">{{ $staff->name }}</span></td>
                <td style="width: 20%"><span class="label">DNI</span><span class="value">{{ $staff->dni ?? '----------' }}</span></td>
                <td style="width: 40%"><span class="label">Cargo / Puesto</span><span class="value">{{ $staff->role?->name ?? 'SIN CARGO' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Unidad Operativa (Mina)</span><span class="value">{{ $location?->unit?->mine?->name ?? 'PROYECTO GENERAL' }}</span></td>
                <td><span class="label">Sede / Comedor</span><span class="value">{{ $location?->name ?? 'ALMACÉN CENTRAL' }}</span></td>
                <td><span class="label">Fecha de Entrega</span><span class="value">{{ $date }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-header">DETALLE DE EQUIPOS Y ROPA ENTREGADA</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">ITEM</th>
                    <th style="width: 100px;">MOTIVO</th>
                    <th>DESCRIPCIÓN DEL EQUIPO DE PROTECCIÓN / INDUMENTARIA</th>
                    <th style="width: 60px;" class="text-center">TALLA</th>
                    <th style="width: 60px;" class="text-center">CANTIDAD</th>
                    <th style="width: 80px;" class="text-center">CONDICIÓN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                <tr>
                    <td class="text-center bold">{{ $index + 1 }}</td>
                    <td class="bold" style="color: #4a5568;">{{ strtoupper($history->reason) }}</td>
                    <td>
                        <div class="value">{{ $item['epp_name'] ?? 'Equipo/Indumentaria' }}</div>
                        <div style="font-size: 7px; color: #a0aec0; margin-top: 2px;">{{ $item['color_name'] ?? '' }}</div>
                    </td>
                    <td class="text-center bold" style="font-size: 12px;">{{ $item['size'] ?? ($item['clothing_size'] ?? '-') }}</td>
                    <td class="text-center bold" style="font-size: 12px;">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="text-center">
                        @php $cond = strtolower($item['condition'] ?? 'nuevo'); @endphp
                        <span class="condition-badge {{ $cond === 'nuevo' ? 'nuevo' : 'almacen' }}">
                            {{ strtoupper($cond) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="legend">
        <strong>NOTA IMPORTANTE:</strong> Declaro haber recibido por parte de la empresa, de forma gratuita y en perfecto estado, los implementos arriba detallados. Me comprometo a utilizarlos correctamente durante mi jornada laboral y velar por su buen estado de conservación. En caso de pérdida o deterioro por negligencia, acepto las responsabilidades administrativas correspondientes.
    </div>

    <div class="footer">
        <div class="signature-container">
            <div class="signature-box" style="margin-left: 5%;">
                <div style="height: 60px;"></div>
                <div class="signature-line"></div>
                <div class="signature-text">{{ strtoupper($staff->name) }}</div>
                <div style="font-size: 7px; color: #718096; margin-top: 2px;">FIRMA Y HUELLA DEL TRABAJADOR</div>
            </div>
            
            <div class="signature-box" style="float: right; margin-right: 5%;">
                <div style="height: 60px;"></div>
                <div class="signature-line"></div>
                <div class="signature-text">{{ strtoupper($user->name ?? 'SUPERVISOR') }}</div>
                <div style="font-size: 7px; color: #718096; margin-top: 2px;">FIRMA DEL RESPONSABLE ({{ $user->role_name ?? 'CONTROL' }})</div>
            </div>
        </div>
    </div>
</body>
</html>
