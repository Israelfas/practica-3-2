<?php

namespace App\Enrutamiento;

use App\Models\DamagedProduct;

/**
 * Message Filter: decide si un mensaje válido debe procesarse.
 *
 * Un rechazo aquí es una decisión de negocio, no un error: el mensaje está
 * bien formado, pero no tiene sentido que llegue a ningún consumidor.
 */
class FiltroDeMensajes
{
    /** Devuelve el motivo del rechazo, o null si el mensaje pasa el filtro. */
    public function motivoDeRechazo(array $mensaje, DamagedProduct $producto): ?string
    {
        if ($producto->status === 'archived') {
            return 'El producto está archivado: ya no se avisa, reembolsa ni ajusta su inventario.';
        }

        if ($mensaje['tipo'] === 'solicitud_reembolso' && $producto->damage_level === 'leve') {
            return 'Los daños leves no se reembolsan: el producto se vende con descuento.';
        }

        $cantidad = (int) ($mensaje['datos']['cantidad'] ?? 1);
        if ($mensaje['tipo'] === 'ajuste_inventario' && $cantidad > $producto->stock) {
            return "No se pueden retirar {$cantidad} unidades: el stock disponible es {$producto->stock}.";
        }

        return null;
    }
}
