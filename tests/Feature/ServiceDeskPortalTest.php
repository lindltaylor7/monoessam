<?php

use App\Models\ServiceDeskCategory;
use App\Models\ServiceDeskSatisfactionSurvey;
use App\Models\ServiceDeskTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Portal de autoservicio (`/soporte`): canal de entrada "portal" del Service Desk. Debe estar
 * abierto a cualquier usuario autenticado, pero cada uno solo opera sus propios tickets.
 */
function portalTicket(User $owner, array $attributes = []): ServiceDeskTicket
{
    $ticket = new ServiceDeskTicket(array_merge([
        'title'             => 'No imprime',
        'requester_user_id' => $owner->id,
        'requester_name'    => $owner->name,
        'channel'           => 'portal',
        'category_id'       => ServiceDeskCategory::first()->id,
        'impact'            => 3,
        'urgency'           => 3,
        'reported_at'       => now()->subHours(3),
    ], $attributes));
    $ticket->code = ServiceDeskTicket::nextCode();
    $ticket->status = $attributes['status'] ?? ServiceDeskTicket::STATUS_NUEVO;
    $ticket->applyPriorityAndSla();
    $ticket->save();

    return $ticket;
}

test('cualquier usuario autenticado entra al portal, sin el permiso del service desk', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('soporte.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('service-desk/Portal')->has('tickets', 0)->has('categories'));
});

test('el usuario solo ve sus propios tickets', function () {
    $me = User::factory()->create();
    portalTicket($me);
    portalTicket(User::factory()->create());

    $this->actingAs($me)
        ->get(route('soporte.index'))
        ->assertInertia(fn ($page) => $page->has('tickets', 1));
});

test('reportar desde el portal crea el ticket con canal portal, solicitante de la sesion y SLA', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('soporte.tickets.store'), [
        'title'             => 'Sin red en el comedor',
        'description'       => 'Desde las 8am',
        'category_id'       => ServiceDeskCategory::where('code', 'RED')->first()->id,
        'impact'            => 1,
        'urgency'           => 1,
        // Intento de suplantar al solicitante: debe ignorarse.
        'requester_user_id' => User::factory()->create()->id,
        'requester_name'    => 'Otro',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $ticket = ServiceDeskTicket::firstOrFail();

    expect($ticket->channel)->toBe('portal')
        ->and($ticket->requester_user_id)->toBe($user->id)
        ->and($ticket->requester_name)->toBe($user->name)
        ->and($ticket->priority)->toBe(1)
        ->and($ticket->sla_id)->not->toBeNull()
        ->and($ticket->resolution_due_at)->not->toBeNull()
        ->and($ticket->events()->where('type', 'creado')->exists())->toBeTrue();
});

test('un comentario del usuario saca el ticket de en espera', function () {
    $user = User::factory()->create();
    $ticket = portalTicket($user, ['status' => ServiceDeskTicket::STATUS_EN_ESPERA]);

    $this->actingAs($user)
        ->post(route('soporte.tickets.comment', $ticket->id), ['note' => 'Ya reinicie el equipo'])
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->status)->toBe(ServiceDeskTicket::STATUS_EN_PROCESO)
        ->and($ticket->events()->where('type', 'comentado')->count())->toBe(1);
});

test('no se puede operar el ticket de otro usuario', function () {
    $ticket = portalTicket(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->post(route('soporte.tickets.comment', $ticket->id), ['note' => 'hola'])
        ->assertNotFound();
});

test('el usuario puede reabrir un ticket resuelto pero no uno cerrado', function () {
    $user = User::factory()->create();
    $resolved = portalTicket($user, ['status' => ServiceDeskTicket::STATUS_RESUELTO]);
    $resolved->forceFill(['resolved_at' => now(), 'first_response_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('soporte.tickets.reopen', $resolved->id), ['note' => 'Sigue fallando'])
        ->assertSessionHasNoErrors();

    $resolved->refresh();
    expect($resolved->status)->toBe(ServiceDeskTicket::STATUS_EN_PROCESO)
        ->and($resolved->resolved_at)->toBeNull()
        ->and($resolved->reopened_count)->toBe(1);

    $closed = portalTicket($user, ['status' => ServiceDeskTicket::STATUS_CERRADO]);

    $this->actingAs($user)
        ->post(route('soporte.tickets.reopen', $closed->id), ['note' => 'Sigue fallando'])
        ->assertSessionHasErrors('note');
});

test('el usuario califica una sola vez y solo tickets resueltos', function () {
    $user = User::factory()->create();
    $open = portalTicket($user);

    $this->actingAs($user)
        ->post(route('soporte.tickets.rate', $open->id), ['score' => 5])
        ->assertSessionHasErrors('score');

    $resolved = portalTicket($user, ['status' => ServiceDeskTicket::STATUS_RESUELTO]);

    $this->actingAs($user)->post(route('soporte.tickets.rate', $resolved->id), ['score' => 4])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('soporte.tickets.rate', $resolved->id), ['score' => 1])->assertSessionHasErrors('score');

    $survey = ServiceDeskSatisfactionSurvey::sole();
    expect($survey->ticket_id)->toBe($resolved->id)
        ->and($survey->score)->toBe(4)
        ->and($survey->user_id)->toBe($user->id);
});
