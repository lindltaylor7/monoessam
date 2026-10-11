<?php

use App\Models\ServiceDeskCategory;
use App\Models\ServiceDeskKedbArticle;
use App\Models\ServiceDeskMaturityAnswer;
use App\Models\ServiceDeskMaturityAssessment;
use App\Models\ServiceDeskMaturityQuestion;
use App\Models\ServiceDeskSatisfactionSurvey;
use App\Models\ServiceDeskTicket;
use App\Models\ServiceDeskTicketAudit;
use App\Models\User;
use App\Services\ServiceDesk\IndicatorCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * El segmento `service-desk` tiene fila propia en `permissions` (migración
 * add_service_desk_permission), así que CheckRoutePermission exige el permiso.
 */
function makeServiceDeskUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('service-desk.index');

    return $user;
}

function makeTicket(array $attributes = []): ServiceDeskTicket
{
    $ticket = new ServiceDeskTicket(array_merge([
        'title'          => 'Incidencia de prueba',
        'requester_name' => 'Usuario',
        'channel'        => 'portal',
        'category_id'    => ServiceDeskCategory::first()->id,
        'impact'         => 2,
        'urgency'        => 2,
        'reported_at'    => now()->subDays(2),
    ], $attributes));

    $ticket->code = ServiceDeskTicket::nextCode();
    $ticket->status = $attributes['status'] ?? ServiceDeskTicket::STATUS_NUEVO;
    $ticket->applyPriorityAndSla();
    $ticket->save();

    return $ticket;
}

// ── Pantallas ─────────────────────────────────────────────────────────────

test('la bandeja se muestra a un usuario con permiso', function () {
    $this->actingAs(makeServiceDeskUser())
        ->get(route('service-desk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('service-desk/Index')->has('tickets')->has('categories'));
});

test('el tablero de indicadores entrega los siete indicadores de la matriz', function () {
    $this->actingAs(makeServiceDeskUser())
        ->get(route('service-desk.indicators.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('service-desk/Indicators')->has('indicators', 7)->has('validations'));
});

test('un usuario sin el permiso no entra al modulo', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('service-desk.index'))
        ->assertStatus(403);
});

// ── Matriz de prioridad y SLA ─────────────────────────────────────────────

test('la prioridad se deriva de la matriz impacto por urgencia', function () {
    expect(ServiceDeskTicket::calculatePriority(1, 1))->toBe(1)
        ->and(ServiceDeskTicket::calculatePriority(1, 2))->toBe(2)
        ->and(ServiceDeskTicket::calculatePriority(2, 2))->toBe(3)
        ->and(ServiceDeskTicket::calculatePriority(3, 3))->toBe(4);
});

test('al registrar un ticket se calculan prioridad, SLA y vencimientos', function () {
    $user = makeServiceDeskUser();
    $category = ServiceDeskCategory::first();

    $this->actingAs($user)
        ->post(route('service-desk.tickets.store'), [
            'title'          => 'No enciende la PC de almacen',
            'requester_name' => 'Juan Perez',
            'channel'        => 'telefono',
            'category_id'    => $category->id,
            'impact'         => 1,
            'urgency'        => 1,
            'reported_at'    => now()->toDateTimeString(),
        ])
        ->assertRedirect();

    $ticket = ServiceDeskTicket::first();

    expect($ticket->code)->toBe('TCK-000001')
        ->and($ticket->priority)->toBe(1)
        ->and($ticket->sla->priority)->toBe(1)
        // P1 por defecto: 15 minutos de respuesta y 240 de resolución.
        ->and($ticket->reported_at->diffInMinutes($ticket->response_due_at))->toBe(15.0)
        ->and($ticket->reported_at->diffInMinutes($ticket->resolution_due_at))->toBe(240.0)
        ->and($ticket->events()->where('type', 'creado')->exists())->toBeTrue();
});

test('reclasificar el ticket recalcula prioridad y vencimientos', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket(['impact' => 3, 'urgency' => 3]);

    expect($ticket->priority)->toBe(4);

    $this->actingAs($user)
        ->put(route('service-desk.tickets.update', $ticket->id), [
            'title'          => $ticket->title,
            'requester_name' => $ticket->requester_name,
            'channel'        => $ticket->channel,
            'category_id'    => $ticket->category_id,
            'impact'         => 1,
            'urgency'        => 1,
        ])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->priority)->toBe(1)
        ->and($ticket->sla->priority)->toBe(1)
        ->and($ticket->events()->where('type', 'reclasificado')->exists())->toBeTrue();
});

// ── Flujo de atención ─────────────────────────────────────────────────────

test('la primera respuesta se sella una sola vez', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket();

    $this->actingAs($user)->post(route('service-desk.tickets.respond', $ticket->id), ['note' => 'Se contacta al usuario']);
    $first = $ticket->fresh()->first_response_at;

    $this->actingAs($user)->post(route('service-desk.tickets.respond', $ticket->id), ['note' => 'Seguimiento']);

    expect($ticket->fresh()->first_response_at->eq($first))->toBeTrue()
        ->and($ticket->fresh()->status)->toBe(ServiceDeskTicket::STATUS_EN_PROCESO)
        ->and($ticket->events()->where('type', 'comentado')->count())->toBe(1);
});

