<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet de matriz diaria (SISTEMA, VISITAS, REFRIGERIOS).
 *
 * Estructura de columnas:
 *   SISTEMA  : N° | APELLIDOS Y NOMBRES | [date1] DD AA CC | ... | TOTAL DD AA CC
 *   VISITAS  : ITEM | DNI | APELLIDOS Y NOMBRES | [date1] DD AA CC | ... | TOTAL DD AA CC
 */
class AttendanceMatrixSheet implements FromArray, WithStyles, WithTitle
{
    private const TYPE_LABELS = [1 => 'DD', 2 => 'AA', 3 => 'CC'];

    /** Final 2-D array sent to Maatwebsite */
    private array $matrixArray = [];

    /** Column index (1-based) where dates start */
    private int $dateColStart;

    /** Number of unique dates */
    private int $numDates;

    /** Number of service types (always 3: D/A/C) */
    private const NUM_TYPES = 3;

    /** Aggregated data: person_key → date → type → count */
    private array $data = [];

    /** person_key → [name, dni] */
    private array $persons = [];

    /** Representative prices per type */
    private array $prices = [];

    public function __construct(
        private readonly string     $sheetName,
        private readonly Collection $sales,      // Sale models with tickets.ticket_details loaded
        private readonly array      $allDates,   // sorted date strings
        private readonly string     $startDate,
        private readonly string     $endDate,
        private readonly string     $title,
        private readonly bool       $showDni,
        private readonly array      $serviceTypes, // e.g. [1,2,3] or [4]
    ) {
        $this->numDates     = count($allDates);
        $this->dateColStart = $showDni ? 4 : 3; // 1-based col index
        $this->buildData();
        $this->buildArray();
    }

    /* ──────────────────────────────────────────────────────────
     |  Aggregate attendance from Sale collection
     ─────────────────────────────────────────────────────────── */
    private function buildData(): void
    {
        $pricesBucket = array_fill_keys($this->serviceTypes, []);

        foreach ($this->sales as $sale) {
            $date = $sale->date;
            foreach ($sale->tickets as $ticket) {
                $personKey = strtoupper(trim($ticket->dinner_name ?? 'DESCONOCIDO'))
                    . '|' . ($ticket->dni ?? '');

                if (!isset($this->persons[$personKey])) {
                    $this->persons[$personKey] = [
                        'name' => strtoupper(trim($ticket->dinner_name ?? 'DESCONOCIDO')),
                        'dni'  => $ticket->dni ?? '',
                    ];
                }

                foreach ($ticket->ticket_details as $detail) {
                    $type = (int) $detail->service_type;
                    if (!in_array($type, $this->serviceTypes)) continue;

                    $amount    = (int) ($detail->amount ?? 1);
                    $unitPrice = (float) ($detail->unit_price ?? 0);

                    $this->data[$personKey][$date][$type] = ($this->data[$personKey][$date][$type] ?? 0) + $amount;

                    if ($unitPrice > 0) $pricesBucket[$type][] = $unitPrice;
                }
            }
        }

        // Sort persons alphabetically
        ksort($this->persons);

        // Average prices
        $avg = fn(array $arr) => count($arr) ? round(array_sum($arr) / count($arr), 2) : 0;
        foreach ($this->serviceTypes as $type) {
            $this->prices[$type] = $avg($pricesBucket[$type]);
        }
    }

