<?php

namespace App\Exports;

use App\Exports\Sheets\WeeklyMenuSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "Menú Semanal" — una hoja por programación seleccionada. Cada hoja es la grilla de menú de
 * esa semana: opciones (categoría de plato) en filas, días en columnas, agrupadas por servicio.
 */
class WeeklyMenuExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $programs,
        private readonly Collection $menuStructure,
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $usedTitles = [];

        foreach ($this->programs as $program) {
            $sheets[] = new WeeklyMenuSheet($program, $this->menuStructure, $this->sheetTitle($program, $usedTitles));
        }

        return $sheets;
    }

    /**
     * Título de hoja: nombre del comedor + semana ISO, recortado a los 31 caracteres que admite
     * Excel y sin caracteres inválidos, con un sufijo numérico si se repite.
     */
    private function sheetTitle($program, array &$usedTitles): string
    {
        $week = \Carbon\Carbon::parse($program->start_date)->isoWeek();
        $base = trim(($program->cafe->name ?? 'Comedor') . ' S' . $week);
        $base = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $base);
        $base = trim(mb_substr($base, 0, 31));

        $title = $base;
        $i = 2;
        while (in_array($title, $usedTitles, true)) {
            $suffix = ' (' . $i . ')';
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $i++;
        }

        $usedTitles[] = $title;

        return $title;
    }
}
