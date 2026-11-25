<?php

namespace App\Mensajeria;

use App\Models\Notificacion;
use App\Services\NotificadorDanio;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Lo que hace el consumidor con cada mensaje de la cola. */
class ProcesadorNotificacion
{
    public function __construct(private NotificadorDanio $notificador) {}

    public function procesar(array $mensaje): void
    {
        $cid = $mensaje['correlation_id'] ?? null;
        $notificacion = Notificacion::where('message_id', $mensaje['message_id'] ?? null)->first();

        if (! $cid || ! $notificacion) {
            throw new RuntimeException('Mensaje sin correlation_id o sin registro de estado');
        }

        // Si RabbitMQ entrega dos veces el mismo mensaje, no se envía otra notificación.
        if ($notificacion->estado === 'enviada') {
            Log::info('[consumidor] Mensaje repetido, ya estaba procesado', ['correlation_id' => $cid]);

            return;
        }

        Log::info('[consumidor] Mensaje recibido', ['correlation_id' => $cid, 'estado' => 'procesando']);
        $notificacion->update(['estado' => 'procesando']);

        $producto = $notificacion->producto;
        if (! $producto) {
            throw new RuntimeException('El producto ya no existe');
        }

        $this->notificador->enviar($producto, $notificacion->canal, $cid);

        $notificacion->update(['estado' => 'enviada', 'procesada_en' => now(), 'error' => null]);
        Log::info('[consumidor] Mensaje procesado', ['correlation_id' => $cid, 'estado' => 'enviada']);
    }

    public function marcarFallida(array $mensaje, string $motivo): void
    {
        Notificacion::where('message_id', $mensaje['message_id'] ?? null)
            ->update(['estado' => 'fallida', 'error' => mb_substr($motivo, 0, 250)]);

        Log::error('[consumidor] Error al procesar, mensaje enviado a fallidos', [
            'correlation_id' => $mensaje['correlation_id'] ?? null,
            'estado' => 'fallida',
            'motivo' => $motivo,
        ]);
    }
}