test('resolver declarando uso de KEDB exige el articulo utilizado', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket();

    $this->actingAs($user)
        ->post(route('service-desk.tickets.resolve', $ticket->id), [
            'resolution_notes'   => 'Se aplicó la solución conocida',
            'resolved_with_kedb' => true,
        ])
        ->assertSessionHasErrors('kedb_article_id');

    expect($ticket->fresh()->resolved_at)->toBeNull();
});

test('resolver con KEDB enlaza el articulo e incrementa su contador de uso', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket();
    $article = ServiceDeskKedbArticle::create([
        'code'     => ServiceDeskKedbArticle::nextCode(),
        'title'    => 'Cola de impresión bloqueada',
        'solution' => 'Reiniciar el servicio de spooler.',
        'status'   => ServiceDeskKedbArticle::STATUS_PUBLICADO,
    ]);

    $this->actingAs($user)
        ->post(route('service-desk.tickets.resolve', $ticket->id), [
            'resolution_notes'   => 'Se reinició el spooler',
            'resolved_with_kedb' => true,
            'kedb_article_id'    => $article->id,
        ])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(ServiceDeskTicket::STATUS_RESUELTO)
        ->and($ticket->resolved_with_kedb)->toBeTrue()
        ->and($ticket->kedb_article_id)->toBe($article->id)
        // Resolver sin respuesta previa sella también la primera respuesta.
        ->and($ticket->first_response_at)->not->toBeNull()
        ->and($article->fresh()->times_used)->toBe(1);
});

test('no se puede resolver con una fecha anterior al reporte', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket(['reported_at' => now()->subDay()]);

    $this->actingAs($user)
        ->post(route('service-desk.tickets.resolve', $ticket->id), [
            'resolution_notes'   => 'Listo',
            'resolved_with_kedb' => false,
            'resolved_at'        => now()->subDays(3)->toDateTimeString(),
        ])
        ->assertSessionHasErrors('resolved_at');
});

test('reabrir limpia los hitos de cierre y suma al contador', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket([
        'status'      => ServiceDeskTicket::STATUS_CERRADO,
        'resolved_at' => now()->subHour(),
        'closed_at'   => now(),
    ]);

    $this->actingAs($user)
        ->post(route('service-desk.tickets.reopen', $ticket->id), ['note' => 'El problema persiste'])
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(ServiceDeskTicket::STATUS_EN_PROCESO)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->reopened_count)->toBe(1);
});

// ── Auditoría de clasificación ────────────────────────────────────────────

test('la auditoria marca incorrecta la clasificacion si la prioridad no sale de la matriz', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket(['impact' => 1, 'urgency' => 1]);
    // Prioridad adulterada: la matriz para I1/U1 es P1.
    $ticket->forceFill(['priority' => 4])->save();

    $this->actingAs($user)
        ->post(route('service-desk.audits.store', $ticket->id), [
            'category_correct' => true,
            'impact_correct'   => true,
            'urgency_correct'  => true,
        ])
        ->assertRedirect();

    $audit = ServiceDeskTicketAudit::where('ticket_id', $ticket->id)->first();

    expect($audit->priority_correct)->toBeFalse()
        ->and($audit->is_correct)->toBeFalse();
});

test('aplicar la correccion de la auditoria reclasifica el ticket', function () {
    $user = makeServiceDeskUser();
    $ticket = makeTicket(['impact' => 3, 'urgency' => 3]);

    ServiceDeskTicketAudit::create([
        'ticket_id'         => $ticket->id,
        'category_correct'  => true,
        'impact_correct'    => false,
        'urgency_correct'   => false,
        'priority_correct'  => true,
        'is_correct'        => false,
        'suggested_impact'  => 1,
        'suggested_urgency' => 1,
        'audited_at'        => now(),
    ]);

    $this->actingAs($user)
        ->post(route('service-desk.audits.apply', $ticket->id))
        ->assertRedirect();

    $ticket->refresh();

    expect($ticket->impact)->toBe(1)
        ->and($ticket->urgency)->toBe(1)
        ->and($ticket->priority)->toBe(1);
});

// ── Cálculo de indicadores ────────────────────────────────────────────────

