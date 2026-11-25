<?php

namespace App\Policies;

use App\Models\DamagedProduct;
use App\Models\User;

/**
 * Autorización sobre productos dañados.
 *  - Administrador: todo.
 *  - Operador de agencia: crea productos y solo ve o modifica los que él registró.
 *    No puede eliminar.
 */
class DamagedProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // el filtro por dueño se aplica en la consulta
    }

    public function view(User $user, DamagedProduct $product): bool
    {
        return $user->esAdmin() || $this->esDueno($user, $product);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [User::ROL_OPERADOR, User::ROL_ADMIN], true);
    }

    public function update(User $user, DamagedProduct $product): bool
    {
        return $user->esAdmin() || $this->esDueno($user, $product);
    }

    public function delete(User $user, DamagedProduct $product): bool
    {
        return $user->esAdmin();
    }

    private function esDueno(User $user, DamagedProduct $product): bool
    {
        return (int) $product->created_by === (int) $user->id;
    }
}
