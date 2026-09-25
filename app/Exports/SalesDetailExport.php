<?php

namespace App\Exports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Exports\Sheets\SalesDetailSheet;
use App\Exports\Sheets\SalesPivotSheet;
use App\Models\Subdealership;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesDetailExport implements WithMultipleSheets, ShouldQueue
{
    use Exportable;
    /** Shared flat row data consumed by both sheets */
    private array $rows = [];
    private array $selectedCafeIds = [];

    public function __construct(
        private readonly string  $startDate,
        private readonly string  $endDate,
        array|int|string|null    $cafeId,
        private readonly ?int    $subdealershipId,
        private readonly array   $cafeIds,
        private readonly string  $cafeName,
        private readonly ?int    $mineId = null,
    ) {
        if (is_array($cafeId)) {
            $this->selectedCafeIds = array_values(array_filter(array_map('intval', $cafeId), fn($id) => $id > 0));
        } elseif ($cafeId && $cafeId !== 'all') {
            $this->selectedCafeIds = [(int) $cafeId];
        }
        $this->loadData();
    }

    /* ── Load once, share with both sheets ── */
    private function loadData(): void
    {
        $targetCafeIds = !empty($this->selectedCafeIds) ? $this->selectedCafeIds : $this->cafeIds;

        $sdName = null;
        if ($this->subdealershipId) {
            $sdName = Subdealership::where('id', $this->subdealershipId)->value('name');
        }

        // Consulta SQL plana con JOIN directo: evita hidratar modelos y recorridos anidados
        $query = DB::table('sales')
            ->join('tickets', 'tickets.sale_id', '=', 'sales.id')
            ->join('ticket_details', 'ticket_details.ticket_id', '=', 'tickets.id')
            ->whereIn('sales.cafe_id', $targetCafeIds)
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

        // Recorremos los registros planos directamente (stdClass en lugar de modelos pesados)
        foreach ($query->cursor() as $row) {
            // Formateo nativo rápido sin invocar instancias completas de Carbon
            $dateFormatted = $row->sale_date
                ? date('d/m/Y', strtotime($row->sale_date))
                : '—';

            $timeFormatted = $row->sale_created_at
                ? date('h:i A', strtotime($row->sale_created_at))
                : '—';

            $this->rows[] = [
                'sd_name'    => strtoupper($row->subdealership_name ?: 'SIN EMPRESA'),
                'name'       => strtoupper($row->dinner_name ?: 'SIN NOMBRE'),
                'dni'        => $row->dni ?: '—',
                'date'       => $dateFormatted,
                'date_raw'   => $row->sale_date,
                'time'       => $timeFormatted,
                'svc_name'   => strtoupper($row->service_name ?: '—'),
                'svc_code'   => strtoupper($row->code ?: '—'),
                'svc_type'   => (int) ($row->service_type ?? 99),
                'amount'     => (int) ($row->amount ?? 1),
                'unit_price' => (float) ($row->unit_price ?? 0),
            ];
        }
    }

    public function sheets(): array
    {
        $targetCafeIds = !empty($this->selectedCafeIds) ? $this->selectedCafeIds : $this->cafeIds;

        $sdName = null;
        if ($this->subdealershipId) {
            $sdName = \App\Models\Subdealership::where('id', $this->subdealershipId)->value('name');
        }

        // Construir el Builder para la hoja de detalle
        $detailQuery = \Illuminate\Support\Facades\DB::table('sales')
            ->join('tickets', 'tickets.sale_id', '=', 'sales.id')
            ->join('ticket_details', 'ticket_details.ticket_id', '=', 'tickets.id')
            ->whereIn('sales.cafe_id', $targetCafeIds)
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
            $detailQuery->where('tickets.subdealership_name', $sdName);
        }

        return [
            new SalesDetailSheet($detailQuery, $this->startDate, $this->endDate, $this->cafeName),
            new SalesPivotSheet($this->rows, $this->startDate, $this->endDate, $this->cafeName, $this->subdealershipId, $this->mineId),
        ];
    }
}
