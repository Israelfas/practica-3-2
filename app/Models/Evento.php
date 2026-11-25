<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un evento de producto dañado y el camino que siguió: filtro, enrutador y consumidor. */
class Evento extends Model
{
    public const RECIBIDO = 'recibido';

    public const FILTRADO = 'filtrado';

    public const ENRUTADO = 'enrutado';

    public const NO_ENRUTABLE = 'no_enrutable';

    public const PROCESADO = 'procesado';

    public const FALLIDO = 'fallido';

    public const ERROR_TRANSPORTE = 'error_transporte';

    protected $table = 'eventos';

    protected $fillable = [
        'message_id', 'correlation_id', 'tipo', 'prioridad', 'product_id', 'datos',
        'destino', 'estado', 'motivo', 'procesado_por', 'resultado', 'procesado_en',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'resultado' => 'array',
            'procesado_en' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(DamagedProduct::class, 'product_id');
    }
}
