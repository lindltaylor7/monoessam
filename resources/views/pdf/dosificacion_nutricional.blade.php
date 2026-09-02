<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dosificación Nutricional</title>
    <style>
        @page { margin: 1cm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8px;
            color: #1a202c;
            margin: 0; padding: 0;
            line-height: 1.35;
        }

        /* Un bloque por fecha + servicio, con su propia cabecera. Fluyen uno tras otro; solo
           se evita que la cabecera quede huérfana al final de una página. */
        .page-block { margin-bottom: 18px; border-bottom: 2px solid #cbd5e0; padding-bottom: 12px; }
        .page-block:last-child { border-bottom: none; }
        .masthead-block { page-break-inside: avoid; }

        .masthead-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .masthead-table td { vertical-align: middle; padding: 0; }
        .logo-main { font-size: 15px; font-weight: 900; color: #c05621; margin: 0; }
        .logo-sub { font-size: 6px; font-weight: 700; letter-spacing: 1px; color: #4a5568; }
        .report-title {
            text-align: right; font-size: 19px; font-weight: 700; font-style: italic;
            color: #1a2456; font-family: Georgia, 'Times New Roman', serif;
        }
        .report-meal { text-align: right; font-size: 13px; font-weight: 800; color: #1a202c; }

        .unidad-base-table { width: 100%; border-collapse: collapse; background: #1a202c; margin-bottom: 6px; }
        .unidad-base-table td { padding: 6px 9px; color: #fff; vertical-align: middle; }
        .ub-label { font-size: 9px; font-weight: 500; color: #cbd5e0; white-space: nowrap; width: 1%; }
        .ub-value { font-size: 12px; font-weight: 800; }

        .meta-line { border-bottom: 1px solid #cbd5e0; padding-bottom: 5px; margin-bottom: 7px; font-size: 9px; }
        .meta-item { margin-right: 16px; }
        .meta-label { text-decoration: underline; font-weight: 700; color: #2d3748; }

        .day-header {
            background: #1a202c; color: #fff; padding: 5px 9px;
            font-weight: 800; font-size: 10px; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 7px;
        }

        .dish-section { margin-bottom: 9px; page-break-inside: avoid; }
        .dish-header-table { width: 100%; border-collapse: collapse; }
        .dish-header-table td {
            border: 1px solid #94a3b8; background: #e2e8f0;
            padding: 4px 7px; font-size: 8.5px; font-weight: 800; color: #2d3748;
            vertical-align: middle;
        }
        .dh-num { width: 3%; text-align: center; }
        .dh-category { width: 27%; }
        .dh-name { width: 55%; }
        .dh-portions { width: 15%; text-align: right; color: #c05621; }

        table.ingredients { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.ingredients th {
            background: #f1f5f9; border: 1px solid #cbd5e0;
            padding: 3px 2px; font-size: 6px; font-weight: 800;
            text-transform: uppercase; color: #4a5568; text-align: right;
        }
        table.ingredients th.c-cod, table.ingredients th.c-prod { text-align: left; }
        table.ingredients td {
            border: 1px solid #cbd5e0; padding: 2px 3px; font-size: 6.5px; text-align: right;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        table.ingredients td.c-cod { text-align: left; font-family: 'Courier New', monospace; }
        table.ingredients td.c-prod { text-align: left; white-space: normal; }
        tr.totals td {
            background: #f8fafc; font-weight: 800; border-top: 2px solid #64748b;
        }
        .empty-note {
            border: 1px solid #cbd5e0; border-top: none; padding: 6px 9px;
            font-size: 8px; color: #a0aec0; font-style: italic;
        }
    </style>
</head>
<body>

    @forelse($pages as $page)
        <div class="page-block">
            <div class="masthead-block">
                <table class="masthead-table">
                    <tr>
                        <td style="width:38%">
                            <div class="logo-main">SANTA MONICA</div>
                            <div class="logo-sub">SERVICIOS ALIMENTICIOS</div>
                        </td>
                        <td style="width:62%">
                            <div class="report-title">Dosificacion Nutricional</div>
                            <div class="report-meal">{{ ucfirst(strtolower($page['meal_type'])) }}</div>
                        </td>
                    </tr>
                </table>

                <table class="unidad-base-table">
                    <tr>
                        <td class="ub-label">Unidad:</td>
                        <td class="ub-value">{{ $page['unit'] ?: '—' }}</td>
                        <td class="ub-label">Base:</td>
                        <td class="ub-value">{{ strtoupper($page['base'] ?: '—') }}</td>
                    </tr>
                </table>

                <div class="meta-line">
                    <span class="meta-item"><span class="meta-label">Período:</span> {{ \Carbon\Carbon::parse($page['date'])->year }}</span>
                    <span class="meta-item"><span class="meta-label">Mes:</span> {{ strtoupper(\Carbon\Carbon::parse($page['date'])->locale('es')->translatedFormat('M')) }}</span>
                    <span class="meta-item"><span class="meta-label">Semana:</span> {{ \Carbon\Carbon::parse($page['date'])->isoWeek() }}</span>
                    <span class="meta-item"><span class="meta-label">Programación:</span> {{ $page['program_id'] }}</span>
                    <span class="meta-item"><span class="meta-label">Nº Orden:</span> {{ $page['program_id'] }}</span>
                    <span class="meta-item"><span class="meta-label">Nivel:</span> {{ $level->name }}</span>
                </div>

                <div class="day-header">Día: {{ \Carbon\Carbon::parse($page['date'])->format('d/m/Y') }}</div>
            </div>

            @foreach($page['dishes'] as $dish)
                <div class="dish-section">
                    <table class="dish-header-table">
                        <tr>
                            <td class="dh-num">{{ $dish['index'] }}</td>
                            <td class="dh-category">{{ strtoupper($dish['category']) }} [{{ str_pad($dish['category_index'], 2, '0', STR_PAD_LEFT) }}]</td>
                            <td class="dh-name">{{ $dish['dish_code'] }} &nbsp; {{ $dish['dish_name'] }}</td>
                            <td class="dh-portions">Nº Rac: {{ $dish['portions'] }}@if(($dish['percentage'] ?? 100) < 100) <span style="color:#a0aec0;">({{ rtrim(rtrim(number_format($dish['percentage'], 2), '0'), '.') }}%)</span>@endif</td>
                        </tr>
                    </table>

                    @if($dish['has_recipe'] && $dish['ingredients']->count() > 0)
                        <table class="ingredients">
                            <thead>
                                <tr>
                                    <th class="c-cod" style="width:5%">Cod</th>
                                    <th class="c-prod" style="width:16%">Producto</th>
                                    <th style="width:6%">Gramaje x Rac</th>
                                    <th style="width:4%">IC</th>
                                    @foreach($nutrients as $label => $column)
                                        <th>{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dish['ingredients'] as $ingredient)
                                    <tr>
                                        <td class="c-cod">{{ $ingredient['code'] }}</td>
                                        <td class="c-prod">{{ $ingredient['name'] }}</td>
                                        <td>{{ number_format($ingredient['gramaje'], 2) }}</td>
                                        <td>{{ $ingredient['ic'] }}</td>
                                        @foreach($nutrients as $label => $column)
                                            <td>{{ number_format($ingredient['values'][$label], 2) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr class="totals">
                                    <td class="c-cod"></td>
                                    <td class="c-prod">TOTAL</td>
                                    <td></td>
                                    <td></td>
                                    @foreach($nutrients as $label => $column)
                                        <td>{{ number_format($dish['totals'][$label], 2) }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    @elseif($dish['has_recipe'])
                        <div class="empty-note">Este plato no tiene ingredientes registrados en su quebrado.</div>
                    @else
                        <div class="empty-note">Sin receta configurada para el nivel "{{ $level->name }}".</div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <p style="text-align:center;color:#a0aec0;padding:40px 0;">Esta programación no tiene platos asignados.</p>
    @endforelse

</body>
</html>