    /* ──────────────────────────────────────────────────────────
     |  Build the 2-D array
     ─────────────────────────────────────────────────────────── */
    private function buildArray(): void
    {
        $types      = $this->serviceTypes;
        $numTypes   = count($types);
        $totalCols  = ($this->dateColStart - 1) + ($this->numDates * $numTypes) + $numTypes;

        $blank = array_fill(0, $totalCols, '');

        /* ── Row 1: Title ── */
        $r1 = $blank;
        $r1[0] = $this->title;
        $this->matrixArray[] = $r1;

        /* ── Row 2: Header – N°, [DNI], Name, dates (merged 3 cols each), TOTAL ── */
        $r2 = $blank;
        $r2[0] = $this->showDni ? 'ITEM' : 'N°';
        if ($this->showDni) $r2[1] = 'DNI';
        $r2[$this->dateColStart - 2] = 'APELLIDOS Y NOMBRES'; // 0-based: dateColStart-2
        foreach ($this->allDates as $i => $date) {
            $colIdx = ($this->dateColStart - 1) + $i * $numTypes; // 0-based
            $r2[$colIdx] = \Carbon\Carbon::parse($date)->format('d/m/Y');
        }
        // TOTAL label
        $totalLabelCol = ($this->dateColStart - 1) + $this->numDates * $numTypes;
        $r2[$totalLabelCol] = 'TOTAL DE CONSUMO';
        $this->matrixArray[] = $r2;

        /* ── Row 3: Sub-header – DD AA CC per date + totals ── */
        $r3 = $blank;
        foreach ($this->allDates as $i => $_) {
            foreach ($types as $j => $type) {
                $colIdx = ($this->dateColStart - 1) + $i * $numTypes + $j;
                $r3[$colIdx] = self::TYPE_LABELS[$type] ?? "T{$type}";
            }
        }
        foreach ($types as $j => $type) {
            $colIdx = ($this->dateColStart - 1) + $this->numDates * $numTypes + $j;
            $r3[$colIdx] = self::TYPE_LABELS[$type] ?? "T{$type}";
        }
        $this->matrixArray[] = $r3;

        /* ── Data rows ── */
        $num = 1;
        $totalsByType = array_fill_keys($types, 0);

        foreach ($this->persons as $personKey => $person) {
            $row = $blank;
            $row[0] = $num++;
            if ($this->showDni) $row[1] = $person['dni'];
            $row[$this->dateColStart - 2] = $person['name'];

            $rowTotals = array_fill_keys($types, 0);

            foreach ($this->allDates as $i => $date) {
                foreach ($types as $j => $type) {
                    $count  = $this->data[$personKey][$date][$type] ?? 0;
                    $colIdx = ($this->dateColStart - 1) + $i * $numTypes + $j;
                    $row[$colIdx] = $count ?: '';
                    $rowTotals[$type] += $count;
                }
            }

            // Row totals
            foreach ($types as $j => $type) {
                $colIdx = ($this->dateColStart - 1) + $this->numDates * $numTypes + $j;
                $row[$colIdx] = $rowTotals[$type] ?: 0;
                $totalsByType[$type] += $rowTotals[$type];
            }

            $this->matrixArray[] = $row;
        }

        /* ── Totals row ── */
        $totRow = $blank;
        foreach ($types as $j => $type) {
            $colIdx = ($this->dateColStart - 1) + $this->numDates * $numTypes + $j;
            $totRow[$colIdx] = $totalsByType[$type];
        }
        $this->matrixArray[] = $totRow;

        /* ── Price / Amount summary (only for VISITAS) ── */
        if ($this->showDni) {
            $priceRow = $blank;
            $amtRow   = $blank;
            foreach ($types as $j => $type) {
                $colIdx = ($this->dateColStart - 1) + $this->numDates * $numTypes + $j;
                $priceRow[$colIdx] = 'S/' . number_format($this->prices[$type] ?? 0, 2);
                $amtRow[$colIdx]   = 'S/' . number_format(($totalsByType[$type] ?? 0) * ($this->prices[$type] ?? 0), 2);
            }
            $this->matrixArray[] = $priceRow;
            $this->matrixArray[] = $amtRow;

            // TOTAL FACTURAR block
            $grandTotal = array_sum(array_map(
                fn($type) => ($totalsByType[$type] ?? 0) * ($this->prices[$type] ?? 0),
                $types
            ));
            $igv   = round($grandTotal * 0.18, 2);
            $total = round($grandTotal + $igv, 2);

            $emptyRow = $blank;
            $this->matrixArray[] = $emptyRow;

            $r = $blank; $r[$totalCols - 1] = 'TOTAL FACTURAR';
            $this->matrixArray[] = $r;
            $r = $blank; $r[$totalCols - 2] = 'S/' . number_format($grandTotal, 2); $r[$totalCols - 1] = '';
            $this->matrixArray[] = $r;
            $r = $blank; $r[$totalCols - 2] = 'EXTRAS'; $this->matrixArray[] = $r;
            $r = $blank; $r[$totalCols - 2] = 'SUBTOTAL'; $r[$totalCols - 1] = 'S/' . number_format($grandTotal, 2);
            $this->matrixArray[] = $r;
            $r = $blank; $r[$totalCols - 2] = 'IGV'; $r[$totalCols - 1] = number_format($igv, 3);
            $this->matrixArray[] = $r;
            $r = $blank; $r[$totalCols - 2] = 'TOTAL'; $r[$totalCols - 1] = 'S/' . number_format($total, 2);
            $this->matrixArray[] = $r;
        }
    }