test('el calculador obtiene MTTR, MTTRsp, cumplimiento de SLA y uso de KEDB', function () {
    $reported = now()->subDays(3)->setTime(8, 0);

    // Dentro de SLA P3 (60 min de respuesta, 1440 de resolución) y resuelto con KEDB.
    $a = makeTicket(['impact' => 2, 'urgency' => 2, 'reported_at' => $reported]);
    $a->forceFill([
        'first_response_at'  => $reported->copy()->addMinutes(20),
        'resolved_at'        => $reported->copy()->addMinutes(120),
        'status'             => ServiceDeskTicket::STATUS_RESUELTO,
        'resolved_with_kedb' => true,
    ])->save();

    // Fuera del SLA de resolución y sin KEDB.
    $b = makeTicket(['impact' => 2, 'urgency' => 2, 'reported_at' => $reported]);
    $b->forceFill([
        'first_response_at' => $reported->copy()->addMinutes(40),
        'resolved_at'       => $reported->copy()->addMinutes(2000),
        'status'            => ServiceDeskTicket::STATUS_RESUELTO,
    ])->save();

    $calculator = new IndicatorCalculator(now()->subDays(10)->toDateString(), now()->toDateString());
    $indicators = collect($calculator->indicators())->keyBy('key');

    expect($indicators['mttr']['value'])->toBe(1060.0)          // (120 + 2000) / 2
        ->and($indicators['mttr_response']['value'])->toBe(30.0) // (20 + 40) / 2
        ->and($indicators['sla_compliance']['value'])->toBe(50.0)
        ->and($indicators['kedb_usage']['value'])->toBe(50.0);
});

test('el indicador de clasificacion solo considera tickets auditados', function () {
    $correct = makeTicket();
    $wrong = makeTicket();
    makeTicket(); // sin auditar: no entra al denominador

    foreach ([[$correct, true], [$wrong, false]] as [$ticket, $isCorrect]) {
        ServiceDeskTicketAudit::create([
            'ticket_id'        => $ticket->id,
            'category_correct' => $isCorrect,
            'impact_correct'   => true,
            'urgency_correct'  => true,
            'priority_correct' => true,
            'is_correct'       => $isCorrect,
            'audited_at'       => now(),
        ]);
    }

    $indicators = collect((new IndicatorCalculator())->indicators())->keyBy('key');

    expect($indicators['classification']['value'])->toBe(50.0)
        ->and($indicators['classification']['sample'])->toBe(2);
});

test('satisfaccion y estandarizacion salen de los cuestionarios del periodo', function () {
    foreach ([5, 4, 3] as $score) {
        ServiceDeskSatisfactionSurvey::create([
            'respondent_name' => 'Usuario',
            'score'           => $score,
            'answered_at'     => now()->subDay(),
        ]);
    }

    $assessment = ServiceDeskMaturityAssessment::create([
        'respondent_name' => 'Analista TI',
        'period'          => now()->format('Y-m'),
        'answered_at'     => now()->subDay(),
    ]);

    foreach (ServiceDeskMaturityQuestion::where('dimension', 'estandarizacion')->get() as $question) {
        ServiceDeskMaturityAnswer::create([
            'assessment_id' => $assessment->id,
            'question_id'   => $question->id,
            'score'         => 4,
        ]);
    }

    $indicators = collect((new IndicatorCalculator())->indicators())->keyBy('key');

    expect($indicators['satisfaction']['value'])->toBe(4.0)
        ->and($indicators['estandarizacion']['value'])->toBe(4.0)
        // Tres encuestas están por debajo de la muestra mínima configurada.
        ->and($indicators['satisfaction']['reliable'])->toBeFalse();
});

test('el semaforo compara contra la meta configurada', function () {
    config(['servicedesk.targets.satisfaction' => 4.0, 'servicedesk.warning_tolerance' => 0.10]);

    ServiceDeskSatisfactionSurvey::create(['respondent_name' => 'A', 'score' => 5, 'answered_at' => now()]);

    $indicators = collect((new IndicatorCalculator())->indicators())->keyBy('key');

    expect($indicators['satisfaction']['status'])->toBe('cumple')
        ->and($indicators['mttr']['status'])->toBe('sin_datos');
});

// ── Validación de la fuente de datos ──────────────────────────────────────

test('la validacion detecta tickets resueltos sin primera respuesta', function () {
    $ticket = makeTicket();
    $ticket->forceFill([
        'resolved_at' => now()->subHour(),
        'status'      => ServiceDeskTicket::STATUS_RESUELTO,
    ])->save();

    $checks = collect((new IndicatorCalculator())->validations())->keyBy('key');

    expect($checks['sin_primera_respuesta']['count'])->toBe(1)
        ->and($checks['sin_primera_respuesta']['severity'])->toBe('error');
});

test('la validacion detecta fechas incoherentes y prioridades fuera de la matriz', function () {
    $ticket = makeTicket(['impact' => 1, 'urgency' => 1, 'reported_at' => now()->subDay()]);
    $ticket->forceFill([
        'priority'    => 4,
        'resolved_at' => now()->subDays(2),
        'status'      => ServiceDeskTicket::STATUS_RESUELTO,
    ])->save();

    $checks = collect((new IndicatorCalculator())->validations())->keyBy('key');

    expect($checks['fechas_incoherentes']['count'])->toBe(1)
        ->and($checks['prioridad_fuera_de_matriz']['count'])->toBe(1);
});

test('sin observaciones los controles quedan en conforme', function () {
    $checks = collect((new IndicatorCalculator())->validations());

    expect($checks->every(fn ($check) => $check['severity'] === 'ok'))->toBeTrue();
});
