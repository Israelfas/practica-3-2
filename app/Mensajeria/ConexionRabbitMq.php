<?php

namespace App\Mensajeria;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Abre la conexión con RabbitMQ y declara las colas.
 * La usan el productor y el consumidor, así los dos ven la misma configuración.
 */
class ConexionRabbitMq
{
    public static function conectar(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            config('mensajeria.host'),
            config('mensajeria.port'),
            config('mensajeria.user'),
            config('mensajeria.password'),
            config('mensajeria.vhost'),
        );
    }

    /** Declara la cola principal (durable) y la de fallidos, a donde van los mensajes rechazados. */
    public static function declararColas(AMQPChannel $canal): void
    {
        $cola = config('mensajeria.cola');
        $fallidos = config('mensajeria.cola_fallidos');

        $canal->queue_declare($fallidos, false, true, false, false);

        $canal->queue_declare($cola, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => $fallidos,
        ]));
    }
}
