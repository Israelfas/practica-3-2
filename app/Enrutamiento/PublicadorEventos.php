<?php

namespace App\Enrutamiento;

/** Entrega un mensaje al destino que decidió el enrutador. */
interface PublicadorEventos
{
    public function publicar(array $mensaje, string $destino): void;
}
