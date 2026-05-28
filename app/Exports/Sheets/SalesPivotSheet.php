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

class SalesPivotSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private array $dates    = [];   // sorted dd/mm/yyyy strings
    private array $codes    = [];   // sorted service codes
    private array $matrix   = [];   // [sd_name][name][(date)|(code)] => qty
    private array $sdGroups = [];   // [{sd, start_row, count}]

    private const FIXED_COLS = 2;  // EMPRESA + APELLIDOS Y NOMBRES
    private const DATA_START = 6;  // rows 1-5 = title, period, spacer, header1, header2

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
        $codeSet = [];
        $sdOrder = [];

        foreach ($this->rows as $row) {
            $sd   = $row['sd_name'];
            $name = $row['name'];
            $date = $row['date'];
            $code = $row['svc_code'];
            $amt  = $row['amount'];

            $dateSet[$date] = true;
            $codeSet[$code] = true;

            if (!isset($this->matrix[$sd])) {
                $this->matrix[$sd] = [];
                $sdOrder[] = $sd;
            }
            if (!isset($this->matrix[$sd][$name])) {
                $this->matrix[$sd][$name] = [];
            }
            $key = $date . '|' . $code;
            $this->matrix[$sd][$name][$key] = ($this->matrix[$sd][$name][$key] ?? 0) + $amt;
        }

        /* Sort dates chronologically */
        $dates = array_keys($dateSet);
        usort($dates, fn($a, $b) =>
            Carbon::createFromFormat('d/m/Y', $a)->timestamp <=>
            Carbon::createFromFormat('d/m/Y', $b)->timestamp
        );
        $this->dates = $dates;

        /* Sort codes alphabetically */
        $codes = array_keys($codeSet);
        sort($codes);
        $this->codes = $codes;

        /* Sort subdealerships and record row ranges for merging */
        sort($sdOrder);
        $currentRow = self::DATA_START;
        foreach ($sdOrder as $sd) {
            ksort($this->matrix[$sd]);
            $count = count($this->matrix[$sd]);
            $this->sdGroups[] = ['sd' => $sd, 'start' => $currentRow, 'count' => $count];
            $currentRow += $count;
        }
    }

    /* ── Array data returned to Maatwebsite ── */
    public function array(): array
    {
        if (empty($this->dates) || empty($this->codes)) {
            return [['Sin datos para el período seleccionado.']];
        }

        $fmt   = fn(string $d) => Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');
        $nCols = self::FIXED_COLS + count($this->dates) * count($this->codes);
        $empty = array_fill(0, $nCols, '');

        /* Row 1: cafe title */
        $title    = $empty;
        $title[0] = 'CAFETERÍA: ' . strtoupper($this->cafeName);

        /* Row 2: date period */
        $period    = $empty;
        $period[0] = 'Período: ' . $fmt($this->startDate) . ' — ' . $fmt($this->endDate);

        /* Row 4: header level 1 — dates (each spans N code columns) */
        $header1 = ['EMPRESA', 'APELLIDOS Y NOMBRES'];
        foreach ($this->dates as $date) {
            $header1[] = $date;
            for ($i = 1; $i < count($this->codes); $i++) {
                $header1[] = '';
            }
        }

        /* Row 5: header level 2 — service codes */
        $header2 = ['', ''];
        foreach ($this->dates as $date) {
            foreach ($this->codes as $code) {
                $header2[] = $code;
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
                    foreach ($this->codes as $code) {
                        $key   = $date . '|' . $code;
                        $val   = $this->matrix[$sd][$person][$key] ?? null;
                        $row[] = ($val !== null && $val > 0) ? (int) $val : '';
                    }
                }

                $rows[] = $row;
                $first  = false;
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'RESUMEN';
    }

    /* ── Styles ── */
    public function styles(Worksheet $sheet): void
    {
        if (empty($this->dates) || empty($this->codes)) {
            return;
        }

        $nCodes    = count($this->codes);
        $totalCols = self::FIXED_COLS + count($this->dates) * $nCodes;
        $lastCol   = Coordinate::stringFromColumnIndex($totalCols);
        $totalRows = array_sum(array_column($this->sdGroups, 'count'));
        $lastDataRow = self::DATA_START + $totalRows - 1;

        /* ── Merge title / period rows ── */
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");

        /* ── Title styling ── */
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

        /* ── Header row 4 — dates (dark blue) ── */
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82A0']]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);

        /* Merge date cells in row 4 when there are multiple codes per date */
        if ($nCodes > 1) {
            foreach ($this->dates as $i => $date) {
                $startIdx = self::FIXED_COLS + 1 + $i * $nCodes; // 1-based
                $endIdx   = $startIdx + $nCodes - 1;
                $startLetter = Coordinate::stringFromColumnIndex($startIdx);
                $endLetter   = Coordinate::stringFromColumnIndex($endIdx);
                $sheet->mergeCells("{$startLetter}4:{$endLetter}4");
            }
        }

        /* ── Header row 5 — service codes (medium blue) ── */
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5FA8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82A0']]],
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
                // Center all quantity columns
                for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($c) . $r)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        }

        /* ── EMPRESA column: merge rows per group + style ── */
        foreach ($this->sdGroups as $group) {
            $endRow = $group['start'] + $group['count'] - 1;

            if ($group['count'] > 1) {
                $sheet->mergeCells("A{$group['start']}:A{$endRow}");
            }

            $sheet->getStyle("A{$group['start']}:A{$endRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
            ]);

            /* Subtle bottom border to separate groups */
            $sheet->getStyle("A{$endRow}:{$lastCol}{$endRow}")->getBorders()
                ->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)
                ->getColor()->setRGB('94A3B8');
        }

        /* ── Column widths ── */
        $sheet->getColumnDimension('A')->setWidth(22); // EMPRESA
        // Column B (APELLIDOS Y NOMBRES) left to ShouldAutoSize
        // Data columns: fixed narrow
        for ($c = self::FIXED_COLS + 1; $c <= $totalCols; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(7);
        }
    }
}
