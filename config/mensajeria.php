<?php

// Conexión a RabbitMQ. Los valores reales están en .env (no se sube a git).
return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER'),
    'password' => env('RABBITMQ_PASSWORD'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    // Cola del proceso y cola donde terminan los mensajes que fallan
    'cola' => env('RABBITMQ_QUEUE', 'correos.notificaciones.danio'),
    'cola_fallidos' => env('RABBITMQ_QUEUE', 'correos.notificaciones.danio').'.fallidas',

    // Segundos que tarda el envío simulado de la notificación
    'demora_envio' => (int) env('NOTIFICACION_DEMORA', 3),
];
