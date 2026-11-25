<?php

namespace App\Services;

use App\Models\DamagedProduct;
use Illuminate\Support\Facades\Log;

/**
 * Avisa al remitente que su paquete llegó dañado.
 * El envío es simulado: espera unos segundos, como haría un proveedor de correo o SMS real.
 */
class NotificadorDanio
{
    public function enviar(DamagedProduct $producto, string $canal, string $correlationId): void
    {
        sleep(config('mensajeria.demora_envio'));

        Log::info('Notificación de daño enviada (simulada)', [
            'correlation_id' => $correlationId,
            'sku' => $producto->sku,
            'canal' => $canal,
        ]);
    }
}
