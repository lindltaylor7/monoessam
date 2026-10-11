<?php

namespace App\Exports;

use App\Services\ServiceDesk\IndicatorCalculator;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta la matriz de operacionalizacion con los valores del periodo, mas el detalle de la
 * validacion de la fuente de datos. Sirve como evidencia adjuntable del corte evaluado.
 */
class ServiceDeskIndicatorsExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private int $headerRow = 4;

    private int $lastIndicatorRow = 0;

    public function __construct(private IndicatorCalculator $calculator)
    {
    }

    public function title(): string
    {
        return 'Indicadores';
    }

    public function array(): array
    {
        $payload = $this->calculator->payload();

        $rows = [
            ['TABLERO DE INDICADORES - SERVICE DESK (ITIL 4)'],
            ['Periodo evaluado', $payload['period']['from'] . ' al ' . $payload['period']['to'], 'Tickets del periodo', $payload['summary']['total']],
            [],
            ['Variable', 'Dimension', 'Indicador', 'Unidad de medida', 'Fuente de datos', 'Valor', 'Meta', 'Estado', 'Muestra', 'Formula'],
        ];

        $variables = [
            'V1' => 'V1: Gestion de Incidencias basada en ITIL 4',
            'V2' => 'V2: Eficiencia del Area de Sistemas',
        ];

        foreach ($payload['indicators'] as $indicator) {
            $rows[] = [
                $variables[$indicator['variable']] ?? $indicator['variable'],
                $indicator['dimension'],
                $indicator['name'],
                $indicator['unit'],
                $indicator['source'],
                $indicator['display'],
                $indicator['target_display'],
                $this->statusLabel($indicator['status']),
                $indicator['sample'] . ' ' . $indicator['sample_label'],
                $indicator['formula'],
            ];
        }

        $this->lastIndicatorRow = count($rows);

        $rows[] = [];
        $rows[] = ['VALIDACION DE LA FUENTE DE DATOS'];
        $rows[] = ['Control', 'Casos', 'Severidad', 'Indicador afectado', 'Recomendacion'];

        foreach ($payload['validations'] as $check) {
            $rows[] = [
                $check['label'],
                $check['count'],
                strtoupper($check['severity']),
                $check['affects'],
                $check['hint'],
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:J1');

        $sheet->getStyle('A' . $this->headerRow . ':J' . $this->headerRow)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A' . $this->headerRow . ':J' . $this->lastIndicatorRow)->applyFromArray([
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
        ];
    }

    private function statusLabel(string $status): string
    {
        return [
            'cumple'    => 'CUMPLE',
            'riesgo'    => 'EN RIESGO',
            'incumple'  => 'NO CUMPLE',
            'sin_datos' => 'SIN DATOS',
        ][$status] ?? strtoupper($status);
    }
}
