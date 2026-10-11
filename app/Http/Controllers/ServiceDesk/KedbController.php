<?php

namespace App\Http\Controllers\ServiceDesk;

use App\Http\Controllers\Controller;
use App\Models\ServiceDeskCategory;
use App\Models\ServiceDeskKedbArticle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Base de errores conocidos (KEDB). Alimenta la dimension "Gestion del Conocimiento": el
 * indicador mide que porcentaje de tickets se resolvio con un articulo de aqui, de modo que
 * esta pantalla es el insumo que hace posible ese porcentaje.
 */
class KedbController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $articles = ServiceDeskKedbArticle::with(['category:id,name', 'author:id,name'])
            ->withCount('tickets')
            ->when($search, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('code', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('symptoms', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('times_used')
            ->orderBy('code')
            ->get();

        return Inertia::render('service-desk/Kedb', [
            'articles'   => $articles,
            'categories' => ServiceDeskCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters'    => ['search' => $search],
            'statuses'   => ServiceDeskKedbArticle::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = ServiceDeskKedbArticle::nextCode();
        $data['created_by'] = $request->user()?->id;

        ServiceDeskKedbArticle::create($data);

        return redirect()->back()->with('success', 'Articulo registrado en la KEDB.');
    }

    public function update(Request $request, int $id)
    {
        $article = ServiceDeskKedbArticle::findOrFail($id);
        $article->update($this->validated($request));

        return redirect()->back()->with('success', 'Articulo actualizado.');
    }

    /**
     * Un articulo usado por tickets no se borra: se retira. Borrarlo dejaria huerfana la
     * trazabilidad del indicador de uso de KEDB en los periodos ya reportados.
     */
    public function destroy(int $id)
    {
        $article = ServiceDeskKedbArticle::withCount('tickets')->findOrFail($id);

        if ($article->tickets_count > 0) {
            $article->update(['status' => ServiceDeskKedbArticle::STATUS_RETIRADO]);

            return redirect()->back()->with('success', 'El articulo tiene tickets asociados: se marco como retirado en lugar de eliminarlo.');
        }

        $article->delete();

        return redirect()->back()->with('success', 'Articulo eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'symptoms'    => ['nullable', 'string', 'max:2000'],
            'root_cause'  => ['nullable', 'string', 'max:2000'],
            'workaround'  => ['nullable', 'string', 'max:2000'],
            'solution'    => ['required', 'string', 'max:5000'],
            'category_id' => ['nullable', 'exists:service_desk_categories,id'],
            'status'      => ['required', Rule::in(ServiceDeskKedbArticle::STATUSES)],
        ]);
    }
}
