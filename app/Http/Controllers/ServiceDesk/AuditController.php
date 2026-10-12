<?php

namespace App\Http\Controllers\ServiceDesk;

use App\Http\Controllers\Controller;
use App\Models\ServiceDeskCategory;
use App\Models\ServiceDeskTicket;
use App\Models\ServiceDeskTicketAudit;
use App\Models\ServiceDeskTicketEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Auditoria de clasificacion (fuente "Auditoria de Logs" de la matriz). Un revisor contrasta
 * la categoria, el impacto y la urgencia registrados contra lo que correspondia, y el sistema
 * verifica ademas que la prioridad derive de la matriz ITIL.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->string('from')->toString() ?: now()->subDays(89)->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();
        $scope = $request->string('scope')->toString() ?: 'pendientes';

        $tickets = ServiceDeskTicket::with(['category:id,name', 'assignee:id,name', 'audit.auditor:id,name', 'audit.suggestedCategory:id,name'])
            ->reportedBetween($from, $to)
            ->when($scope === 'pendientes', fn ($q) => $q->whereDoesntHave('audit'))
            ->when($scope === 'auditados', fn ($q) => $q->whereHas('audit'))
            ->when($scope === 'incorrectos', fn ($q) => $q->whereHas('audit', fn ($a) => $a->where('is_correct', false)))
            ->orderByDesc('reported_at')
            ->limit(300)
            ->get();

        $universe = ServiceDeskTicket::reportedBetween($from, $to);
        $total = (clone $universe)->count();
        $audited = (clone $universe)->whereHas('audit')->count();
        $correct = (clone $universe)->whereHas('audit', fn ($a) => $a->where('is_correct', true))->count();

        return Inertia::render('service-desk/Audits', [
            'tickets'    => $tickets,
            'categories' => ServiceDeskCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters'    => ['from' => $from, 'to' => $to, 'scope' => $scope],
            'stats'      => [
                'total'        => $total,
                'audited'      => $audited,
                'pending'      => $total - $audited,
                'correct'      => $correct,
                'coverage_pct' => $total > 0 ? round($audited * 100 / $total, 2) : null,
                'accuracy_pct' => $audited > 0 ? round($correct * 100 / $audited, 2) : null,
                'min_coverage' => (float) config('servicedesk.min_coverage.audit'),
                'target'       => (float) config('servicedesk.targets.classification'),
            ],
        ]);
    }

    /**
     * Registra o actualiza la auditoria del ticket. `is_correct` se deriva de los cuatro
     * criterios y nunca se recibe del cliente: es el numerador del indicador y debe ser
     * reproducible a partir de los criterios marcados.
     */
    public function store(Request $request, int $ticketId)
    {
        $data = $request->validate([
            'category_correct'      => ['required', 'boolean'],
            'impact_correct'        => ['required', 'boolean'],
            'urgency_correct'       => ['required', 'boolean'],
            'suggested_category_id' => ['nullable', 'exists:service_desk_categories,id'],
            'suggested_impact'      => ['nullable', 'integer', 'between:1,3'],
            'suggested_urgency'     => ['nullable', 'integer', 'between:1,3'],
            'findings'              => ['nullable', 'string', 'max:2000'],
            'audited_at'            => ['nullable', 'date'],
        ]);

        $ticket = ServiceDeskTicket::findOrFail($ticketId);

        // La prioridad es correcta si coincide con la que arroja la matriz para el impacto y
        // la urgencia registrados: eso se comprueba, no se opina.
        $priorityCorrect = (int) $ticket->priority === ServiceDeskTicket::calculatePriority((int) $ticket->impact, (int) $ticket->urgency);

        $isCorrect = $data['category_correct'] && $data['impact_correct'] && $data['urgency_correct'] && $priorityCorrect;

        ServiceDeskTicketAudit::updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'auditor_id'            => $request->user()?->id,
                'category_correct'      => $data['category_correct'],
                'impact_correct'        => $data['impact_correct'],
                'urgency_correct'       => $data['urgency_correct'],
                'priority_correct'      => $priorityCorrect,
                'is_correct'            => $isCorrect,
                'suggested_category_id' => $data['suggested_category_id'] ?? null,
                'suggested_impact'      => $data['suggested_impact'] ?? null,
                'suggested_urgency'     => $data['suggested_urgency'] ?? null,
                'findings'              => $data['findings'] ?? null,
                'audited_at'            => ! empty($data['audited_at']) ? Carbon::parse($data['audited_at']) : now(),
            ]
        );

        ServiceDeskTicketEvent::log(
            $ticket,
            'auditado',
            ($isCorrect ? 'Clasificacion conforme.' : 'Clasificacion observada.') . ' ' . ($data['findings'] ?? '')
        );

        return redirect()->back()->with('success', "Auditoria registrada para {$ticket->code}.");
    }

    /**
     * Aplica al ticket la clasificacion sugerida por la auditoria y recalcula prioridad y SLA.
     * Cierra el ciclo: auditar sin corregir no mejora el indicador del periodo siguiente.
     */
    public function applyCorrection(Request $request, int $ticketId)
    {
        $ticket = ServiceDeskTicket::with('audit')->findOrFail($ticketId);

        if (! $ticket->audit) {
            return redirect()->back()->withErrors(['audit' => 'El ticket no tiene auditoria registrada.']);
        }

        $before = "{$ticket->priority_label} (I{$ticket->impact}/U{$ticket->urgency})";

        $ticket->category_id = $ticket->audit->suggested_category_id ?? $ticket->category_id;
        $ticket->impact = $ticket->audit->suggested_impact ?? $ticket->impact;
        $ticket->urgency = $ticket->audit->suggested_urgency ?? $ticket->urgency;
        $ticket->applyPriorityAndSla();
        $ticket->save();

        ServiceDeskTicketEvent::log(
            $ticket,
            'reclasificado',
            "Correccion aplicada desde auditoria: {$before} -> {$ticket->priority_label} (I{$ticket->impact}/U{$ticket->urgency})."
        );

        return redirect()->back()->with('success', "Clasificacion corregida en {$ticket->code}.");
    }

    public function destroy(int $ticketId)
    {
        ServiceDeskTicketAudit::where('ticket_id', $ticketId)->delete();

        return redirect()->back()->with('success', 'Auditoria eliminada.');
    }
}
