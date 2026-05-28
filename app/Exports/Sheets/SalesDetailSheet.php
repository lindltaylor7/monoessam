<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesDetailSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private array $dataRows  = [];
    private const DATA_START = 5;

    public function __construct(
        private readonly array  $rows,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly string $cafeName,
    ) {
        $num = 1;
        foreach ($this->rows as $row) {
            $price = (float) ($row['unit_price'] ?? 0) * (int) ($row['amount'] ?? 1);
            $this->dataRows[] = [
                $num++,
                $row['sd_name'],
                $row['name'],
                $row['date'],
                $row['time'],
                $row['svc_name'],
                (int) ($row['amount'] ?? 1),
                number_format($price, 2),
            ];
        }
    }

    public function array(): array
    {
        $fmt        = fn(string $d) => Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');
        $totalQty   = array_sum(array_column($this->dataRows, 6));
        $totalPrice = array_sum(
            array_map(fn($r) => (float) str_replace(',', '', $r[7] ?? '0'), $this->dataRows)
        );

        return array_merge(
            [['CAFETERÍA: ' . strtoupper($this->cafeName), '', '', '', '', '', '', '']],
            [['Período: ' . $fmt($this->startDate) . ' — ' . $fmt($this->endDate), '', '', '', '', '', '', '']],
            [['', '', '', '', '', '', '', '']],
            [['N°', 'SUBCONCESIONARIA', 'APELLIDOS Y NOMBRES', 'FECHA', 'HORA', 'SERVICIO', 'CANT.', 'PRECIO']],
            $this->dataRows,
            [['', '', '', '', '', 'TOTAL', $totalQty, number_format($totalPrice, 2)]],
        );
    }

    public function title(): string
    {
        return 'DETALLE';
    }

    public function styles(Worksheet $sheet): void
    {
        $lastDataRow = self::DATA_START + count($this->dataRows) - 1;
        $totalsRow   = $lastDataRow + 1;
        $lastCol     = 'H';

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");

        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 10, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(16);

        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B5998']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);

        if ($lastDataRow >= self::DATA_START) {
            $sheet->getStyle("A" . self::DATA_START . ":{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'font'    => ['size' => 9],
            ]);
            for ($r = self::DATA_START; $r <= $lastDataRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
                // Center: N°, Fecha, Hora, Cant.
                foreach (['A', 'D', 'E', 'G'] as $col) {
                    $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                // Right-align: Precio
                $sheet->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        $sheet->getStyle("F{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(32);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(26);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(12);
    }
}
