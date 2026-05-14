<?php

namespace App\Exports;

use App\Models\MenuCycle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CycleExport implements FromArray, ShouldAutoSize, WithStyles, WithDrawings
{
    protected $menuCycle;

    public function __construct(MenuCycle $menuCycle)
    {
        $this->menuCycle = $menuCycle;
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo Santa Monica');

        // Verifica si el archivo del logo existe antes de añadirlo para evitar errores fatales.
        $logoPath = public_path('images/logo.jpg');
        if (file_exists($logoPath)) {
            $drawing->setPath($logoPath);
            $drawing->setHeight(80);
            $drawing->setCoordinates('A1'); // Lo colocaremos en A1 para que encaje bien
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(10);
        }

        return $drawing;
    }

    public function array(): array
    {
        $rows = [];

        // Fetch Metadata
        $mineName = 'N/A';
        $unitName = 'N/A';
        $cafeName = 'N/A';
        $serviceName = 'N/A';
        $cycleId = $this->menuCycle->id;

        $serviceable = \DB::table('serviceables')->find($this->menuCycle->serviceable_id);
        if ($serviceable) {
            $service = \App\Models\Service::find($serviceable->service_id);
            $serviceName = $service ? $service->name : 'N/A';

            if ($serviceable->serviceable_type === 'App\Models\Cafe') {
                $cafe = \App\Models\Cafe::with('unit.mine')->find($serviceable->serviceable_id);
                if ($cafe) {
                    $cafeName = $cafe->name;
                    if ($cafe->unit) {
                        $unitName = $cafe->unit->name;
                        if ($cafe->unit->mine) {
                            $mineName = $cafe->unit->mine->name;
                        }
                    }
                }
            } elseif ($serviceable->serviceable_type === 'App\Models\Unit') {
                $unit = \App\Models\Unit::with('mine')->find($serviceable->serviceable_id);
                if ($unit) {
                    $unitName = $unit->name;
                    if ($unit->mine) {
                        $mineName = $unit->mine->name;
                    }
                }
            } elseif ($serviceable->serviceable_type === 'App\Models\Mine') {
                $mine = \App\Models\Mine::find($serviceable->serviceable_id);
                if ($mine) {
                    $mineName = $mine->name;
                }
            }
        }

        // Filas 1 a 4: Espacio para Logo y Metadata
        $totalCols = 2 + ($this->menuCycle->days * 3);
        $rows[] = array_pad([], $totalCols, ''); // Fila 1

        // Fila 2: Etiquetas Superiores
        $row2 = ['', '', 'MINA', $mineName, 'UNIDAD', $unitName, 'COMEDOR', $cafeName];
        $rows[] = array_pad($row2, $totalCols, '');

        // Fila 3: Etiquetas Inferiores
        $row3 = ['', '', 'SERVICIO:', $serviceName, 'CICLICO', $cycleId, '', ''];
        $rows[] = array_pad($row3, $totalCols, '');

        $rows[] = array_pad([], $totalCols, ''); // Fila 4
        $rows[] = array_pad([], $totalCols, ''); // Fila 5: Espaciador

        // Fila 6: Day Number Groupings
        $row6 = ['', ''];
        for ($i = 1; $i <= $this->menuCycle->days; $i++) {
            $row6[] = str_pad($i, 2, '0', STR_PAD_LEFT);
            $row6[] = '';
            $row6[] = '';
        }
        $rows[] = $row6;

        // Fila 7: Column Headers
        $row7 = ['N°', 'OPCIÓN'];
        for ($i = 1; $i <= $this->menuCycle->days; $i++) {
            $row7[] = "Día $i";
            $row7[] = 'COSTO';
            $row7[] = 'KCAL';
        }
        $rows[] = $row7;

        // Data Rows
        $index = 1;
        foreach ($this->menuCycle->cycle_data as $dataRow) {
            $r = [
                $index++,
                $dataRow['category'] ?? 'N/A'
            ];
            for ($i = 1; $i <= $this->menuCycle->days; $i++) {
                $dayData = $dataRow['days'][$i] ?? null;
                if ($dayData) {
                    $r[] = $dayData['dish_name'];
                    $r[] = number_format(floatval($dayData['price'] ?? 0), 2);
                    $r[] = number_format(floatval($dayData['calories'] ?? 0), 2);
                } else {
                    $r[] = '';
                    $r[] = '0.00';
                    $r[] = '0.00';
                }
            }
            $rows[] = $r;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColIndex = 2 + ($this->menuCycle->days * 3);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
        $lastRow = $sheet->getHighestRow();

        // 1. Set Global Font
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Montserrat');
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(9);

        // Paleta de colores solicitada:
        // 1. 08182B (Very dark blue)
        // 2. 3E5276 (Dark grayish blue)
        // 3. 698BBA (Medium blue)
        // 4. C1D1E4 (Light grayish blue)
        // 5. F1F4F7 (Off-white)

        // 2. Global Borders & Alignment (solo para la tabla desde fila 6)
        $sheet->getStyle('A6:' . $lastCol . $lastRow)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ]);

        $sheet->getStyle('A6:' . $lastCol . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'C1D1E4'], // Color 4 para bordes suaves
                ],
            ]
        ]);

        // 3. Header Metadata Boxes (Fila 2 & 3)
        // Columnas C, E, G: Etiquetas (Color 2 bg, white text)
        // Columnas D, F, H: Valores (Color 4 bg, Color 1 text)

        $headerLabels = ['C2', 'C3', 'E2', 'E3', 'G2'];
        $headerValues = ['D2', 'D3', 'F2', 'F3', 'H2'];

        foreach ($headerLabels as $cell) {
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3E5276']], // Color 2
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ]);
        }

        foreach ($headerValues as $cell) {
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '08182B'], 'size' => 10], // Color 1
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'C1D1E4']], // Color 4
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ]);
        }

        // Unir celdas para el Comedor en fila 2 & 3
        $sheet->mergeCells('G2:G3');
        $sheet->mergeCells('H2:H3');

        // 4. Fila 6 Styling (Day Groupings)
        for ($i = 1; $i <= $this->menuCycle->days; $i++) {
            $startColIdx = 3 + ($i - 1) * 3;
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx);
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + 2);
            $sheet->mergeCells($startCol . '6:' . $endCol . '6');

            $sheet->getStyle($startCol . '6')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '698BBA']], // Color 3 para los dias
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
        }

        // 5. Fila 7 Styling (Column Headers)
        $sheet->getStyle('A7:' . $lastCol . '7')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 8],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '08182B']], // Color 1 (Más oscuro)
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // 6. Data Rows Styling
        for ($row = 8; $row <= $lastRow; $row++) {
            // Fondo general para las filas
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFFFFF']],
            ]);

            // N°
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '3E5276']], // Color 2
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            // OPCIÓN
            $sheet->getStyle('B' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '08182B'], 'size' => 8], // Color 1
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F1F4F7']], // Color 5 bg
                'alignment' => ['wrapText' => true]
            ]);

            // Dishes, Cost, Kcal
            for ($i = 1; $i <= $this->menuCycle->days; $i++) {
                $dishColIdx = 3 + ($i - 1) * 3;
                $costColIdx = 4 + ($i - 1) * 3;
                $kcalColIdx = 5 + ($i - 1) * 3;

                $dishCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dishColIdx);
                $costCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($costColIdx);
                $kcalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kcalColIdx);

                // Dish Name
                $sheet->getStyle($dishCol . $row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '08182B'], 'size' => 8], // Color 1
                    'alignment' => ['wrapText' => true]
                ]);
                $sheet->getColumnDimension($dishCol)->setWidth(25); // Fijar ancho para texto

                // Costo
                $sheet->getStyle($costCol . $row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '3E5276']], // Color 2
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                // Kcal
                $sheet->getStyle($kcalCol . $row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '698BBA']], // Color 3
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);
            }
        }

        // Set row heights
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(30);
        $sheet->getRowDimension(3)->setRowHeight(30);
        $sheet->getRowDimension(4)->setRowHeight(15);
        $sheet->getRowDimension(6)->setRowHeight(20);
        $sheet->getRowDimension(7)->setRowHeight(20);
        for ($row = 8; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(35);
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);

        return [];
    }
}
