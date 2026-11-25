<?php

namespace App\Mensajeria;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

/** Productor: publica el mensaje en la cola de RabbitMQ. */
class RabbitMqPublicador implements PublicadorMensajes
{
    public function publicar(array $mensaje): void
    {
        $conexion = ConexionRabbitMq::conectar();
        $canal = $conexion->channel();

        try {
            ConexionRabbitMq::declararColas($canal);

            $amqp = new AMQPMessage(json_encode($mensaje, JSON_UNESCAPED_UNICODE), [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // sobrevive a un reinicio del broker
                'message_id' => $mensaje['message_id'],
                'correlation_id' => $mensaje['correlation_id'],
                'type' => $mensaje['tipo'],
            ]);

            $canal->basic_publish($amqp, '', config('mensajeria.cola'));

            Log::info('[productor] Mensaje publicado', [
                'correlation_id' => $mensaje['correlation_id'],
                'message_id' => $mensaje['message_id'],
                'cola' => config('mensajeria.cola'),
                'estado' => 'pendiente',
            ]);
        } finally {
            $canal->close();
            $conexion->close();
        }
    }
}
