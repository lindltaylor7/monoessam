<?php

namespace App\Http\Controllers\Concerns;

use App\Models\EquipmentDispatch;
use Illuminate\Support\Facades\Cache;

/**
 * Correlativos de guía (GR-AAAA-NNNN) y despacho (DESP-AAAA-NNNN).
 *
 * Antes se calculaban con `COUNT(*) + 1` sin ningún lock: dos usuarios simultáneos
 * obtenían el mismo número, y el `$seq` de despacho se recalculaba dentro del bucle
 * contando filas que la propia iteración acababa de insertar. Aquí el bloque entero
 * de asignación + inserción se ejecuta bajo un lock distribuido (Cache::lock), el
 * mismo mecanismo que usa SaleController para la doble venta.
 */
trait GeneratesDispatchNumbers
{
    /**
     * Ejecuta $callback con la exclusión garantizada sobre los correlativos de despacho.
     * El callback recibe un "emisor de correlativos": function(): array{guide,dispatch}
     * — o se usan los helpers next* directamente dentro de él.
     *
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    protected function withDispatchNumberLock(\Closure $callback)
    {
        return Cache::lock('equipment_dispatch_correlatives', 15)->block(10, $callback);
    }

    /** Siguiente número de guía del año. Llamar solo dentro de withDispatchNumberLock(). */
    protected function nextGuideNumber(): string
    {
        $seq = EquipmentDispatch::whereYear('created_at', now()->year)
            ->whereNotNull('guide_number')
            ->distinct('guide_number')
            ->count('guide_number') + 1;

        return 'GR-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /** Siguiente número de despacho del año. Llamar solo dentro de withDispatchNumberLock(). */
    protected function nextDispatchNumber(int $offset = 0): string
    {
        $seq = EquipmentDispatch::whereYear('created_at', now()->year)->count() + 1 + $offset;

        return 'DESP-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
