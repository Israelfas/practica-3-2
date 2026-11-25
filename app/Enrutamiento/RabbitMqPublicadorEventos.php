<?php

namespace App\Enrutamiento;

use App\Mensajeria\ConexionRabbitMq;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

/** Publica en el exchange correos.eventos usando el destino como routing key. */
class RabbitMqPublicadorEventos implements PublicadorEventos
{
    public function publicar(array $mensaje, string $destino): void
    {
        $conexion = ConexionRabbitMq::conectar();
        $canal = $conexion->channel();

        try {
            TopologiaEventos::declarar($canal);

            $amqp = new AMQPMessage(json_encode($mensaje, JSON_UNESCAPED_UNICODE), [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $mensaje['id'],
                'correlation_id' => $mensaje['correlation_id'],
                'type' => $mensaje['tipo'],
                'priority' => config('enrutamiento.prioridades')[$mensaje['prioridad']] ?? 5,
            ]);

            $canal->basic_publish($amqp, config('enrutamiento.exchange'), $destino);

            Log::info('[enrutador] Mensaje publicado', [
                'correlation_id' => $mensaje['correlation_id'],
                'id' => $mensaje['id'],
                'exchange' => config('enrutamiento.exchange'),
                'routing_key' => $destino,
                'cola' => config('enrutamiento.colas')[$destino],
            ]);
        } finally {
            $canal->close();
            $conexion->close();
        }
    }
}
