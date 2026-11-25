<?php

namespace App\Enrutamiento\Consumidores;

use App\Models\DamagedProduct;
use Illuminate\Support\Facades\Log;

/** Retira del inventario las unidades que no se pueden vender. */
class ConsumidorInventario implements ConsumidorEvento
{
    public function procesar(array $mensaje, DamagedProduct $producto): array
    {
        $cantidad = (int) ($mensaje['datos']['cantidad'] ?? 1);
        $anterior = $producto->stock;

        $producto->stock = max(0, $anterior - $cantidad);
        $producto->save();

        Log::info('[consumidor:inventario] Stock ajustado', [
            'correlation_id' => $mensaje['correlation_id'],
            'sku' => $producto->sku,
            'stock_anterior' => $anterior,
            'stock_nuevo' => $producto->stock,
        ]);

        return ['stock_anterior' => $anterior, 'stock_nuevo' => $producto->stock];
    }
}
