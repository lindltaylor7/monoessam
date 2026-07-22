<?php

namespace App\Exports;

use App\Models\Epp;
use App\Models\EquipmentStock;
use App\Models\InventoryStock;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Todo el stock (Tecnológico, Menaje, EPP) almacenado en un café o unidad puntual, en una
 * sola hoja plana — usado por el botón "Exportar Excel" de Almacén / Recepciones por Café.
 * Insumos queda fuera: ese tipo todavía no tiene modelo de stock implementado en esta vista.
 */
class StoreLocationStockExport implements FromArray, ShouldAutoSize, WithStyles
{
    private const HEADINGS = ['Tipo', 'Código', 'Ítem', 'Marca / Modelo', 'N° Serie', 'Talla', 'Color', 'Estado', 'Responsable', 'Cantidad'];

    private array $rows = [];

    public function __construct(
        private readonly string $locationType,
        private readonly int $locationId,
        private readonly string $locationName,
    ) {
        $this->loadRows();
    }

    private function loadRows(): void
    {
        $locationCol = $this->locationType === 'unit' ? 'unit_id' : 'cafe_id';

        $equipmentRows = EquipmentStock::with('stockable.responsible:id,name')
            ->where($locationCol, $this->locationId)
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($s) {
                $isComputer = str_contains($s->stockable_type, 'Computer');

                return [
                    $isComputer ? 'Tecnológico' : 'Menaje',
                    $s->stockable?->code ?: '—',
                    $s->stockable?->name ?: '—',
                    trim(($s->stockable?->brand ?: '') . ' ' . ($s->stockable?->model ?: '')) ?: '—',
                    $s->stockable?->series ?: '—',
                    '—',
                    '—',
                    $this->equipmentStatusLabel($s->stockable?->status),
                    $s->stockable?->responsible?->name ?: 'Sin asignar',
                    $s->quantity,
                ];
            });

        $eppRows = InventoryStock::with(['stockable:id,name', 'color:id,name'])
            ->where('stockable_type', Epp::class)
            ->where($locationCol, $this->locationId)
            ->where('quantity', '>', 0)
            ->get()
            ->map(fn($s) => [
                'EPP',
                '—',
                $s->stockable?->name ?: '—',
                '—',
                '—',
                $s->size ?: '—',
                $s->color?->name ?: '—',
                'Disponible',
                '—',
                $s->quantity,
            ]);

        $this->rows = $equipmentRows->concat($eppRows)->sortBy(fn($r) => $r[0] . $r[2])->values()->all();
    }

    private function equipmentStatusLabel(?int $status): string
    {
        return match ($status) {
            0 => 'Nuevo',
            1 => 'Bueno',
            2 => 'Regular',
            3 => 'Dañado',
            4 => 'Baja',
            default => '—',
        };
    }

    public function array(): array
    {
        return array_merge(
            [[$this->locationName, '', '', '', '', '', '', '', '', '']],
            [self::HEADINGS],
            $this->rows,
        );
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'J';
        $lastRow = 2 + count($this->rows);

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E293B']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        if ($lastRow >= 3) {
            $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);
            for ($r = 3; $r <= $lastRow; $r++) {
                if ($r % 2 === 1) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
                $sheet->getStyle("J{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        return [];
    }
}
