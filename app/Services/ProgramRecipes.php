<?php

namespace App\Services;

use App\Models\DishRecipe;
use App\Models\Level;
use App\Models\WeeklyProgramItem;
use Illuminate\Support\Collection;

/**
 * Resuelve la receta (quebrado) de cada plato programado. Cada ítem usa la receta de SU nivel
 * (`weekly_program_items.level_id`, heredado del ciclo de menú); el nivel que se elige al generar
 * un reporte solo cubre a los ítems que no lo tienen (programaciones anteriores al cambio).
 *
 * Si el plato no tiene receta en el nivel del ítem, se cae a la del nivel de respaldo antes que
 * dejarlo fuera del requerimiento.
 */
class ProgramRecipes
{
    /** @var Collection<string, DishRecipe> indexado por "dish_id-level_id" */
    private Collection $recipes;

    /** @var Collection<int, Level> */
    private Collection $levels;

    private function __construct(private ?Level $fallback)
    {
    }

    /**
     * @param  Collection<int, WeeklyProgramItem>  $items
     */
    public static function load(Collection $items, ?Level $fallback, array $with = ['ingredients']): self
    {
        $resolver = new self($fallback);

        $levelIds = $items->pluck('level_id')->filter()->push($fallback?->id)->filter()->unique()->values();
        $dishIds = $items->pluck('dish_id')->filter()->unique()->values();

        $resolver->levels = Level::whereIn('id', $levelIds)->get()->keyBy('id');
        $resolver->recipes = $levelIds->isEmpty() || $dishIds->isEmpty()
            ? collect()
            : DishRecipe::whereIn('level_id', $levelIds)
                ->whereIn('dish_id', $dishIds)
                ->with($with)
                ->get()
                ->keyBy(fn ($recipe) => $recipe->dish_id . '-' . $recipe->level_id);

        return $resolver;
    }

    public function for(WeeklyProgramItem $item): ?DishRecipe
    {
        if ($item->level_id && ($recipe = $this->recipes->get($item->dish_id . '-' . $item->level_id))) {
            return $recipe;
        }

        return $this->fallback ? $this->recipes->get($item->dish_id . '-' . $this->fallback->id) : null;
    }

    /** Nombre del nivel con que se costea el ítem (el suyo, o el de respaldo si no tiene). */
    public function levelNameFor(WeeklyProgramItem $item): ?string
    {
        $levelId = $item->level_id ?: $this->fallback?->id;

        return $levelId ? $this->levels->get($levelId)?->name : null;
    }

    /**
     * Texto para la cabecera de los reportes: el único nivel usado, o "Según programación" cuando
     * los platos vienen con niveles distintos.
     *
     * @param  Collection<int, WeeklyProgramItem>  $items
     */
    public function label(Collection $items): string
    {
        $names = $items->map(fn ($item) => $this->levelNameFor($item))->filter()->unique()->values();

        return match ($names->count()) {
            0 => $this->fallback?->name ?? '—',
            1 => $names->first(),
            default => 'Según programación (' . $names->implode(', ') . ')',
        };
    }
}
