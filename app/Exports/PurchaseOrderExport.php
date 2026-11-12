<?php

namespace App\Exports;

use App\Exports\Sheets\PurchaseOrderByProviderSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel del detalle de un pedido (/purchase-orders/{id}): la orden consolidada por categoría
 * (mismo formato que la Orden de Pedido Semanal de Planificación) y una segunda pestaña con
 * los productos separados por proveedor activo, para armar el pedido a cada uno.
 */
class PurchaseOrderExport implements WithMultipleSheets
{
    public function __construct(
        private readonly WeeklyPurchaseOrderExport $orderSheet,
        private readonly PurchaseOrderByProviderSheet $providerSheet,
    ) {
    }

    public function sheets(): array
    {
        return [$this->orderSheet, $this->providerSheet];
    }
}
