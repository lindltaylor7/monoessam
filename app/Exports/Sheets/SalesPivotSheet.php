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
 * Cross-tab: rows = [EMPRESA > NOMBRE], columns = [DATE > SERVICE_NAME (qty only)].
 * Services ordered by type: 1=Desayuno, 2=Almuerzo, 3=Cena, 4=Refrigerio.
 */
class SalesPivotSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** Chronologically-sorted date strings (dd/mm/yyyy) */
    private array $dates = [];

    /** Service names sorted by type (D→A→C→R) */
    private array $services = [];

    /** [sd_name][name][date|svc_name] => qty */
    private array $matrix = [];

    /** [{sd, start_row, count}] */
    private array $sdGroups = [];

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

    private function buildMatrix(): void
    {
        $dateSet    = [];
        /** svc_name => svc_type (for ordering) */
        $svcTypeMap = [];
        $sdOrder    = [];

        foreach ($this->rows as $row) {
            $sd   = $row['sd_name'];
            $name = $row['name'];
            $date = $row['date'];
            $svc  = $row['svc_name'];
            $qty  = (int) ($row['amount'] ?? 1);
            $type = (int) ($row['svc_type'] ?? 99);

            $dateSet[$date]       = true;
            $svcTypeMap[$svc]     = $type;

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

        /* Sort subdealerships and record row ranges for EMPRESA cell merges */
        sort($sdOrder);
        $currentRow = self::DATA_START;
        foreach ($sdOrder as $sd) {
            ksort($this->matrix[$sd]);
            $count = count($this->matrix[$sd]);
            $this->sdGroups[] = ['sd' => $sd, 'start' => $currentRow, 'count' => $count];
            $currentRow += $count;
        }
    }

    public function array(): array
    {
        if (empty($this->dates) || empty($this->services)) {
            return [['Sin datos para el período seleccionado.']];
        }

        $fmt   = fn(string $d) => Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');
        $nSvcs = count($this->services);
        $nCols = self::FIXED_COLS + count($this->dates) * $nSvcs;
        $empty = array_fill(0, $nCols, '');

        /* Row 1 – cafe title */
        $title    = $empty;
        $title[0] = 'CAFETERÍA: ' . strtoupper($this->cafeName);

        /* Row 2 – period */
        $period    = $empty;
        $period[0] = 'Período: ' . $fmt($this->startDate) . ' — ' . $fmt($this->endDate);

        /* Row 4 – header level 1: dates (each merges over nSvcs cols) */
        $header1 = ['EMPRESA', 'APELLIDOS Y NOMBRES'];
        foreach ($this->dates as $date) {
            $header1[] = $date;
            for ($i = 1; $i < $nSvcs; $i++) {
                $header1[] = '';
            }
        }

        /* Row 5 – header level 2: service names (one col each) */
        $header2 = ['', ''];
        foreach ($this->dates as $_) {
            foreach ($this->services as $svc) {
                $header2[] = $svc;
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
                        $key   = $date . '|' . $svc;
                        $qty   = $this->matrix[$sd][$person][$key] ?? null;
                        $row[] = ($qty !== null && $qty > 0) ? (int) $qty : '';
                    }
                }
                $rows[] = $row;
                $first  = false;
            }
        }

        /* Totals row */
        $totals        = ['', 'TOTAL'];
        $dataRowArrays = array_slice($rows, self::DATA_START - 1);
        foreach ($this->dates as $di => $_) {
            foreach ($this->services as $si => $_s) {
                $colIdx = self::FIXED_COLS + $di * $nSvcs + $si; // 0-based
                $sum    = 0;
                foreach ($dataRowArrays as $dr) {
                    $v = $dr[$colIdx] ?? '';
                    $sum += is_numeric($v) ? (int) $v : 0;
                }
                $totals[] = $sum > 0 ? $sum : '';
            }
        }
        $rows[] = $totals;

        return $rows;
    }

    public function title(): string
    {
        return 'RESUMEN';
    }

    public function styles(Worksheet $sheet): void
    {
        if (empty($this->dates) || empty($this->services)) {
            return;
        }

        $nSvcs     = count($this->services);
        $totalCols = self::FIXED_COLS + count($this->dates) * $nSvcs;
        $lastCol   = Coordinate::stringFromColumnIndex($totalCols);

        $totalRows   = array_sum(array_column($this->sdGroups, 'count'));
        $lastDataRow = self::DATA_START + $totalRows - 1;
        $totalsRow   = $lastDataRow + 1;

        /* ── Title / period merges ── */
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

        /* Merge date cells in row 4 (each spans nSvcs columns) */
        if ($nSvcs > 1) {
            foreach ($this->dates as $i => $_) {
                $startIdx = self::FIXED_COLS + 1 + $i * $nSvcs; // 1-based
                $endIdx   = $startIdx + $nSvcs - 1;
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($startIdx) . '4:' .
                    Coordinate::stringFromColumnIndex($endIdx)   . '4'
                );
            }
        }

        /* ── Header row 5: service names (medium blue) ── */
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
                for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($c) . $r)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        }

        /* ── Totals row ── */
        $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']]],
        ]);
        $sheet->getStyle("B{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
            $sheet->getStyle(Coordinate::stringFromColumnIndex($c) . $totalsRow)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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
            $sheet->getStyle("A{$endRow}:{$lastCol}{$endRow}")->getBorders()
                ->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()->setRGB('94A3B8');
        }

        /* ── Column widths ── */
        $sheet->getColumnDimension('A')->setWidth(22);
        // Column B auto-sized (ShouldAutoSize)
        for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(10);
        }
    }
}
