<?php

namespace App\Enrutamiento;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Exchange, colas y routing keys del enrutamiento en RabbitMQ.
 * La declaran el productor y los consumidores, así ambos ven lo mismo.
 */
class TopologiaEventos
{
    public static function declarar(AMQPChannel $canal): void
    {
        $exchange = config('enrutamiento.exchange');
        $canal->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

        foreach (config('enrutamiento.colas') as $destino => $cola) {
            // Durable y con prioridad: los mensajes "alta" se entregan antes.
            $canal->queue_declare($cola, false, true, false, false, false, new AMQPTable(['x-max-priority' => 10]));
            $canal->queue_bind($cola, $exchange, $destino);
        }
    }
}
