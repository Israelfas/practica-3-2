<?php

namespace App\Enrutamiento\Consumidores;

use App\Models\DamagedProduct;
use Illuminate\Support\Facades\Log;

/**
 * Avisa al remitente que su paquete llegó dañado.
 * El mensaje llega de verdad por la cola; lo que se simula es el envío del
 * correo o SMS, que queda registrado en el log en lugar de salir a un proveedor.
 */
class ConsumidorNotificaciones implements ConsumidorEvento
{
    public function procesar(array $mensaje, DamagedProduct $producto): array
    {
        $canal = $mensaje['datos']['canal'] ?? 'email';

        Log::info('[consumidor:notificaciones] Aviso al remitente enviado (simulado)', [
            'correlation_id' => $mensaje['correlation_id'],
            'sku' => $producto->sku,
            'canal' => $canal,
        ]);

        return ['canal' => $canal, 'sku' => $producto->sku];
    }
}
