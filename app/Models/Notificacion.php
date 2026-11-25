<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'message_id', 'correlation_id', 'modo', 'product_id', 'canal', 'estado', 'error', 'procesada_en',
    ];

    protected function casts(): array
    {
        return ['procesada_en' => 'datetime'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(DamagedProduct::class, 'product_id');
    }
}
