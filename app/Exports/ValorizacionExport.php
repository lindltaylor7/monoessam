<?php

namespace App\Exports;

use App\Exports\Sheets\AttendanceMatrixSheet;
use App\Exports\Sheets\VlzSummarySheet;
use App\Models\Sale;
use App\Models\Subdealership;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ValorizacionExport implements WithMultipleSheets
{
    private Collection $sistemaSales;
    private Collection $visitasSales;
    private Collection $refrigeriosSales;
    private array      $allDates  = [];
    private array      $prices    = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

    public function __construct(
        private readonly string  $startDate,
        private readonly string  $endDate,
        private readonly ?string $cafeId,
        private readonly ?int    $subdealershipId,
        private readonly array   $cafeIds,
        private readonly ?int    $businessId,
        private readonly array   $businessInfo,
        private readonly array   $unitInfo,
        private readonly array   $cafeInfo,
        private readonly string  $aFavorDe,
    ) {
        $this->loadData();
    }

    /* ──────────────────────────────────────────────────────────
     |  Load and partition sales
     ─────────────────────────────────────────────────────────── */
    private function loadData(): void
    {
        $query = Sale::query()
            ->whereIn('cafe_id', $this->cafeIds)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->when($this->businessId, fn($q) => $q->where('business_id', $this->businessId))
            ->when($this->cafeId,     fn($q) => $q->where('cafe_id',     $this->cafeId))
            ->with(['tickets.ticket_details', 'tickets.dinner']);

        // Filter by subdealership if provided
        if ($this->subdealershipId) {
            $sdName = Subdealership::find($this->subdealershipId)?->name;
            if ($sdName) {
                $query->whereHas('tickets', fn($tq) => $tq->where('subdealership_name', $sdName));
            }
        }

        $allSales = $query->orderBy('date')->get();

        /* ── Partition ── */
        $this->sistemaSales = $allSales
            ->where('is_visitor', false)
            ->filter(fn($s) => $s->tickets->flatMap->ticket_details
                ->whereIn('service_type', [1, 2, 3])->isNotEmpty());

        $this->visitasSales = $allSales->where('is_visitor', true);

        $this->refrigeriosSales = $allSales
            ->filter(fn($s) => $s->tickets->flatMap->ticket_details
                ->whereNotIn('service_type', [1, 2, 3])->isNotEmpty());

        /* ── Unique sorted dates ── */
        $this->allDates = $allSales
            ->pluck('date')
            ->unique()
            ->sort()
            ->values()
            ->all();

        /* ── Representative prices ── */
        $buckets = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($allSales as $sale) {
            foreach ($sale->tickets as $ticket) {
                foreach ($ticket->ticket_details as $d) {
                    $t = (int) $d->service_type;
                    if (isset($buckets[$t]) && $d->unit_price > 0) {
                        $buckets[$t][] = (float) $d->unit_price;
                    }
                }
            }
        }
        $avg = fn(array $arr) => count($arr) ? round(array_sum($arr) / count($arr), 2) : 0;
        foreach ($buckets as $type => $arr) {
            $this->prices[$type] = $avg($arr);
        }
    }

    /* ──────────────────────────────────────────────────────────
     |  Aggregate totals for the VLZ summary sheet
     ─────────────────────────────────────────────────────────── */
    private function aggregate(Collection $sales, int $type): array
    {
        $cnt = 0;
        $amt = 0.0;
        foreach ($sales as $sale) {
            foreach ($sale->tickets as $ticket) {
                foreach ($ticket->ticket_details as $d) {
                    if ((int) $d->service_type === $type) {
                        $cnt += (int) ($d->amount ?? 1);
                        $amt += (float) ($d->unit_price ?? 0) * (int) ($d->amount ?? 1);
                    }
                }
            }
        }
        return [$cnt, round($amt, 2)];
    }

    /* ──────────────────────────────────────────────────────────
     |  Build month title for matrix sheets
     ─────────────────────────────────────────────────────────── */
    private function monthTitle(string $prefix): string
    {
        return strtoupper($prefix . ' ' .
            \Carbon\Carbon::parse($this->startDate)->translatedFormat('F Y'));
    }

    /* ──────────────────────────────────────────────────────────
     |  Build sheets
     ─────────────────────────────────────────────────────────── */
    public function sheets(): array
    {
        $allSales = $this->sistemaSales->merge($this->visitasSales)->merge($this->refrigeriosSales);

        [$cntD, $amtD] = $this->aggregate($allSales, 1);
        [$cntA, $amtA] = $this->aggregate($allSales, 2);
        [$cntC, $amtC] = $this->aggregate($allSales, 3);
        [$cntR, $amtR] = $this->aggregate($allSales, 4);

        $sheets = [
            /* ── Tab 1: Resumen ── */
            new VlzSummarySheet(
                startDate:    $this->startDate,
                endDate:      $this->endDate,
                businessInfo: $this->businessInfo,
                unitInfo:     $this->unitInfo,
                cafeInfo:     $this->cafeInfo,
                aFavorDe:     $this->aFavorDe,
                cntD: $cntD, amtD: $amtD, priceD: $this->prices[1],
                cntA: $cntA, amtA: $amtA, priceA: $this->prices[2],
                cntC: $cntC, amtC: $amtC, priceC: $this->prices[3],
                cntR: $cntR, amtR: $amtR, priceR: $this->prices[4],
            ),

            /* ── Tab 2: SISTEMA (comensales regulares) ── */
            new AttendanceMatrixSheet(
                sheetName:    'SISTEMA',
                sales:        $this->sistemaSales,
                allDates:     $this->allDates,
                startDate:    $this->startDate,
                endDate:      $this->endDate,
                title:        $this->monthTitle('VALORIZACION ' . strtoupper($this->aFavorDe)),
                showDni:      false,
                serviceTypes: [1, 2, 3],
            ),

            /* ── Tab 3: VISITAS ── */
            new AttendanceMatrixSheet(
                sheetName:    'VISITAS',
                sales:        $this->visitasSales,
                allDates:     $this->allDates,
                startDate:    $this->startDate,
                endDate:      $this->endDate,
                title:        $this->monthTitle('VALORIZACION VISITAS'),
                showDni:      true,
                serviceTypes: [1, 2, 3],
            ),
        ];

        /* ── Tab 4: REFRIGERIOS (optional) ── */
        if ($this->refrigeriosSales->isNotEmpty()) {
            $sheets[] = new AttendanceMatrixSheet(
                sheetName:    'REFRIGERIOS',
                sales:        $this->refrigeriosSales,
                allDates:     $this->allDates,
                startDate:    $this->startDate,
                endDate:      $this->endDate,
                title:        $this->monthTitle('VALORIZACION REFRIGERIOS'),
                showDni:      false,
                serviceTypes: [4],
            );
        }

        return $sheets;
    }
}
