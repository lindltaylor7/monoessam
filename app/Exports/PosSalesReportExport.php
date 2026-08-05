<?php

namespace App\Exports;

use App\Models\MercantilSale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PosSalesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected string $startDate,
        protected string $endDate,
        protected ?string $mercantilId = null,
        protected ?string $paymentMethod = null,
        protected ?string $subdealershipId = null,
    ) {}

    public function collection()
    {
        $query = MercantilSale::with([
            'mercantil:id,name',
            'unit:id,name',
            'user:id,name',
            'subdealership:id,name',
            'dinner:id,name,dni',
            'details',
        ])
        ->whereBetween('date', [$this->startDate, $this->endDate]);

        if (!empty($this->mercantilId) && $this->mercantilId !== 'all') {
            $query->where('mercantil_id', $this->mercantilId);
        }

        if (!empty($this->paymentMethod) && $this->paymentMethod !== 'all') {
            $query->where('payment_method', $this->paymentMethod);
        }

        if (!empty($this->subdealershipId) && $this->subdealershipId !== 'all') {
            $query->where('subdealership_id', $this->subdealershipId);
        }

        return $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'ID Venta',
            'Fecha',
            'Hora',
            'Mercantil / Local',
            'Unidad',
            'Vendedor / Usuario',
            'Condición de Pago',
            'Método de Pago',
            'Cliente',
            'DNI',
            'Subdealership',
            'Cant. Ítems',
            'Detalle de Productos',
            'Subtotal (S/)',
            'IGV (S/)',
            'Total (S/)',
        ];
    }

    public function map($sale): array
    {
        $productsDetail = $sale->details->map(function ($d) {
            return "{$d->product_name} ({$d->quantity} x S/ " . number_format($d->unit_price, 2) . ")";
        })->implode('; ');

        $totalItems = $sale->details->sum('quantity');

        return [
            '#' . $sale->id,
            $sale->date ? $sale->date->format('Y-m-d') : '',
            $sale->created_at ? $sale->created_at->format('H:i:s') : '',
            $sale->mercantil?->name ?? '—',
            $sale->unit?->name ?? '—',
            $sale->user?->name ?? '—',
            strtoupper($sale->payment_condition ?? 'CONTADO'),
            strtoupper($sale->payment_method ?? 'EFECTIVO'),
            $sale->dinner?->name ?? '—',
            $sale->buyer_dni ?? $sale->dinner?->dni ?? '—',
            $sale->subdealership?->name ?? '—',
            $totalItems,
            $productsDetail ?: 'Sin productos',
            number_format($sale->subtotal, 2, '.', ''),
            number_format($sale->igv, 2, '.', ''),
            number_format($sale->total, 2, '.', ''),
        ];
    }

    public function styles($sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
