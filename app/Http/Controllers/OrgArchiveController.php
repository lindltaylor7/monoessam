<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\Mine;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Archivar / reactivar Minas, Unidades y Comedores (no borra nada).
 *
 * - Archivar se propaga hacia abajo: mina → sus unidades → sus comedores.
 * - Reactivar se propaga hacia abajo y también hacia arriba (un comedor activo no
 *   puede colgar de una unidad o mina archivada, porque los selectores operativos
 *   no lo alcanzarían).
 */
class OrgArchiveController extends Controller
{
    public function mine(Request $request, Mine $mine): JsonResponse
    {
        $archive = $this->archiveFlag($request);
        $unitIds = $mine->units()->pluck('id');

        DB::transaction(function () use ($archive, $mine, $unitIds) {
            $this->stamp(Mine::whereKey($mine->id), $archive);
            $this->stamp(Unit::whereIn('id', $unitIds), $archive);
            $this->stamp(Cafe::whereIn('unit_id', $unitIds), $archive);
        });

        return response()->json(['ok' => true]);
    }

    public function unit(Request $request, Unit $unit): JsonResponse
    {
        $archive = $this->archiveFlag($request);

        DB::transaction(function () use ($archive, $unit) {
            $this->stamp(Unit::whereKey($unit->id), $archive);
            $this->stamp(Cafe::where('unit_id', $unit->id), $archive);
            if (! $archive) {
                $this->stamp(Mine::whereKey($unit->mine_id), false);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function cafe(Request $request, Cafe $cafe): JsonResponse
    {
        $archive = $this->archiveFlag($request);

        DB::transaction(function () use ($archive, $cafe) {
            $this->stamp(Cafe::whereKey($cafe->id), $archive);
            if (! $archive) {
                $unit = $cafe->unit;
                $this->stamp(Unit::whereKey($unit->id), false);
                $this->stamp(Mine::whereKey($unit->mine_id), false);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function archiveFlag(Request $request): bool
    {
        return $request->validate(['archived' => ['required', 'boolean']])['archived'];
    }

    /** Archivar conserva la fecha original de lo que ya estaba archivado. */
    private function stamp($query, bool $archive): void
    {
        $archive
            ? $query->whereNull('archived_at')->update(['archived_at' => now()])
            : $query->whereNotNull('archived_at')->update(['archived_at' => null]);
    }
}
