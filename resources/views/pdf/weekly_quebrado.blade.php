<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Quebrado Semanal {{ $program->id }}</title>
    <style>
        @page { margin: 1.2cm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #1a202c;
            margin: 0; padding: 0;
            line-height: 1.4;
        }

        /* ── One block per date + servicio, own masthead each. They flow one after
           another instead of forcing a page break, so a short day/servicio doesn't
           waste the rest of a page — only the masthead itself is kept from being
           orphaned alone at the bottom of a page. ─────────────────────────────── */
        .page-block { margin-bottom: 22px; border-bottom: 2px solid #cbd5e0; padding-bottom: 14px; }
        .page-block:last-child { border-bottom: none; }
        .masthead-block { page-break-inside: avoid; }

        .masthead-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .masthead-table td { vertical-align: middle; padding: 0; }
        .logo-main { font-size: 16px; font-weight: 900; color: #c05621; margin: 0; }
        .logo-sub { font-size: 6.5px; font-weight: 700; letter-spacing: 1px; color: #4a5568; }
        .quebrados-title {
            text-align: right; font-size: 21px; font-weight: 700; font-style: italic;
            color: #1a2456; font-family: Georgia, 'Times New Roman', serif;
        }

        .unidad-base-table { width: 100%; border-collapse: collapse; background: #1a202c; margin-bottom: 6px; }
        .unidad-base-table td { padding: 7px 10px; color: #fff; vertical-align: middle; }
        .ub-label { font-size: 10px; font-weight: 500; color: #cbd5e0; white-space: nowrap; width: 1%; }
        .ub-value { font-size: 13px; font-weight: 800; }

        .meta-line { border-bottom: 1px solid #cbd5e0; padding-bottom: 6px; margin-bottom: 8px; font-size: 9.5px; }
        .meta-item { margin-right: 18px; }
        .meta-label { text-decoration: underline; font-weight: 700; color: #2d3748; }

        .day-header {
            background: #1a202c; color: #fff; padding: 6px 10px;
            font-weight: 800; font-size: 10px; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 8px;
        }

        /* ── Dish section ───────────────────────────────── */
        .dish-section { margin-bottom: 10px; }
        .dish-header-table { width: 100%; border-collapse: collapse; }
        .dish-header-table td {
            border: 1px solid #94a3b8; background: #e2e8f0;
            padding: 5px 8px; font-size: 9px; font-weight: 800; color: #2d3748;
            vertical-align: middle;
        }
        .dh-code { width: 8%; text-align: center; }
        .dh-category { width: 22%; }
        .dh-name { width: 48%; }
        .dh-portions { width: 22%; text-align: right; color: #c05621; }

        table.ingredients { width: 100%; border-collapse: collapse; }
        table.ingredients th {
            background: #f1f5f9; border: 1px solid #cbd5e0;
            padding: 4px 8px; font-size: 7.5px; font-weight: 800;
            text-transform: uppercase; color: #4a5568; text-align: left;
        }
        table.ingredients td { border: 1px solid #cbd5e0; padding: 4px 8px; font-size: 8.5px; }
        .text-right { text-align: right; }
        .mono { font-family: 'Courier New', monospace; }
        .empty-note {
            border: 1px solid #cbd5e0; border-top: none; padding: 8px 10px;
            font-size: 8.5px; color: #a0aec0; font-style: italic;
        }
    </style>
</head>
<body>

    @forelse($pages as $page)
        <div class="page-block">
            <div class="masthead-block">
                <table class="masthead-table">
                    <tr>
                        <td style="width:35%">
                            <div class="logo-main">SANTA MONICA</div>
                            <div class="logo-sub">SERVICIOS ALIMENTICIOS</div>
                        </td>
                        <td class="quebrados-title" style="width:65%">Quebrados: {{ $page['meal_type'] }}</td>
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

                <div class="meta-line">
                    <span class="meta-item"><span class="meta-label">Período:</span> {{ \Carbon\Carbon::parse($page['date'])->year }}</span>
                    <span class="meta-item"><span class="meta-label">Mes:</span> {{ ucfirst(\Carbon\Carbon::parse($page['date'])->locale('es')->translatedFormat('F')) }}</span>
                    <span class="meta-item"><span class="meta-label">Semana:</span> {{ \Carbon\Carbon::parse($page['date'])->isoWeek() }}</span>
                    <span class="meta-item"><span class="meta-label">Programación:</span> {{ $program->id }}</span>
                    <span class="meta-item"><span class="meta-label">Nivel:</span> {{ $level->name }}</span>
                </div>

                <div class="day-header">Fecha: {{ \Carbon\Carbon::parse($page['date'])->format('d-m-Y') }}</div>
            </div>

            @foreach($page['dishes'] as $dish)
                <div class="dish-section">
                    <table class="dish-header-table">
                        <tr>
                            <td class="dh-code">{{ $dish['category_id'] }}</td>
                            <td class="dh-category">{{ strtoupper($dish['category']) }}</td>
                            <td class="dh-name">{{ $dish['dish_id'] }} {{ $dish['dish_name'] }}</td>
                            <td class="dh-portions">N° Raciones: {{ $page['portions'] }}</td>
                        </tr>
                    </table>

                    @if($dish['has_recipe'] && $dish['ingredients']->count() > 0)
                        <table class="ingredients">
                            <thead>
                                <tr>
                                    <th style="width:10%">Cod</th>
                                    <th style="width:41%">Producto</th>
                                    <th style="width:16%" class="text-right">Gramaje x Ración</th>
                                    <th style="width:17%" class="text-right">Total: Requerido</th>
                                    <th style="width:16%" class="text-right">Total: Redondeado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dish['ingredients'] as $ingredient)
                                    <tr>
                                        <td class="mono">{{ $ingredient['code'] }}</td>
                                        <td>{{ $ingredient['name'] }}</td>
                                        <td class="text-right mono">{{ number_format($ingredient['qty_per_ration'], 2) }} gr</td>
                                        <td class="text-right mono">{{ number_format($ingredient['total_required'], 2) }} gr</td>
                                        <td class="text-right mono"><strong>{{ number_format($ingredient['total_rounded'], 0) }} gr</strong></td>
                                    </tr>
                                @endforeach
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
