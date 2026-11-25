<?php

namespace App\Console\Commands;

use App\Enrutamiento\ProcesadorDeEventos;
use App\Enrutamiento\TopologiaEventos;
use App\Mensajeria\ConexionRabbitMq;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Consumidor de un destino: php artisan eventos:consumir {notificaciones|reembolsos|inventario}
 * Cada consumidor escucha solo su cola, así recibe solo lo que el enrutador le mandó.
 */
class ConsumirEventos extends Command
{
    protected $signature = 'eventos:consumir {destino : notificaciones, reembolsos o inventario}';

    protected $description = 'Consume la cola de un destino del enrutamiento por contenido';

    public function handle(ProcesadorDeEventos $procesador): int
    {
        $destino = $this->argument('destino');
        if (! in_array($destino, ProcesadorDeEventos::destinosConConsumidor(), true)) {
            $this->error('Destino desconocido. Use: '.implode(', ', ProcesadorDeEventos::destinosConConsumidor()));

            return self::INVALID;
        }

        $cola = config('enrutamiento.colas')[$destino];
        $conexion = ConexionRabbitMq::conectar();
        $canal = $conexion->channel();
        TopologiaEventos::declarar($canal);
        $canal->basic_qos(0, 1, false);

        $this->info("Consumidor de {$destino} escuchando la cola {$cola} (Ctrl+C para detener)");

        $canal->basic_consume($cola, '', false, false, false, false,
            function (AMQPMessage $msg) use ($procesador, $destino) {
                $mensaje = json_decode($msg->getBody(), true) ?? [];
                $id = $mensaje['id'] ?? '?';
                $this->line('['.now()->format('H:i:s')."] Recibido   id={$id} tipo=".($mensaje['tipo'] ?? '?').' prioridad='.($mensaje['prioridad'] ?? '?'));

                try {
                    $evento = $procesador->procesar($destino, $mensaje);
                    $msg->ack();
                    $this->info('['.now()->format('H:i:s')."] Procesado  id={$id} resultado=".json_encode($evento->resultado, JSON_UNESCAPED_UNICODE).' (ack)');
                } catch (Throwable $e) {
                    // Queda registrado como fallido en la tabla eventos y en el log.
                    $procesador->marcarFallido($mensaje, $e->getMessage());
                    $msg->ack();
                    $this->error('['.now()->format('H:i:s')."] Fallido    id={$id}: {$e->getMessage()}");
                }
            });

        while ($canal->is_consuming()) {
            $canal->wait();
        }

        $canal->close();
        $conexion->close();

        return self::SUCCESS;
    }
}
