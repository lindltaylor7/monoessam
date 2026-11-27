<?php

namespace App\Http\Controllers\ServiceDesk;

use App\Http\Controllers\Controller;
use App\Models\ServiceDeskCategory;
use App\Models\ServiceDeskSatisfactionSurvey;
use App\Models\ServiceDeskTicket;
use App\Models\ServiceDeskTicketEvent;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Canal de entrada "portal": autoservicio para que cualquier usuario del ERP reporte sus
 * incidencias y les haga seguimiento, sin depender de que TI las transcriba.
 *
 * Vive bajo el segmento `soporte`, que a proposito NO tiene fila en `permissions`: el
 * CheckRoutePermission lo deja abierto a todo usuario autenticado. La bandeja de TI sigue
 * en `service-desk` (con permiso propio). Aqui el control es por registro: cada usuario
 * solo ve y opera los tickets en los que figura como solicitante.
 *
 * Los tickets nacen con channel = 'portal' y pasan por el mismo `applyPriorityAndSla()` que
 * los de la bandeja, asi que entran a MTTR/MTTRsp/SLA sin tratamiento especial. La
 * clasificacion que elige el usuario es preliminar: TI la corrige al editar y la auditoria
 * de clasificacion mide justamente cuanto se corrige.
 */
class PortalController extends Controller
{
    /** Eventos de la bitacora que se muestran al solicitante; el resto es interno de TI. */
    private const PUBLIC_EVENTS = ['creado', 'asignado', 'respondido', 'comentado', 'estado', 'resuelto', 'cerrado', 'reabierto'];

    public function index(Request $request)
    {
        $user = $request->user();

        $tickets = ServiceDeskTicket::with([
            'category:id,name',
            'survey:id,ticket_id,score,answered_at',
            'events' => fn ($q) => $q->whereIn('type', self::PUBLIC_EVENTS)->with('user:id,name'),
        ])
            ->where('requester_user_id', $user->id)
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END', ServiceDeskTicket::CLOSED_STATUSES)
            ->orderByDesc('reported_at')
            ->limit(100)
            ->get()
            ->map(fn (ServiceDeskTicket $ticket) => $this->present($ticket, $user->id));

        return Inertia::render('service-desk/Portal', [
            'tickets'    => $tickets,
            'categories' => ServiceDeskCategory::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'description']),
            'units'      => Unit::operational()->orderBy('name')->get(['id', 'name']),
            'defaults'   => [
                'unit_id' => $user->unit_id,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['required', 'string', 'max:5000'],
            'category_id'    => ['required', 'exists:service_desk_categories,id'],
            'impact'         => ['required', 'integer', 'between:1,3'],
            'urgency'        => ['required', 'integer', 'between:1,3'],
            'requester_area' => ['nullable', 'string', 'max:255'],
            'unit_id'        => ['nullable', 'exists:units,id'],
        ]);

        // El solicitante sale de la sesion, nunca del formulario: el portal no permite
        // reportar a nombre de otro (eso lo hace TI desde la bandeja).
        $ticket = DB::transaction(function () use ($data, $user) {
            $ticket = new ServiceDeskTicket($data);
            $ticket->requester_user_id = $user->id;
            $ticket->requester_name = $user->name;
            $ticket->requester_email = $user->email;
            $ticket->channel = 'portal';
            $ticket->code = ServiceDeskTicket::nextCode();
            $ticket->status = ServiceDeskTicket::STATUS_NUEVO;
            $ticket->reported_at = now();
            $ticket->applyPriorityAndSla();
            $ticket->save();

            ServiceDeskTicketEvent::log($ticket, 'creado', 'Reportado por el usuario desde el portal de soporte.', null, $ticket->status);

            return $ticket;
        });

