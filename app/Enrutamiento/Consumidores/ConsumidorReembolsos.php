<?php

namespace App\Enrutamiento\Consumidores;

use App\Models\DamagedProduct;
use Illuminate\Support\Facades\Log;

/** Calcula cuánto se le reembolsa al remitente según el nivel de daño. */
class ConsumidorReembolsos implements ConsumidorEvento
{
    public function procesar(array $mensaje, DamagedProduct $producto): array
    {
        $porcentaje = config('enrutamiento.porcentaje_reembolso')[$producto->damage_level] ?? 0;
        $monto = round((float) $producto->price * $porcentaje / 100, 2);

        Log::info('[consumidor:reembolsos] Reembolso calculado', [
            'correlation_id' => $mensaje['correlation_id'],
            'sku' => $producto->sku,
            'porcentaje' => $porcentaje,
            'monto' => $monto,
        ]);

        return ['porcentaje' => $porcentaje, 'monto' => $monto];
    }
}
