<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet "VLZ [MES]-[AÑO]"
 * Resumen valorización con info de empresa, precios por tipo y totales + IGV.
 */
class VlzSummarySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly array  $businessInfo,  // name, ruc, legal_address
        private readonly array  $unitInfo,       // name
        private readonly array  $cafeInfo,       // name
        private readonly string $aFavorDe,       // mine / subdealership name
        // Aggregated totals
        private readonly int    $cntD,
        private readonly float  $amtD,
        private readonly float  $priceD,
        private readonly int    $cntA,
        private readonly float  $amtA,
        private readonly float  $priceA,
        private readonly int    $cntC,
        private readonly float  $amtC,
        private readonly float  $priceC,
        private readonly int    $cntR,
        private readonly float  $amtR,
        private readonly float  $priceR,
    ) {}

    public function title(): string
    {
        return 'VLZ ' . strtoupper(\Carbon\Carbon::parse($this->startDate)->translatedFormat('M')) . '-' .
               \Carbon\Carbon::parse($this->startDate)->format('y');
    }

    public function array(): array
    {
        $fmt  = fn(string $d) => \Carbon\Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');
        $subTotal = round($this->amtD + $this->amtA + $this->amtC + $this->amtR, 2);
        $igv      = round($subTotal * 0.18, 2);
        $total    = round($subTotal + $igv, 2);

        $rows = [
            /* 1 */ ['', '', '', '', '', '', '', '', '', '', ''],
            /* 2 */ ['UNIDAD:',       '', $this->unitInfo['name']     ?? '', '', '', '', '', '', '', '', ''],
            /* 3 */ ['COMEDOR:',      '', $this->cafeInfo['name']     ?? '', '', '', '', '', '', '', '', ''],
            /* 4 */ ['RAZÓN SOCIAL:', '', $this->businessInfo['legal_address'] ?? $this->businessInfo['name'] ?? '', '', '', '', '', '', '', '', ''],
            /* 5 */ ['RUC:',          '', $this->businessInfo['ruc']  ?? '', '', '', '', '', '', '', '', ''],
            /* 6 */ ['A FAVOR DE:',   '', strtoupper($this->aFavorDe), '', '', '', '', '', '', '', ''],
            /* 7 */ ['', '', '', '', '', '', '', '', '', '', ''],
            /* 8 */ ['', 'Valorización Resumen: Desde el ' . $fmt($this->startDate) . ' al ' . $fmt($this->endDate), '', '', '', '', '', '', '', '', ''],
            /* 9 */ ['', 'PRECIOS SIN IGV', '', '', '', '', '', 'REFRIGERIOS', '', '', 'TOTAL'],
            /*10 */ ['', 'S/. ' . number_format($this->priceD, 2), '', 'S/. ' . number_format($this->priceA, 2), '', 'S/. ' . number_format($this->priceC, 2), '', '', '', '', ''],
            /*11 */ ['', 'DESAYUNOS', '', 'ALMUERZO', '', 'CENA', '', '', '', '', ''],
            /*12 */ ['', 'N° DESAYUNO', 'IMPORTE', 'N° ALMUERZOS', 'IMPORTE', 'CANTIDAD', 'IMPORTE', 'IMPORTE', 'IMPORTE', 'IMPORTE', 'V. VENTA'],
            /*13 */ ['', $this->cntD, number_format($this->amtD, 2), $this->cntA, number_format($this->amtA, 2), $this->cntC, number_format($this->amtC, 2), number_format($this->amtR, 2), '-', '-', number_format($subTotal, 2)],
            /*14 */ ['', $this->cntD, number_format($this->amtD, 2), $this->cntA, number_format($this->amtA, 2), $this->cntC, number_format($this->amtC, 2), number_format($this->amtR, 2), '-', '-', ''],
            /*15 */ ['', '', '', '', '', '', '', '', '', '', ''],
            /*16 */ ['', '', '', '', '', '', '', '', '', '', number_format($subTotal, 2)],
            /*17 */ ['', '', '', '', '', '', '', '', '', '', ''],
            /*18 */ ['', '', '', '', '', '', '', 'SUB TOTAL GENERAL :', '', '', number_format($subTotal, 2)],
            /*19 */ ['', '', '', '', '', '', '', 'IGV :', '', '', number_format($igv, 2)],
            /*20 */ ['', '', '', '', '', '', '', 'TOTAL GENERAL :', '', '', number_format($total, 2)],
            /*21 */ ['', '', '', '', '', '', '', '', '', '', ''],
            /*22 */ ['', '', '', '', '', '', '', '', '', '', ''],
            /*23 */ ['', '', '', '', '', '', '', '', '', '', ''],
            /*24 */ ['', 'ADMINISTRACION', '', '', '', '', 'P\' EMP. SERV. ' . strtoupper($this->businessInfo['name'] ?? ''), '', '', '', ''],
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): void
    {
        /* ── Merge title cells ── */
        $sheet->mergeCells('C2:K2'); // UNIDAD value
        $sheet->mergeCells('C3:K3'); // COMEDOR value
        $sheet->mergeCells('C4:K4'); // RAZÓN SOCIAL value
        $sheet->mergeCells('C5:K5'); // RUC value
        $sheet->mergeCells('C6:K6'); // A FAVOR DE value

        /* ── Valorización title row 8 ── */
        $sheet->mergeCells('B8:K8');
        $sheet->getStyle('B8')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(8)->setRowHeight(24);

        /* ── "PRECIOS SIN IGV" row 9 ── */
        $sheet->mergeCells('B9:G9');
        $sheet->getStyle('B9')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        // REFRIGERIOS header
        $sheet->mergeCells('H9:J9');
        $sheet->getStyle('H9:K9')->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BDD7EE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        /* ── Price row 10 ── */
        foreach (['B10:C10', 'D10:E10', 'F10:G10'] as $merge) {
            $sheet->mergeCells($merge);
        }
        $sheet->getStyle('B10:G10')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        /* ── Service name row 11 ── */
        foreach (['B11:C11', 'D11:E11', 'F11:G11'] as $merge) {
            $sheet->mergeCells($merge);
        }
        $sheet->getStyle('B11:K11')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        /* ── Column header row 12 ── */
        $sheet->getStyle('B12:K12')->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        /* ── Data row 13 ── */
        $sheet->getStyle('B13:K13')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        /* ── Totals row 14 (bold, yellow) ── */
        $sheet->getStyle('B14:K14')->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        /* ── V. VENTA amount row 16 ── */
        $sheet->getStyle('K16')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1F3864']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
        ]);

        /* ── Summary box rows 18-20 ── */
        foreach (['H18:J18', 'H19:J19', 'H20:J20'] as $merge) {
            $sheet->mergeCells($merge);
        }
        $sheet->getStyle('H18:K18')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getStyle('H19:K20')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        /* ── Label column A bold ── */
        $sheet->getStyle('A2:A6')->applyFromArray(['font' => ['bold' => true]]);

        /* ── Column widths ── */
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(14);
        foreach (['C','D','E','F','G','H','I','J'] as $c) {
            $sheet->getColumnDimension($c)->setWidth(12);
        }
        $sheet->getColumnDimension('K')->setWidth(14);

        /* ── Business logo at A1 ── */
        $logoPath = $this->businessInfo['logo'] ?? null;
        if ($logoPath) {
            $fullPath = storage_path('app/public/' . $logoPath);
            if (file_exists($fullPath)) {
                $drawing = new Drawing();
                $drawing->setName('Logo');
                $drawing->setDescription('Business Logo');
                $drawing->setPath($fullPath);
                $drawing->setCoordinates('A1');
                $drawing->setOffsetX(2);
                $drawing->setOffsetY(2);
                $drawing->setHeight(48);
                $drawing->setWorksheet($sheet);
            }
        }
    }
}
