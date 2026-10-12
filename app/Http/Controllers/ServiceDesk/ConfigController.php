<?php

namespace App\Http\Controllers\ServiceDesk;

use App\Http\Controllers\Controller;
use App\Models\ServiceDeskCategory;
use App\Models\ServiceDeskMaturityQuestion;
use App\Models\ServiceDeskSla;
use App\Models\ServiceDeskTicket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Configuracion del modulo: catalogo de categorias, politicas de SLA por prioridad y el
 * cuestionario de madurez. Tambien expone la matriz impacto/urgencia y las metas vigentes,
 * que es la referencia con la que se interpreta el tablero.
 */
class ConfigController extends Controller
{
    public function index()
    {
        return Inertia::render('service-desk/Config', [
            'categories' => ServiceDeskCategory::withCount('tickets')->orderBy('name')->get(),
            'slas'       => ServiceDeskSla::orderBy('priority')->get(),
            'questions'  => ServiceDeskMaturityQuestion::orderBy('order')->get(),
            'matrix'     => ServiceDeskTicket::PRIORITY_MATRIX,
            'targets'    => config('servicedesk.targets'),
            'minSample'  => config('servicedesk.min_sample'),
            'minCoverage' => config('servicedesk.min_coverage'),
            'dimensions' => ServiceDeskMaturityQuestion::DIMENSIONS,
        ]);
    }

    public function storeCategory(Request $request)
    {
        ServiceDeskCategory::create($this->validatedCategory($request));

        return redirect()->back()->with('success', 'Categoria creada.');
    }

    public function updateCategory(Request $request, int $id)
    {
        $category = ServiceDeskCategory::findOrFail($id);
        $category->update($this->validatedCategory($request, $category->id));

        return redirect()->back()->with('success', 'Categoria actualizada.');
    }

    /**
     * Una categoria con tickets se desactiva en lugar de borrarse: los indicadores
     * historicos se leen por categoria y perderian su etiqueta.
     */
    public function destroyCategory(int $id)
    {
        $category = ServiceDeskCategory::withCount('tickets')->findOrFail($id);

        if ($category->tickets_count > 0) {
            $category->update(['is_active' => false]);

            return redirect()->back()->with('success', 'La categoria tiene tickets asociados: se desactivo en lugar de eliminarla.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Categoria eliminada.');
    }

    /**
     * Ajusta los minutos comprometidos de una prioridad. No reescribe los vencimientos ya
     * congelados en los tickets existentes: el cumplimiento historico se mide contra el
     * acuerdo que estaba vigente cuando se reporto la incidencia.
     */
    public function updateSla(Request $request, int $id)
    {
        $sla = ServiceDeskSla::findOrFail($id);

        $sla->update($request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'response_minutes'   => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
            'description'        => ['nullable', 'string', 'max:500'],
            'is_active'          => ['required', 'boolean'],
        ]));

        return redirect()->back()->with('success', 'Politica de SLA actualizada. Aplica a los tickets que se registren desde ahora.');
    }

    public function storeQuestion(Request $request)
    {
        $data = $this->validatedQuestion($request);
        $data['order'] = (int) (ServiceDeskMaturityQuestion::max('order') ?? 0) + 1;

        ServiceDeskMaturityQuestion::create($data);

        return redirect()->back()->with('success', 'Item agregado al cuestionario.');
    }

    public function updateQuestion(Request $request, int $id)
    {
        $question = ServiceDeskMaturityQuestion::findOrFail($id);
        $question->update($this->validatedQuestion($request, $question->id));

        return redirect()->back()->with('success', 'Item actualizado.');
    }

    /**
     * Los items respondidos se desactivan: borrarlos cambiaria el promedio de cuestionarios
     * ya aplicados y romperia la comparabilidad entre periodos.
     */
    public function destroyQuestion(int $id)
    {
        $question = ServiceDeskMaturityQuestion::withCount('answers')->findOrFail($id);

        if ($question->answers_count > 0) {
            $question->update(['is_active' => false]);

            return redirect()->back()->with('success', 'El item ya fue respondido: se desactivo en lugar de eliminarlo.');
        }

        $question->delete();

        return redirect()->back()->with('success', 'Item eliminado.');
    }

    private function validatedCategory(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'        => ['required', 'string', 'max:30', Rule::unique('service_desk_categories', 'code')->ignore($ignoreId)],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id'   => ['nullable', 'exists:service_desk_categories,id'],
            'is_active'   => ['required', 'boolean'],
        ]);
    }

    private function validatedQuestion(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'      => ['required', 'string', 'max:20', Rule::unique('service_desk_maturity_questions', 'code')->ignore($ignoreId)],
            'dimension' => ['required', Rule::in(ServiceDeskMaturityQuestion::DIMENSIONS)],
            'statement' => ['required', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
