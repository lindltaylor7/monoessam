<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PosInventoryReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    private const LAST_COL = 'I';

    private int $rowNumber = 0;

    public function __construct(
        protected array $mercantilIds,
        protected null|string|int $mercantilId = null,
    ) {}

    public function collection()
    {
        $query = Product::with('mercantil:id,name')
            ->whereIn('mercantil_id', $this->mercantilIds)
            ->where('is_active', true);

        if (!empty($this->mercantilId) && $this->mercantilId !== 'all') {
            $query->where('mercantil_id', $this->mercantilId);
        }

        return $query->orderBy('mercantil_id')->orderBy('category')->orderBy('name')->get();
    }

    public function headings(): array
    {
        return [
            'Item',
            'ID Producto',
            'Producto',
            'Marca',
            'Clase',
            'Almacén / Mercantil',
            'Stock',
            'Valor Unitario (S/)',
            'Valor Total (S/)',
        ];
    }

    public function map($product): array
    {
        $this->rowNumber++;

        $valorTotal = round($product->stock * $product->price, 2);

        return [
            $this->rowNumber,
            $product->sku ?: ('PRD-' . str_pad((string) $product->id, 6, '0', STR_PAD_LEFT)),
            $product->name,
            $product->marca ?: '—',
            $product->category ?: '—',
            $product->mercantil?->name ?? '—',
            $product->stock,
            number_format($product->price, 2, '.', ''),
            number_format($valorTotal, 2, '.', ''),
        ];
    }

    public function styles($sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Inserta dos filas arriba de todo (título + fecha de generación) empujando el encabezado y
     * los datos hacia abajo — corre después de que WithHeadings/WithMapping/WithStyles ya
     * escribieron la hoja, así que las filas existentes (y sus estilos) bajan intactas.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;

                $sheet->insertNewRowBefore(1, 2);

                $sheet->setCellValue('A1', 'Reporte de Inventario');
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E293B']],
                ]);

                $sheet->setCellValue('A2', 'Fecha: ' . now()->format('d/m/Y H:i'));
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(18);
            },
        ];
    }
}
