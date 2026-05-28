<?php

namespace App\Exports;

use App\Exports\Sheets\SalesDetailSheet;
use App\Exports\Sheets\SalesPivotSheet;
use App\Models\Sale;
use App\Models\Subdealership;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesDetailExport implements WithMultipleSheets
{
    /** Shared flat row data consumed by both sheets */
    private array $rows = [];

    public function __construct(
        private readonly string  $startDate,
        private readonly string  $endDate,
        private readonly ?string $cafeId,
        private readonly ?int    $subdealershipId,
        private readonly array   $cafeIds,
        private readonly string  $cafeName,
    ) {
        $this->loadData();
    }

    /* ── Load once, share with both sheets ── */
    private function loadData(): void
    {
        $query = Sale::query()
            ->whereIn('cafe_id', $this->cafeIds)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->when($this->cafeId, fn($q) => $q->where('cafe_id', $this->cafeId))
            ->with(['tickets.ticket_details']);

        if ($this->subdealershipId) {
            $sdName = Subdealership::find($this->subdealershipId)?->name;
            if ($sdName) {
                $query->whereHas('tickets', fn($tq) => $tq->where('subdealership_name', $sdName));
            }
        }

        $sales = $query->orderBy('date')->orderBy('created_at')->get();

        foreach ($sales as $sale) {
            $date = Carbon::parse($sale->date)->format('d/m/Y');
            $time = $sale->created_at
                ? Carbon::parse($sale->created_at)->format('h:i A')
                : '—';

            foreach ($sale->tickets as $ticket) {
                foreach ($ticket->ticket_details as $detail) {
                    $this->rows[] = [
                        'sd_name'    => strtoupper($ticket->subdealership_name ?: 'SIN EMPRESA'),
                        'name'       => strtoupper($ticket->dinner_name ?: 'SIN NOMBRE'),
                        'date'       => $date,
                        'time'       => $time,
                        'svc_name'   => strtoupper($detail->service_name ?: '—'),
                        'svc_code'   => strtoupper($detail->code ?: '—'),
                        'amount'     => (int) ($detail->amount ?? 1),
                        'unit_price' => (float) ($detail->unit_price ?? 0),
                    ];
                }
            }
        }
    }

    public function sheets(): array
    {
        return [
            new SalesDetailSheet($this->rows, $this->startDate, $this->endDate, $this->cafeName),
            new SalesPivotSheet($this->rows, $this->startDate, $this->endDate, $this->cafeName),
        ];
    }
}
