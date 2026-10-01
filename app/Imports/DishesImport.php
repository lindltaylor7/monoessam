<?php

namespace App\Imports;

use App\Models\Dish;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa el catalogo de platos desde Excel, sin reasignar ids.
 *
 * POR QUE NO ES UN ToModel
 *
 * La version anterior implementaba ToModel y devolvia `new Dish(['name' => $name])`
 * por cada fila. Eso inserta siempre una fila nueva: reimportar el mismo Excel
 * duplicaba el catalogo entero, y si antes se vaciaba `dishes` el AUTO_INCREMENT
 * arrancaba de cero y TODOS los ids quedaban reasignados.
 *
 * Reasignar ids de `dishes` es destructivo aunque la tabla quede perfecta, porque
 * hay dos cosas que apuntan a esos ids y no se enteran:
 *
 *   dish_recipes.dish_id        cada receta queda colgada del plato equivocado
 *   menu_cycles.cycle_data      los ciclos referencian dish_id dentro del JSON
 *
 * Nada de eso falla: las FK siguen cuadrando porque los ids existen, solo que
 * identifican otro plato. El sintoma aparece mucho despues, como recetas que no
 * tienen sentido (una infusion con esparragos y comino), y para entonces ya no
 * se distingue el dato bueno del malo. Es exactamente lo que le paso al
 * recetario: quedo a medias desalineado y sin forma de reconstruirlo.
 *
 * Por eso el emparejamiento es por NOMBRE y nunca por posicion ni por id:
 * firstOrCreate conserva el id del plato que ya existe y solo crea el que falta,
 * asi que reimportar es idempotente y no rompe a nadie.
 */
class DishesImport implements ToCollection, WithHeadingRow
{
    /** Platos creados en esta corrida. */
    private int $creados = 0;

    /** Filas cuyo plato ya existia y se respeto. */
    private int $existentes = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['cnomplato'] ?? ''));
            if ($name === '') {
                continue;
            }

            // firstOrCreate y no updateOrCreate: si el plato ya esta, se deja
            // intacto con su id. Lo unico que puede pasar es que se agregue uno
            // nuevo al final, que es inofensivo para las referencias existentes.
            $dish = Dish::firstOrCreate(['name' => $name]);

            if ($dish->wasRecentlyCreated) {
                $this->creados++;
            } else {
                $this->existentes++;
            }
        }
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function creados(): int
    {
        return $this->creados;
    }

    public function existentes(): int
    {
        return $this->existentes;
    }
}
