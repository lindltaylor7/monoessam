<?php

namespace App\Exports;

use App\Models\City;
use App\Models\Level;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class WeeklyPurchaseOrderExport implements FromArray, ShouldAutoSize, WithStyles, WithDrawings
{
    /** @var Collection<int,\App\Models\WeeklyProgram> */
    protected Collection $programs;
    protected Level $level;
    protected City $city;
    protected Collection $categories;
    protected float $grandTotal;
    protected int $missingPriceCount;

    /** Row numbers of category header rows and the closing total row, filled in by array() and read by styles(). */
    protected array $categoryRows = [];
    protected int $lastRow = 0;

    public function __construct(Collection $programs, Level $level, City $city, Collection $categories, float $grandTotal, int $missingPriceCount)
    {
        $this->programs = $programs;
        $this->level = $level;
        $this->city = $city;
        $this->categories = $categories;
        $this->grandTotal = $grandTotal;
        $this->missingPriceCount = $missingPriceCount;
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo Santa Monica');

        $logoPath = public_path('images/logo.jpg');
        if (file_exists($logoPath)) {
            $drawing->setPath($logoPath);
            $drawing->setHeight(60);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(10);
        }

        return $drawing;
    }

    public function array(): array
    {
        $rows = [];

        $unidad = $this->programs->map(fn ($p) => optional($p->cafe->unit)->name)->filter()->unique()->implode(' / ') ?: 'N/A';
        $baseChain = $this->programs->map(function ($p) {
            return collect([
                optional(optional($p->cafe->unit)->mine)->name,
                optional($p->cafe->unit)->name,
                optional($p->cafe)->name,
            ])->filter()->implode(' - ');
        })->filter()->unique()->implode('  ·  ') ?: 'N/A';

        $first = $this->programs->first();
        $week = $first ? \Carbon\Carbon::parse($first->start_date)->isoWeek() : '';
        $year = $first ? \Carbon\Carbon::parse($first->start_date)->year : '';
        $ordenes = $this->programs->pluck('id')->implode(', ');
        $countLabel = $this->programs->count() > 1 ? " ({$this->programs->count()} programaciones: {$ordenes})" : '';

        // Fila 1: espacio para logo + total general
        $rows[] = ['', '', '', '', 'TOTAL:', number_format($this->grandTotal, 2)];

        // Fila 2: título + semana/año
        $rows[] = ['ORDEN DE PEDIDO SEMANAL COMPLETO' . $countLabel, '', '', '', "SEMANA: {$week}", "AÑO: {$year}"];

        // Fila 3: metadata
        $rows[] = ["Unidad: {$unidad}  |  Base: {$baseChain}  |  Nivel: {$this->level->name}  |  Ciudad: {$this->city->name}", '', '', '', '', ''];

        // Fila 4: espaciador
        $rows[] = ['', '', '', '', '', ''];

        // Fila 5: encabezados de columna
        $rows[] = ['CÓDIGO', 'PRODUCTO', 'UND', 'PRECIO', 'CANTIDAD', 'SUBTOTAL'];

        $rowNumber = 6; // 1-indexed, matches PhpSpreadsheet rows (array is 0-indexed, +1 for the header row already counted)

        foreach ($this->categories as $category) {
            $rows[] = [0, strtoupper($category['name']), '', '', '', ''];
            $this->categoryRows[] = $rowNumber;
            $rowNumber++;

            foreach ($category['rows'] as $ingredient) {
                $rows[] = [
                    $ingredient['code'],
                    $ingredient['name'],
                    $ingredient['unit'],
                    $ingredient['price'] !== null ? number_format($ingredient['price'], 2) : 'Sin precio',
                    number_format($ingredient['quantity'], 3),
                    $ingredient['subtotal'] !== null ? number_format($ingredient['subtotal'], 2) : '—',
                ];
                $rowNumber++;
            }
        }

        if ($this->missingPriceCount > 0) {
            $rows[] = ['', '', '', '', '', ''];
            $rows[] = ["Nota: {$this->missingPriceCount} insumo(s) no tienen precio registrado para la ciudad \"{$this->city->name}\" y no están incluidos en el TOTAL.", '', '', '', '', ''];
            $rowNumber += 2;
        }

        $this->lastRow = $rowNumber - 1;

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->lastRow;

        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Montserrat');
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(9);

        // Fila 1: TOTAL
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('E1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '08182B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle('F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '08182B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Fila 2: título + badges
        $sheet->mergeCells('A2:D2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '08182B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getStyle('E2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2F855A']], // verde
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('F2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'C05621']], // naranja
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Fila 3: metadata
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '4A5568']],
        ]);

        // Fila 5: encabezados
        $sheet->getStyle('A5:F5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3E5276']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Filas de categoría (fondo azul, texto blanco, columna B en negrita)
        foreach ($this->categoryRows as $row) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2B6CB0']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }

        // Bordes suaves para toda la tabla, desde encabezados hasta la última fila de datos
        $sheet->getStyle("A5:F{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'C1D1E4'],
                ],
            ],
        ]);

        // Alinear a la derecha las columnas numéricas
        $sheet->getStyle("D6:F{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle("A6:A{$lastRow}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(26);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(5)->setRowHeight(20);

        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(40);

        return [];
    }
}
