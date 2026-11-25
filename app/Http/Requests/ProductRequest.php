<?php

namespace App\Http\Requests;

use App\Models\DamagedProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /products (crear) y PATCH /products/{id} (solo los campos enviados). */
class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => [$required, 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock' => [$required, 'integer', 'min:0'],
            'sku' => [$required, 'string', 'max:40', Rule::unique('damaged_products', 'sku')->ignore($this->route('product'))],
            'damageLevel' => [$required, Rule::in(DamagedProduct::DAMAGE_LEVELS)],
            'damageNotes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(DamagedProduct::STATUSES)],
        ];
    }

    /** Campos validados con los nombres de columna de damaged_products. */
    public function attributesForModel(): array
    {
        $map = [
            'name' => 'title', 'description' => 'description', 'price' => 'price', 'stock' => 'stock',
            'sku' => 'sku', 'damageLevel' => 'damage_level', 'damageNotes' => 'damage_notes', 'status' => 'status',
        ];
        $data = [];
        foreach ($this->validated() as $field => $value) {
            $data[$map[$field]] = $value;
        }

        return $data;
    }
}
