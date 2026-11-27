<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Mina / Unidad / Comedor que ya no opera pero conserva su historia.
 *
 * NO es un global scope a propósito (ni SoftDeletes): las relaciones hacia un comedor
 * archivado (weekly_programs->cafe, sales->cafe, …) tienen que seguir resolviendo, y los
 * módulos de alimentación y los reportes lo siguen mostrando. Los selectores de los módulos
 * operativos aplican `->operational()` explícitamente.
 *
 * Archivar/reactivar se propaga en cascada (ver App\Http\Controllers\OrgArchiveController),
 * así que basta con mirar la columna propia: no hace falta revisar la unidad/mina padre.
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
