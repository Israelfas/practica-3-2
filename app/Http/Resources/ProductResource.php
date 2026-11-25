<?php

namespace App\Http\Resources;

use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Respuesta de producto del backend del proyecto de aula.
 * Se omiten imageUrl y photos porque esta práctica no maneja archivos (L5).
 *
 * @mixin \App\Models\DamagedProduct
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->title,
            'description' => $this->description,
            'damageLevel' => $this->damage_level,
            'damageNotes' => $this->damage_notes,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'publishedAt' => Iso::date($this->published_at),
            'createdAt' => Iso::date($this->created_at),
            'createdBy' => $this->created_by,
        ];
    }
}
