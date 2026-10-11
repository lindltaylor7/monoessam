<?php

namespace App\Http\Controllers\ServiceDesk;

use App\Exports\ServiceDeskIndicatorsExport;
use App\Http\Controllers\Controller;
use App\Services\ServiceDesk\IndicatorCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Tablero de indicadores: reproduce la matriz de operacionalizacion (variable, dimension,
 * indicador, unidad de medida y fuente de datos) con el valor calculado del periodo, su meta
 * y el resultado de la validacion de la fuente.
 */
class IndicatorController extends Controller
{
    public function index(Request $request)
    {
        $calculator = new IndicatorCalculator(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        return Inertia::render('service-desk/Indicators', array_merge($calculator->payload(), [
            'filters' => [
                'from' => $calculator->from()->toDateString(),
                'to'   => $calculator->to()->toDateString(),
            ],
        ]));
    }

    /**
     * Misma informacion en Excel, para adjuntarla como evidencia del periodo evaluado.
     */
    public function export(Request $request)
    {
        $calculator = new IndicatorCalculator(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $name = 'indicadores-service-desk-' . $calculator->from()->toDateString() . '-a-' . $calculator->to()->toDateString() . '.xlsx';

        return Excel::download(new ServiceDeskIndicatorsExport($calculator), $name);
    }
}
