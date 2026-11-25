<?php

namespace App\Enrutamiento;

/**
 * Content-Based Router: elige el destino de un mensaje según su tipo.
 *
 * Las reglas están en config/enrutamiento.php. Un tipo que ninguna regla
 * reconoce no se descarta: va a no_enrutables para que alguien lo revise.
 */
class EnrutadorPorContenido
{
    public function destino(array $mensaje): string
    {
        $reglas = config('enrutamiento.reglas');

        return $reglas[$mensaje['tipo'] ?? ''] ?? config('enrutamiento.destino_no_enrutable');
    }

    public function esNoEnrutable(string $destino): bool
    {
        return $destino === config('enrutamiento.destino_no_enrutable');
    }
}
