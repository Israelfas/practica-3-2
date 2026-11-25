<?php

namespace App\Enrutamiento\Consumidores;

use App\Models\DamagedProduct;

/** Un proceso del proyecto que atiende un solo tipo de evento. */
interface ConsumidorEvento
{
    /** Hace su trabajo y devuelve lo que produjo, para dejarlo registrado. */
    public function procesar(array $mensaje, DamagedProduct $producto): array;
}
