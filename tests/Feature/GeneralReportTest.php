<?php

use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Mercantil;
use App\Models\MercantilSale;
use App\Models\Mine;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subdealership;
use App\Models\Ticket;
use App\Models\Ticket_detail;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * El dashboard gerencial arma ~30 agregaciones en una sola respuesta. Estos tests las
 * ejecutan de verdad contra la base: lo que se rompe al tocar el módulo es una consulta
 * mal formada (alias ausente, GROUP BY incompleto, join sobre columna inexistente), y eso
 * solo se detecta corriendo el SQL, no revisando el Vue.
 */
function reportUser(): User
{
    Permission::firstOrCreate(
        ['name' => 'Reporte de ventas', 'guard_name' => 'web'],
        ['route_name' => 'reportsales'],
    );

    $user = User::factory()->create();
    $user->givePermissionTo('Reporte de ventas');

    return $user;
}

/** Un escenario mínimo pero completo: mina → unidad → comedor + mercantil con venta y stock. */
function seedReportScenario(string $date = '2026-08-10'): array
{
    $mine = Mine::factory()->create();
    $unit = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafe = Cafe::factory()->create(['unit_id' => $unit->id]);

    $subdealership = Subdealership::factory()->create(['name' => 'Subconcesionaria Alfa']);
    $dinner        = Dinner::factory()->create(['subdealership_id' => $subdealership->id]);

    $sale = Sale::factory()->create([
        'cafe_id'    => $cafe->id,
        'dinner_id'  => $dinner->id,
        'cafe_name'  => $cafe->name,
        'date'       => $date,
        'total'      => 25.50,
        'is_visitor' => false,
    ]);

    $ticket = Ticket::create([
        'sale_id'            => $sale->id,
        'dinner_id'          => $dinner->id,
        'dinner_name'        => $dinner->name,
        'dni'                => $dinner->dni,
        'subdealership_name' => $subdealership->name,
        // La migracion declaro serial_number con un typo (`nuallable`), asi que es NOT NULL.
        'serial_number'      => 'B001-000001',
    ]);

    // service_type 4 = Almuerzo; el mapa de calor y el mix se alimentan de esta tabla.
    Ticket_detail::create([
        'ticket_id'    => $ticket->id,
        'service_name' => 'Almuerzo',
        'service_type' => 4,
        'amount'       => 1,
        'unit_price'   => 25.50,
        'total'        => 25.50,
    ]);

    $mercantil = Mercantil::factory()->create(['unit_id' => $unit->id]);
    $product   = Product::factory()->create(['mercantil_id' => $mercantil->id, 'stock' => 3, 'price' => 8.00]);

    $mercSale = MercantilSale::create([
        'mercantil_id'      => $mercantil->id,
        'unit_id'           => $unit->id,
        'payment_method'    => 'valorizado',
        'payment_condition' => 'credito',
        'buyer_dni'         => $dinner->dni,
        'subdealership_id'  => $subdealership->id,
        'dinner_id'         => $dinner->id,
        'date'              => $date,
        'subtotal'          => 16.00,
        'igv'               => 2.88,
        'total'             => 16.00,
    ]);

    $mercSale->details()->create([
        'product_id'   => $product->id,
        'product_name' => $product->name,
        'category'     => 'Bebidas',
        'quantity'     => 2,
        'unit_price'   => 8.00,
        'subtotal'     => 16.00,
    ]);

    return compact('mine', 'unit', 'cafe', 'subdealership', 'dinner', 'mercantil', 'product', 'date');
}