        return redirect()->back()->with('success', "Tu incidencia fue registrada con el codigo {$ticket->code}.");
    }

    /**
     * Informacion adicional del usuario. Si TI habia dejado el ticket "en espera" (tipicamente
     * esperando al usuario), la respuesta lo devuelve a "en proceso".
     */
    public function comment(Request $request, int $id)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        $ticket = $this->ownTicket($request, $id);

        if ($ticket->status === ServiceDeskTicket::STATUS_CERRADO) {
            return redirect()->back()->withErrors(['note' => 'El ticket esta cerrado. Reporta una nueva incidencia si el problema persiste.']);
        }

        $from = $ticket->status;
        if ($ticket->status === ServiceDeskTicket::STATUS_EN_ESPERA) {
            $ticket->status = ServiceDeskTicket::STATUS_EN_PROCESO;
            $ticket->save();
        }

        ServiceDeskTicketEvent::log($ticket, 'comentado', $data['note'], $from, $ticket->status);

        return redirect()->back()->with('success', 'Tu mensaje fue enviado a la mesa de ayuda.');
    }

    /**
     * El usuario rechaza la resolucion. Solo mientras el ticket esta "resuelto": una vez que
     * TI lo cierra, el caso se da por terminado y lo que siga es una incidencia nueva.
     */
    public function reopen(Request $request, int $id)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $ticket = $this->ownTicket($request, $id);

        if ($ticket->status !== ServiceDeskTicket::STATUS_RESUELTO) {
            return redirect()->back()->withErrors(['note' => 'Solo se puede reabrir un ticket resuelto que aun no fue cerrado.']);
        }

        $from = $ticket->status;
        $ticket->status = ServiceDeskTicket::STATUS_EN_PROCESO;
        $ticket->resolved_at = null;
        $ticket->closed_at = null;
        $ticket->reopened_count = $ticket->reopened_count + 1;
        $ticket->save();

        ServiceDeskTicketEvent::log($ticket, 'reabierto', 'El usuario indica que no quedo resuelto: ' . $data['note'], $from, $ticket->status);

        return redirect()->back()->with('success', "Ticket {$ticket->code} reabierto.");
    }

    /**
     * Encuesta de satisfaccion respondida por el propio solicitante: es la fuente directa del
     * indicador de Calidad Percibida (CSAT), sin intermediacion de TI.
     */
    public function rate(Request $request, int $id)
    {
        $data = $request->validate([
            'score'           => ['required', 'integer', 'between:1,5'],
            'q_attention'     => ['nullable', 'integer', 'between:1,5'],
            'q_response_time' => ['nullable', 'integer', 'between:1,5'],
            'q_solution'      => ['nullable', 'integer', 'between:1,5'],
            'q_communication' => ['nullable', 'integer', 'between:1,5'],
            'comments'        => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket = $this->ownTicket($request, $id);

        if (! in_array($ticket->status, ServiceDeskTicket::CLOSED_STATUSES, true)) {
            return redirect()->back()->withErrors(['score' => 'Podras calificar la atencion cuando el ticket este resuelto.']);
        }

        if ($ticket->survey()->exists()) {
            return redirect()->back()->withErrors(['score' => 'Ya calificaste la atencion de este ticket.']);
        }

        ServiceDeskSatisfactionSurvey::create($data + [
            'ticket_id'       => $ticket->id,
            'user_id'         => $request->user()->id,
            'respondent_name' => $request->user()->name,
            'respondent_area' => $ticket->requester_area,
            'answered_at'     => now(),
        ]);

        return redirect()->back()->with('success', 'Gracias por calificar la atencion.');
    }

    /** 404 y no 403: no se confirma la existencia de tickets ajenos. */
    private function ownTicket(Request $request, int $id): ServiceDeskTicket
    {
        return ServiceDeskTicket::where('requester_user_id', $request->user()->id)->findOrFail($id);
    }

    /**
     * Vista del ticket para el solicitante: sin asignado, KEDB ni auditoria. En la bitacora
     * se oculta la nota de asignacion (es interna) y el autor se muestra como "Mesa de ayuda"
     * cuando no es el propio usuario.
     */
    private function present(ServiceDeskTicket $ticket, int $userId): array
    {
        return [
            'id'                => $ticket->id,
            'code'              => $ticket->code,
            'title'             => $ticket->title,
            'description'       => $ticket->description,
            'category'          => $ticket->category?->name,
            'priority'          => $ticket->priority,
            'status'            => $ticket->status,
            'reported_at'       => $ticket->reported_at,
            'resolution_due_at' => $ticket->resolution_due_at,
            'resolved_at'       => $ticket->resolved_at,
            'resolution_notes'  => $ticket->resolution_notes,
            'survey_score'      => $ticket->survey?->score,
            'events'            => $ticket->events->map(fn (ServiceDeskTicketEvent $event) => [
                'id'         => $event->id,
                'type'       => $event->type,
                'to_status'  => $event->to_status,
                'note'       => $event->type === 'asignado' ? null : $event->note,
                'mine'       => $event->user_id === $userId,
                'author'     => $event->user_id === $userId ? 'Tu' : 'Mesa de ayuda',
                'created_at' => $event->created_at,
            ])->values(),
        ];
    }
}