    public function array(): array
    {
        return $this->matrixArray;
    }

    public function title(): string
    {
        return substr(preg_replace('/[\/\\\?\*\[\]:]/', '', $this->sheetName), 0, 31);
    }

    /* ──────────────────────────────────────────────────────────
     |  Styles
     ─────────────────────────────────────────────────────────── */
    public function styles(Worksheet $sheet): void
    {
        $types    = $this->serviceTypes;
        $numTypes = count($types);
        $dataRows = count($this->persons);
        $lastDataRow = 3 + $dataRows; // row 4 = first data, row 3+dataRows = last

        $totalCols    = ($this->dateColStart - 1) + ($this->numDates * $numTypes) + $numTypes;
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);
        $firstNameCol  = Coordinate::stringFromColumnIndex($this->dateColStart - 1);

        /* ── Title row (row 1) ── */
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        /* ── Header rows 2-3: freeze pane ── */
        $sheet->freezePane('A4');

        /* ── Merge date headers in row 2 ── */
        foreach ($this->allDates as $i => $_) {
            $startCol = ($this->dateColStart - 1) + $i * $numTypes + 1; // 1-based
            $endCol   = $startCol + $numTypes - 1;
            if ($startCol !== $endCol) {
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($startCol) . '2:' .
                    Coordinate::stringFromColumnIndex($endCol)   . '2'
                );
            }
        }

        // Merge TOTAL DE CONSUMO header
        $totStart = ($this->dateColStart - 1) + $this->numDates * $numTypes + 1;
        $totEnd   = $totStart + $numTypes - 1;
        if ($totStart !== $totEnd) {
            $sheet->mergeCells(
                Coordinate::stringFromColumnIndex($totStart) . '2:' .
                Coordinate::stringFromColumnIndex($totEnd)   . '2'
            );
        }

        /* ── Header rows 2-3 style ── */
        $sheet->getStyle("A2:{$lastColLetter}3")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2E75B6']]],
        ]);

        // Name column header background
        $sheet->getStyle("{$firstNameCol}2:{$firstNameCol}3")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E75B6');

        // TOTAL DE CONSUMO header different color
        $sheet->getStyle(
            Coordinate::stringFromColumnIndex($totStart) . '2:' .
            Coordinate::stringFromColumnIndex($totEnd)   . '3'
        )->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('375623');

        /* ── Data rows ── */
        if ($dataRows > 0) {
            $dataRange = "A4:{$lastColLetter}{$lastDataRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'font'    => ['size' => 9],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);

            // Alternating rows
            for ($r = 4; $r <= $lastDataRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBF3FB');
                }
                // Center attendance values
                $dateDataStart = Coordinate::stringFromColumnIndex($this->dateColStart);
                $sheet->getStyle("{$dateDataStart}{$r}:{$lastColLetter}{$r}")
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        /* ── Totals row ── */
        $totalsRow = $lastDataRow + 1;
        $sheet->getStyle("A{$totalsRow}:{$lastColLetter}{$totalsRow}")->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        /* ── Column widths ── */
        $sheet->getColumnDimension('A')->setWidth(6);
        if ($this->showDni) $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension($firstNameCol)->setWidth(32);

        for ($i = 0; $i < $this->numDates * $numTypes + $numTypes; $i++) {
            $col = Coordinate::stringFromColumnIndex($this->dateColStart + $i);
            $sheet->getColumnDimension($col)->setWidth(5);
        }

        /* ── VISITAS summary styling ── */
        if ($this->showDni) {
            $priceRow = $totalsRow + 1;
            $amtRow   = $totalsRow + 2;

            $sheet->getStyle("A{$priceRow}:{$lastColLetter}{$priceRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle("A{$amtRow}:{$lastColLetter}{$amtRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // TOTAL row (last rows)
            $numExtraRows = 7;
            $lastExtraRow = $amtRow + $numExtraRows;
            $totalRow     = $lastExtraRow;
            $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            ]);
        }
    }
}
