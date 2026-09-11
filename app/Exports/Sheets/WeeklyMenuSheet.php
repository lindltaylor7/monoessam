<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Grilla de menú de una semana: VOL + OPCIÓN en columnas A/B, un día por columna a partir de C,
 * y las opciones agrupadas por servicio (Desayuno / Almuerzo / Cena / Refrigerio).
 *
 * La verdad de lo que tiene la programación son sus `weekly_program_items` (fecha, servicio,
 * categoría, plato) y sus `daily_portions`. Las filas de opción se derivan de las categorías
 * presentes en cada servicio, ordenadas por el `sort_order` de `menu_structures`; cuando una
 * misma categoría tiene varios platos el mismo día, se abre en varios slots ([01], [02], …).
 * La columna VOL sale del `reference_volume` de la estructura de costos del programa, si existe.
 */
class WeeklyMenuSheet implements FromArray, WithStyles, WithTitle
{
    private const MEAL_ORDER = ['Desayuno', 'Almuerzo', 'Cena', 'Refrigerio'];

    private array $dates = [];
    private int $totalCols = 9;
    private string $lastCol = 'I';

    private int $headerRow = 0;
    private array $bandRows = [];
    private array $portionRows = [];
    private int $lastRow = 0;

    public function __construct(
        private readonly object $program,
        private readonly Collection $menuStructure,
        private readonly string $title,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $p = $this->program;

        $this->dates = $this->weekDates();
        $dayCount = count($this->dates);
        $this->totalCols = 2 + $dayCount;
        $this->lastCol = Coordinate::stringFromColumnIndex($this->totalCols);

        $pad = fn (array $cells): array => array_pad(array_slice($cells, 0, $this->totalCols), $this->totalCols, '');

        // Índices por celda a partir de los items / raciones reales del programa.
        $itemIndex  = [];   // [meal][categoryId][date] => ["[code] plato", ...]
        $categoryNames = []; // [categoryId] => nombre
        foreach ($p->items as $it) {
            $d = \Carbon\Carbon::parse($it->date)->format('Y-m-d');
            $label = '[' . $it->dish_id . '] ' . ($it->dish->name ?? 'Plato eliminado');
            $itemIndex[$it->meal_type][$it->dish_category_id][$d][] = $label;
            $categoryNames[$it->dish_category_id] = $it->dish_category->name ?? 'Sin categoría';
        }

        $portionIndex = []; // [meal][date] => count
        foreach ($p->portions as $pt) {
            $d = \Carbon\Carbon::parse($pt->date)->format('Y-m-d');
            $portionIndex[$pt->meal_type][$d] = $pt->portions_count;
        }

        $volumeByCategory = [];
        foreach (optional($p->structure)->costs ?? [] as $cost) {
            if ($cost->dish_category_id !== null && $cost->reference_volume !== null) {
                $volumeByCategory[$cost->dish_category_id] = 0 + $cost->reference_volume;
            }
        }

        $cafeName = $p->cafe->name ?? 'Comedor';
        $week = \Carbon\Carbon::parse($p->start_date)->isoWeek();
        $year = \Carbon\Carbon::parse($p->start_date)->year;

        $rows = [];

        // ── Cabecera ──────────────────────────────────────────────────────────
        $h1 = $pad(['COMEDOR : ' . strtoupper($cafeName)]);
        $h1[$this->totalCols - 2] = 'SEMANA : ' . $week;
        $h1[$this->totalCols - 1] = (string) $year;
        $rows[] = $h1;

        $rows[] = $pad([
            'Desde: ' . \Carbon\Carbon::parse($this->dates[0])->format('d/m/Y')
                . '     Hasta: ' . \Carbon\Carbon::parse(end($this->dates))->format('d/m/Y'),
        ]);

        $rows[] = $pad([]); // espaciador

        $dayHeaders = array_map(function ($d, $i) {
            $c = \Carbon\Carbon::parse($d);
            return sprintf('%02d  %s  %s', $i + 1, $c->format('d/m'), ucfirst($c->locale('es')->translatedFormat('D')));
        }, $this->dates, array_keys($this->dates));

        $rows[] = $pad(array_merge(['VOL', 'OPCIÓN'], $dayHeaders));
        $this->headerRow = count($rows); // 1-indexed

        // ── Servicios ────────────────────────────────────────────────────────
        $presentMeals = collect($p->items)->pluck('meal_type')->unique()->values();

        if ($presentMeals->isEmpty()) {
            $rows[] = $pad(['', 'Esta programación no tiene platos asignados.']);
            $this->lastRow = count($rows);
            return $rows;
        }

        $meals = collect(self::MEAL_ORDER)
            ->filter(fn ($m) => $presentMeals->contains($m))
            ->merge($presentMeals->reject(fn ($m) => in_array($m, self::MEAL_ORDER, true)))
            ->values();

        foreach ($meals as $meal) {
            $rows[] = $pad([strtoupper($meal)]);
            $this->bandRows[] = count($rows);

            $portionsRow = ['', 'RACIONES'];
            foreach ($this->dates as $d) {
                $portionsRow[] = $portionIndex[$meal][$d] ?? '';
            }
            $rows[] = $pad($portionsRow);
            $this->portionRows[] = count($rows);

            foreach ($this->orderedCategories($meal, array_keys($itemIndex[$meal] ?? []), $categoryNames) as $categoryId) {
                $slotCount = 1;
                foreach ($this->dates as $d) {
                    $slotCount = max($slotCount, count($itemIndex[$meal][$categoryId][$d] ?? []));
                }

                for ($slot = 1; $slot <= $slotCount; $slot++) {
                    $cells = [
                        $slot === 1 ? ($volumeByCategory[$categoryId] ?? '') : '',
                        strtoupper($categoryNames[$categoryId] ?? 'Sin categoría') . ' [' . str_pad((string) $slot, 2, '0', STR_PAD_LEFT) . ']',
                    ];
                    foreach ($this->dates as $d) {
                        $list = $itemIndex[$meal][$categoryId][$d] ?? [];
                        sort($list);
                        $cells[] = $list[$slot - 1] ?? '';
                    }
                    $rows[] = $pad($cells);
                }
            }
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    /**
     * Categorías del servicio ordenadas por el sort_order de menu_structures y luego por nombre.
     */
    private function orderedCategories(string $meal, array $categoryIds, array $categoryNames): array
    {
        $order = $this->menuStructure
            ->where('meal_type', $meal)
            ->pluck('sort_order', 'dish_category_id');

        return collect($categoryIds)
            ->sortBy(fn ($cid) => [$order->get($cid, 9999), $categoryNames[$cid] ?? ''])
            ->values()
            ->all();
    }

    /**
     * Días de la semana del programa. Usa start_date..end_date; si end_date falta o es inválido,
     * toma 7 días desde start_date. Se acota a 14 por seguridad.
     */
    private function weekDates(): array
    {
        $start = \Carbon\Carbon::parse($this->program->start_date)->startOfDay();
        $end = $this->program->end_date ? \Carbon\Carbon::parse($this->program->end_date)->startOfDay() : null;

        if (!$end || $end->lt($start)) {
            $end = $start->copy()->addDays(6);
        }
        if ($end->diffInDays($start) > 13) {
            $end = $start->copy()->addDays(13);
        }

        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        return $dates;
    }

    public function styles(Worksheet $sheet)
    {
        $last = $this->lastCol;
        $lastRow = $this->lastRow;
        $headerRow = $this->headerRow;
        $semanaCol = Coordinate::stringFromColumnIndex($this->totalCols - 1);
        $titleEndCol = Coordinate::stringFromColumnIndex($this->totalCols - 2);

        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(9);

        // Fila 1: comedor + semana/año
        $sheet->mergeCells("A1:{$titleEndCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '08182B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getStyle("{$semanaCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2F855A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'C05621']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Fila 2: rango de fechas
        $sheet->mergeCells("A2:{$last}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '2D3748']],
            'alignment' => ['indent' => 1],
        ]);

        // Fila de encabezado VOL / OPCIÓN / días
        $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3E5276']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        // Bandas de servicio
        foreach ($this->bandRows as $row) {
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '1A202C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(18);
        }

        // Filas de raciones
        foreach ($this->portionRows as $row) {
            $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1A202C']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EDF2F7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        if ($headerRow > 0 && $lastRow >= $headerRow) {
            // Bordes de toda la grilla
            $sheet->getStyle("A{$headerRow}:{$last}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E0']],
                ],
            ]);

            // Celdas de plato: texto ajustado, alineado arriba
            $sheet->getStyle("C" . ($headerRow + 1) . ":{$last}{$lastRow}")->applyFromArray([
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP, 'horizontal' => Alignment::HORIZONTAL_LEFT],
                'font' => ['size' => 8],
            ]);

            // VOL centrado
            $sheet->getStyle("A" . ($headerRow + 1) . ":A{$lastRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            // OPCIÓN
            $sheet->getStyle("B" . ($headerRow + 1) . ":B{$lastRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '2D3748']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);

            $sheet->freezePane('C' . ($headerRow + 1));
        }

        // Anchos de columna
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(32);
        for ($i = 3; $i <= $this->totalCols; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(24);
        }

        return [];
    }
}
