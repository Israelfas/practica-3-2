<?php

namespace App\Providers;

use App\Enrutamiento\PublicadorEventos;
use App\Enrutamiento\RabbitMqPublicadorEventos;
use App\Mensajeria\PublicadorMensajes;
use App\Mensajeria\RabbitMqPublicador;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El productor publica en RabbitMQ (en las pruebas se cambia por uno falso).
        $this->app->bind(PublicadorMensajes::class, RabbitMqPublicador::class);

        // Práctica 3.2: los eventos enrutados se publican en el exchange correos.eventos.
        $this->app->bind(PublicadorEventos::class, RabbitMqPublicadorEventos::class);
    }

    public function boot(): void
    {
        // En producción todas las URL se generan con https (el TLS lo pone el servidor web).
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Máximo 5 intentos de login por minuto para el mismo correo e IP.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(strtolower((string) $request->input('email')).'|'.$request->ip())
                ->response(fn () => response()->json(['message' => 'Demasiados intentos. Espere un minuto.'], 429));
        });
    }
}
