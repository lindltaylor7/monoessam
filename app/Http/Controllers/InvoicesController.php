<?php

namespace App\Http\Controllers;

use App\Models\ClothInvoice;
use App\Models\EquipmentInvoice;
use Inertia\Inertia;

class InvoicesController extends Controller
{
    public function index()
    {
        $clothInvoices = ClothInvoice::with([
            'business:id,name',
            'headquarter:id,name',
            'provider:id,name',
            'user:id,name',
            'items.cloth:id,name',
            'items.epp:id,name',
            'items.color:id,name,hex_code',
        ])->latest()->get()->map(function ($inv) {
            $hasEpp   = $inv->items->some(fn($i) => $i->epp_id);
            $hasCloth = $inv->items->some(fn($i) => $i->cloth_id);
            $category = match (true) {
                $hasEpp && $hasCloth => 'mixed_cloth',
                $hasEpp              => 'epp',
                default              => 'cloth',
            };
            return [
                'id'             => $inv->id,
                'source'         => 'cloth',
                'category'       => $category,
                'invoice_number' => $inv->invoice_number,
                'document_type'  => $inv->document_type ?? null,
                'date'           => $inv->date,
                'provider'       => $inv->provider,
                'business'       => $inv->business,
                'headquarter'    => $inv->headquarter,
                'total_amount'   => (float) $inv->total_amount,
                'items_count'    => $inv->items->count(),
                'user'           => $inv->user,
                'invoice_image'  => $inv->invoice_image,
                'notes'          => $inv->notes,
                'items'          => $inv->items->map(fn($i) => [
                    'id'         => $i->id,
                    'name'       => $i->cloth?->name ?? $i->epp?->name ?? '—',
                    'type'       => $i->cloth_id ? 'cloth' : 'epp',
                    'size'       => $i->size,
                    'color'      => $i->color,
                    'quantity'   => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'total_price'=> (float) $i->total_price,
                ]),
            ];
        });

        $equipmentInvoices = EquipmentInvoice::with([
            'business:id,name',
            'provider:id,name',   // now references providers table
            'user:id,name',
            'computerEquipments:id,equipment_invoice_id,name,brand,model,code,series,status,unit_price',
            'kitchenEquipments:id,equipment_invoice_id,name,brand,model,code,series,status,unit_price',
        ])->latest()->get()->map(function ($inv) {
            $computers = $inv->computerEquipments;
            $kitchens  = $inv->kitchenEquipments;
            $category  = match (true) {
                $computers->isNotEmpty() && $kitchens->isNotEmpty() => 'mixed_equipment',
                $computers->isNotEmpty()                            => 'computer',
                default                                              => 'kitchen',
            };
            $items = collect()
                ->merge($computers->map(fn($e) => [
                    'id'         => $e->id,
                    'name'       => $e->name,
                    'type'       => 'computer',
                    'brand'      => $e->brand,
                    'model'      => $e->model,
                    'code'       => $e->code,
                    'series'     => $e->series,
                    'status'     => $e->status,
                    'unit_price' => $e->unit_price !== null ? (float) $e->unit_price : null,
                    'total_price'=> $e->unit_price !== null ? (float) $e->unit_price : null,
                ]))
                ->merge($kitchens->map(fn($e) => [
                    'id'         => $e->id,
                    'name'       => $e->name,
                    'type'       => 'kitchen',
                    'brand'      => $e->brand,
                    'model'      => $e->model,
                    'code'       => $e->code,
                    'series'     => $e->series,
                    'status'     => $e->status,
                    'unit_price' => $e->unit_price !== null ? (float) $e->unit_price : null,
                    'total_price'=> $e->unit_price !== null ? (float) $e->unit_price : null,
                ]));

            return [
                'id'             => $inv->id,
                'source'         => 'equipment',
                'category'       => $category,
                'invoice_number' => $inv->invoice_number,
                'document_type'  => $inv->document_type,
                'date'           => $inv->date,
                'provider'       => $inv->provider,
                'business'       => $inv->business,
                'headquarter'    => null,
                'total_amount'   => (float) $inv->total_amount,
                'items_count'    => $items->count(),
                'user'           => $inv->user,
                'invoice_image'  => $inv->invoice_image,
                'notes'          => $inv->notes,
                'items'          => $items->values(),
            ];
        });

        $all = $clothInvoices->merge($equipmentInvoices)
            ->sortByDesc('date')
            ->values();

        // Summary stats
        $stats = [
            'total_invoices'   => $all->count(),
            'total_amount'     => $all->sum('total_amount'),
            'cloth_count'      => $clothInvoices->count(),
            'cloth_amount'     => $clothInvoices->sum('total_amount'),
            'equipment_count'  => $equipmentInvoices->count(),
            'equipment_amount' => $equipmentInvoices->sum('total_amount'),
            'computer_count'   => $equipmentInvoices->filter(fn($i) => in_array($i['category'], ['computer', 'mixed_equipment']))->count(),
            'kitchen_count'    => $equipmentInvoices->filter(fn($i) => in_array($i['category'], ['kitchen', 'mixed_equipment']))->count(),
        ];

        // Monthly totals for the last 6 months
        $monthly = $all->groupBy(fn($inv) => substr($inv['date'], 0, 7))
            ->map(fn($group, $month) => [
                'month'  => $month,
                'total'  => $group->sum('total_amount'),
                'count'  => $group->count(),
            ])
            ->sortKeys()
            ->take(-6)
            ->values();

        return Inertia::render('invoices/Index', [
            'invoices' => $all,
            'stats'    => $stats,
            'monthly'  => $monthly,
        ]);
    }
}