test('the dashboard renders every data block', function () {
    seedReportScenario();

    $this->actingAs(reportUser())
        ->get(route('generalreport.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('generalreport/Index')
                ->has('mines')
                ->has('subdealerships')
                ->has('mercantiles')
                ->has('service_types')
                ->has('kpis')
                ->has('daily_trend')
                ->has('org_breakdown.rows')
                ->has('revenue_by_cafe')
                ->has('by_service_type')
                ->has('by_subdealership')
                ->has('top_diners')
                ->has('visitor_ratio')
                ->has('by_weekday', 7)
                ->has('service_heatmap')
                ->has('service_mix_by_cafe.categories')
                ->has('visit_frequency', 5)
                ->has('period_comparison.current')
                ->has('mercantil.kpis')
                ->has('mercantil.top_products')
                ->has('mercantil.by_category')
                ->has('mercantil.credit_by_subdealership')
                ->has('mercantil.by_hour', 24)
                ->has('mercantil.low_stock')
                ->has('mercantil.slow_movers')
                ->has('consolidated.daily.labels')
                ->has('consolidated.by_unit'),
        );
});

test('the period totals add up for both channels', function () {
    $s = seedReportScenario();

    $this->actingAs(reportUser())
        ->get(route('generalreport.index', ['start_date' => $s['date'], 'end_date' => $s['date']]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('kpis.total_revenue', 25.5)
                ->where('kpis.total_sales', 1)
                ->where('kpis.total_servings', 1)
                ->where('mercantil.kpis.total_revenue', 16)
                ->where('mercantil.kpis.total_units', 2)
                ->where('mercantil.kpis.credit_outstanding', 16)
                ->where('consolidated.total_revenue', 41.5)
                ->etc(),
        );
});

test('each filter narrows the result without breaking a query', function () {
    $s = seedReportScenario();

    $base = ['start_date' => $s['date'], 'end_date' => $s['date']];

    // Filtros que SÍ alcanzan el escenario sembrado.
    $matching = [
        'mine_id'          => $s['mine']->id,
        'unit_id'          => $s['unit']->id,
        'cafe_id'          => $s['cafe']->id,
        'service_type'     => 4,
        'subdealership_id' => $s['subdealership']->id,
        'diner_type'       => 'diners',
    ];

    foreach ($matching as $key => $value) {
        $this->actingAs(reportUser())
            ->get(route('generalreport.index', $base + [$key => $value]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('kpis.total_sales', 1)->etc());
    }

    // Filtros que NO alcanzan el escenario: deben devolver cero, no reventar.
    $this->actingAs(reportUser())
        ->get(route('generalreport.index', $base + ['service_type' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kpis.total_sales', 0)->etc());

    $this->actingAs(reportUser())
        ->get(route('generalreport.index', $base + ['diner_type' => 'visitors']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kpis.total_sales', 0)->etc());
});

test('mercantil filters apply to the mercantil block only', function () {
    $s = seedReportScenario();
    $base = ['start_date' => $s['date'], 'end_date' => $s['date']];

    $this->actingAs(reportUser())
        ->get(route('generalreport.index', $base + ['payment_condition' => 'contado']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('mercantil.kpis.total_revenue', 0)
                // La venta de comedor no se toca: el filtro es propio del canal mercantil.
                ->where('kpis.total_revenue', 25.5)
                ->etc(),
        );

    $this->actingAs(reportUser())
        ->get(route('generalreport.index', $base + ['payment_method' => 'valorizado', 'mercantil_id' => $s['mercantil']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('mercantil.kpis.total_revenue', 16)->etc());
});

test('the org breakdown drills down as the geographic filter narrows', function () {
    $s = seedReportScenario();
    $base = ['start_date' => $s['date'], 'end_date' => $s['date']];

    $levelFor = function (array $params) {
        $level = null;
        $this->actingAs(reportUser())
            ->get(route('generalreport.index', $params))
            ->assertOk()
            ->assertInertia(function ($page) use (&$level) {
                $level = $page->toArray()['props']['org_breakdown']['level'];
                return $page->etc();
            });

        return $level;
    };

    expect($levelFor($base))->toBe('Mina')
        ->and($levelFor($base + ['mine_id' => $s['mine']->id]))->toBe('Unidad')
        ->and($levelFor($base + ['unit_id' => $s['unit']->id]))->toBe('Comedor');
});

test('an inverted date range is reordered instead of returning nothing', function () {
    $s = seedReportScenario();

    $this->actingAs(reportUser())
        ->get(route('generalreport.index', ['start_date' => $s['date'], 'end_date' => '2026-08-01']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('filters.start_date', '2026-08-01')
                ->where('filters.end_date', $s['date'])
                ->where('kpis.total_sales', 1)
                ->etc(),
        );
});

test('an empty period renders without errors', function () {
    $this->actingAs(reportUser())
        ->get(route('generalreport.index', ['start_date' => '2020-01-01', 'end_date' => '2020-01-05']))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('kpis.total_revenue', 0)
                ->where('kpis.avg_ticket', 0)
                ->where('consolidated.total_revenue', 0)
                ->has('period_comparison.current', 5)
                ->etc(),
        );
});
