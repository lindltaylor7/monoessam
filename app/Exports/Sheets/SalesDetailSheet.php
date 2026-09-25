<?php

namespace App\Exports\Sheets;

use App\Models\Subdealership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesDetailSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithChunkReading, WithCustomStartCell
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly array   $targetCafeIds,
        private readonly string  $startDate,
        private readonly string  $endDate,
        private readonly string  $cafeName,
        private readonly ?int    $subdealershipId = null,
    ) {}

    public function startCell(): string
    {
        return 'A4';
    }

    public function query()
    {
        $sdName = null;
        if ($this->subdealershipId) {
            $sdName = Subdealership::where('id', $this->subdealershipId)->value('name');
        }

        $query = DB::table('sales')
            ->join('tickets', 'tickets.sale_id', '=', 'sales.id')
            ->join('ticket_details', 'ticket_details.ticket_id', '=', 'tickets.id')
            ->whereIn('sales.cafe_id', $this->targetCafeIds)
            ->whereBetween('sales.date', [$this->startDate, $this->endDate])
            ->select([
                'tickets.subdealership_name',
                'tickets.dinner_name',
                'tickets.dni',
                'sales.date as sale_date',
                'sales.created_at as sale_created_at',
                'ticket_details.service_name',
                'ticket_details.code',
                'ticket_details.service_type',
                'ticket_details.amount',
                'ticket_details.unit_price',
            ])
            ->orderBy('sales.date')
            ->orderBy('sales.created_at');

        if ($sdName) {
            $query->where('tickets.subdealership_name', $sdName);
        }

        return $query;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function map($row): array
    {
        $this->rowNumber++;
        $price = (float) ($row->unit_price ?? 0) * (int) ($row->amount ?? 1);

        $dateFormatted = $row->sale_date ? date('d/m/Y', strtotime($row->sale_date)) : '—';
        $timeFormatted = $row->sale_created_at ? date('h:i A', strtotime($row->sale_created_at)) : '—';

        return [
            $this->rowNumber,
            strtoupper($row->subdealership_name ?: 'SIN EMPRESA'),
            strtoupper($row->dinner_name ?: 'SIN NOMBRE'),
            $row->dni ?: '—',
            $dateFormatted,
            $timeFormatted,
            strtoupper($row->service_name ?: '—'),
            (int) ($row->amount ?? 1),
            number_format($price, 2, '.', ''),
        ];
    }

    public function headings(): array
    {
        return [
            'N°',
            'SUBCONCESIONARIA',
            'APELLIDOS Y NOMBRES',
            'DNI',
            'FECHA',
            'HORA',
            'SERVICIO',
            'CANT.',
            'PRECIO',
        ];
    }

    public function title(): string
    {
        return 'DETALLE';
    }

    public function styles(Worksheet $sheet): void
    {
        $fmt = fn(string $d) => Carbon::parse($d)->translatedFormat('d \d\e F \d\e Y');
        $lastCol = 'I';

        $sheet->setCellValue('A1', 'CAFETERÍA: ' . strtoupper($this->cafeName));
        $sheet->setCellValue('A2', 'Período: ' . $fmt($this->startDate) . ' — ' . $fmt($this->endDate));

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");

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
