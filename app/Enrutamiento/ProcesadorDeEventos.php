<?php

namespace App\Enrutamiento;

use App\Enrutamiento\Consumidores\ConsumidorEvento;
use App\Enrutamiento\Consumidores\ConsumidorInventario;
use App\Enrutamiento\Consumidores\ConsumidorNotificaciones;
use App\Enrutamiento\Consumidores\ConsumidorReembolsos;
use App\Models\DamagedProduct;
use App\Models\Evento;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Lo que hace cada consumidor al recibir un mensaje de su cola: comprobar que
 * el mensaje es suyo, procesarlo una sola vez y dejar el resultado registrado.
 */
class ProcesadorDeEventos
{
    private const CONSUMIDORES = [
        'notificaciones' => ConsumidorNotificaciones::class,
        'reembolsos' => ConsumidorReembolsos::class,
        'inventario' => ConsumidorInventario::class,
    ];

    public static function destinosConConsumidor(): array
    {
        return array_keys(self::CONSUMIDORES);
    }

    public function procesar(string $destino, array $mensaje): Evento
    {
        $evento = Evento::where('message_id', $mensaje['id'] ?? null)->first();
        if (! $evento) {
            throw new RuntimeException('El mensaje no está registrado');
        }

        // Garantía del enrutamiento: un consumidor solo atiende lo que le tocaba.
        if ($evento->destino !== $destino) {
            throw new RuntimeException("El mensaje era para {$evento->destino}, no para {$destino}");
        }

        if ($evento->estado === Evento::PROCESADO) {
            return $evento; // entrega repetida: ya se atendió
        }

        $producto = DamagedProduct::findOrFail($evento->product_id);

        /** @var ConsumidorEvento $consumidor */
        $consumidor = app(self::CONSUMIDORES[$destino]);
        $resultado = $consumidor->procesar($mensaje, $producto);

        $evento->update([
            'estado' => Evento::PROCESADO,
            'procesado_por' => $destino,
            'resultado' => $resultado,
            'procesado_en' => now(),
        ]);

        return $evento;
    }

    public function marcarFallido(array $mensaje, string $error): void
    {
        Evento::where('message_id', $mensaje['id'] ?? null)
            ->update(['estado' => Evento::FALLIDO, 'motivo' => $error]);

        Log::error('[consumidor] No se pudo procesar el mensaje', [
            'correlation_id' => $mensaje['correlation_id'] ?? null,
            'id' => $mensaje['id'] ?? null,
            'error' => $error,
        ]);
    }
}
