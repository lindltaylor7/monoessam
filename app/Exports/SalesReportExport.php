<?php

namespace App\Exports;

use App\Models\Sale;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesReportExport implements WithMultipleSheets
{
    public function __construct(
        protected string $startDate,
        protected string $endDate,
        protected ?string $cafeId,
        protected ?int   $subdealershipId,
        protected array  $cafeIds,
        protected ?int   $businessId,
    ) {}

    public function sheets(): array
    {
        /* ── 1. Load all matching sales with nested relations ── */
        $sales = Sale::query()
            ->whereIn('cafe_id', $this->cafeIds)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->when($this->businessId,       fn($q) => $q->where('business_id', $this->businessId))
            ->when($this->cafeId,           fn($q) => $q->where('cafe_id', $this->cafeId))
            ->with(['tickets.ticket_details'])
            ->get();

        /* ── 2. Flatten to tickets and filter by subdealership if needed ── */
        $tickets = $sales->flatMap(fn($sale) => $sale->tickets);

        if ($this->subdealershipId) {
            /* We already have the name from the join done at controller level,
               but here we filter by matching subdealership_id stored in tickets.
               Since tickets only store the name, we look it up once. */
            $sdName = \App\Models\Subdealership::find($this->subdealershipId)?->name;
            $tickets = $tickets->filter(fn($t) => $t->subdealership_name === $sdName);
        }

        /* ── 3. Group tickets by subdealership name ── */
        $grouped = $tickets->groupBy(fn($t) => $t->subdealership_name ?: 'SIN EMPRESA');

        /* ── 4. Create one sheet per subdealership ── */
        return $grouped
            ->sortKeys()
            ->map(fn($ticketsForSd, $sdName) => new SubdealershipSheetExport(
                $sdName,
                $ticketsForSd,
                $this->startDate,
                $this->endDate,
            ))
            ->values()
            ->all();
    }
}
