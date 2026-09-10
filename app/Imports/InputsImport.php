<?php

namespace App\Imports;

use App\Models\Input;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithoutHeadingRow;
use Illuminate\Support\Collection;

/**
 * Importa el maestro de insumos desde el Excel de compras.
 *
 * La planilla tiene la fila 1 con encabezados parciales (las columnas de código y descripción
 * van sin título), así que se lee por posición en lugar de por encabezado:
 *   A: código | B: descripción | C: u. de medida | D: unidad | E: costo | F: total
 *
 * Si la fila trae código, se hace upsert por `code`; si no, se crea siempre una fila nueva.
 */
class InputsImport implements ToCollection, WithoutHeadingRow
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // Salta la fila de encabezados y las filas sin descripción.
            $name = trim((string) ($row[1] ?? ''));
            if ($name === '' || strtolower($name) === 'descripcion') {
                continue;
            }

            $code = trim((string) ($row[0] ?? ''));

            $attributes = [
                'name'            => $name,
                'unit_of_measure' => trim((string) ($row[2] ?? '')) ?: null,
                'unit'            => $this->number($row[3] ?? 0),
                'cost'            => $this->number($row[4] ?? 0),
                'total'           => $this->number($row[5] ?? 0),
            ];

            if ($code !== '') {
                Input::updateOrCreate(['code' => $code], $attributes);
            } else {
                Input::create($attributes);
            }
        }
    }

    private function number(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
