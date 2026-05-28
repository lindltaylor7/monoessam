<?php

namespace Database\Seeders;

use App\Models\Dinner;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\Ticket_detail;
use App\Models\Service;
use App\Models\Subdealership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestSalesSeeder extends Seeder
{
    // Prices per service type
    private const PRICES = [1 => 20.0, 2 => 25.0, 3 => 20.0, 4 => 10.0];

    // cafe_id => unit name (for cafe_name)
    private const CAFES = [
        1 => 'Staff',
        2 => 'Plaza',
        8 => 'Staff 2',
        9 => 'Test',
    ];

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // ── 1. Create 15 Corimayo dinners (subdealership_id=29) ──────────────
        $corimaoyDinners = $this->createDinners(29, [
            ['Álvaro Mendoza',    '60001001'],
            ['Beatriz Serrano',   '60001002'],
            ['Carlos Bustamante', '60001003'],
            ['Diana Chávez',      '60001004'],
            ['Eduardo Ramos',     '60001005'],
            ['Fernanda Ortiz',    '60001006'],
            ['Gonzalo Peralta',   '60001007'],
            ['Hilda Pacheco',     '60001008'],
            ['Iván Condori',      '60001009'],
            ['Jacqueline Puma',   '60001010'],
            ['Kevin Flores',      '60001011'],
            ['Lucía Torres',      '60001012'],
            ['Manuel Quispe',     '60001013'],
            ['Nadia Vargas',      '60001014'],
            ['Omar Huanca',       '60001015'],
        ]);

        // Existing dinners per subdealership (use first N)
        $southernIds = Dinner::where('subdealership_id', 1)->pluck('id')->take(20)->values();
        $roboconIds  = Dinner::where('subdealership_id', 32)->pluck('id')->values();

        // ── 2. Test dates spread across May 2026 ──────────────────────────────
        $dates = [
            '2026-05-01', '2026-05-05', '2026-05-08', '2026-05-12',
            '2026-05-15', '2026-05-19', '2026-05-22', '2026-05-25',
            '2026-05-26', '2026-05-27',
        ];

        foreach ($dates as $date) {
            // Café 1 – Staff: Southern comensales, D+A+C
            $this->createSalesForDinners(
                dinnerIds: $southernIds->slice(0, 8)->values(),
                cafeId: 1,
                services: [[1, 1], [4, 2], [8, 3]],   // [service_id, service_type]
                date: $date,
                sdId: 1, sdName: 'Southern',
            );

            // Café 2 – Plaza: Robocon comensales, D+A
            $this->createSalesForDinners(
                dinnerIds: $roboconIds->slice(0, 4)->values(),
                cafeId: 2,
                services: [[1, 1], [4, 2]],
                date: $date,
                sdId: 32, sdName: 'Robocon',
            );

            // Café 8 – Staff 2: Corimayo comensales, D+C
            $this->createSalesForDinners(
                dinnerIds: collect($corimaoyDinners)->slice(0, 5)->pluck('id')->values(),
                cafeId: 8,
                services: [[1, 1], [8, 3]],
                date: $date,
                sdId: 29, sdName: 'Corimayo',
            );

            // Café 9 – Test: Southern refrigerios
            $this->createSalesForDinners(
                dinnerIds: $southernIds->slice(8, 4)->values(),
                cafeId: 9,
                services: [[12, 4]],
                date: $date,
                sdId: 1, sdName: 'Southern',
            );

            // Café 1 – visitor sales (Southern + Robocon)
            $this->createVisitorSale(1, 1, 'Southern',  'Visitante Sur-'  . substr($date, 8), '7'  . substr($date, 5, 2) . substr($date, 8), $date);
            $this->createVisitorSale(2, 32, 'Robocon',  'Visitante Rob-'  . substr($date, 8), '8'  . substr($date, 5, 2) . substr($date, 8), $date);
            $this->createVisitorSale(8, 29, 'Corimayo', 'Visitante Cor-'  . substr($date, 8), '9'  . substr($date, 5, 2) . substr($date, 8), $date);
        }

        // ── 3. Extra: Almuerzo variants and Cena variants spread across days ──
        $extraDates = ['2026-05-06', '2026-05-13', '2026-05-20', '2026-05-27'];
        foreach ($extraDates as $date) {
            // Almuerzo (segundo solo) for Robocon
            $this->createSalesForDinners(
                dinnerIds: $roboconIds->slice(1, 3)->values(),
                cafeId: 2,
                services: [[5, 2]],   // ALMUERZO (SEGUNDO SOLO)
                date: $date,
                sdId: 32, sdName: 'Robocon',
            );
            // Cena (dieta) for Corimayo
            $this->createSalesForDinners(
                dinnerIds: collect($corimaoyDinners)->slice(5, 4)->pluck('id')->values(),
                cafeId: 8,
                services: [[11, 3]],  // CENA (DIETA)
                date: $date,
                sdId: 29, sdName: 'Corimayo',
            );
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('TestSalesSeeder: done.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createDinners(int $sdId, array $people): array
    {
        $sd      = Subdealership::find($sdId);
        $created = [];
        foreach ($people as [$name, $dni]) {
            if (!Dinner::where('dni', $dni)->exists()) {
                $created[] = Dinner::create([
                    'name'            => $name,
                    'dni'             => $dni,
                    'subdealership_id' => $sdId,
                ]);
            } else {
                $created[] = Dinner::where('dni', $dni)->first();
            }
        }
        return $created;
    }

    /**
     * For each dinner, create one sale with all the requested services as
     * separate ticket_details on a single ticket.
     *
     * @param \Illuminate\Support\Collection $dinnerIds
     * @param int   $cafeId
     * @param array $services  [[service_id, service_type], ...]
     * @param string $date
     * @param int   $sdId
     * @param string $sdName
     */
    private function createSalesForDinners(
        $dinnerIds, int $cafeId, array $services,
        string $date, int $sdId, string $sdName
    ): void {
        $sd     = Subdealership::find($sdId);
        $sdRuc  = $sd?->ruc ?? '';
        $cafeName = self::CAFES[$cafeId] ?? 'Cafetería';

        foreach ($dinnerIds as $dinnerId) {
            $dinner = Dinner::find($dinnerId);
            if (!$dinner) continue;

            // Total = sum of all service prices
            $total = collect($services)->sum(fn($s) => self::PRICES[$s[1]] ?? 0);
            $igv   = round($total * 0.18, 2);

            $sale = Sale::create([
                'dinner_id'   => $dinner->id,
                'cafe_id'     => $cafeId,
                'date'        => $date,
                'sale_type_id' => 1,
                'user_id'     => 127,
                'cafe_name'   => $cafeName,
                'is_visitor'  => false,
                'total'       => $total,
                'total_igv'   => $igv,
                'status'      => 1,
            ]);

            $ticket = Ticket::create([
                'sale_id'            => $sale->id,
                'dinner_id'          => $dinner->id,
                'dinner_name'        => $dinner->name,
                'dni'                => $dinner->dni,
                'subdealership_name' => $sdName,
                'subdealership_ruc'  => $sdRuc,
                'serial_number'      => 'T00',
                'price_value'        => $total,
                'igv'                => $igv,
                'status'             => 1,
            ]);

            foreach ($services as [$svcId, $svcType]) {
                $svc   = Service::find($svcId);
                $price = self::PRICES[$svcType] ?? 0;
                Ticket_detail::create([
                    'ticket_id'    => $ticket->id,
                    'service_id'   => $svcId,
                    'code'         => $svc?->code ?? '000',
                    'service_name' => $svc?->name ?? '',
                    'amount'       => 1,
                    'um'           => 'UNI',
                    'service_type' => $svcType,
                    'description'  => '',
                    'unit_value'   => $price,
                    'unit_price'   => $price,
                    'sale_value'   => $price,
                    'igv'          => round($price * 0.18, 2),
                    'total'        => $price,
                ]);
            }
        }
    }

    private function createVisitorSale(
        int $cafeId, int $sdId, string $sdName,
        string $visitorName, string $dni, string $date
    ): void {
        $sd       = Subdealership::find($sdId);
        $sdRuc    = $sd?->ruc ?? '';
        $cafeName = self::CAFES[$cafeId] ?? 'Cafetería';
        $price    = 20.0;

        // Visitor gets a DESAYUNO
        $sale = Sale::create([
            'dinner_id'    => null,
            'cafe_id'      => $cafeId,
            'date'         => $date,
            'sale_type_id' => 2,
            'user_id'      => 127,
            'cafe_name'    => $cafeName,
            'is_visitor'   => true,
            'total'        => $price,
            'total_igv'    => round($price * 0.18, 2),
            'status'       => 1,
        ]);

        $ticket = Ticket::create([
            'sale_id'            => $sale->id,
            'dinner_id'          => null,
            'dinner_name'        => $visitorName,
            'dni'                => $dni,
            'subdealership_name' => $sdName,
            'subdealership_ruc'  => $sdRuc,
            'serial_number'      => 'T00-VIS',
            'price_value'        => $price,
            'igv'                => round($price * 0.18, 2),
            'status'             => 1,
        ]);

        $svc = Service::find(1); // DESAYUNO
        Ticket_detail::create([
            'ticket_id'    => $ticket->id,
            'service_id'   => 1,
            'code'         => $svc?->code ?? '001',
            'service_name' => $svc?->name ?? 'DESAYUNO',
            'amount'       => 1,
            'um'           => 'UNI',
            'service_type' => 1,
            'description'  => '',
            'unit_value'   => $price,
            'unit_price'   => $price,
            'sale_value'   => $price,
            'igv'          => round($price * 0.18, 2),
            'total'        => $price,
        ]);
    }
}
