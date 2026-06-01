<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubdealershipSheetExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** Accumulated per-person rows [num, name, cntD, amtD, cntA, amtA, cntC, amtC, total] */
    private array $dataRows = [];

    /** Representative unit prices found in the data */
    private float $priceD = 0;
    private float $priceA = 0;
    private float $priceC = 0;

    /** Row index where the data starts (after header block) */
    private const DATA_START = 6; // rows 1-5 are title + headers

    public function __construct(
        private readonly string     $subdealershipName,
        private readonly Collection $tickets,
        private readonly string     $startDate,
        private readonly string     $endDate,
    ) {
        $this->buildDataRows();
    }

    /* ──────────────────────────────────────────────────────────
     |  Build aggregated rows grouped by person (name + dni)
     ─────────────────────────────────────────────────────────── */
    private function buildDataRows(): void
    {
        $pricesD = $pricesA = $pricesC = [];

        // Group by person
        $byPerson = $this->tickets->groupBy(
            fn($t) => trim(strtoupper($t->dinner_name ?? 'DESCONOCIDO')) . '|' . ($t->dni ?? '')
        )->sortKeys();

        $num = 1;
        foreach ($byPerson as $personTickets) {
            $name   = strtoupper($personTickets->first()->dinner_name ?? 'DESCONOCIDO');
            $cntD   = $amtD = $cntA = $amtA = $cntC = $amtC = 0;

            foreach ($personTickets as $ticket) {
                foreach ($ticket->ticket_details as $detail) {
                    $type      = (int) $detail->service_type;
                    $amount    = (int) ($detail->amount ?? 1);
                    $unitPrice = (float) ($detail->unit_price ?? 0);

                    match ($type) {
                        1 => (function () use (&$cntD, &$amtD, &$pricesD, $amount, $unitPrice) {
                            $cntD += $amount;
                            $amtD += round($unitPrice * $amount, 4);
                            if ($unitPrice > 0) $pricesD[] = $unitPrice;
                        })(),
                        2 => (function () use (&$cntA, &$amtA, &$pricesA, $amount, $unitPrice) {
                            $cntA += $amount;
                            $amtA += round($unitPrice * $amount, 4);
                            if ($unitPrice > 0) $pricesA[] = $unitPrice;
                        })(),
                        3 => (function () use (&$cntC, &$amtC, &$pricesC, $amount, $unitPrice) {
                            $cntC += $amount;
                            $amtC += round($unitPrice * $amount, 4);
                            if ($unitPrice > 0) $pricesC[] = $unitPrice;
                        })(),
                        default => null,
                    };
                }
            }

            $this->dataRows[] = [
                $num++,
                $name,
                $cntD ?: 0,
                round($amtD, 2),
                $cntA ?: 0,
                round($amtA, 2),
                $cntC ?: 0,
                round($amtC, 2),
                round($amtD + $amtA + $amtC, 2),
            ];
        }

        $avg       = fn(array $arr) => count($arr) ? round(array_sum($arr) / count($arr), 2) : 0;
        $this->priceD = $avg($pricesD);
        $this->priceA = $avg($pricesA);
        $this->priceC = $avg($pricesC);
    }

    /* ──────────────────────────────────────────────────────────
     |  Array returned to Maatwebsite (becomes worksheet data)
     ─────────────────────────────────────────────────────────── */
    public function array(): array
    {
        $fmt = fn(string $d) => \Carbon\Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');

        // Totals
        $totD  = array_sum(array_column($this->dataRows, 2));
        $totAD = array_sum(array_column($this->dataRows, 3));
        $totA  = array_sum(array_column($this->dataRows, 4));
        $totAA = array_sum(array_column($this->dataRows, 5));
        $totC  = array_sum(array_column($this->dataRows, 6));
        $totAC = array_sum(array_column($this->dataRows, 7));
        $totM  = array_sum(array_column($this->dataRows, 8));

        return array_merge(
            /* Row 1 – empty (logo placeholder) */
            [['', '', '', '', '', '', '', '', '']],

            /* Row 2 – title line 1 */
            [['', 'ATENCION DE ALIMENTOS DEL ' . $fmt($this->startDate) . ' AL ' . $fmt($this->endDate), '', '', '', '', '', '', '']],

            /* Row 3 – title line 2 */
            [['', 'AL PERSONAL DE ' . strtoupper($this->subdealershipName), '', '', '', '', '', '', '']],

            /* Row 4 – empty spacer */
            [['', '', '', '', '', '', '', '', '']],

            /* Row 5 – column headers */
            [['N°', 'APELLIDOS Y NOMBRES', 'D', number_format($this->priceD, 2), 'A', number_format($this->priceA, 2), 'C', number_format($this->priceC, 2), 'MONTO']],

            /* Data rows */
            $this->dataRows,

            /* Totals row */
            [['', '', $totD, number_format($totAD, 2), $totA, number_format($totAA, 2), $totC, number_format($totAC, 2), number_format($totM, 2)]],
        );
    }

    /* ──────────────────────────────────────────────────────────
     |  Sheet tab name (max 31 chars, no invalid chars)
     ─────────────────────────────────────────────────────────── */
    public function title(): string
    {
        $safe = preg_replace('/[\/\\\?\*\[\]:]/', '', $this->subdealershipName) ?? 'SIN EMPRESA';
        return substr(trim($safe) ?: 'SIN EMPRESA', 0, 31);
    }

    /* ──────────────────────────────────────────────────────────
     |  Styles
     ─────────────────────────────────────────────────────────── */
    public function styles(Worksheet $sheet): void
    {
        $lastDataRow  = self::DATA_START + count($this->dataRows) - 1;
        $totalsRow    = $lastDataRow + 1;
        $lastCol      = 'I';

        /* ── Merge title cells ── */
        $sheet->mergeCells("B2:{$lastCol}2");
        $sheet->mergeCells("B3:{$lastCol}3");

        /* ── Title styling ── */
        foreach (['B2', 'B3'] as $cell) {
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        /* ── Column header row (row 5) ── */
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(20);

        /* ── Data rows ── */
        if ($lastDataRow >= self::DATA_START) {
            $sheet->getStyle("A" . self::DATA_START . ":{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);

            // Alternating row shading
            for ($r = self::DATA_START; $r <= $lastDataRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F1F5F9');
                }
                // Right-align numeric columns
                $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I'] as $col) {
                    $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
        }

        /* ── Totals row ── */
        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I'] as $col) {
            $sheet->getStyle("{$col}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        /* ── Column widths ── */
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(35);
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(10);
        }
    }
}
