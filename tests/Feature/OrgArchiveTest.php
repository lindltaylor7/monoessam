<?php

use App\Models\Cafe;
use App\Models\Mine;
use App\Models\Unit;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Minas/Unidades/Comedores archivados (p. ej. los z(Hist) del sistema antiguo): se ocultan en
 * los módulos operativos pero no se borran, y sus relaciones siguen resolviendo.
 */
function managementUser(): User
{
    Permission::firstOrCreate(
        ['name' => 'Lugares', 'guard_name' => 'web'],
        ['route_name' => 'management'],
    );

    $user = User::factory()->create();
    $user->givePermissionTo('Lugares');

    return $user;
}

function seedOrgTree(): array
{
    $mine = Mine::factory()->create();
    $unitA = Unit::factory()->create(['mine_id' => $mine->id]);
    $unitB = Unit::factory()->create(['mine_id' => $mine->id]);
    $cafeA = Cafe::factory()->create(['unit_id' => $unitA->id]);
    $cafeB = Cafe::factory()->create(['unit_id' => $unitB->id]);

    return compact('mine', 'unitA', 'unitB', 'cafeA', 'cafeB');
}

test('archivar una mina archiva en cascada sus unidades y comedores sin borrarlos', function () {
    ['mine' => $mine, 'cafeA' => $cafeA] = seedOrgTree();
    $other = Cafe::factory()->create();

    $this->actingAs(managementUser())
        ->patchJson("/mines/{$mine->id}/archive", ['archived' => true])
        ->assertOk();

    expect(Mine::operational()->pluck('id'))->not->toContain($mine->id)
        ->and(Unit::operational()->where('mine_id', $mine->id)->count())->toBe(0)
        ->and(Cafe::operational()->pluck('id')->all())->toBe([$other->id])
        ->and(Cafe::count())->toBe(3)
        // la relación hacia el comedor archivado sigue resolviendo (historia de alimentación)
        ->and($cafeA->fresh()->unit->mine->id)->toBe($mine->id);
});

test('reactivar un comedor reactiva su unidad y su mina, pero no a sus hermanos', function () {
    ['mine' => $mine, 'unitA' => $unitA, 'unitB' => $unitB, 'cafeA' => $cafeA, 'cafeB' => $cafeB] = seedOrgTree();
    $user = managementUser();

    $this->actingAs($user)->patchJson("/mines/{$mine->id}/archive", ['archived' => true])->assertOk();
    $this->actingAs($user)->patchJson("/cafes/{$cafeA->id}/archive", ['archived' => false])->assertOk();

    expect($cafeA->fresh()->isArchived())->toBeFalse()
        ->and($unitA->fresh()->isArchived())->toBeFalse()
        ->and($mine->fresh()->isArchived())->toBeFalse()
        ->and($unitB->fresh()->isArchived())->toBeTrue()
        ->and($cafeB->fresh()->isArchived())->toBeTrue();
});

test('archivar una unidad no toca la mina ni las otras unidades', function () {
    ['mine' => $mine, 'unitA' => $unitA, 'unitB' => $unitB, 'cafeA' => $cafeA] = seedOrgTree();

    $this->actingAs(managementUser())
        ->patchJson("/units/{$unitA->id}/archive", ['archived' => true])
        ->assertOk();

    expect($unitA->fresh()->isArchived())->toBeTrue()
        ->and($cafeA->fresh()->isArchived())->toBeTrue()
        ->and($unitB->fresh()->isArchived())->toBeFalse()
        ->and($mine->fresh()->isArchived())->toBeFalse();
});

test('archivar requiere el permiso del módulo Lugares', function () {
    ['mine' => $mine] = seedOrgTree();
    managementUser(); // registra el permiso: sin ninguno, el middleware es fail-open

    $this->actingAs(User::factory()->create())
        ->patchJson("/mines/{$mine->id}/archive", ['archived' => true])
        ->assertForbidden();

    expect($mine->fresh()->isArchived())->toBeFalse();
});
