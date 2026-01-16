<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Cafe;
use App\Models\Dinner;
use App\Models\Sale;
use App\Models\Subdealership;
use App\Models\Ticket;
use App\Models\Ticket_detail;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Barryvdh\DomPDF\Facades\Pdf;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $user = Auth::user();

        $units = $user->units;

        $cafes = Cafe::whereIn('unit_id', $units->pluck('id'))->get();

        return Inertia::render('sales/Index', [
            'dinners' => Dinner::with(['subdealership', 'cafe'])->paginate(20),
            'cafes' => $cafes,
            'subdealerships' => Subdealership::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Verify if the user exits and the side that own.
     */
    public function verifyUser($dni, $cafeId)
    {
        $dinner = Dinner::where('dni', $dni)->first();

        if ($dinner) {
            $cafe = Cafe::find($cafeId);
            if ($cafe) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Verify if the sale is duplicated or service has been taken.
     */

    public function verifySale($cafeId, $dinnerId, $services)
    {
        $sale = Sale::with(['tickets', 'tickets.ticket_details'])
            ->where('cafe_id', $cafeId)
            ->where('date', date('Y-m-d'))
            ->where('dinner_id', $dinnerId)
            ->orderBy('id', 'desc')
            ->first();

        if (!$sale) {
            return false; // No existe venta previa, no hay duplicidad
        }

        // Si no hay tickets o detalles, no hay duplicidad
        if ($sale->tickets->isEmpty() || $sale->tickets[0]->ticket_details->isEmpty()) {
            return false;
        }

        $existingCodes = $sale->tickets[0]->ticket_details->pluck('code')->toArray();
        $newCodes = collect($services)->pluck('code')->toArray();

        // Verificar si todos los nuevos códigos ya existen en la venta anterior
        $allExist = collect($newCodes)->every(function ($code) use ($existingCodes) {
            return in_array($code, $existingCodes);
        });

        // Si todos los servicios nuevos ya existen en la venta anterior, es duplicado
        return $allExist;
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $services = json_decode($request->services, true);
        $dinner = Dinner::where('dni', $request->dni)->with(['cafe', 'cafe.unit', 'subdealership', 'cafe.businesses'])->first();

        $boolValue = filter_var($request->double_price, FILTER_VALIDATE_BOOLEAN);

        if ($request->sale_type_id == 1) {
            if (!$boolValue) {
                if ($request->cafe_id != $dinner->cafe->id) {
                    return response()->json([
                        'message' => 'El usuario pertenece a otra cafetería: ' . $dinner->cafe->name,
                        'otherCafe' => true
                    ]);
                }
            }

            if ($this->verifySale($request->cafe_id, $dinner->id, $services)) {
                return response()->json([
                    'message' => 'Venta ya registrada a este usuario.',
                    'verification' => $this->verifySale($request->cafe_id, $dinner->id, $services)
                ], 404);
            }
        }

        $cafe = Cafe::find($request->cafe_id);
        $cafe->load(['businesses']);

        $services = json_decode($request->services, true);

        $total = array_reduce($services, function ($carry, $service) {
            return $carry + $service['price'];
        }, 0);

        if ($request->double_price == 'true') {
            $total *= 2;
        }

        $date = date('Y-m-d H:i:s');

        $sale = Sale::create([
            'dinner_id' => $dinner->id,
            'cafe_id' => $request->cafe_id,
            'date' => $request->date,
            'serial_number' => 'F00',
            'sale_type_id' => $request->sale_type_id,
            'payment_method_id' => null,
            'business_id' =>  $cafe->businesses[0]->id,
            'total_discounts' => 0.0,
            'total_non_taxable_operations' => 0.0,
            'total_taxable_operations' => 0.0,
            'total_unaffected_operations' => 0.0,
            'total_exonerated_operations' => 0.0,
            'total_exported_operations' => 0.0,
            'total_igv' => $total * 0.18,
            'total_icsc' => 0.0,
            'total_other_taxes' => 0.0,
            'total_other_charges' => 0.0,
            'total' => $total,
            'status' => 1
        ]);



        if ($request->receipt_type == 1) {
            $ticket = Ticket::create([
                'sale_id' => $sale->id,
                'dinner_id' => $dinner->id,
                'dinner_name' => $dinner->name,
                'dni' => $request->dni,
                'subdealership_name' => $dinner->subdealership['name'],
                'serial_number' => 'T00',
                'subdealership_ruc' => $dinner->subdealership['ruc'],
                'price_value' => $sale->total,
                'igv' => $sale->total_igv,
                'status' => 1
            ]);

            if ($ticket) {
                foreach ($services as $service) {
                    $ticket_detail = Ticket_detail::create([
                        'ticket_id' => $ticket->id,
                        'service_id' => $service['serviceID'],
                        'code' => $service['code'],
                        'service_name' => $service['name'],
                        'amount' => $service['quantity'],
                        'um' => 'UNI',
                        'service_type' => $service['serviceID'],
                        'description' => '',
                        'unit_value' => $service['price'],
                        'unit_price' => $service['unit_price'],
                        'sale_value' => $service['price'],
                        'igv' => $service['price'] * 0.18,
                        'total' => $service['price']
                    ]);
                }
            }
        }

        $newTicket = $ticket->load(['ticket_details']);


        //$this->printTest($newTicket, $dinner->cafe->businesses[0]);

        return response()->json([
            'dinner' => $dinner,
            'ticket' => $ticket ?? null,
            'message' => 'Venta registrada correctamente.',
            'sales' => Sale::with(['tickets', 'tickets.ticket_details', 'tickets.dinner', 'sale_details'])
                ->where('cafe_id', $request->cafe_id)
                ->where('date', date('Y-m-d'))
                ->orderBy('id', 'desc')
                ->get(),
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    public function printTest($ticketId, $businessId)
    {
        $ticket = Ticket::with(['ticket_details', 'dinner'])->find($ticketId);
        $business = Business::find($businessId);

        // Datos para la vista
        $data = [
            'ticket' => $ticket,
            'business' => $business,
            'date' => $ticket->created_at->format('d/m/Y H:i:s'),
        ];

        try {
            // Generar PDF
            $dompdf = FacadePdf::loadView('tickets.print', $data)->setPaper([0, 0, 226.77, 1000], 'portrait');

            // Opción 1: Descargar el PDF
            return $dompdf->stream('ticket-' . $ticketId . '.pdf');

            // Opción 2: Mostrar en el navegador
            //return $dompdf->stream('ticket-' . $ticketId . '.pdf');

            // Opción 3: Guardar en el servidor
            // $pdf->save(storage_path('app/public/tickets/ticket-'.$ticketId.'.pdf'));
            // return response()->json(['success' => 'PDF generado correctamente']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()]);
        }
    }

    public function report($dateInitial, $dateFinal)
    {
        $sales = Sale::with(['cafe', 'dinner', 'dinner.subdealership', 'tickets', 'tickets.ticket_details'])
            ->whereBetween('date', [$dateInitial, $dateFinal])
            ->get();
        return response()->json($sales);
    }

    public function pagination(Request $request, $offset)
    {
        $sales = Sale::with(['tickets', 'tickets.ticket_details', 'tickets.dinner', 'sale_details'])
            ->where('cafe_id', $request->cafe_id)
            ->where('date', date('Y-m-d'))
            ->orderBy('id', 'desc')
            ->offset($offset)
            ->limit(10)
            ->get();

        return response()->json($sales);
    }
}
