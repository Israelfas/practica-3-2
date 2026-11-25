<?php

namespace App\Console\Commands;

use App\Mensajeria\ConexionRabbitMq;
use App\Mensajeria\ProcesadorNotificacion;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Consumidor independiente: php artisan notificaciones:consumir
 * Lee la cola de a un mensaje y solo confirma (ack) cuando terminó bien.
 */
class ConsumirNotificaciones extends Command
{
    protected $signature = 'notificaciones:consumir';

    protected $description = 'Consume la cola de notificaciones de productos dañados';

    public function handle(ProcesadorNotificacion $procesador): int
    {
        $conexion = ConexionRabbitMq::conectar();
        $canal = $conexion->channel();
        ConexionRabbitMq::declararColas($canal);

        // Un mensaje a la vez: no se toma el siguiente hasta confirmar el actual.
        $canal->basic_qos(0, 1, false);

        $this->info('Consumidor escuchando la cola '.config('mensajeria.cola').' (Ctrl+C para detener)');

        $canal->basic_consume(config('mensajeria.cola'), '', false, false, false, false,
            function (AMQPMessage $msg) use ($procesador) {
                $mensaje = json_decode($msg->getBody(), true) ?? [];
                $cid = $mensaje['correlation_id'] ?? 'sin-correlation';
                $this->line('['.now()->format('H:i:s')."] Recibido   correlation_id={$cid}");

                try {
                    $procesador->procesar($mensaje);
                    $msg->ack();
                    $this->info('['.now()->format('H:i:s')."] Procesado  correlation_id={$cid} -> enviada (ack)");
                } catch (Throwable $e) {
                    $procesador->marcarFallida($mensaje, $e->getMessage());
                    $msg->nack(false); // sin reencolar: pasa a la cola de fallidos
                    $this->error('['.now()->format('H:i:s')."] Error      correlation_id={$cid} -> fallida (nack): {$e->getMessage()}");
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
