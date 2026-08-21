<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Requerimiento x Producto {{ $program->id }}</title>
    <style>
        @page { margin: 1.2cm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #1a202c;
            margin: 0; padding: 0;
            line-height: 1.4;
        }

        .masthead-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .masthead-table td { vertical-align: middle; padding: 0; }
        .logo-main { font-size: 16px; font-weight: 900; color: #c05621; margin: 0; }
        .logo-sub { font-size: 6.5px; font-weight: 700; letter-spacing: 1px; color: #4a5568; }
        .requirement-title {
            text-align: right; font-size: 21px; font-weight: 700; font-style: italic;
            color: #1a2456; font-family: Georgia, 'Times New Roman', serif;
        }

        .unidad-base-table { width: 100%; border-collapse: collapse; background: #1a202c; margin-bottom: 6px; }
        .unidad-base-table td { padding: 7px 10px; color: #fff; vertical-align: middle; }
        .ub-label { font-size: 10px; font-weight: 500; color: #cbd5e0; white-space: nowrap; width: 1%; }
        .ub-value { font-size: 13px; font-weight: 800; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { border-bottom: 1px solid #cbd5e0; padding-bottom: 6px; vertical-align: bottom; font-size: 9.5px; }
        .meta-item { margin-right: 18px; }
        .meta-label { text-decoration: underline; font-weight: 700; color: #2d3748; }
        .orden-box { text-align: right; }
        .orden-label { text-decoration: underline; font-weight: 700; color: #2d3748; }
        .orden-value { font-size: 16px; font-weight: 900; color: #1a202c; margin-left: 8px; }

        /* ── Category / ingredient blocks — flow one after another instead of
           forcing a page each, so a short category doesn't waste the rest of a
           page; only the header itself is kept from being orphaned alone. ───── */
        .category-block { margin-bottom: 16px; }
        .category-header {
            background: #94a3b8; color: #1a202c;
            padding: 6px 10px; font-weight: 800; font-size: 12px;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;
            page-break-after: avoid;
        }

        .ingredient-block { margin-bottom: 12px; }
        .ingredient-header {
            background: #2b6cb0; color: #fff;
            padding: 5px 10px; font-weight: 800; font-size: 10px;
            text-transform: uppercase; letter-spacing: 0.5px;
            page-break-after: avoid;
        }

        table.usage { width: 100%; border-collapse: collapse; }
        table.usage th {
            background: #f1f5f9; border: 1px solid #cbd5e0;
            padding: 4px 8px; font-size: 7.5px; font-weight: 800;
            text-transform: uppercase; color: #4a5568; text-align: left;
        }
        table.usage td { border: 1px solid #cbd5e0; padding: 4px 8px; font-size: 8.5px; }
        .text-right { text-align: right; }
        .mono { font-family: 'Courier New', monospace; }
        .total-row td {
            background: #edf2f7; font-weight: 800; text-align: right;
            border: 1px solid #cbd5e0; padding: 4px 8px;
        }
    </style>
</head>
<body>

    <table class="masthead-table">
        <tr>
            <td style="width:35%">
                <div class="logo-main">SANTA MONICA</div>
                <div class="logo-sub">SERVICIOS ALIMENTICIOS</div>
            </td>
            <td class="requirement-title" style="width:65%">Requerimiento x Producto:</td>
        </tr>
    </table>

    <table class="unidad-base-table">
        <tr>
            <td class="ub-label">Unidad:</td>
            <td class="ub-value">{{ strtoupper($program->cafe->unit->name ?? '—') }}</td>
            <td class="ub-label">Base:</td>
            <td class="ub-value">{{ strtoupper($baseChain ?: '—') }}</td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="text-align:left">
                <span class="meta-item"><span class="meta-label">Período:</span> {{ \Carbon\Carbon::parse($program->start_date)->year }}</span>
                <span class="meta-item"><span class="meta-label">Mes:</span> {{ ucfirst(\Carbon\Carbon::parse($program->start_date)->locale('es')->translatedFormat('F')) }}</span>
                <span class="meta-item"><span class="meta-label">Semana:</span> {{ \Carbon\Carbon::parse($program->start_date)->isoWeek() }}</span>
                <span class="meta-item"><span class="meta-label">Nivel:</span> {{ $level->name }}</span>
            </td>
            <td class="orden-box"><span class="orden-label">Orden:</span><span class="orden-value">{{ $program->id }}</span></td>
        </tr>
    </table>

    @php
        $mealAbbr = ['Desayuno' => 'DES.', 'Almuerzo' => 'ALM.', 'Cena' => 'CEN.', 'Refrigerio' => 'REF.'];
    @endphp

    @forelse($categories as $category)
        <div class="category-block">
            <div class="category-header">{{ $category['name'] }}</div>

            @foreach($category['ingredients'] as $ingredient)
                <div class="ingredient-block">
                    <div class="ingredient-header">{{ $ingredient['name'] }}</div>
                    <table class="usage">
                        <thead>
                            <tr>
                                <th style="width:12%">Fecha</th>
                                <th style="width:8%">Cat</th>
                                <th style="width:35%">Nombre del Plato</th>
                                <th style="width:13%" class="text-right">Gramaje x Ración</th>
                                <th style="width:10%" class="text-right">Raciones</th>
                                <th style="width:11%" class="text-right">Total: Requerido</th>
                                <th style="width:11%" class="text-right">Total Equivalente</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ingredient['rows'] as $row)
                                <tr>
                                    <td class="mono">{{ \Carbon\Carbon::parse($row['date'])->format('Y-m-d') }}</td>
                                    <td>{{ $mealAbbr[$row['meal_type']] ?? strtoupper(substr($row['meal_type'], 0, 3)) . '.' }}</td>
                                    <td>{{ $row['dish_id'] }}|{{ $row['dish_name'] }}</td>
                                    <td class="text-right mono">{{ number_format($row['qty_per_ration'], 0) }} gr.</td>
                                    <td class="text-right mono">{{ $row['portions'] }}</td>
                                    <td class="text-right mono">{{ number_format($row['total_required'], 2) }} gr.</td>
                                    <td class="text-right mono">{{ number_format($row['total_kg'], 2) }} KILOGRAMO</td>
                                </tr>
                            @endforeach
                            <tr class="total-row">
                                <td colspan="6">Total:</td>
                                <td>{{ number_format($ingredient['total_kg'], 2) }} KILOGRAMO</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @empty
        <div style="border:1px solid #fbd38d;background:#fffaf0;padding:16px 20px;margin-top:10px;">
            <p style="margin:0 0 8px;font-weight:800;color:#9c4221;font-size:11px;">No se generó ningún requerimiento.</p>
            @if(!$hasMatchingRecipes)
                <p style="margin:0;color:#744210;font-size:9.5px;">
                    Ninguno de los platos programados tiene una receta (quebrado) configurada para el nivel "{{ $level->name }}".
                    Verifique en el módulo Alimentos que cada plato tenga su receta para ese nivel, o elija otro nivel al generar este PDF.
                </p>
            @elseif(!$hasAnyPortions)
                <p style="margin:0;color:#744210;font-size:9.5px;">
                    Los platos sí tienen receta para el nivel "{{ $level->name }}", pero el <strong>número de raciones (comensales)</strong>
                    registrado es 0 para todos los días y servicios de esta semana. Vaya a la pestaña "Planificar" de esta programación,
                    complete "Número de Comensales" para cada día/servicio y guarde de nuevo antes de generar este reporte.
                </p>
            @else
                <p style="margin:0;color:#744210;font-size:9.5px;">
                    Los platos con receta y raciones registradas no tienen insumos con gramaje mayor a cero en su quebrado para el nivel "{{ $level->name }}".
                </p>
            @endif
        </div>
    @endforelse

</body>
</html>
