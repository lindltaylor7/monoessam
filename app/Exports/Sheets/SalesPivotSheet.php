<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cross-tab: rows = [EMPRESA > NOMBRE], columns = [DATE > SERVICE (qty)] + [TOTAL DE CONSUMO].
 * Below the matrix: unit-price row, amount row, and TOTAL FACTURAR block.
 */
class SalesPivotSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private array $dates      = [];
    private array $services   = [];
    private array $matrix     = [];
    private array $sdGroups   = [];
    private array $svcPrices  = []; // svc_name => representative unit price
    private array $grandTotals = []; // svc_name => total qty (all persons × dates)

    private const FIXED_COLS = 2; // EMPRESA + APELLIDOS Y NOMBRES
    private const DATA_START  = 6; // rows 1-5: title, period, spacer, header-dates, header-services

    public function __construct(
        private readonly array  $rows,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly string $cafeName,
    ) {
        $this->buildMatrix();
    }

    /* ── Build cross-tab structure, prices, and grand totals ── */
    private function buildMatrix(): void
    {
        $dateSet    = [];
        $svcTypeMap = [];
        $sdOrder    = [];

        foreach ($this->rows as $row) {
            $sd    = $row['sd_name'];
            $name  = $row['name'];
            $date  = $row['date'];
            $svc   = $row['svc_name'];
            $qty   = (int) ($row['amount'] ?? 1);
            $type  = (int) ($row['svc_type'] ?? 99);
            $price = (float) ($row['unit_price'] ?? 0);

            $dateSet[$date]   = true;
            $svcTypeMap[$svc] = $type;

            /* Track first non-zero price per service */
            if (!isset($this->svcPrices[$svc]) && $price > 0) {
                $this->svcPrices[$svc] = $price;
            }

            if (!isset($this->matrix[$sd])) {
                $this->matrix[$sd] = [];
                $sdOrder[] = $sd;
            }
            if (!isset($this->matrix[$sd][$name])) {
                $this->matrix[$sd][$name] = [];
            }
            $key = $date . '|' . $svc;
            $this->matrix[$sd][$name][$key] = ($this->matrix[$sd][$name][$key] ?? 0) + $qty;
        }

        /* Sort dates chronologically */
        $dates = array_keys($dateSet);
        usort($dates, fn($a, $b) =>
            Carbon::createFromFormat('d/m/Y', $a)->timestamp <=>
            Carbon::createFromFormat('d/m/Y', $b)->timestamp
        );
        $this->dates = $dates;

        /* Sort services by type (1=D, 2=A, 3=C, 4=R) then name */
        $svcs = array_keys($svcTypeMap);
        usort($svcs, fn($a, $b) =>
            ($svcTypeMap[$a] ?? 99) <=> ($svcTypeMap[$b] ?? 99) ?: strcmp($a, $b)
        );
        $this->services = $svcs;

        /* Sort subdealerships and record row ranges */
        sort($sdOrder);
        $currentRow = self::DATA_START;
        foreach ($sdOrder as $sd) {
            ksort($this->matrix[$sd]);
            $count = count($this->matrix[$sd]);
            $this->sdGroups[] = ['sd' => $sd, 'start' => $currentRow, 'count' => $count];
            $currentRow += $count;
        }

        /* Compute grand totals per service */
        foreach ($this->services as $svc) {
            $total = 0;
            foreach ($this->matrix as $persons) {
                foreach ($persons as $cells) {
                    foreach ($this->dates as $date) {
                        $total += $cells[$date . '|' . $svc] ?? 0;
                    }
                }
            }
            $this->grandTotals[$svc] = $total;
        }
    }

    /* ── Number of extra "TOTAL DE CONSUMO" columns appended on the right ── */
    private function nExtraCols(): int
    {
        return count($this->services);
    }

    /* ── Total column count (dates + TOTAL DE CONSUMO) ── */
    private function totalCols(): int
    {
        return self::FIXED_COLS + count($this->dates) * count($this->services) + $this->nExtraCols();
    }

    /* ── 0-based column index of the first TOTAL DE CONSUMO service column ── */
    private function totalConsumoStartIdx(): int
    {
        return self::FIXED_COLS + count($this->dates) * count($this->services);
    }

    public function array(): array
    {
        if (empty($this->dates) || empty($this->services)) {
            return [['Sin datos para el período seleccionado.']];
        }

        $fmt       = fn(string $d) => Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');
        $nSvcs     = count($this->services);
        $nTotCols  = $this->totalCols();
        $tcStart   = $this->totalConsumoStartIdx(); // 0-based
        $empty     = array_fill(0, $nTotCols, '');

        /* ── Row 1: cafe title ── */
        $title    = $empty;
        $title[0] = 'CAFETERÍA: ' . strtoupper($this->cafeName);

        /* ── Row 2: period ── */
        $period    = $empty;
        $period[0] = 'Período: ' . $fmt($this->startDate) . ' — ' . $fmt($this->endDate);

        /* ── Row 4: header level 1 (dates + TOTAL DE CONSUMO) ── */
        $header1 = ['EMPRESA', 'APELLIDOS Y NOMBRES'];
        foreach ($this->dates as $date) {
            $header1[] = $date;
            for ($i = 1; $i < $nSvcs; $i++) $header1[] = '';
        }
        $header1[] = 'TOTAL DE CONSUMO';
        for ($i = 1; $i < $nSvcs; $i++) $header1[] = '';

        /* ── Row 5: header level 2 (service names repeated for dates + for TOTAL) ── */
        $header2 = ['', ''];
        foreach ($this->dates as $_) {
            foreach ($this->services as $svc) $header2[] = $svc;
        }
        foreach ($this->services as $svc) $header2[] = $svc;

        $rows = [$title, $period, $empty, $header1, $header2];

        /* ── Data rows ── */
        foreach ($this->sdGroups as $group) {
            $sd      = $group['sd'];
            $persons = array_keys($this->matrix[$sd]);
            $first   = true;

            foreach ($persons as $person) {
                $row = [$first ? $sd : '', $person];

                /* Per-date quantities */
                foreach ($this->dates as $date) {
                    foreach ($this->services as $svc) {
                        $qty   = $this->matrix[$sd][$person][$date . '|' . $svc] ?? null;
                        $row[] = ($qty !== null && $qty > 0) ? (int) $qty : '';
                    }
                }

                /* TOTAL DE CONSUMO: sum across dates per service */
                foreach ($this->services as $svc) {
                    $rowTotal = 0;
                    foreach ($this->dates as $date) {
                        $rowTotal += $this->matrix[$sd][$person][$date . '|' . $svc] ?? 0;
                    }
                    $row[] = $rowTotal > 0 ? $rowTotal : '';
                }

                $rows[] = $row;
                $first  = false;
            }
        }

        /* ── Totals row (per-date totals + TOTAL DE CONSUMO grand totals) ── */
        $totals        = ['', 'TOTAL'];
        $dataRowArrays = array_slice($rows, self::DATA_START - 1);

        for ($ci = self::FIXED_COLS; $ci < $nTotCols; $ci++) {
            $sum = 0;
            foreach ($dataRowArrays as $dr) {
                $v = $dr[$ci] ?? '';
                $sum += is_numeric($v) ? (int) $v : 0;
            }
            $totals[] = $sum > 0 ? $sum : '';
        }
        $rows[] = $totals;

        /* ── Unit-price row (under TOTAL DE CONSUMO columns only) ── */
        $priceRow = $empty;
        foreach ($this->services as $si => $svc) {
            $priceRow[$tcStart + $si] = 'S/' . number_format($this->svcPrices[$svc] ?? 0, 2);
        }
        $rows[] = $priceRow;

        /* ── Amount row (grand total qty × price per service) ── */
        $amtRow    = $empty;
        $subtotal  = 0.0;
        foreach ($this->services as $si => $svc) {
            $qty   = $this->grandTotals[$svc] ?? 0;
            $price = $this->svcPrices[$svc]   ?? 0;
            $amt   = $qty * $price;
            $subtotal += $amt;
            $amtRow[$tcStart + $si] = 'S/' . number_format($amt, 2);
        }
        $rows[] = $amtRow;

        /* ── TOTAL FACTURAR block ── */
        $igv   = $subtotal * 0.18;
        $total = $subtotal + $igv;

        $labelCol = $tcStart + $nSvcs - 2; // second-to-last TOTAL col (0-based)
        $valueCol = $tcStart + $nSvcs - 1; // last col (0-based)

        /* header */
        $r = $empty; $r[$labelCol] = 'TOTAL FACTURAR'; $r[$valueCol] = 'S/' . number_format($subtotal, 2);
        $rows[] = $r;
        /* extras */
        $r = $empty; $r[$labelCol] = 'EXTRAS'; $r[$valueCol] = '';
        $rows[] = $r;
        /* subtotal */
        $r = $empty; $r[$labelCol] = 'SUBTOTAL'; $r[$valueCol] = 'S/' . number_format($subtotal, 2);
        $rows[] = $r;
        /* igv */
        $r = $empty; $r[$labelCol] = 'IGV'; $r[$valueCol] = number_format($igv, 2);
        $rows[] = $r;
        /* total */
        $r = $empty; $r[$labelCol] = 'TOTAL'; $r[$valueCol] = 'S/' . number_format($total, 2);
        $rows[] = $r;

        return $rows;
    }

    public function title(): string { return 'RESUMEN'; }

    /* ── Styles ── */
    public function styles(Worksheet $sheet): void
    {
        if (empty($this->dates) || empty($this->services)) return;

        $nSvcs       = count($this->services);
        $nDates      = count($this->dates);
        $totalCols   = $this->totalCols();
        $tcStart     = $this->totalConsumoStartIdx(); // 0-based
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);

        $totalPersonRows = array_sum(array_column($this->sdGroups, 'count'));
        $lastDataRow = self::DATA_START + $totalPersonRows - 1;
        $totalsRow   = $lastDataRow + 1;
        $priceRow    = $totalsRow + 1;
        $amtRow      = $totalsRow + 2;
        $facRow      = $totalsRow + 3; // TOTAL FACTURAR header
        $extrasRow   = $totalsRow + 4;
        $subtotalRow = $totalsRow + 5;
        $igvRow      = $totalsRow + 6;
        $totalRow    = $totalsRow + 7;

        /* TOTAL DE CONSUMO first data column letter (1-based) */
        $tcFirstLetter = Coordinate::stringFromColumnIndex($tcStart + 1);
        $tcLastLetter  = $lastCol;

        /* Label and value col letters for TOTAL FACTURAR */
        $labelColLetter = Coordinate::stringFromColumnIndex($tcStart + $nSvcs - 1);
        $valueColLetter = $lastCol;

        /* ── Merge title / period ── */
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

        /* ── Header row 4: dates + TOTAL DE CONSUMO (dark navy) ── */
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E3A5F']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);

        /* Merge date cells in row 4 */
        if ($nSvcs > 1) {
            for ($i = 0; $i < $nDates; $i++) {
                $s = self::FIXED_COLS + 1 + $i * $nSvcs;
                $e = $s + $nSvcs - 1;
                $sheet->mergeCells(Coordinate::stringFromColumnIndex($s) . '4:' . Coordinate::stringFromColumnIndex($e) . '4');
            }
            /* Merge TOTAL DE CONSUMO header */
            $tcS = $tcStart + 1;
            $tcE = $tcStart + $nSvcs;
            $sheet->mergeCells(Coordinate::stringFromColumnIndex($tcS) . '4:' . Coordinate::stringFromColumnIndex($tcE) . '4');
        }

        /* Style TOTAL DE CONSUMO header differently (teal accent) */
        $sheet->getStyle("{$tcFirstLetter}4:{$tcLastLetter}4")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
        ]);

        /* ── Header row 5: service names (medium blue) ── */
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5FA8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2D5FA8']]],
        ]);
        /* TOTAL DE CONSUMO service headers: teal */
        $sheet->getStyle("{$tcFirstLetter}5:{$tcLastLetter}5")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(18);

        /* ── Data rows ── */
        if ($lastDataRow >= self::DATA_START) {
            $sheet->getStyle("A" . self::DATA_START . ":{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                'font'    => ['size' => 9],
            ]);
            for ($r = self::DATA_START; $r <= $lastDataRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
                for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($c) . $r)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                /* Teal background for TOTAL DE CONSUMO data columns */
                $sheet->getStyle("{$tcFirstLetter}{$r}:{$tcLastLetter}{$r}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDFA']],
                ]);
            }
        }

        /* ── Totals row ── */
        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        /* TOTAL DE CONSUMO totals in teal */
        $sheet->getStyle("{$tcFirstLetter}{$totalsRow}:{$tcLastLetter}{$totalsRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCFBF1']],
        ]);
        $sheet->getStyle("B{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
            $sheet->getStyle(Coordinate::stringFromColumnIndex($c) . $totalsRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        /* ── Unit-price row ── */
        $sheet->getStyle("{$tcFirstLetter}{$priceRow}:{$tcLastLetter}{$priceRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '0F766E']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDFA']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'A7F3D0']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        /* ── Amount row ── */
        $sheet->getStyle("{$tcFirstLetter}{$amtRow}:{$tcLastLetter}{$amtRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '0F766E']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCFBF1']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6EE7B7']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        /* ── TOTAL FACTURAR block ── */
        /* Header row */
        $sheet->mergeCells("{$labelColLetter}{$facRow}:{$valueColLetter}{$facRow}");
        $sheet->getStyle("{$labelColLetter}{$facRow}:{$valueColLetter}{$facRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E3A5F']]],
        ]);
        $sheet->getRowDimension($facRow)->setRowHeight(20);

        /* Inner rows: EXTRAS, SUBTOTAL, IGV, TOTAL */
        $innerRows = [$extrasRow, $subtotalRow, $igvRow];
        foreach ($innerRows as $r) {
            $sheet->getStyle("{$labelColLetter}{$r}:{$valueColLetter}{$r}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
        }

        /* TOTAL row */
        $sheet->getStyle("{$labelColLetter}{$totalRow}:{$valueColLetter}{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F766E']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getRowDimension($totalRow)->setRowHeight(22);

        /* ── EMPRESA column merges ── */
        foreach ($this->sdGroups as $group) {
            $endRow = $group['start'] + $group['count'] - 1;
            if ($group['count'] > 1) {
                $sheet->mergeCells("A{$group['start']}:A{$endRow}");
            }
            $sheet->getStyle("A{$group['start']}:A{$endRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getStyle("A{$endRow}:{$lastCol}{$endRow}")->getBorders()
                ->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()->setRGB('94A3B8');
        }

        /* ── Column widths ── */
        $sheet->getColumnDimension('A')->setWidth(22);
        for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(10);
        }
    }
}
