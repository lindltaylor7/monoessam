<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja DETALLE: una fila por servicio consumido.
 *
 * Sin ShouldAutoSize a propósito: los anchos se fijan explícitamente más abajo y
 * el cálculo automático recorre cada celda de cada columna, lo que con decenas de
 * miles de filas es el cuello de botella del export.
 */
class SalesDetailSheet implements FromArray, WithStyles, WithTitle
{
    private array $dataRows  = [];
    private const DATA_START = 5;

    /* Índices de columna dentro de $dataRows (0-based) */
    private const COL_QTY   = 7;
    private const COL_PRICE = 8;

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
                $row['dni'] ?? '—',
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
        $fmt = fn(string $d) => Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');

        // Se suman las columnas CANT. (7) y PRECIO (8). Antes se leían los índices
        // 6 y 7 —SERVICIO y CANT.—, con lo que el total de cantidad salía 0 y el de
        // importe repetía la cantidad.
        $totalQty   = array_sum(array_column($this->dataRows, self::COL_QTY));
        $totalPrice = array_sum(
            array_map(fn($r) => (float) str_replace(',', '', $r[self::COL_PRICE] ?? '0'), $this->dataRows)
        );

        return array_merge(
            [['CAFETERÍA: ' . strtoupper($this->cafeName), '', '', '', '', '', '', '', '']],
            [['Período: ' . $fmt($this->startDate) . ' — ' . $fmt($this->endDate), '', '', '', '', '', '', '', '']],
            [['', '', '', '', '', '', '', '', '']],
            [['N°', 'SUBCONCESIONARIA', 'APELLIDOS Y NOMBRES', 'DNI', 'FECHA', 'HORA', 'SERVICIO', 'CANT.', 'PRECIO']],
            $this->dataRows,
            [['', '', '', '', '', '', 'TOTAL', $totalQty, number_format($totalPrice, 2)]],
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
        $lastCol     = 'I';

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
            $dataRange = 'A' . self::DATA_START . ":{$lastCol}{$lastDataRow}";

            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'font'    => ['size' => 9],
            ]);

            // Alineación por columna completa en vez de celda por celda:
            // A=N°, D=DNI, E=FECHA, F=HORA, H=CANT. centradas; I=PRECIO a la derecha.
            foreach (['A', 'D', 'E', 'F', 'H'] as $col) {
                $sheet->getStyle("{$col}" . self::DATA_START . ":{$col}{$lastDataRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->getStyle('I' . self::DATA_START . ":I{$lastDataRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Filas alternas vía formato condicional: una sola entrada para todo el
            // rango, en vez de un getStyle()->getFill() por cada fila par.
            $zebra = new Conditional();
            $zebra->setConditionType(Conditional::CONDITION_EXPRESSION);
            $zebra->addCondition('MOD(ROW(),2)=0');
            $zebra->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            $sheet->getStyle($dataRange)->setConditionalStyles([$zebra]);
        }

        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        $sheet->getStyle("G{$totalsRow}:H{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("I{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // A=N°, B=SUBCONCESIONARIA, C=APELLIDOS Y NOMBRES, D=DNI, E=FECHA, F=HORA, G=SERVICIO, H=CANT., I=PRECIO
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(32);
        $sheet->getColumnDimension('D')->setWidth(13);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(26);
        $sheet->getColumnDimension('H')->setWidth(8);
        $sheet->getColumnDimension('I')->setWidth(12);
    }
}
