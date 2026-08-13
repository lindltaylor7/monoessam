<?php

namespace App\Http\Controllers;

use App\Models\AtwaterFactor;
use App\Models\AtwaterSubfactor;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AtwaterFactorController extends Controller
{
    public function index(): Response
    {
        $groups = AtwaterFactor::orderBy('order')
            ->get()
            ->groupBy('group')
            ->map(fn ($rows, $title) => [
                'title' => $title,
                'rows' => $rows->map(fn (AtwaterFactor $row) => [
                    'name' => $row->name,
                    'protein' => self::formatValue($row->protein_kcal, $row->protein_kj),
                    'fat' => self::formatValue($row->fat_kcal, $row->fat_kj),
                    'carb' => self::formatValue($row->carb_kcal, $row->carb_kj, $row->footnote),
                ])->values(),
            ])
            ->values();

        return Inertia::render('atwater/Index', [
            'groups' => $groups,
            'factors' => AtwaterFactor::orderBy('order')->get(['id', 'group', 'name']),
            'subfactors' => AtwaterSubfactor::orderBy('name')->get(['id', 'name', 'carb_kcal']),
        ]);
    }

    public function updateIngredientAtwater(Request $request, string $ingredient)
    {
        $validated = $request->validate([
            'atwater_factor_id' => 'nullable|exists:atwater_factors,id',
            'atwater_subfactor_id' => 'nullable|exists:atwater_subfactors,id',
        ]);

        $ingredientModel = Ingredient::findOrFail($ingredient);
        $ingredientModel->update([
            'atwater_factor_id' => $validated['atwater_factor_id'] ?? null,
            'atwater_subfactor_id' => $validated['atwater_subfactor_id'] ?? null,
        ]);

        // Se consume vía axios (XHR), así que devolvemos JSON. Un back() responde 302 hacia el
        // referer y el navegador reintenta el PUT sobre /atwater, que solo acepta GET (405).
        return response()->json([
            'message' => 'Ingrediente actualizado correctamente.',
            'ingredient' => [
                'id' => $ingredientModel->id,
                'atwater_factor_id' => $ingredientModel->atwater_factor_id,
                'atwater_subfactor_id' => $ingredientModel->atwater_subfactor_id,
            ],
        ]);
    }

    private static function formatValue(?string $kcal, ?string $kj, ?string $footnote = null): string
    {
        if ($kcal === null || $kj === null) {
            return '--';
        }

        $formattedKcal = number_format((float) $kcal, 2, ',', '');
        $formattedKj = number_format((float) $kj, 1, ',', '');

        return "{$formattedKcal} ({$formattedKj})" . ($footnote ?? '');
    }
}
