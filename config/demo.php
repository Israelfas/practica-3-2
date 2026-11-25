<?php

// Usuarios de prueba (ficticios). Los valores reales van solo en .env, que no se sube a git.
return [
    'password' => env('DEMO_PASSWORD'),
    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@correos.test'),
    'operador_email' => env('DEMO_OPERADOR_EMAIL', 'manta@correos.test'),
    'operador2_email' => env('DEMO_OPERADOR2_EMAIL', 'portoviejo@correos.test'),
];
