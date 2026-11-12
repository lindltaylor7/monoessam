<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pestaña "Por proveedor": un cuadro por proveedor activo con sus productos y su total, y al
 * final un resumen con el total de cada cuadro y el total general. Los totales son fórmulas
 * SUM para que sigan cuadrando si alguien corrige una cantidad o un precio en el Excel.
 *
 * Los insumos sin proveedor activo en la ciudad van en un cuadro aparte al final; si tienen
 * costo manual entran al total, igual que en la pantalla del pedido.
 */
class PurchaseOrderByProviderSheet implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public const NO_PROVIDER = 'SIN PROVEEDOR ACTIVO';

    private const HEADINGS = ['CÓDIGO', 'PRODUCTO', 'UND', 'PRECIO', 'CANTIDAD', 'SUBTOTAL'];

    /** Filas a estilizar, llenadas por array() y leídas por styles(). */
    private array $providerRows = [];
    private array $headingRows = [];
    private array $itemRows = [];
    private array $blockTotalRows = [];
    private array $summaryRows = [];
    private ?int $summaryHeadingRow = null;
    private ?int $grandTotalRow = null;

    /**
     * @param  Collection<int,array{code:mixed,name:string,unit:string,price:?float,quantity:float,subtotal:?float,provider:?string}>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly string $title,
        private readonly string $cityName,
    ) {
    }

    public function title(): string
    {
        return 'Por proveedor';
    }

    public function array(): array
    {
        $out = [];
        $out[] = [$this->title, '', '', '', '', ''];
        $out[] = ["Precios del proveedor activo en: {$this->cityName}", '', '', '', '', ''];
        $out[] = ['', '', '', '', '', ''];

        $blocks = $this->rows
            ->groupBy(fn ($row) => $row['provider'] ?: self::NO_PROVIDER)
            ->sortKeys()
            // El cuadro sin proveedor siempre al final.
            ->sortBy(fn ($group, $provider) => $provider === self::NO_PROVIDER ? 1 : 0);

        $blockTotals = [];

        foreach ($blocks as $provider => $items) {
            $out[] = ["PROVEEDOR: {$provider}", '', '', '', '', ''];
            $this->providerRows[] = count($out);

            $out[] = self::HEADINGS;
            $this->headingRows[] = count($out);

            $first = count($out) + 1;
            foreach ($items->sortBy('name') as $item) {
                $out[] = [
                    (string) $item['code'],
                    $item['name'],
                    $item['unit'],
                    $item['price'] !== null ? round($item['price'], 2) : 'Sin precio',
                    round($item['quantity'], 3),
                    $item['subtotal'] !== null ? round($item['subtotal'], 2) : null,
                ];
                $this->itemRows[] = count($out);
            }
            $last = count($out);

            $out[] = ['', '', '', '', "TOTAL {$provider}", "=SUM(F{$first}:F{$last})"];
            $this->blockTotalRows[] = count($out);
            $blockTotals[$provider] = ['row' => count($out), 'count' => $items->count()];

            $out[] = ['', '', '', '', '', ''];
        }

        // Resumen: un renglón por cuadro, apuntando a su total.
        $out[] = ['RESUMEN POR PROVEEDOR', '', '', '', '', ''];
        $this->providerRows[] = count($out);
        $out[] = ['', 'PROVEEDOR', '', '', 'PRODUCTOS', 'TOTAL'];
        $this->summaryHeadingRow = count($out);

        $firstSummary = count($out) + 1;
        foreach ($blockTotals as $provider => $info) {
            $out[] = ['', $provider, '', '', $info['count'], "=F{$info['row']}"];
            $this->summaryRows[] = count($out);
        }
        $lastSummary = count($out);

        $out[] = ['', 'TOTAL GENERAL', '', '', "=SUM(E{$firstSummary}:E{$lastSummary})", "=SUM(F{$firstSummary}:F{$lastSummary})"];
        $this->grandTotalRow = count($out);

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 48, 'C' => 8, 'D' => 12, 'E' => 26, 'F' => 14];
    }

    public function styles(Worksheet $sheet)
    {
        $money = '#,##0.00';

        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '08182B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('4A5568');

        foreach ($this->providerRows as $row) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2B6CB0']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }

        foreach (array_merge($this->headingRows, [$this->summaryHeadingRow]) as $row) {
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '3E5276']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C1D1E4']]]];

        foreach ($this->itemRows as $row) {
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($thin);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($money);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($money);
            $sheet->getStyle("D{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($this->blockTotalRows as $row) {
            $sheet->getStyle("E{$row}:F{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EDF2F7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ] + $thin);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($money);
        }

        foreach ($this->summaryRows as $row) {
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($thin);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($money);
        }

        $row = $this->grandTotalRow;
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '08182B']],
        ] + $thin);
        $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($money);

        return [];
    }
}
