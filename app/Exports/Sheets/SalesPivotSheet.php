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
 * Cross-tab: rows = [EMPRESA > NOMBRE], columns = [DATE > SERVICE_NAME > (CANT | S/)].
 */
class SalesPivotSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** Unique, chronologically-sorted date strings (dd/mm/yyyy) */
    private array $dates = [];

    /** Unique, alphabetically-sorted service names */
    private array $services = [];

    /** [sd_name][name][date|svc_name] => ['qty' => int, 'price' => float] */
    private array $matrix = [];

    /** [{sd, start_row, count}] — for EMPRESA cell merges */
    private array $sdGroups = [];

    private const FIXED_COLS = 2;  // EMPRESA + APELLIDOS Y NOMBRES
    private const COLS_PER_SVC = 2; // CANT + S/
    private const DATA_START = 6;  // rows 1-5: title, period, spacer, header-dates, header-services

    public function __construct(
        private readonly array  $rows,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly string $cafeName,
    ) {
        $this->buildMatrix();
    }

    /* ── Build cross-tab structure ── */
    private function buildMatrix(): void
    {
        $dateSet = [];
        $svcSet  = [];
        $sdOrder = [];

        foreach ($this->rows as $row) {
            $sd    = $row['sd_name'];
            $name  = $row['name'];
            $date  = $row['date'];
            $svc   = $row['svc_name'];
            $qty   = $row['amount'];
            $price = (float) ($row['unit_price'] ?? 0);

            $dateSet[$date] = true;
            $svcSet[$svc]   = true;

            if (!isset($this->matrix[$sd])) {
                $this->matrix[$sd] = [];
                $sdOrder[] = $sd;
            }
            if (!isset($this->matrix[$sd][$name])) {
                $this->matrix[$sd][$name] = [];
            }
            $key = $date . '|' . $svc;
            $prev = $this->matrix[$sd][$name][$key] ?? ['qty' => 0, 'price' => 0.0];
            $this->matrix[$sd][$name][$key] = [
                'qty'   => $prev['qty'] + $qty,
                'price' => $prev['price'] + $qty * $price,
            ];
        }

        /* Sort dates chronologically */
        $dates = array_keys($dateSet);
        usort($dates, fn($a, $b) =>
            Carbon::createFromFormat('d/m/Y', $a)->timestamp <=>
            Carbon::createFromFormat('d/m/Y', $b)->timestamp
        );
        $this->dates = $dates;

        /* Sort services alphabetically */
        $svcs = array_keys($svcSet);
        sort($svcs);
        $this->services = $svcs;

        /* Sort subdealerships and record row ranges for cell merges */
        sort($sdOrder);
        $currentRow = self::DATA_START;
        foreach ($sdOrder as $sd) {
            ksort($this->matrix[$sd]);
            $count = count($this->matrix[$sd]);
            $this->sdGroups[] = ['sd' => $sd, 'start' => $currentRow, 'count' => $count];
            $currentRow += $count;
        }
    }

    /* ── Array returned to Maatwebsite ── */
    public function array(): array
    {
        if (empty($this->dates) || empty($this->services)) {
            return [['Sin datos para el período seleccionado.']];
        }

        $fmt   = fn(string $d) => Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');
        $nSvcs = count($this->services);
        $nCols = self::FIXED_COLS + count($this->dates) * $nSvcs * self::COLS_PER_SVC;
        $empty = array_fill(0, $nCols, '');

        /* Row 1 – cafe title */
        $title    = $empty;
        $title[0] = 'CAFETERÍA: ' . strtoupper($this->cafeName);

        /* Row 2 – period */
        $period    = $empty;
        $period[0] = 'Período: ' . $fmt($this->startDate) . ' — ' . $fmt($this->endDate);

        /* Row 4 – header level 1: dates (each merges over nSvcs * COLS_PER_SVC cols) */
        $header1 = ['EMPRESA', 'APELLIDOS Y NOMBRES'];
        foreach ($this->dates as $date) {
            $header1[] = $date;
            for ($i = 1; $i < $nSvcs * self::COLS_PER_SVC; $i++) {
                $header1[] = '';
            }
        }

        /* Row 5 – header level 2: service name | S/ repeated per date */
        $header2 = ['', ''];
        foreach ($this->dates as $_) {
            foreach ($this->services as $svc) {
                $header2[] = $svc;
                $header2[] = 'S/';
            }
        }

        $rows = [$title, $period, $empty, $header1, $header2];

        /* Data rows */
        foreach ($this->sdGroups as $group) {
            $sd      = $group['sd'];
            $persons = array_keys($this->matrix[$sd]);
            $first   = true;

            foreach ($persons as $person) {
                $row = [$first ? $sd : '', $person];

                foreach ($this->dates as $date) {
                    foreach ($this->services as $svc) {
                        $key  = $date . '|' . $svc;
                        $cell = $this->matrix[$sd][$person][$key] ?? null;
                        $row[] = ($cell && $cell['qty'] > 0) ? $cell['qty'] : '';
                        $row[] = ($cell && $cell['price'] > 0) ? number_format($cell['price'], 2) : '';
                    }
                }

                $rows[] = $row;
                $first  = false;
            }
        }

        /* Totals row — sum only the S/ columns (every 2nd data col) */
        $totals = ['', 'TOTAL'];
        $dataRowArrays = array_slice($rows, self::DATA_START - 1);
        foreach ($this->dates as $di => $date) {
            foreach ($this->services as $si => $svc) {
                $qtyColIdx   = self::FIXED_COLS + ($di * $nSvcs + $si) * self::COLS_PER_SVC;       // 0-based
                $priceColIdx = $qtyColIdx + 1;

                $totQty   = 0;
                $totPrice = 0.0;
                foreach ($dataRowArrays as $dr) {
                    $qVal = $dr[$qtyColIdx]   ?? '';
                    $pVal = $dr[$priceColIdx] ?? '';
                    $totQty   += is_numeric($qVal) ? (int) $qVal : 0;
                    $totPrice += is_numeric(str_replace(',', '', $pVal)) ? (float) str_replace(',', '', $pVal) : 0.0;
                }
                $totals[] = $totQty   > 0 ? $totQty   : '';
                $totals[] = $totPrice > 0 ? number_format($totPrice, 2) : '';
            }
        }
        $rows[] = $totals;

        return $rows;
    }

    public function title(): string
    {
        return 'RESUMEN';
    }

    /* ── Styles ── */
    public function styles(Worksheet $sheet): void
    {
        if (empty($this->dates) || empty($this->services)) {
            return;
        }

        $nSvcs     = count($this->services);
        $nDataCols = count($this->dates) * $nSvcs * self::COLS_PER_SVC;
        $totalCols = self::FIXED_COLS + $nDataCols;
        $lastCol   = Coordinate::stringFromColumnIndex($totalCols);

        $totalRows   = array_sum(array_column($this->sdGroups, 'count'));
        $lastDataRow = self::DATA_START + $totalRows - 1;
        $totalsRow   = $lastDataRow + 1;

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

        /* ── Header row 4: dates (dark navy) ── */
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E3A5F']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);

        /* Merge date cells in row 4 */
        $dateMergeSpan = $nSvcs * self::COLS_PER_SVC;
        foreach ($this->dates as $i => $date) {
            $startIdx = self::FIXED_COLS + 1 + $i * $dateMergeSpan; // 1-based
            $endIdx   = $startIdx + $dateMergeSpan - 1;
            if ($startIdx < $endIdx) {
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($startIdx) . '4:' .
                    Coordinate::stringFromColumnIndex($endIdx)   . '4'
                );
            }
        }

        /* ── Header row 5: service names + S/ (medium blue) ── */
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5FA8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2D5FA8']]],
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
                /* Center CANT columns, right-align S/ columns */
                for ($di = 0; $di < count($this->dates); $di++) {
                    for ($si = 0; $si < $nSvcs; $si++) {
                        $qtyIdx   = self::FIXED_COLS + 1 + ($di * $nSvcs + $si) * self::COLS_PER_SVC;
                        $priceIdx = $qtyIdx + 1;
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($qtyIdx) . $r)
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle(Coordinate::stringFromColumnIndex($priceIdx) . $r)
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }
            }
        }

        /* ── Totals row ── */
        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        for ($di = 0; $di < count($this->dates); $di++) {
            for ($si = 0; $si < $nSvcs; $si++) {
                $qtyIdx   = self::FIXED_COLS + 1 + ($di * $nSvcs + $si) * self::COLS_PER_SVC;
                $priceIdx = $qtyIdx + 1;
                $sheet->getStyle(Coordinate::stringFromColumnIndex($qtyIdx) . $totalsRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle(Coordinate::stringFromColumnIndex($priceIdx) . $totalsRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        /* ── EMPRESA column: merge rows per group ── */
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
            /* Bottom border to separate groups */
            $sheet->getStyle("A{$endRow}:{$lastCol}{$endRow}")->getBorders()
                ->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()->setRGB('94A3B8');
        }

        /* ── Column widths ── */
        $sheet->getColumnDimension('A')->setWidth(22); // EMPRESA
        // Column B auto-sized (ShouldAutoSize)
        for ($di = 0; $di < count($this->dates); $di++) {
            for ($si = 0; $si < $nSvcs; $si++) {
                $qtyIdx   = self::FIXED_COLS + 1 + ($di * $nSvcs + $si) * self::COLS_PER_SVC;
                $priceIdx = $qtyIdx + 1;
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($qtyIdx))->setWidth(7);
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($priceIdx))->setWidth(10);
            }
        }
    }
}
