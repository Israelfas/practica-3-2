<?php

namespace App\Mensajeria;

/** Lo que necesita el productor: publicar un mensaje en una cola. */
interface PublicadorMensajes
{
    public function publicar(array $mensaje): void;
}
