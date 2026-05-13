<?php

namespace App\Exports;

use App\Models\MenuCycle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CycleExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $menuCycle;

    public function __construct(MenuCycle $menuCycle)
    {
        $this->menuCycle = $menuCycle;
    }

    public function collection()
    {
        return collect($this->menuCycle->cycle_data);
    }

    public function headings(): array
    {
        $headings = ['Categoría', 'Estado'];
        for ($i = 1; $i <= $this->menuCycle->days; $i++) {
            $headings[] = "Día $i";
        }
        return $headings;
    }

    public function map($row): array
    {
        $data = [
            $row['category'] ?? 'N/A',
            $this->getStatusText($row)
        ];

        for ($i = 1; $i <= $this->menuCycle->days; $i++) {
            $dayData = $row['days'][$i] ?? null;
            if ($dayData) {
                $data[] = $dayData['dish_name'];
            } else {
                $data[] = '';
            }
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        // Style the Header
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '334155'], // slate-700
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'F8FAFC'], // slate-50
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ]
        ]);

        // Style the entire table borders
        $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ]);

        // Style cells based on their content
        for ($row = 2; $row <= $lastRow; $row++) {
            // First column style
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
            ]);

            // Status cell style
            $statusCell = 'B' . $row;
            $status = $sheet->getCell($statusCell)->getValue();

            $statusColor = 'F1F5F9';
            $textColor = '64748B';

            if ($status === 'Óptimo') {
                $statusColor = 'DCFCE7';
                $textColor = '166534';
            } elseif ($status === 'Bajo') {
                $statusColor = 'FEF9C3';
                $textColor = '854D0E';
            } elseif ($status === 'Alto') {
                $statusColor = 'FEE2E2';
                $textColor = '991B1B';
            }

            $sheet->getStyle($statusCell)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => $statusColor],
                ],
                'font' => [
                    'color' => ['rgb' => $textColor],
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ]
            ]);

            // Add background to assigned dishes to mimic Airtable pills
            for ($colIndex = 3; $colIndex <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastColumn); $colIndex++) {
                $colString = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $cellCoordinate = $colString . $row;
                $cellValue = $sheet->getCell($cellCoordinate)->getValue();

                if (!empty($cellValue)) {
                    $sheet->getStyle($cellCoordinate)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'EFF6FF'], // blue-50
                        ],
                        'font' => [
                            'color' => ['rgb' => '1D4ED8'], // blue-700
                            'bold' => true,
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'wrapText' => true,
                        ]
                    ]);
                } else {
                    $sheet->getStyle($cellCoordinate)->applyFromArray([
                        'font' => ['color' => ['rgb' => '94A3B8']], // Empty cells light gray
                    ]);
                }
            }
        }
        
        $sheet->getRowDimension(1)->setRowHeight(30);
        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(40);
        }

        return [];
    }

    private function getStatusText($row)
    {
        $days = (array) ($row['days'] ?? []);
        if (empty($days)) return 'Sin Asignar';

        $totalAssignedCost = 0;
        foreach ($days as $day) {
            $totalAssignedCost += floatval($day['price'] ?? 0);
        }

        $averageCost = $totalAssignedCost / count($days);
        $minLimit = floatval($row['costValue'] ?? 0);
        $maxLimit = floatval($row['costValueMax'] ?? 0);

        if ($averageCost < $minLimit) return 'Bajo';
        if ($averageCost > $maxLimit) return 'Alto';
        return 'Óptimo';
    }
}
