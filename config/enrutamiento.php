<?php

// Práctica 3.2: enrutamiento por contenido de los eventos de un producto dañado.
return [
    // Exchange directo de RabbitMQ: entrega cada mensaje a la cola cuya
    // routing key coincide exactamente con el destino que eligió el enrutador.
    'exchange' => env('RABBITMQ_EXCHANGE_EVENTOS', 'correos.eventos'),

    // Tabla de reglas del Content-Based Router: tipo del evento => destino.
    'reglas' => [
        'aviso_remitente' => 'notificaciones',
        'solicitud_reembolso' => 'reembolsos',
        'ajuste_inventario' => 'inventario',
    ],

    // Destino de revisión para los tipos que ninguna regla reconoce.
    'destino_no_enrutable' => 'no_enrutables',

    // Cola de cada destino. La routing key es el nombre del destino.
    'colas' => [
        'notificaciones' => 'correos.eventos.notificaciones',
        'reembolsos' => 'correos.eventos.reembolsos',
        'inventario' => 'correos.eventos.inventario',
        'no_enrutables' => 'correos.eventos.no_enrutables',
    ],

    // Prioridad AMQP que se pone a cada mensaje según su prioridad de negocio.
    'prioridades' => ['alta' => 9, 'media' => 5, 'baja' => 1],

    // Porcentaje del precio que se reembolsa según el nivel de daño.
    'porcentaje_reembolso' => ['moderado' => 30, 'severo' => 60],
];
