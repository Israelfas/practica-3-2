<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Producto dañado: mismo modelo del backend del proyecto de aula (sin fotos, que viven en L5). */
class DamagedProduct extends Model
{
    public const UPDATED_AT = null;

    public const DAMAGE_LEVELS = ['leve', 'moderado', 'severo'];

    public const STATUSES = ['draft', 'published', 'sold_out', 'archived'];

    /** Estados visibles en el catálogo público */
    public const VISIBLE = ['published', 'sold_out'];

    protected $fillable = [
        'sku', 'title', 'description', 'damage_level', 'damage_notes',
        'price', 'stock', 'status', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // El estado sigue al stock: sin stock -> sold_out; con stock otra vez -> published
        static::saving(function (self $product) {
            if ($product->status === 'published' && $product->stock === 0) {
                $product->status = 'sold_out';
            } elseif ($product->status === 'sold_out' && $product->stock > 0) {
                $product->status = 'published';
            }
            if (in_array($product->status, self::VISIBLE, true) && $product->published_at === null) {
                $product->published_at = now();
            }
        });
    }
}
