<?php

namespace App\Http\Controllers\ServiceDesk;

use App\Http\Controllers\Controller;
use App\Models\ServiceDeskCategory;
use App\Models\ServiceDeskKedbArticle;
use App\Models\ServiceDeskSla;
use App\Models\ServiceDeskTicket;
use App\Models\ServiceDeskTicketEvent;
use App\Models\Unit;
use App\Models\User;
use App\Services\ServiceDesk\IndicatorCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Bandeja de incidencias. Cada transicion del ticket (asignar, responder, resolver, cerrar,
 * reabrir) escribe un evento en la bitacora: los indicadores de la V2 se calculan sobre los
 * hitos que se sellan aqui, asi que la trazabilidad no es opcional.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search'      => $request->string('search')->toString(),
            'status'      => $request->string('status')->toString(),
            'priority'    => $request->string('priority')->toString(),
            'category_id' => $request->string('category_id')->toString(),
            'assigned_to' => $request->string('assigned_to')->toString(),
            'from'        => $request->string('from')->toString(),
            'to'          => $request->string('to')->toString(),
        ];

        $tickets = ServiceDeskTicket::with(['category:id,name', 'assignee:id,name', 'sla:id,name', 'audit:id,ticket_id,is_correct', 'survey:id,ticket_id,score'])
            ->when($filters['search'], function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['priority'], fn ($q, $v) => $q->where('priority', (int) $v))
            ->when($filters['category_id'], fn ($q, $v) => $q->where('category_id', (int) $v))
            ->when($filters['assigned_to'], fn ($q, $v) => $q->where('assigned_to', (int) $v))
            ->when($filters['from'], fn ($q, $v) => $q->where('reported_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'], fn ($q, $v) => $q->where('reported_at', '<=', Carbon::parse($v)->endOfDay()))
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END', [ServiceDeskTicket::STATUS_RESUELTO, ServiceDeskTicket::STATUS_CERRADO])
            ->orderBy('priority')
            ->orderByDesc('reported_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('service-desk/Index', [
            'tickets'    => $tickets,
            'filters'    => $filters,
            'categories' => ServiceDeskCategory::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'slas'       => ServiceDeskSla::orderBy('priority')->get(),
            'agents'     => User::orderBy('name')->get(['id', 'name']),
            'units'      => Unit::orderBy('name')->get(['id', 'name']),
            'articles'   => ServiceDeskKedbArticle::where('status', ServiceDeskKedbArticle::STATUS_PUBLICADO)
                ->orderBy('code')
                ->get(['id', 'code', 'title']),
            'stats'      => (new IndicatorCalculator())->summary(),
            'statuses'   => ServiceDeskTicket::STATUSES,
            'channels'   => ServiceDeskTicket::CHANNELS,
        ]);
    }

    /**
     * Detalle con bitacora. Lo consume el panel lateral por fetch, no por navegacion Inertia,
     * para no recargar la bandeja cada vez que se abre un ticket.
     */
    public function show(int $id)
    {
        $ticket = ServiceDeskTicket::with([
            'category', 'sla', 'assignee:id,name', 'requester:id,name', 'unit:id,name',
            'kedbArticle:id,code,title', 'events.user:id,name', 'audit.auditor:id,name', 'survey',
        ])->findOrFail($id);

        return response()->json(['ticket' => $ticket]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedTicket($request);

        $ticket = new ServiceDeskTicket($data);
        $ticket->code = ServiceDeskTicket::nextCode();
        $ticket->status = ServiceDeskTicket::STATUS_NUEVO;
        $ticket->reported_at = $data['reported_at'] ?? now();
        $ticket->applyPriorityAndSla();

        if (! empty($data['assigned_to'])) {
            $ticket->status = ServiceDeskTicket::STATUS_ASIGNADO;
        }

        $ticket->save();

        ServiceDeskTicketEvent::log($ticket, 'creado', 'Ticket registrado por el canal: ' . $ticket->channel, null, $ticket->status);

        return redirect()->back()->with('success', "Ticket {$ticket->code} registrado correctamente.");
    }

    /**
     * Edicion y reclasificacion. Si cambia impacto o urgencia se recalculan prioridad y SLA:
     * el compromiso de atencion depende de la clasificacion, no puede quedar desfasado.
     */
    public function update(Request $request, int $id)
    {
        $ticket = ServiceDeskTicket::findOrFail($id);
        $data = $this->validatedTicket($request);

        $reclassified = (int) $ticket->impact !== (int) $data['impact']
            || (int) $ticket->urgency !== (int) $data['urgency']
            || (int) $ticket->category_id !== (int) ($data['category_id'] ?? 0);

        $previousPriority = $ticket->priority_label;

        $ticket->fill($data);
        $ticket->reported_at = $data['reported_at'] ?? $ticket->reported_at;
        $ticket->applyPriorityAndSla();
        $ticket->save();

        if ($reclassified) {
            ServiceDeskTicketEvent::log(
                $ticket,
                'reclasificado',
                "Reclasificado: prioridad {$previousPriority} -> {$ticket->priority_label}."
            );
        }

        return redirect()->back()->with('success', "Ticket {$ticket->code} actualizado.");
    }

    public function assign(Request $request, int $id)
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = ServiceDeskTicket::findOrFail($id);
        $from = $ticket->status;

        $ticket->assigned_to = $data['assigned_to'];
        if ($ticket->status === ServiceDeskTicket::STATUS_NUEVO) {
            $ticket->status = ServiceDeskTicket::STATUS_ASIGNADO;
        }
        $ticket->save();

        ServiceDeskTicketEvent::log(
            $ticket,
            'asignado',
            $data['note'] ?? ('Asignado a ' . optional(User::find($data['assigned_to']))->name),
            $from,
            $ticket->status
        );

        return redirect()->back()->with('success', 'Ticket asignado.');
    }

    /**
     * Primera respuesta al usuario. Sella `first_response_at` una sola vez: el MTTRsp mide
     * el primer contacto, no el ultimo comentario.
     */
    public function respond(Request $request, int $id)
    {
        $data = $request->validate([
            'note'        => ['required', 'string', 'max:1000'],
            'responded_at' => ['nullable', 'date'],
        ]);

        $ticket = ServiceDeskTicket::findOrFail($id);
        $from = $ticket->status;
        $isFirst = $ticket->first_response_at === null;

        if ($isFirst) {
            $ticket->first_response_at = $data['responded_at'] ?? now();
            if (in_array($ticket->status, [ServiceDeskTicket::STATUS_NUEVO, ServiceDeskTicket::STATUS_ASIGNADO], true)) {
                $ticket->status = ServiceDeskTicket::STATUS_EN_PROCESO;
            }
            $ticket->save();
        }

        ServiceDeskTicketEvent::log(
            $ticket,
            $isFirst ? 'respondido' : 'comentado',
            $data['note'],
            $from,
            $ticket->status
        );

        return redirect()->back()->with('success', $isFirst ? 'Primera respuesta registrada.' : 'Comentario agregado.');
    }

    public function changeStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                ServiceDeskTicket::STATUS_NUEVO,
                ServiceDeskTicket::STATUS_ASIGNADO,
                ServiceDeskTicket::STATUS_EN_PROCESO,
                ServiceDeskTicket::STATUS_EN_ESPERA,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket = ServiceDeskTicket::findOrFail($id);
        $from = $ticket->status;
        $ticket->status = $data['status'];
        $ticket->save();

        ServiceDeskTicketEvent::log($ticket, 'estado', $data['note'] ?? null, $from, $ticket->status);

        return redirect()->back()->with('success', 'Estado actualizado.');
    }

    /**
     * Resolucion. Si se declara uso de la KEDB se exige el articulo: sin el enlace el
     * indicador de uso de la base de conocimientos no seria auditable.
     */
    public function resolve(Request $request, int $id)
    {
        $data = $request->validate([
            'resolution_notes'   => ['required', 'string', 'max:2000'],
            'resolved_with_kedb' => ['required', 'boolean'],
            'kedb_article_id'    => ['nullable', 'exists:service_desk_kedb_articles,id', 'required_if:resolved_with_kedb,1,true'],
            'resolved_at'        => ['nullable', 'date'],
        ], [
            'kedb_article_id.required_if' => 'Indique el articulo de la KEDB utilizado.',
        ]);

        $ticket = ServiceDeskTicket::findOrFail($id);
        $from = $ticket->status;

        $resolvedAt = ! empty($data['resolved_at']) ? Carbon::parse($data['resolved_at']) : now();

        if ($resolvedAt->lessThan($ticket->reported_at)) {
            return redirect()->back()->withErrors([
                'resolved_at' => 'La fecha de resolucion no puede ser anterior al reporte del ticket.',
            ]);
        }

        // Resolver sin haber registrado respuesta dejaria el MTTRsp sin dato: se sella el
        // mismo instante como primera respuesta y queda explicito en la bitacora.
        $responseBackfilled = false;
        if ($ticket->first_response_at === null) {
            $ticket->first_response_at = $resolvedAt;
            $responseBackfilled = true;
        }

        $ticket->resolution_notes = $data['resolution_notes'];
        $ticket->resolved_with_kedb = (bool) $data['resolved_with_kedb'];
        $ticket->kedb_article_id = $data['resolved_with_kedb'] ? ($data['kedb_article_id'] ?? null) : null;
        $ticket->resolved_at = $resolvedAt;
        $ticket->status = ServiceDeskTicket::STATUS_RESUELTO;
        $ticket->save();

        if ($ticket->kedb_article_id) {
            ServiceDeskKedbArticle::whereKey($ticket->kedb_article_id)->increment('times_used');
        }

        ServiceDeskTicketEvent::log(
            $ticket,
            'resuelto',
            $data['resolution_notes'] . ($responseBackfilled ? ' [Primera respuesta sellada junto con la resolucion]' : ''),
            $from,
            $ticket->status
        );

        return redirect()->back()->with('success', "Ticket {$ticket->code} resuelto.");
    }

    public function close(Request $request, int $id)
    {
        $ticket = ServiceDeskTicket::findOrFail($id);

        if ($ticket->resolved_at === null) {
            return redirect()->back()->withErrors(['status' => 'Registre primero la resolucion del ticket.']);
        }

        $from = $ticket->status;
        $ticket->status = ServiceDeskTicket::STATUS_CERRADO;
        $ticket->closed_at = now();
        $ticket->save();

        ServiceDeskTicketEvent::log($ticket, 'cerrado', $request->input('note'), $from, $ticket->status);

        return redirect()->back()->with('success', "Ticket {$ticket->code} cerrado.");
    }

    /**
     * Reapertura: limpia los hitos de cierre pero conserva el contador, que es una senal de
     * calidad de la resolucion (un MTTR bueno con muchas reaperturas no es un buen MTTR).
     */
    public function reopen(Request $request, int $id)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $ticket = ServiceDeskTicket::findOrFail($id);
        $from = $ticket->status;

        $ticket->status = ServiceDeskTicket::STATUS_EN_PROCESO;
        $ticket->resolved_at = null;
        $ticket->closed_at = null;
        $ticket->reopened_count = $ticket->reopened_count + 1;
        $ticket->save();

        ServiceDeskTicketEvent::log($ticket, 'reabierto', $data['note'], $from, $ticket->status);

        return redirect()->back()->with('success', "Ticket {$ticket->code} reabierto.");
    }

    public function destroy(int $id)
    {
        $ticket = ServiceDeskTicket::findOrFail($id);
        $code = $ticket->code;
        $ticket->delete();

        return redirect()->back()->with('success', "Ticket {$code} eliminado.");
    }

    private function validatedTicket(Request $request): array
    {
        return $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'requester_user_id' => ['nullable', 'exists:users,id'],
            'requester_name'    => ['required', 'string', 'max:255'],
            'requester_email'   => ['nullable', 'email', 'max:255'],
            'requester_area'    => ['nullable', 'string', 'max:255'],
            'unit_id'           => ['nullable', 'exists:units,id'],
            'channel'           => ['required', Rule::in(ServiceDeskTicket::CHANNELS)],
            'category_id'       => ['required', 'exists:service_desk_categories,id'],
            'impact'            => ['required', 'integer', 'between:1,3'],
            'urgency'           => ['required', 'integer', 'between:1,3'],
            'assigned_to'       => ['nullable', 'exists:users,id'],
            'reported_at'       => ['nullable', 'date'],
        ]);
    }
}
