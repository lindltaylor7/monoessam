<?php

namespace App\Http\Controllers\ServiceDesk;

use App\Http\Controllers\Controller;
use App\Models\ServiceDeskMaturityAnswer;
use App\Models\ServiceDeskMaturityAssessment;
use App\Models\ServiceDeskMaturityQuestion;
use App\Models\ServiceDeskSatisfactionSurvey;
use App\Models\ServiceDeskTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Los dos instrumentos de la matriz que no salen de los logs:
 *
 *  - Cuestionario a usuarios (Likert 1..5) -> Calidad Percibida del Servicio.
 *  - Cuestionario a personal TI (ordinal 1..5) -> Adopcion de Practicas ITIL 4.
 *
 * Ambos se guardan con la fecha de respuesta, que es la que usa el tablero para ubicarlos
 * en el periodo analizado.
 */
class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->string('from')->toString() ?: now()->subDays(89)->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();

        $surveys = ServiceDeskSatisfactionSurvey::with(['ticket:id,code,title', 'user:id,name'])
            ->whereBetween('answered_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->orderByDesc('answered_at')
            ->limit(300)
            ->get();

        $assessments = ServiceDeskMaturityAssessment::with(['answers'])
            ->whereBetween('answered_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            ->orderByDesc('answered_at')
            ->get()
            ->map(fn ($assessment) => [
                'id'              => $assessment->id,
                'respondent_name' => $assessment->respondent_name,
                'respondent_role' => $assessment->respondent_role,
                'period'          => $assessment->period,
                'answered_at'     => $assessment->answered_at,
                'comments'        => $assessment->comments,
                'average'         => $assessment->averageScore(),
            ]);

        // Tickets atendidos que todavia no tienen encuesta: es la lista de trabajo para
        // levantar la cobertura del indicador de satisfaccion.
        $pendingTickets = ServiceDeskTicket::whereIn('status', ServiceDeskTicket::CLOSED_STATUSES)
            ->whereDoesntHave('survey')
            ->orderByDesc('resolved_at')
            ->limit(200)
            ->get(['id', 'code', 'title', 'requester_name', 'resolved_at']);

        return Inertia::render('service-desk/Surveys', [
            'surveys'        => $surveys,
            'assessments'    => $assessments,
            'questions'      => ServiceDeskMaturityQuestion::where('is_active', true)->orderBy('order')->get(),
            'pendingTickets' => $pendingTickets,
            'filters'        => ['from' => $from, 'to' => $to],
            'stats'          => $this->stats($from, $to),
        ]);
    }

    public function storeSatisfaction(Request $request)
    {
        $data = $request->validate([
            'ticket_id'       => ['nullable', 'exists:service_desk_tickets,id', 'unique:service_desk_satisfaction_surveys,ticket_id'],
            'respondent_name' => ['required', 'string', 'max:255'],
            'respondent_area' => ['nullable', 'string', 'max:255'],
            'score'           => ['required', 'integer', 'between:1,5'],
            'q_attention'     => ['nullable', 'integer', 'between:1,5'],
            'q_response_time' => ['nullable', 'integer', 'between:1,5'],
            'q_solution'      => ['nullable', 'integer', 'between:1,5'],
            'q_communication' => ['nullable', 'integer', 'between:1,5'],
            'comments'        => ['nullable', 'string', 'max:1000'],
            'answered_at'     => ['nullable', 'date'],
        ], [
            'ticket_id.unique' => 'Ese ticket ya tiene una encuesta registrada.',
        ]);

        ServiceDeskSatisfactionSurvey::create(array_merge($data, [
            'user_id'     => $request->user()?->id,
            'answered_at' => $data['answered_at'] ?? now(),
        ]));

        return redirect()->back()->with('success', 'Encuesta de satisfaccion registrada.');
    }

    public function destroySatisfaction(int $id)
    {
        ServiceDeskSatisfactionSurvey::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Encuesta eliminada.');
    }

    /**
     * Guarda una aplicacion completa del cuestionario de madurez. Se exige una respuesta por
     * cada item activo: un cuestionario a medias sesgaria el promedio de la dimension.
     */
    public function storeMaturity(Request $request)
    {
        $data = $request->validate([
            'respondent_name' => ['required', 'string', 'max:255'],
            'respondent_role' => ['nullable', 'string', 'max:255'],
            'period'          => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'answered_at'     => ['nullable', 'date'],
            'comments'        => ['nullable', 'string', 'max:1000'],
            'answers'         => ['required', 'array'],
            'answers.*'       => ['required', 'integer', 'between:1,5'],
        ], [
            'period.regex' => 'El periodo debe tener el formato YYYY-MM.',
        ]);

        $questions = ServiceDeskMaturityQuestion::where('is_active', true)->pluck('id');
        $answered = collect($data['answers'])->keys()->map(fn ($id) => (int) $id);

        if ($questions->diff($answered)->isNotEmpty()) {
            return redirect()->back()->withErrors([
                'answers' => 'Responda todos los items del cuestionario antes de guardar.',
            ]);
        }

        DB::transaction(function () use ($data, $request, $questions) {
            $assessment = ServiceDeskMaturityAssessment::create([
                'user_id'         => $request->user()?->id,
                'respondent_name' => $data['respondent_name'],
                'respondent_role' => $data['respondent_role'] ?? null,
                'period'          => $data['period'],
                'answered_at'     => $data['answered_at'] ?? now(),
                'comments'        => $data['comments'] ?? null,
            ]);

            foreach ($data['answers'] as $questionId => $score) {
                if (! $questions->contains((int) $questionId)) {
                    continue;
                }

                ServiceDeskMaturityAnswer::create([
                    'assessment_id' => $assessment->id,
                    'question_id'   => (int) $questionId,
                    'score'         => (int) $score,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Cuestionario de madurez ITIL 4 registrado.');
    }

    public function destroyMaturity(int $id)
    {
        ServiceDeskMaturityAssessment::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Cuestionario eliminado.');
    }

    /**
     * Resumen de los dos instrumentos: promedio global y promedio por dimension ITIL.
     */
    private function stats(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $surveys = ServiceDeskSatisfactionSurvey::whereBetween('answered_at', [$start, $end])->get();
        $assessmentIds = ServiceDeskMaturityAssessment::whereBetween('answered_at', [$start, $end])->pluck('id');

        $byDimension = collect(ServiceDeskMaturityQuestion::DIMENSIONS)->mapWithKeys(function ($dimension) use ($assessmentIds) {
            $avg = $assessmentIds->isEmpty() ? null : ServiceDeskMaturityAnswer::whereIn('assessment_id', $assessmentIds)
                ->whereHas('question', fn ($q) => $q->where('dimension', $dimension))
                ->avg('score');

            return [$dimension => $avg === null ? null : round((float) $avg, 2)];
        })->all();

        return [
            'surveys_count'     => $surveys->count(),
            'satisfaction_avg'  => $surveys->isEmpty() ? null : round((float) $surveys->avg('score'), 2),
            'satisfaction_pct'  => $surveys->isEmpty() ? null : round($surveys->where('score', '>=', 4)->count() * 100 / $surveys->count(), 2),
            'assessments_count' => $assessmentIds->count(),
            'maturity_by_dimension' => $byDimension,
            'targets'           => [
                'satisfaction'    => (float) config('servicedesk.targets.satisfaction'),
                'estandarizacion' => (float) config('servicedesk.targets.estandarizacion'),
            ],
        ];
    }
}
